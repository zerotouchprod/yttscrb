# Plan: Интеграция Laravel AI SDK (`laravel/ai`)

> **Статус:** Approved for Development
> **Дата:** 2026-05-11
> **Автор:** Архитектор проекта

---

## 1. Контекст и мотивация

`laravel/ai` — официальный Laravel AI SDK, предоставляющий:

- **Structured Output** через `HasStructuredOutput` + JSON Schema — AI гарантированно возвращает валидный типизированный объект, не «стену текста».
- **Failover из коробки** — атрибут `#[Provider([Lab::OpenAI, Lab::Anthropic, Lab::Groq])]` автоматически переключает провайдера при ошибке/rate-limit.
- **Тестирование без HTTP** — `YoutubeSummarizerAgent::fake()`, `Transcription::fake()` заменяют моки cURL — тесты пишутся за минуты.

### Где SDK — чит-код

| Область | Выгода |
|---|---|
| Суммаризация (LLM) | Structured Output, failover, `::fake()` |
| Тестирование | `Agent::fake()`, `assertPrompted()` |
| Переключение провайдеров | Один атрибут вместо условной логики |

### Стоимость провайдеров суммаризации (сравнение)

| Провайдер | Цена (input/1M токенов) | Роль в failover |
|---|---|---|
| **DeepSeek V4 Flash** (`deepseek-v4-flash`) | **<$0.07** | ✅ Default — самый дешёвый |
| Groq (LLaMA/Mixtral) | ~$0.05–0.27 (бесплатный tier) | Fallback #1 — уже есть ключ |
| OpenAI GPT-4o-mini | $0.15 | Fallback #2 — надёжный |
| Anthropic Claude Haiku | $0.25 | Fallback #3 — резерв |

> На 1 000 суммаризаций видео (~30 мин, ~4k токенов/запрос) экономия DeepSeek vs GPT-4o-mini: **~$0.60 экономии** — незначительно для MVP, но при масштабировании до 10k+/мес разница ощутима.

### Где SDK не применяется

| Область | Причина |
|---|---|
| `GroqWhisperAdapter` (STT) | SDK поддерживает STT только OpenAI/ElevenLabs/Mistral/Gemini; хак через `driver: openai` с подменой URL хрупок при минорных обновлениях |
| `NocixWhisperCppAdapter` (STT) | Кастомный HTTP-сервер, SDK не поддерживает |
| Workflow оркестрация | `->queue()` SDK использует стандартные очереди Laravel — несовместимо с `durable-workflow`; вся оркестрация остаётся за Workflow Engine |

---

## 2. Архитектурные ограничения

```
SDK-классы (Laravel\Ai\*) разрешены ТОЛЬКО в слое Infrastructure.
Domain и Application никогда не импортируют Laravel\Ai\*.
Порты (SummaryProviderInterface) не меняются.
Deptrac должен явно разрешить Laravel\Ai\* для Infrastructure.
```

---

## 3. Пошаговый план реализации

### Шаг 1 — Установить зависимости

```bash
composer require laravel/ai webmozart/assert
php artisan vendor:publish --provider="Laravel\Ai\AiServiceProvider"
```

`webmozart/assert` уже присутствует в `vendor/` как транзитивная зависимость PHPStan (v2.3.0), но должен быть прописан явно в `require` для PHPStan Level 9.

**Сразу после публикации — удалить миграции SDK:**

```bash
rm database/migrations/*_create_agent_conversations_table.php
rm database/migrations/*_create_agent_conversation_messages_table.php
```

*Обоснование:* `YoutubeSummarizerAgent` не использует `RemembersConversations` — это one-off анализ текста. Состояние хранит `durable-workflow`. Таблицы — мусор.*

**Добавить в `.env`:**

```env
DEEPSEEK_API_KEY=sk-...        # Default provider — самый дешёвый (<$0.07/1M input tokens, deepseek-v4-flash)
OPENAI_API_KEY=sk-...          # Fallback #2
ANTHROPIC_API_KEY=sk-ant-...   # Fallback #3
# GROQ_API_KEY уже есть — fallback #1 (бесплатный tier, совместим с laravel/ai)
```

