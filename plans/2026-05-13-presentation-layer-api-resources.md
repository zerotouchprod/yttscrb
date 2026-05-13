# Plan: Presentation Layer — API Resources + OpenAPI/Swagger

**Дата:** 2026-05-13  
**Статус:** требует корректировок перед реализацией  
**Затрагивает:** `TranscribeVideoController`, Domain value objects, тесты, OpenAPI-документация

---

## Проблема

Текущий `TranscribeVideoController` содержит ~200 строк ручного маппинга `MediaTask → array`,
который дублируется в 5 местах (`create`-dedup, `status`, `latest`, `history`, `search`).

Конкретные нарушения:

- **DRY**: поля `task_id`, `youtube_url`, `video_id`, `_links.public_page` строятся вручную
  в каждом методе.
- **SRP**: контроллер совмещает бизнес-проверки (квота, длительность) и HTTP-сериализацию.
- **Типобезопасность**: сырые `array` без phpdoc-типов → `mixed` на всём пути.
- **Архитектурная утечка**: `$task->summary()?->toArray()` вызывается из контроллера напрямую —
  Domain-метод, предназначенный для JSONB-персистентности, используется как HTTP-сериализатор.

---

## Цель

Ввести тонкий **Presentation Layer** из Laravel JSON Resources в Infrastructure.
Контроллер перестаёт строить массивы вручную — делегирует маппинг ресурсам.

---

## Результат ревью / обязательные корректировки перед стартом

В текущем виде план **не готов к прямой реализации** без дополнительных правок, потому что в нём были найдены несколько конфликтов с `Prd.md`, текущим кодом и собственными шагами плана:

1. **Конфликт контракта `summary`:**
   - `Prd.md` в примерах `GET /api/transcribe/{id}` и `GET /api/history/latest` показывает `result.summary` как **строку**.
   - Текущий runtime-код и фронтенд уже работают с `summary` как со **структурированным объектом** (`introduction`, `key_points`, `conclusion`).
   - Следовательно, реализация resources/OpenAPI **не может считаться "без изменения контракта"**. Нужно либо:
     - сначала обновить `Prd.md` под фактический runtime-контракт,
     - либо отдельно спланировать возврат API и фронтенда к строковому `summary`.

2. **Конфликт по лимиту 429:**
   - В коде и тестах уже реализован **daily limit**.
   - В `Prd.md` встречаются противоречивые формулировки: и `10/день`, и `10/мес`.
   - OpenAPI-документация в плане не должна закреплять спорный контракт, пока `Prd.md` не приведён к одному варианту.

3. **Неполная OpenAPI-схема для `latest()`:**
   - План правильно вводит `LatestMediaTaskResource`, потому что `latest()` не включает `video_id`.
   - Но в секции OpenAPI ответ `GET /api/history/latest` всё ещё ссылается на общую `MediaTask`-схему, которая **требует `video_id` и `_links.self`**.
   - Это внутренняя несогласованность плана.

4. **Ошибка в примере использования `MediaTaskCollection`:**
   - В шаге 5 третий аргумент конструктора — `?string $searchQuery`.
   - В шаге 7 в пример передаётся `$baseUrl`, а не поисковая строка.
   - Такой код не соответствует описанному API коллекции.

Ниже план уже исправлен с учётом этих замечаний.

---

## Структура новых файлов

```
# Часть 1 — Resources (9 новых + 1 изменяемый):
app/Infrastructure/Adapters/Input/Web/Resources/SummaryResource.php
app/Infrastructure/Adapters/Input/Web/Resources/MediaTaskResource.php
app/Infrastructure/Adapters/Input/Web/Resources/LatestMediaTaskResource.php
app/Infrastructure/Adapters/Input/Web/Resources/MediaTaskListItemResource.php
app/Infrastructure/Adapters/Input/Web/Resources/MediaTaskCollection.php
app/Infrastructure/Adapters/Input/Web/Resources/MediaTaskCreatedResource.php
tests/Unit/Infrastructure/Adapters/Input/Web/Resources/SummaryResourceTest.php
tests/Unit/Infrastructure/Adapters/Input/Web/Resources/MediaTaskResourceTest.php
tests/Unit/Infrastructure/Adapters/Input/Web/Resources/MediaTaskListItemResourceTest.php
tests/Unit/Infrastructure/Adapters/Input/Web/Resources/MediaTaskCollectionTest.php

app/Infrastructure/Adapters/Input/Web/TranscribeVideoController.php  # изменяемый

# Часть 2 — OpenAPI/Swagger (9 новых + 4 изменяемых):
app/Infrastructure/Adapters/Input/Web/OpenApi/Schemas/SummarySchema.php
app/Infrastructure/Adapters/Input/Web/OpenApi/Schemas/MediaTaskSchema.php
app/Infrastructure/Adapters/Input/Web/OpenApi/Schemas/LatestMediaTaskSchema.php
app/Infrastructure/Adapters/Input/Web/OpenApi/Schemas/LatestMediaTaskEmptySchema.php
app/Infrastructure/Adapters/Input/Web/OpenApi/Schemas/MediaTaskListItemSchema.php
app/Infrastructure/Adapters/Input/Web/OpenApi/Schemas/MediaTaskCreatedSchema.php
app/Infrastructure/Adapters/Input/Web/OpenApi/Schemas/ErrorSchema.php
app/Infrastructure/Adapters/Input/Web/OpenApi/TranscribeVideoApi.php
app/Infrastructure/Adapters/Input/Web/OpenApi/OpenApiConfig.php

composer.json           # изменяемый: добавить zircote/swagger-php в require
config/openapi.php      # изменяемый (новый): конфигурация генерации
routes/api.php          # изменяемый: добавить маршрут /api/docs/openapi.json
phpstan.neon            # изменяемый: исключения для Schema-классов
```