**Порядок failover:** DeepSeek → Groq → OpenAI → Anthropic.  
DeepSeek V4 Flash (`deepseek-v4-flash`) — быстрее и дешевле V3, при сопоставимом качестве суммаризации.

---

### Шаг 2 — Создать `SummaryKeyPoint` Value Object

**Файл:** `app/Domain/ValueObjects/SummaryKeyPoint.php`

```php
<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class SummaryKeyPoint
{
    public function __construct(
        public readonly string $timecode, // Format: "MM:SS"
        public readonly string $title,
        public readonly string $details,
    ) {}

    /**
     * @param array{timecode: string, title: string, details: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            timecode: $data['timecode'],
            title: $data['title'],
            details: $data['details'],
        );
    }

    /**
     * @return array{timecode: string, title: string, details: string}
     */
    public function toArray(): array
    {
        return [
            'timecode' => $this->timecode,
            'title'    => $this->title,
            'details'  => $this->details,
        ];
    }
}
```

**Тесты:** `tests/Unit/Domain/ValueObjects/SummaryKeyPointTest.php`

- Создание с корректными данными
- `toArray()` возвращает правильную структуру
- `fromArray()` корректно маппит данные

---

### Шаг 3 — Расширить `SummaryResult` DTO

**Файл:** `app/Application/DTO/SummaryResult.php`

Заменить плоскую строку `$text` на три семантических поля:

```php
<?php

declare(strict_types=1);

namespace App\Application\DTO;

use App\Domain\ValueObjects\SummaryKeyPoint;

final readonly class SummaryResult
{
    /**
     * @param SummaryKeyPoint[] $keyPoints
     */
    public function __construct(
        private string $introduction,
        private array $keyPoints,
        private ?string $conclusion = null,
    ) {}

    public function introduction(): string
    {
        return $this->introduction;
    }

    /**
     * @return SummaryKeyPoint[]
     */
    public function keyPoints(): array
    {
        return $this->keyPoints;
    }

    public function conclusion(): ?string
    {
        return $this->conclusion;
    }

    /**
     * Сериализация для хранения в JSONB-колонке.
     *
     * @return array{introduction: string, key_points: array<int, array{timecode: string, title: string, details: string}>, conclusion: string|null}
     */
    public function toArray(): array
    {
        return [
            'introduction' => $this->introduction,
            'key_points'   => array_map(fn (SummaryKeyPoint $kp) => $kp->toArray(), $this->keyPoints),
            'conclusion'   => $this->conclusion,
        ];
    }

    /**
     * Восстановление из JSONB-массива (используется репозиторием при чтении из БД).
     *
     * @param array{introduction: string, key_points: array<int, array{timecode: string, title: string, details: string}>, conclusion?: string|null} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            introduction: $data['introduction'],
            keyPoints: array_map(
                fn (array $kp) => SummaryKeyPoint::fromArray($kp),
                $data['key_points'],
            ),
            conclusion: $data['conclusion'] ?? null,
        );
    }
}
```

**Обновить тесты** `SummaryResultTest.php` — все сценарии с новым контрактом.

---

### Шаг 4 — Создать `YoutubeSummarizerAgent`

**Файл:** `app/Ai/Agents/YoutubeSummarizerAgent.php`