---

## Шаг 1 — Аудит `toArray()` в Domain

> **Предусловие по документации:** до начала реализации нужно зафиксировать, какой контракт является целевым для `result.summary`.
> Если оставляем текущий runtime-подход со структурированным объектом summary, в этом же change set обязательно обновляем `Prd.md`.

**Файлы:** `SummaryResult.php`, `SummaryKeyPoint.php`

**Проблема:** методы `toArray()` в Domain используются в двух разных контекстах:
1. JSONB-персистентность (репозиторий) — **правомерное** использование.
2. HTTP-сериализация (контроллер) — **нарушение** архитектурных границ.

**Решение:**
- Методы `toArray()` **остаются** в Domain — они нужны репозиторию.
- В контроллере перестаём вызывать `$task->summary()?->toArray()` — эту роль берёт `SummaryResource`.
- Добавить PHPDoc-комментарий `/** @internal Used only by persistence layer. */` к обоим `toArray()`.
- HTTP-ресурс читает поля через геттеры / public properties объекта, не через `->toArray()`.

---

## Шаг 2 — Создать `SummaryResource`

**Файл:** `app/Infrastructure/Adapters/Input/Web/Resources/SummaryResource.php`

Расширяет `JsonResource`. Принимает `SummaryResult`.

```php
toArray():
  introduction  → $this->introduction()
  key_points    → [['timecode' => ..., 'title' => ..., 'details' => ...], ...]
                  (поля SummaryKeyPoint читаем через public properties, не через ->toArray())
  conclusion    → $this->conclusion()   // может быть null
```

**Тест `SummaryResourceTest`:**
- `SummaryResult` с двумя `SummaryKeyPoint` → `toArray()` возвращает правильную структуру.
- `conclusion = null` → ключ присутствует, значение `null`.
- Пустой `key_points` → `key_points` присутствует как `[]`.

---

## Шаг 3 — Создать `MediaTaskResource`

**Файл:** `app/Infrastructure/Adapters/Input/Web/Resources/MediaTaskResource.php`

Расширяет `JsonResource<MediaTask>`.

Используется в: `status()`, `create()` (dedup-ветка, HTTP 200), `latest()`.

```
Всегда:
  task_id       → $this->id()
  status        → $this->status()->value
  youtube_url   → $this->youtubeUrl()->value()
  video_id      → $this->youtubeUrl()->videoId()->value()
  title         → $this->title()
  created_at    → $this->createdAt()->format('c')
  _links.self   → "/api/transcribe/{id}"

Если status === completed:
  duration_sec            → $this->durationSec()
  completed_at            → $this->completedAt()->format('c')
  result.transcript       → $this->resultText()?->value()
  result.summary          → new SummaryResource($this->summary()) | null
  result.word_count       → $this->resultText()?->wordCount()
  _links.download_txt     → "/api/transcribe/{id}/download"
  _links.public_page      → "/v/{slug}" (если slug !== null && !isDmcaRemoved())

Если status === processing:
  estimated_completion_sec → 90

Если status === failed:
  error_message → $this->errorMessage()
  failed_at     → $this->failedAt()?->format('c')
```

Реализация: явные `if`-блоки в `toArray()` вместо `$this->when()` — для полной type inference
под PHPStan level 9 (избегаем `mixed` из `when()`).

**Тест `MediaTaskResourceTest`:**
- `pending` задача: только базовые поля.
- `processing` задача: добавляется `estimated_completion_sec`.
- `completed` задача без slug: нет `public_page`.
- `completed` задача со slug, без DMCA: `public_page = '/v/...'`.
- `completed` задача со slug + DMCA: `public_page` отсутствует.
- `completed` задача без summary: `result.summary = null`.
- `completed` задача с summary: правильная вложенная структура через `SummaryResource`.
- `failed` задача: поля `error_message`, `failed_at`.

---

### Шаг 3а — Создать `LatestMediaTaskResource`

**Файл:** `app/Infrastructure/Adapters/Input/Web/Resources/LatestMediaTaskResource.php`

**Причина:** `latest()` эндпоинт (PRD §7.5) НЕ включает `video_id` в ответе.
Применение `MediaTaskResource` к `latest()` **добавило бы** `video_id` — расширение
публичного API-контракта. Поэтому создаём отдельный облегчённый ресурс.

Расширяет `JsonResource<MediaTask>`. Используется **только** в `latest()`.

```
task_id     → $this->id()
youtube_url → $this->youtubeUrl()->value()
title       → $this->title()
status      → $this->status()->value
duration_sec → $this->durationSec()
result      → [
    transcript  → $this->resultText()?->value()
    summary     → new SummaryResource($this->summary()) | null
    word_count  → $this->resultText()?->wordCount()
]
created_at  → $this->createdAt()->format('c')
completed_at → $this->completedAt()?->format('c')
_links.download_txt → "/api/transcribe/{id}/download"
```

**Тест:** проверить отсутствие `video_id` в ответе, наличие `result.summary`.

## Шаг 4 — Создать `MediaTaskListItemResource`

**Файл:** `app/Infrastructure/Adapters/Input/Web/Resources/MediaTaskListItemResource.php`

Облегчённый ресурс **без транскрипта и summary-содержимого**.

Используется в: `history()`, `search()`.

```
task_id       → $this->id()
youtube_url   → $this->youtubeUrl()->value()
video_id      → $this->youtubeUrl()->videoId()->value()
title         → $this->title()
status        → $this->status()->value
duration_sec  → $this->durationSec()
created_at    → $this->createdAt()->format('c')
completed_at  → $this->completedAt()?->format('c')
_links.public_page → "/v/{slug}" (условно, как выше)
```

Применяем `array_filter($data, fn ($v) => $v !== null)` для исключения `null`-полей.
**Важно:** фильтруем только `null`, а не falsy-значения — `duration_sec = 0` (теоретически
возможный случай) не должно быть удалено.

**Тест `MediaTaskListItemResourceTest`:**
- `pending` задача: нет `completed_at`, нет `public_page`, нет `duration_sec`.
- `completed` задача со slug: `public_page` присутствует.
- `completed` задача с DMCA: `public_page` отсутствует.

---

## Шаг 5 — Создать `MediaTaskCollection`

**Файл:** `app/Infrastructure/Adapters/Input/Web/Resources/MediaTaskCollection.php`

Расширяет `ResourceCollection`. Принимает `LengthAwarePaginator`, опциональный
`array $extraMeta = []` и опциональный `string $searchQuery = null`.

**Конструктор** — чтобы не конфликтовать с `parent::__construct(mixed $resource)`:
храним `$extraMeta` и `$searchQuery` в приватных свойствах, вызываем
`parent::__construct($paginator->getCollection())`.

```php
final class MediaTaskCollection extends ResourceCollection
{
    public function __construct(
        LengthAwarePaginator $paginator,
        array $extraMeta = [],
        ?string $searchQuery = null,
    ) {
        $this->paginator = $paginator;
        $this->extraMeta = $extraMeta;
        $this->searchQuery = $searchQuery;
        parent::__construct($paginator->getCollection());
    }
}
```

Метод `toArray()` — с корректными URL (учитываем `?` vs `&`):
```
data   → MediaTaskListItemResource::collection($this->collection)
meta   → [
    current_page  → $paginator->currentPage()
    last_page     → $paginator->lastPage()
    per_page      → $paginator->perPage()
    total         → $paginator->total()
    ...$extraMeta                       ← для search: ['query' => $q]
]
_links → [
    first → buildPageUrl(page: 1)
    prev  → buildPageUrl(page: $paginator->currentPage() - 1)
    next  → buildPageUrl(page: $paginator->currentPage() + 1)
    last  → buildPageUrl(page: $paginator->lastPage())
]
```

**Вспомогательный метод `buildPageUrl(int $page): string`:**
- Если `$searchQuery !== null` → `'/api/search?q=' . urlencode($searchQuery) . '&per_page=' . $paginator->perPage() . '&page=' . $page`
- Иначе → `'/api/history?page=' . $page . '&per_page=' . $paginator->perPage()`

**Важно:** `previousPageUrl()`/`nextPageUrl()` из Laravel возвращают **абсолютные** URL
(`http://localhost/...`). План строит относительные URL вручную для единообразия
с `first`/`last`. Это изменение поведения (было: абсолютные prev/next),
но делает API консистентным — все _links теперь относительные.

Использование в контроллере:
```php
// history:
return (new MediaTaskCollection($paginator))->response();

// search:
return (new MediaTaskCollection($paginator, ['query' => $query], $query))->response();
```

---

## Шаг 6 — Создать `MediaTaskCreatedResource`

**Файл:** `app/Infrastructure/Adapters/Input/Web/Resources/MediaTaskCreatedResource.php`

Используется **только** в `create()` — ветка нового (pending) задания, HTTP 202.
Форма не совпадает с `MediaTaskResource` (минимальный ответ без result).

```
task_id     → $this->id()
status      → $this->status()->value
youtube_url → $this->youtubeUrl()->value()
created_at  → $this->createdAt()->format('c')
_links.status → "/api/transcribe/{id}"
```

---

## Шаг 7 — Рефакторить `TranscribeVideoController`

**Файл:** `app/Infrastructure/Adapters/Input/Web/TranscribeVideoController.php`

После рефакторинга методы сокращаются примерно вдвое:

```php
// create() — dedup-ветка:
return (new MediaTaskResource($storedTask))->response()->setStatusCode(200);

// create() — новая задача:
return (new MediaTaskCreatedResource($storedTask))->response()->setStatusCode(202);

// status():
return (new MediaTaskResource($task))->response();

// latest():
return (new LatestMediaTaskResource($task))->response();

// history():
return (new MediaTaskCollection($paginator))->response();
// ВНИМАНИЕ: кэш-обёртку обновить — см. Шаг 7а.

// search():
return (new MediaTaskCollection($paginator, ['query' => $query], $query))->response();
```

Конструктор контроллера **не меняется** — `SubtitleProviderInterface` остаётся (используется для `extractDuration()`).

### Шаг 7а — Исправить кэширование в `history()`

**Важно:** сейчас кэшируется `$buildResponse` (Closure), которая возвращает обычный массив.
После рефакторинга нужно кэшировать `->toArray(request())` из коллекции, а не сам объект
`ResourceCollection` (он не сериализуем).

```php
// Было:
$responseData = Cache::remember($cacheKey, 60, $buildResponse);
return new JsonResponse($responseData);

// Станет:
$responseData = Cache::remember(
    $cacheKey,
    60,
    fn () => (new MediaTaskCollection($paginator))->toArray(request()),
);
return new JsonResponse($responseData);
```