```php
<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;
use Stringable;

#[Provider([Lab::DeepSeek, Lab::Groq, Lab::OpenAI, Lab::Anthropic])]
#[Model('deepseek-v4-flash')]   // DeepSeek V4 Flash — default; быстрее V3, SDK переключится при сбое
#[Temperature(0.3)]
#[Timeout(120)]
final class YoutubeSummarizerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): Stringable|string
    {
        return <<<'PROMPT'
        You are an expert assistant that summarizes YouTube video transcripts.

        Rules:
        - Write a concise introduction paragraph (2-4 sentences).
        - Extract key points from the transcript and assign each a timecode in "MM:SS" or "HH:MM:SS" format.
          Use the timecodes that appear naturally in the transcript content. If no timecodes
          are present, distribute them proportionally based on transcript position.
          For videos under 1 hour use MM:SS (e.g. "03:45"). For videos 1 hour or longer use HH:MM:SS (e.g. "01:15:30").
        - Each key point must have: timecode (MM:SS or HH:MM:SS), a short title, and a 1-2 sentence detail.
        - Write a brief conclusion if the transcript has a clear takeaway.
        - Respond entirely in English.
        - Do not invent content not present in the transcript.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'introduction' => $schema->string()->required(),
            'key_points'   => $schema->array()->items(
                $schema->object(fn (JsonSchema $s): array => [
                    'timecode' => $s->string()->description('Format MM:SS (e.g. "03:45") for videos under 1 hour, or HH:MM:SS (e.g. "01:15:30") for longer videos')->required(),
                    'title'    => $s->string()->required(),
                    'details'  => $s->string()->required(),
                ]),
            )->required(),
            'conclusion' => $schema->string(),
        ];
    }
}
```

---

### Шаг 5 — Создать `LaravelAiSummaryAdapter`

**Файл:** `app/Infrastructure/Adapters/Output/Summary/LaravelAiSummaryAdapter.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Summary;

use App\Ai\Agents\YoutubeSummarizerAgent;
use App\Application\DTO\SummaryResult;
use App\Application\Ports\Output\SummaryProviderInterface;
use App\Domain\ValueObjects\SummaryKeyPoint;
use App\Domain\ValueObjects\SummaryOptions;
use App\Domain\ValueObjects\TranscriptionText;
use App\Shared\Exceptions\SummaryFailedException;
use RuntimeException;
use Webmozart\Assert\Assert;

final class LaravelAiSummaryAdapter implements SummaryProviderInterface
{
    public function summarize(TranscriptionText $transcriptText, SummaryOptions $options): SummaryResult
    {
        try {
            $response = YoutubeSummarizerAgent::make()->prompt(
                sprintf(
                    "Summarize the following transcript in maximum %d words. Style: %s.\n\nTranscript:\n%s",
                    $options->maxWords(),
                    $options->style(),
                    $transcriptText->value(),
                ),
            );

            // StructuredAgentResponse implements ArrayAccess — обращаемся через [] напрямую.
            // toArray() не гарантирован SDK; (array) $response безопаснее, но ArrayAccess-индексация
            // работает и с webmozart/assert без промежуточного приведения.
            /** @var array<string, mixed> $data */
            $data = (array) $response;

            Assert::isArray($data, 'AI SDK response must be an array.');
            Assert::keyExists($data, 'introduction', 'Missing key: introduction.');
            Assert::string($data['introduction'], 'introduction must be a string.');
            Assert::keyExists($data, 'key_points', 'Missing key: key_points.');
            Assert::isArray($data['key_points'], 'key_points must be an array.');

            $keyPoints = [];

            foreach ($data['key_points'] as $index => $kp) {
                Assert::isArray($kp, sprintf('key_points[%d] must be an array.', $index));
                Assert::keyExists($kp, 'timecode', sprintf('key_points[%d] missing timecode.', $index));
                Assert::keyExists($kp, 'title', sprintf('key_points[%d] missing title.', $index));
                Assert::keyExists($kp, 'details', sprintf('key_points[%d] missing details.', $index));
                Assert::string($kp['timecode'], sprintf('key_points[%d].timecode must be a string.', $index));
                Assert::string($kp['title'], sprintf('key_points[%d].title must be a string.', $index));
                Assert::string($kp['details'], sprintf('key_points[%d].details must be a string.', $index));

                $keyPoints[] = new SummaryKeyPoint(
                    timecode: $kp['timecode'],
                    title: $kp['title'],
                    details: $kp['details'],
                );
            }

            $conclusion = $data['conclusion'] ?? null;

            if ($conclusion !== null) {
                Assert::string($conclusion, 'conclusion must be a string or null.');
            }

            return new SummaryResult(
                introduction: $data['introduction'],
                keyPoints: $keyPoints,
                conclusion: $conclusion,
            );
        } catch (RuntimeException $e) {
            throw new SummaryFailedException(
                'LaravelAiSummaryAdapter failed: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }
}
```

**Переименовать** `OpenAiSummaryAdapter.php` → `LegacyOpenAiSummaryAdapter.php` (оставить до завершения CI-цикла с новым адаптером, затем удалить).

---

### Шаг 6 — Миграция `summary` TEXT → JSON

**Файл:** `database/migrations/2026_05_11_000009_change_summary_to_jsonb_in_media_tasks.php`

> ⚠️ **Важно:** сырой SQL (`json_build_object`, `::jsonb`) работает только в PostgreSQL.
> Проект использует PostgreSQL 16+ (PRD §2), но backfill пишем через Eloquent-чанки —
> это кросс-базово, безопасно на больших таблицах и не блокирует таблицу на массовом UPDATE.

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Backfill — wrap legacy plain-text rows in valid JSON before changing column type.
        // Chunked to avoid table-lock on large datasets.
        DB::table('media_tasks')
            ->whereNotNull('summary')
            ->where('summary', 'not like', '{%')
            ->orderBy('id')
            ->chunk(100, function ($tasks): void {
                foreach ($tasks as $task) {
                    DB::table('media_tasks')
                        ->where('id', $task->id)
                        ->update([
                            'summary' => json_encode([
                                'introduction' => $task->summary,
                                'key_points'   => [],
                                'conclusion'   => null,
                            ]),
                        ]);
                }
            });

        // Step 2: Change column type TEXT → JSON (Laravel handles driver-specific DDL).
        Schema::table('media_tasks', function (Blueprint $table): void {
            $table->json('summary')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('media_tasks', function (Blueprint $table): void {
            $table->text('summary')->nullable()->change();
        });
    }
};
```

**Обновить Eloquent-модель** *(MediaTask-модель в Infrastructure)*:

```php
protected function casts(): array
{
    return [
        'summary' => 'array', // Laravel авто-декодирует jsonb → PHP array
    ];
}
```

**Обновить `MediaTaskEloquentRepository`** — при чтении вызывать `SummaryResult::fromArray($model->summary)` если `$model->summary !== null`.

---

### Шаг 7 — Обновить DI, Deptrac, тесты

#### AppServiceProvider

```php
// Заменить старый биндинг:
$this->app->bind(SummaryProviderInterface::class, LaravelAiSummaryAdapter::class);
```

#### deptrac.yaml — добавить разрешения для Infrastructure

```yaml
ruleset:
  Infrastructure:
    - Application
    - Domain
    # SDK-классы разрешены только в Infrastructure
    # Laravel\Ai\* и Webmozart\Assert\* — добавить в allowedLayers или через ignore-uncovered
```

#### Тесты адаптера

**Файл:** `tests/Unit/Infrastructure/Summary/LaravelAiSummaryAdapterTest.php`

```php
use App\Ai\Agents\YoutubeSummarizerAgent;
use App\Infrastructure\Adapters\Output\Summary\LaravelAiSummaryAdapter;

beforeEach(function () {
    YoutubeSummarizerAgent::fake([
        [
            'introduction' => 'This video covers Laravel best practices.',
            'key_points'   => [
                ['timecode' => '01:30', 'title' => 'Service Providers', 'details' => 'How to register bindings.'],
                ['timecode' => '05:00', 'title' => 'Eloquent Tips',     'details' => 'Avoid N+1 queries.'],
            ],
            'conclusion' => 'Follow these practices for clean code.',
        ],
    ]);
});

it('maps structured output to SummaryResult correctly', function () {
    $adapter  = new LaravelAiSummaryAdapter();
    $result   = $adapter->summarize($transcriptText, $options);

    expect($result->introduction())->toBe('This video covers Laravel best practices.')
        ->and($result->keyPoints())->toHaveCount(2)
        ->and($result->keyPoints()[0]->timecode)->toBe('01:30')
        ->and($result->conclusion())->toBe('Follow these practices for clean code.');

    YoutubeSummarizerAgent::assertPrompted(
        fn ($p) => str_contains($p->prompt, $transcriptText->value()),
    );
});