**Известный архитектурный долг:** `Cache::remember()` — статический Laravel facade,
запрещённый AGENTS.md §4. Текущий контроллер уже нарушает это правило (строки 18, 80, 280).
Полноценное исправление требует инъекции `\Illuminate\Contracts\Cache\Repository` через
конструктор — это выходит за scope данного плана, но должно быть выполнено в отдельной задаче
по устранению facade-зависимостей из Infrastructure.

---

## Шаг 8 — Обновить тесты

### Существующие тесты

Файл `tests/Feature/Feature/Transcribe/CreateTranscriptionTaskTest.php` проверяет JSON-пути
(`assertJsonPath`). Форма ответа **не меняется** → тесты должны пройти без изменений.
Запускаем для верификации после рефакторинга.

### Новые unit-тесты

Четыре новых файла в `tests/Unit/Infrastructure/Adapters/Input/Web/Resources/`:
- `SummaryResourceTest.php` — 3 кейса (см. Шаг 2)
- `MediaTaskResourceTest.php` — 8 кейсов (см. Шаг 3)
- `MediaTaskListItemResourceTest.php` — 3 кейса (см. Шаг 4)
- `MediaTaskCollectionTest.php` — 5 кейсов (см. ниже)

**`MediaTaskCollectionTest` — 5 кейсов:**
- `history()` коллекция: первая страница (есть `next`/`last`, нет `prev`)
- `history()` коллекция: последняя страница (есть `prev`/`first`, нет `next`)
- `history()` коллекция: `meta` содержит `current_page`, `last_page`, `per_page`, `total`
- `search()` коллекция: `extraMeta` содержит `query`
- `search()` коллекция: `_links` используют `/api/search?...` URL

Все тесты **не требуют HTTP-стека** — вызывают `$resource->toArray(request())` напрямую.
Для `MediaTask` используем factory-хелпер или реальный конструктор через рефлексию.

### Новые / обновлённые feature-тесты

Не плодим дублирующие сценарии, если покрытие уже есть. Вместо этого:

- **обновить существующий** `tests/Feature/Feature/Transcribe/CreateTranscriptionTaskTest.php`:
  - явно проверить, что `GET /api/history/latest` возвращает **структурированный** `result.summary`, а не `{}`;
  - при необходимости добавить точечные `assertJsonPath()` на `result.summary.introduction`, `result.summary.key_points`, `result.summary.conclusion`.
- **использовать существующий** `tests/Feature/Feature/Seo/SearchControllerTest.php` для верификации `_links` и формы list-item ресурса;
- **добавить недостающее feature-покрытие** только там, где его реально нет:
  - `GET /api/transcribe/{id}` — completed / processing / failed / 404;
  - `GET /api/transcribe/{id}/download` — 200 / 404 not found / 404 not completed.

---

## Часть 2: OpenAPI/Swagger документация через атрибуты

> **Важно:** эта часть выполняется только после выравнивания `Prd.md` с целевым публичным контрактом.
> Иначе OpenAPI закрепит поведение, которое формально противоречит source of truth.

### Выбор пакета

Используем [`zircote/swagger-php`](https://github.com/zircote/swagger-php) — de-facto стандарт
для PHP OpenAPI через атрибуты. Он НЕ требует Laravel-прослойки, работает на чистом PHP 8.x
с атрибутами вроде `#[OA\Schema]`, `#[OA\Get]`, `#[OA\Response]`.

**Почему не альтернативы:**
- `dedoc/scramble` — авто-генерация без атрибутов, но мы ценим **явность** и контроль.
- `darkaonline/l5-swagger` — Laravel-обёртка, тянет Swagger UI; можно добавить позже.

Атрибуты добавляются в **Infrastructure** слой — на Resource-классы и на Controller.
Domain и Application остаются чистыми.

---

### Шаг 9 — Установить `zircote/swagger-php` и конфигурацию

**Файлы:** `composer.json`, `config/openapi.php`, `app/Infrastructure/Adapters/Input/Web/OpenApi/OpenApiConfig.php`

```bash
composer require zircote/swagger-php
```

**Важно:** пакет ставится в `require` (не `require-dev`), потому что маршрут
`GET /api/docs/openapi.json` вызывает `Generator::scan()` во время HTTP-запроса.
В production `vendor` не содержит dev-зависимостей — если пакет в `require-dev`,
маршрут упадёт с `Class "OpenApi\Generator" not found`.

Создать `config/openapi.php`:

```php
<?php

declare(strict_types=1);

return [
    'title'       => env('APP_NAME', 'yttscrb') . ' API',
    'version'     => '1.0.0',
    'description' => 'YouTube Transcriber & Summarizer — Public API.',
    'servers'     => [
        ['url' => env('APP_URL', 'http://localhost'), 'description' => 'Current environment'],
    ],
    'scan_paths' => [
        app_path('Infrastructure/Adapters/Input/Web/Resources'),
        app_path('Infrastructure/Adapters/Input/Web/OpenApi'),
        app_path('Infrastructure/Adapters/Input/Web/TranscribeVideoController.php'),
    ],
    'output_path' => public_path('openapi.json'),
];
```

Создать типизированный конфиг-объект `OpenApiConfig` — чтобы избежать `mixed`
от `config()` под PHPStan level 9:

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi;

final readonly class OpenApiConfig
{
    /** @param string[] $scanPaths */
    public function __construct(
        public string $title,
        public string $version,
        public string $description,
        public array $servers,
        public array $scanPaths,
        public string $outputPath,
    ) {
    }

    public static function fromArray(array $config): self
    {
        return new self(
            title: (string) ($config['title'] ?? ''),
            version: (string) ($config['version'] ?? '1.0.0'),
            description: (string) ($config['description'] ?? ''),
            servers: (array) ($config['servers'] ?? []),
            scanPaths: (array) ($config['scan_paths'] ?? []),
            outputPath: (string) ($config['output_path'] ?? ''),
        );
    }
}
```

---

### Шаг 10 — Создать OpenAPI Schema-классы

Schema-классы — это «пустые» классы с `#[OA\Schema]` атрибутом. Они **не инстанцируются**
во время выполнения — используются **только** `swagger-php` при генерации `openapi.json`.
Размещаем в `app/Infrastructure/Adapters/Input/Web/OpenApi/Schemas/`.

#### 10a. `ErrorSchema.php`

Универсальная схема ошибок — используется во всех endpoint'ах.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ErrorResponse',
    required: ['error'],
    properties: [
        new OA\Property(
            property: 'error',
            required: ['code', 'message'],
            properties: [
                new OA\Property(property: 'code', type: 'string', example: 'TASK_NOT_FOUND'),
                new OA\Property(property: 'message', type: 'string', example: 'Task not found.'),
                new OA\Property(
                    property: 'details',
                    type: 'object',
                    additionalProperties: new OA\AdditionalProperties(type: 'string'),
                    nullable: true,
                ),
            ],
            type: 'object',
        ),
    ],
)]
final class ErrorSchema
{
}
```

#### 10b. `SummarySchema.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'Summary',
    required: ['introduction', 'key_points', 'conclusion'],
    properties: [
        new OA\Property(property: 'introduction', type: 'string'),
        new OA\Property(
            property: 'key_points',
            type: 'array',
            items: new OA\Items(
                required: ['timecode', 'title', 'details'],
                properties: [
                    new OA\Property(property: 'timecode', type: 'string', example: '00:02:30'),
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'details', type: 'string'),
                ],
                type: 'object',
            ),
        ),
        new OA\Property(property: 'conclusion', type: 'string', nullable: true),
    ],
)]
final class SummarySchema
{
}
```

#### 10c. `MediaTaskCreatedSchema.php`

Схема для HTTP 202 (задача создана, ожидает обработки).

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MediaTaskCreated',
    required: ['task_id', 'status', 'youtube_url', 'created_at', '_links'],
    properties: [
        new OA\Property(property: 'task_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'youtube_url', type: 'string', format: 'uri'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(
            property: '_links',
            required: ['status'],
            properties: [
                new OA\Property(property: 'status', type: 'string', format: 'uri',
                    example: '/api/transcribe/550e8400-e29b-41d4-a716-446655440000'),
            ],
            type: 'object',
        ),
    ],
)]
final class MediaTaskCreatedSchema
{
}
```

#### 10d. `MediaTaskSchema.php`

Полная схема задачи с условными полями по статусу.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MediaTask',
    description: 'Full task resource (status/latest/dedup-200 endpoints). Fields vary by status.',
    required: ['task_id', 'status', 'youtube_url', 'video_id', 'title', 'created_at', '_links'],
    properties: [
        new OA\Property(property: 'task_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'processing', 'completed', 'failed']),
        new OA\Property(property: 'youtube_url', type: 'string', format: 'uri'),
        new OA\Property(property: 'video_id', type: 'string', example: 'dQw4w9WgXcQ'),
        new OA\Property(property: 'title', type: 'string', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'duration_sec', type: 'integer', nullable: true),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'error_message', type: 'string', nullable: true),
        new OA\Property(property: 'failed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'estimated_completion_sec', type: 'integer', example: 90),
        new OA\Property(
            property: 'result',
            properties: [
                new OA\Property(property: 'transcript', type: 'string'),
                new OA\Property(property: 'summary', ref: '#/components/schemas/Summary', nullable: true),
                new OA\Property(property: 'word_count', type: 'integer'),
            ],
            type: 'object',
            nullable: true,
        ),
        new OA\Property(
            property: '_links',
            properties: [
                new OA\Property(property: 'self', type: 'string', format: 'uri'),
                new OA\Property(property: 'download_txt', type: 'string', format: 'uri'),
                new OA\Property(property: 'public_page', type: 'string', format: 'uri', nullable: true),
            ],
            type: 'object',
        ),
    ],
)]
final class MediaTaskSchema
{
}
```

#### 10e. `LatestMediaTaskSchema.php`

Отдельная схема для `GET /api/history/latest`, потому что этот endpoint **не включает**
`video_id` и `_links.self`, а использует только `_links.download_txt`.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LatestMediaTask',
    required: ['task_id', 'youtube_url', 'title', 'status', 'duration_sec', 'result', 'created_at', 'completed_at', '_links'],
    properties: [
        new OA\Property(property: 'task_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'youtube_url', type: 'string', format: 'uri'),
        new OA\Property(property: 'title', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['completed']),
        new OA\Property(property: 'duration_sec', type: 'integer'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(
            property: 'result',
            required: ['transcript', 'summary', 'word_count'],
            properties: [
                new OA\Property(property: 'transcript', type: 'string'),
                new OA\Property(property: 'summary', ref: '#/components/schemas/Summary', nullable: true),
                new OA\Property(property: 'word_count', type: 'integer'),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: '_links',
            required: ['download_txt'],
            properties: [
                new OA\Property(property: 'download_txt', type: 'string', format: 'uri'),
            ],
            type: 'object',
        ),
    ],
)]
final class LatestMediaTaskSchema
{
}
```

#### 10f. `LatestMediaTaskEmptySchema.php`