it('throws SummaryFailedException on invalid schema response', function () {
    YoutubeSummarizerAgent::fake([['bad_key' => 'value']]);

    expect(fn () => (new LaravelAiSummaryAdapter())->summarize($transcriptText, $options))
        ->toThrow(SummaryFailedException::class);
});
```

---

## 4. Фронтенд: кликабельные таймкоды (Vue 3)

После получения структурированного ответа от API фронтенд рендерит `key_points` с кликом на таймкод:

```vue
<!-- Инициализация YouTube Player API -->
<iframe
  ref="player"
  :src="`https://www.youtube.com/embed/${videoId}?enablejsapi=1`"
/>

<div v-for="point in result.key_points" :key="point.timecode">
  <button @click="seekTo(point.timecode)" class="timecode-btn">
    {{ point.timecode }}
  </button>
  <strong>{{ point.title }}</strong>
  <p>{{ point.details }}</p>
</div>
```

```js
function seekTo(timecode) {
  // Поддержка MM:SS и HH:MM:SS — видео подкасты и стримы могут быть длиннее часа
  const parts = timecode.split(':').map(Number)
  let seconds = 0
  if (parts.length === 3) {
    seconds = parts[0] * 3600 + parts[1] * 60 + parts[2] // HH:MM:SS
  } else if (parts.length === 2) {
    seconds = parts[0] * 60 + parts[1]                    // MM:SS
  }
  // enablejsapi=1 обязателен в src iframe — без него postMessage игнорируется
  playerIframe.value.contentWindow.postMessage(
    JSON.stringify({ event: 'command', func: 'seekTo', args: [seconds, true] }),
    '*',
  )
}
```

Фича разблокируется автоматически через Structured Output — никакого парсинга регулярками.

---

## 5. Чеклист перед завершением

- [ ] `composer require laravel/ai webmozart/assert` выполнен
- [ ] Миграции SDK удалены из `database/migrations/`
- [ ] `SummaryKeyPoint` VO создан и покрыт 100% юнит-тестами
- [ ] `SummaryResult` DTO обновлён, старые тесты актуализированы
- [ ] `YoutubeSummarizerAgent` создан в `app/Ai/Agents/`
- [ ] `LaravelAiSummaryAdapter` реализован с `webmozart/assert` валидацией
- [ ] `OpenAiSummaryAdapter` переименован в `LegacyOpenAiSummaryAdapter`
- [ ] Миграция `summary TEXT → JSONB` создана и применена (с backfill)
- [ ] `MediaTaskEloquentRepository` обновлён для маппинга `SummaryResult::fromArray()`
- [ ] DI-биндинг в `AppServiceProvider` обновлён
- [ ] `deptrac.yaml` допускает `Laravel\Ai\*` только в Infrastructure
- [ ] Тесты: success, invalid schema, failover сценарии покрыты
- [ ] `GroqWhisperAdapter` и `NocixWhisperCppAdapter` **не тронуты**
- [ ] `php artisan check` (phpstan + phpcs + deptrac + pest) проходит

---

## 6. Риски и митигация

| Риск | Митигация |
|---|---|
| `laravel/ai` не поддерживает PHP 8.5 | Проверить `composer require` без `--ignore-platform-reqs` |
| Breaking change в SDK при обновлении | `LegacyOpenAiSummaryAdapter` как страховка; зафиксировать версию в `composer.json` |
| DeepSeek не поддерживает Structured Output (JSON Schema) | Проверить совместимость `HasStructuredOutput` с `deepseek-v4-flash` при установке SDK; если нет — добавить `response_format: json_object` через `HasProviderOptions` |
| Groq failover не срабатывает | Интеграционный тест с `preventStrayPrompts()` |
| Backfill-миграция падает на prod данных | Тест миграции на копии prod БД до деплоя |
| PHPStan Level 9 на `$response->toArray()` | `webmozart/assert` + явные `is_array`/`is_string` снимают все `mixed`-ошибки |