Схема для случая, когда completed-транскрипций ещё нет.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'LatestMediaTaskEmpty',
    required: ['task_id', 'status', 'message'],
    properties: [
        new OA\Property(property: 'task_id', type: 'null'),
        new OA\Property(property: 'status', type: 'null'),
        new OA\Property(property: 'message', type: 'string', example: 'No completed transcriptions yet.'),
    ],
)]
final class LatestMediaTaskEmptySchema
{
}
```

#### 10g. `MediaTaskListItemSchema.php`

Облегчённая схема для коллекций.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'MediaTaskListItem',
    required: ['task_id', 'youtube_url', 'video_id', 'title', 'status', 'created_at', '_links'],
    properties: [
        new OA\Property(property: 'task_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'youtube_url', type: 'string', format: 'uri'),
        new OA\Property(property: 'video_id', type: 'string'),
        new OA\Property(property: 'title', type: 'string', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'processing', 'completed', 'failed']),
        new OA\Property(property: 'duration_sec', type: 'integer', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'completed_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(
            property: '_links',
            properties: [
                new OA\Property(property: 'public_page', type: 'string', format: 'uri', nullable: true),
            ],
            type: 'object',
        ),
    ],
)]
final class MediaTaskListItemSchema
{
}
```

---

### Шаг 11 — Создать `TranscribeVideoApi` с атрибутами эндпоинтов

**Файл:** `app/Infrastructure/Adapters/Input/Web/OpenApi/TranscribeVideoApi.php`

Этот класс **не является контроллером**. Он существует только для группировки
`#[OA\*]` атрибутов в одном месте, чтобы `swagger-php` мог их обнаружить.
Все методы имеют пустое тело — они никогда не вызываются.

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\OpenApi;

use App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas\ErrorSchema;
use App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas\LatestMediaTaskEmptySchema;
use App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas\LatestMediaTaskSchema;
use App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas\MediaTaskCreatedSchema;
use App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas\MediaTaskListItemSchema;
use App\Infrastructure\Adapters\Input\Web\OpenApi\Schemas\MediaTaskSchema;
use OpenApi\Attributes as OA;

#[OA\Info(
    title: 'yttscrb API',
    version: '1.0.0',
    description: 'YouTube Transcriber & Summarizer — Public API.',
)]
#[OA\Tag(name: 'Transcription', description: 'Create and manage transcription tasks')]
#[OA\Tag(name: 'History', description: 'Browse and search completed transcriptions')]
/** @codeCoverageIgnore OpenAPI annotation container — never executed at runtime. */
final class TranscribeVideoApi
{
    // ── POST /api/transcribe ────────────────────────────────────────────

    #[OA\Post(
        path: '/api/transcribe',
        summary: 'Create a transcription task',
        description: 'Submits a YouTube URL for transcription and AI summarization.',
        tags: ['Transcription'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['youtube_url'],
                properties: [
                    new OA\Property(
                        property: 'youtube_url',
                        type: 'string',
                        format: 'uri',
                        example: 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                    ),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 202,
                description: 'Task created (new video)',
                content: new OA\JsonContent(ref: '#/components/schemas/MediaTaskCreated'),
            ),
            new OA\Response(
                response: 200,
                description: 'Task already completed (deduplication)',
                content: new OA\JsonContent(ref: '#/components/schemas/MediaTask'),
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid YouTube URL',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
            new OA\Response(
                response: 422,
                description: 'Video too long (>30 min)',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
            new OA\Response(
                response: 429,
                description: 'Daily quota exceeded',
                headers: [
                    new OA\Header(
                        header: 'Retry-After',
                        description: 'Seconds until quota resets',
                        schema: new OA\Schema(type: 'integer'),
                    ),
                ],
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function create(): void
    {
    }

    // ── GET /api/transcribe/{id} ────────────────────────────────────────

    #[OA\Get(
        path: '/api/transcribe/{id}',
        summary: 'Get task status',
        tags: ['Transcription'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Task status (shape varies by status)',
                content: new OA\JsonContent(ref: '#/components/schemas/MediaTask'),
            ),
            new OA\Response(
                response: 404,
                description: 'Task not found',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function status(): void
    {
    }

    // ── GET /api/transcribe/{id}/download ───────────────────────────────

    #[OA\Get(
        path: '/api/transcribe/{id}/download',
        summary: 'Download transcript as TXT',
        tags: ['Transcription'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Plain text transcript',
                content: new OA\MediaType(
                    mediaType: 'text/plain',
                    schema: new OA\Schema(type: 'string'),
                ),
            ),
            new OA\Response(
                response: 404,
                description: 'Task not found or not completed',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function download(): void
    {
    }

    // ── GET /api/history ────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/history',
        summary: 'List transcription history',
        tags: ['History'],
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15, maximum: 50)),
            new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string', enum: ['pending', 'processing', 'completed', 'failed'])),
            new OA\Parameter(name: 'public', in: 'query', schema: new OA\Schema(type: 'string', enum: ['1'])),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Paginated history',
                content: new OA\JsonContent(
                    required: ['data', 'meta', '_links'],
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/MediaTaskListItem'),
                        ),
                        new OA\Property(
                            property: 'meta',
                            required: ['current_page', 'last_page', 'per_page', 'total'],
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer'),
                                new OA\Property(property: 'last_page', type: 'integer'),
                                new OA\Property(property: 'per_page', type: 'integer'),
                                new OA\Property(property: 'total', type: 'integer'),
                            ],
                            type: 'object',
                        ),
                        new OA\Property(
                            property: '_links',
                            properties: [
                                new OA\Property(property: 'first', type: 'string', format: 'uri'),
                                new OA\Property(property: 'prev', type: 'string', format: 'uri', nullable: true),
                                new OA\Property(property: 'next', type: 'string', format: 'uri', nullable: true),
                                new OA\Property(property: 'last', type: 'string', format: 'uri'),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
        ],
    )]
    public function history(): void
    {
    }

    // ── GET /api/history/latest ─────────────────────────────────────────

    #[OA\Get(
        path: '/api/history/latest',
        summary: 'Get latest completed transcription',
        tags: ['History'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Latest completed task or null',
                content: new OA\JsonContent(
                    oneOf: [
                        new OA\Schema(ref: '#/components/schemas/LatestMediaTask'),
                        new OA\Schema(ref: '#/components/schemas/LatestMediaTaskEmpty'),
                    ],
                ),
            ),
        ],
    )]
    public function latest(): void
    {
    }

    // ── GET /api/search ─────────────────────────────────────────────────

    #[OA\Get(
        path: '/api/search',
        summary: 'Search transcriptions by title',
        tags: ['History'],
        parameters: [
            new OA\Parameter(
                name: 'q',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string', minLength: 2, maxLength: 100),
            ),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer', default: 1)),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15, maximum: 50)),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Search results',
                content: new OA\JsonContent(
                    required: ['data', 'meta', '_links'],
                    properties: [
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: '#/components/schemas/MediaTaskListItem'),
                        ),
                        new OA\Property(
                            property: 'meta',
                            required: ['current_page', 'last_page', 'per_page', 'total', 'query'],
                            properties: [
                                new OA\Property(property: 'current_page', type: 'integer'),
                                new OA\Property(property: 'last_page', type: 'integer'),
                                new OA\Property(property: 'per_page', type: 'integer'),
                                new OA\Property(property: 'total', type: 'integer'),
                                new OA\Property(property: 'query', type: 'string'),
                            ],
                            type: 'object',
                        ),
                        new OA\Property(
                            property: '_links',
                            properties: [
                                new OA\Property(property: 'first', type: 'string', format: 'uri'),
                                new OA\Property(property: 'prev', type: 'string', format: 'uri', nullable: true),
                                new OA\Property(property: 'next', type: 'string', format: 'uri', nullable: true),
                                new OA\Property(property: 'last', type: 'string', format: 'uri'),
                            ],
                            type: 'object',
                        ),
                    ],
                ),
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid query (empty, too short, too long, wildcard-only)',
                content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'),
            ),
        ],
    )]
    public function search(): void
    {
    }
}
```

---

### Шаг 12 — Маршрут для `openapi.json` и консольная команда

#### 12a. Маршрут

Добавить в `routes/api.php`:

```php
use App\Infrastructure\Adapters\Input\Web\OpenApi\OpenApiConfig;
use Illuminate\Support\Facades\Route;
use OpenApi\Generator;

Route::get('/docs/openapi.json', function () {
    $config = OpenApiConfig::fromArray(config('openapi'));

    $openapi = Generator::scan($config->scanPaths);

    // response()->json(string) делает двойную JSON-сериализацию.
    // Используем response(string, 200, [Content-Type header]) напрямую.
    return response($openapi->toJson(), 200, [
        'Content-Type' => 'application/json',
    ])->header('Access-Control-Allow-Origin', '*');
})->name('api.openapi');
```

#### 12b. Консольная команда (опционально, для CI/CD)

Добавить artisan-команду для генерации статического `openapi.json`:

```php
// app/Infrastructure/Console/Commands/GenerateOpenApiCommand.php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Infrastructure\Adapters\Input\Web\OpenApi\OpenApiConfig;
use Illuminate\Console\Command;
use OpenApi\Generator;

final class GenerateOpenApiCommand extends Command
{
    protected $signature = 'openapi:generate';
    protected $description = 'Generate openapi.json from #[OA\*] attributes';

    public function handle(): int
    {
        $config = OpenApiConfig::fromArray(config('openapi'));
        $openapi = Generator::scan($config->scanPaths);

        file_put_contents($config->outputPath, $openapi->toJson());

        $this->info("OpenAPI spec written to {$config->outputPath}");

        return self::SUCCESS;
    }
}
```

**Примечание:** `config('openapi')` всё ещё возвращает `mixed` для PHPStan.
`OpenApiConfig::fromArray()` принимает `array` — на практике `config()` для
существующего ключа всегда возвращает массив. PHPStan-ошибку можно подавить
через `/** @var array<string, mixed> $raw */` перед вызовом, либо добавить
в `phpstan.neon` исключение для данного паттерна.

---

## Дополнительные соображения

### PHPStan level 9 и `when()`

Laravel `$this->when(condition, value)` возвращает `MissingValue|mixed` — PHPStan
видит это как `mixed`. Поэтому используем явные `if`-блоки в `toArray()`:

```php
public function toArray(Request $request): array
{
    $data = [
        'task_id' => $this->id(),
        // ...
    ];

    if ($this->status() === TranscriptionStatus::Completed) {
        $data['result'] = [...];
    }

    return $data;
}
```

### `SummaryResult::toArray()` в Domain

Метод остаётся в Domain для JSONB-персистентности (репозиторий использует его при записи).
HTTP-ресурс читает поля напрямую, не вызывая `->toArray()`.
Добавить `@internal` PHPDoc чтобы явно задокументировать намеренное использование.

### `latest()` — текущий баг (regression fix)

Метод `latest()` в контроллере (строка 308) возвращает `$task->summary()` **как объект**
`SummaryResult`, не вызывая `->toArray()`. У `SummaryResult` все свойства `private readonly` —
`json_encode` на таком объекте возвращает `{}`. Это **production-баг**: эндпоинт
`GET /api/history/latest` прямо сейчас возвращает пустой объект вместо summary.

После рефакторинга `LatestMediaTaskResource` будет использовать `new SummaryResource($this->summary())` —
баг исправлен. Это не просто рефакторинг, а **regression fix**.

### `zircote/swagger-php` и deptrac

Schema-классы в `OpenApi/Schemas/` — это «мёртвый код» с точки зрения выполнения
(только атрибуты, ни один метод не вызывается). Deptrac должен разрешить
`Infrastructure → OpenApi\Attributes` (внешняя библиотека). Проверить `deptrac.yaml`
после добавления — возможно, потребуется добавить исключение для `OpenApi\Attributes`.

### `zircote/swagger-php` и PHPStan

Атрибуты `zircote/swagger-php` используют нативные PHP 8 атрибуты — PHPStan level 9
не должен иметь проблем с ними. Schema-классы пустые (`final class ... {}`) —
PHPStan может выдать `UnusedClass`, но это ложное срабатывание (классы используются
только `swagger-php` во время сканирования). Добавить в `phpstan.neon`:

```neon
parameters:
    ignoreErrors:
        - '#Class App\\Infrastructure\\Adapters\\Input\\Web\\OpenApi\\Schemas\\\w+ is never used#'
        - '#Class App\\Infrastructure\\Adapters\\Input\\Web\\OpenApi\\TranscribeVideoApi is never used#'
```

### Порядок реализации (полный)

Рекомендуемый порядок для минимального риска:

**Часть 1 — Resources:**
1. `SummaryResource` + тест
2. `MediaTaskResource` + тест
3. `LatestMediaTaskResource` + тест
4. `MediaTaskListItemResource` + тест
5. `MediaTaskCreatedResource`
6. `MediaTaskCollection` + тест
7. Рефакторинг `TranscribeVideoController`
8. `composer check` — зелёный

**Часть 2 — OpenAPI/Swagger:**
9. Сначала обновить `Prd.md`, если фиксируем текущий runtime-контракт (`summary` как объект, финальная трактовка 429-лимита)
10. `composer require zircote/swagger-php` + `config/openapi.php` + `OpenApiConfig`
11. Schema-классы (`ErrorSchema`, `SummarySchema`, `MediaTaskCreatedSchema`, `MediaTaskSchema`, `LatestMediaTaskSchema`, `LatestMediaTaskEmptySchema`, `MediaTaskListItemSchema`)
12. `TranscribeVideoApi` с атрибутами эндпоинтов
13. Маршрут `GET /api/docs/openapi.json`
14. Консольная команда `openapi:generate`
15. Валидация: `openapi.json` валиден против OpenAPI 3.1
16. `composer check` — зелёный

---

## Checklist (AGENTS.md §11)

### Часть 1 — Resources
- [ ] Зафиксирован целевой контракт `result.summary`: либо PRD обновлён под объект, либо план адаптирован под строку
- [ ] `latest()` **regression fix**: summary больше не `{}` (баг с private-свойствами SummaryResult)
- [ ] `latest()` не включает `video_id` (сохранение контракта PRD §7.5)
- [ ] Тесты добавлены/обновлены без дублирования существующего feature-покрытия
- [ ] `MediaTaskCollection` тесты покрывают `history`, `search`, пагинацию URL
- [ ] `array_filter` фильтрует только `null`, не falsy (`duration_sec = 0` сохраняется)
- [ ] `_links.first` использует `?` для первого параметра, `&` для последующих
- [ ] `_links.prev`/`next` — относительные URL (изменение поведения, задокументировано)
- [ ] PHPStan level 9 пройден (явные `if`-блоки вместо `when()`)
- [ ] PSR-12 соблюдён
- [ ] Нет Laravel facades в Resource-классах
- [ ] Нет утечки Domain через HTTP-специфичный `toArray()`
- [ ] Кэш в `history()` кэширует массив, не объект
- [ ] `SummaryResult::toArray()` и `SummaryKeyPoint::toArray()` помечены `@internal`
- [ ] Cache facade вынесен в известный долг (Scope: отдельная задача)

### Часть 2 — OpenAPI/Swagger
- [ ] `Prd.md` обновлён в том же change set, если OpenAPI фиксирует текущий runtime-контракт
- [ ] `zircote/swagger-php` добавлен в `require` (не `require-dev`)
- [ ] `OpenApiConfig` — типизированный конфиг-объект (PHPStan level 9)
- [ ] Schema-классы не нарушают deptrac (Infrastructure → nowhere)
- [ ] Для `GET /api/history/latest` используется отдельная схема, а не `MediaTask`
- [ ] `TranscribeVideoApi` помечен `@codeCoverageIgnore`
- [ ] `openapi.json` генерируется без ошибок через `php artisan openapi:generate`
- [ ] `GET /api/docs/openapi.json` возвращает валидный OpenAPI 3.1 spec (без двойной сериализации)
- [ ] Все 6 эндпоинтов задокументированы: POST create, GET status, GET download, GET history, GET latest, GET search
- [ ] Все error-ответы (400, 404, 422, 429) задокументированы
- [ ] PHPStan исключения для Schema-классов добавлены в `phpstan.neon`
- [ ] Лимит 429 (`daily` vs `monthly`) закреплён единообразно в PRD, коде и OpenAPI

