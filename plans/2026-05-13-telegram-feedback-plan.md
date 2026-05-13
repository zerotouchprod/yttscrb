# Plan: Telegram Feedback Bot

**Дата:** 2026-05-13
**Статус:** готов к реализации *(скорректирован после ревью 2026-05-13)*
**Затрагивает:** `config/services.php`, `routes/api.php`, новые файлы в Application и Infrastructure, новый Vue-компонент

---

## Проблема

Нет механизма получения обратной связи от пользователей. В первые дни запуска критически важно получать фидбэк в режиме реального времени — без базы данных, без тикет-систем. Telegram-бот — самый быстрый и личный способ: сообщение летит напрямую владельцу на телефон.

---

## Цель

Добавить форму обратной связи на фронтенде и POST-эндпоинт на бэкенде. Сообщение отправляется в личный Telegram-бот через Telegram Bot API. Никаких записей в БД. Архитектура строго Hexagonal: Telegram-адаптер скрыт за портом `FeedbackNotifierInterface`.

---

## Структура новых файлов

```
# Бэкенд (9 новых + 3 изменяемых):
app/Application/DTO/FeedbackData.php
app/Application/Ports/Output/FeedbackNotifierInterface.php
app/Application/UseCases/SendFeedbackHandler.php
app/Infrastructure/Adapters/Input/Web/FeedbackController.php
app/Infrastructure/Adapters/Input/Web/Requests/FeedbackRequest.php
app/Infrastructure/Adapters/Output/Notification/TelegramFeedbackNotifier.php
tests/Unit/Application/UseCases/SendFeedbackHandlerTest.php
tests/Unit/Infrastructure/Adapters/Output/Notification/TelegramFeedbackNotifierTest.php
tests/Feature/Feedback/SendFeedbackTest.php

config/services.php                           # изменяемый: секция telegram
routes/api.php                                # изменяемый: POST /feedback
app/Providers/AppServiceProvider.php          # изменяемый: биндинг интерфейс → адаптер

# Фронтенд (2 новых + 1 изменяемый):
resources/js/components/FeedbackModal.vue
resources/js/components/FeedbackButton.vue
resources/js/components/NavBar.vue            # изменяемый: добавить кнопку
```

---

## Шаг 1 — Env-переменные и конфиг

**Файлы:** `.env.example`, `config/services.php`

Добавить в `.env.example` (без реальных значений):

```
TELEGRAM_BOT_TOKEN=
TELEGRAM_CHAT_ID=
```

Добавить в `config/services.php` секцию:

```php
'telegram' => [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'chat_id'   => env('TELEGRAM_CHAT_ID'),
],
```

**Важно:** ключи читаются только через `config('services.telegram.*')` — не через `env()` напрямую в коде. Это обязательное правило проекта.

---

## Шаг 2 — DTO: `FeedbackData`

**Файл:** `app/Application/DTO/FeedbackData.php`

```php
<?php

declare(strict_types=1);

namespace App\Application\DTO;

final readonly class FeedbackData
{
    public function __construct(
        public string  $message,
        public ?string $email,
    ) {
    }
}
```

**Правила:**
- `readonly` — immutable DTO.
- `email` — `?string`, nullable (поле опциональное).
- Нет валидации внутри DTO — валидация на уровне Form Request (Infrastructure).

---

## Шаг 3 — Порт: `FeedbackNotifierInterface`

**Файл:** `app/Application/Ports/Output/FeedbackNotifierInterface.php`

```php
<?php

declare(strict_types=1);

namespace App\Application\Ports\Output;

use App\Application\DTO\FeedbackData;

interface FeedbackNotifierInterface
{
    /**
     * Send feedback notification to the owner.
     * Implementations must be fault-tolerant:
     * if the transport is unavailable, they log the error and return silently.
     */
    public function notify(FeedbackData $data): void;
}
```

**Почему порт, а не прямой вызов из контроллера:**
AGENTS.md §3 требует: «External services must always be hidden behind Application ports.» Telegram — внешний сервис. Порт позволяет в тестах подменить реализацию без HTTP-запросов.

---

## Шаг 4 — Use Case: `SendFeedbackHandler`

**Файл:** `app/Application/UseCases/SendFeedbackHandler.php`

```php
<?php

declare(strict_types=1);

namespace App\Application\UseCases;

use App\Application\DTO\FeedbackData;
use App\Application\Ports\Output\FeedbackNotifierInterface;

final readonly class SendFeedbackHandler
{
    public function __construct(
        private FeedbackNotifierInterface $notifier,
    ) {
    }

    public function handle(FeedbackData $data): void
    {
        $this->notifier->notify($data);
    }
}
```

**Почему не встраивать логику в контроллер:**
SOLID / SRP. Контроллер отвечает за HTTP-слой: валидацию запроса, формирование ответа. Контроллер не должен знать о деталях отправки.

**Почему хэндлер такой тонкий:**
Бизнес-логика здесь минимальна по назначению — нет доменной модели `Feedback` (нет хранения, нет событий, нет статусных переходов). YAGNI: добавляем только то, что нужно сейчас. Хэндлер служит точкой расширения — при необходимости логирования попыток, rate-limiting на уровне бизнес-правил, дедупликации — всё добавляется здесь без изменения контроллера.

---

## Шаг 5 — Адаптер: `TelegramFeedbackNotifier`

**Файл:** `app/Infrastructure/Adapters/Output/Notification/TelegramFeedbackNotifier.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Notification;

use App\Application\DTO\FeedbackData;
use App\Application\Ports\Output\FeedbackNotifierInterface;
use Illuminate\Http\Client\Factory as HttpClient;
use Psr\Log\LoggerInterface;

final readonly class TelegramFeedbackNotifier implements FeedbackNotifierInterface
{
    private const int    TIMEOUT_SECONDS = 3;
    private const string API_BASE        = 'https://api.telegram.org';

    public function __construct(
        private HttpClient      $http,
        private LoggerInterface $logger,
        private string          $botToken,
        private string          $chatId,
    ) {
    }

    public function notify(FeedbackData $data): void
    {
        try {
            $response = $this->http
                ->timeout(self::TIMEOUT_SECONDS)
                ->post(
                    self::API_BASE . '/bot' . $this->botToken . '/sendMessage',
                    [
                        'chat_id'    => $this->chatId,
                        'text'       => $this->buildMessage($data),
                        'parse_mode' => 'HTML',
                    ],
                );

            // Laravel's Http client does NOT throw on non-2xx by default.
            // We must explicitly check and log application-level failures.
            if ($response->failed()) {
                $this->logger->error('Telegram feedback notification failed: non-2xx response', [
                    'status' => $response->status(),
                ]);
            }
        } catch (\Throwable $e) {
            // Transport-level failure (DNS, connection timeout, etc.)
            $this->logger->error('Telegram feedback notification failed: transport error', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function buildMessage(FeedbackData $data): string
    {
        $text = "🔔 <b>New Feedback — YTTSCRB</b>\n\n";

        if ($data->email !== null) {
            $text .= '📧 <b>Email:</b> ' . htmlspecialchars($data->email, ENT_QUOTES) . "\n";
        }

        $text .= '📝 <b>Message:</b>' . "\n" . htmlspecialchars($data->message, ENT_QUOTES);

        return $text;
    }
}
```

**Ключевые решения:**

| Решение | Обоснование |
|---|---|
| `$response->failed()` + лог | Laravel HTTP-клиент **не бросает** исключение на HTTP 500 от Telegram. Без явной проверки `failed()` ошибка Telegram была бы проглочена бесследно — fault-tolerance стала бы ненастоящей |
| `try/catch \Throwable` | Ловит transport-level ошибки: DNS failure, connection timeout, SSL error |
| Два разных лог-сообщения | Различаем application-level (5xx от Telegram) и transport-level — проще дебажить |
| `timeout(3)` | Предотвращает зависание если Telegram не отвечает |
| `htmlspecialchars()` | Экранирование для HTML parse_mode |
| `LoggerInterface` (PSR-3) | Не статический `Log::` фасад — инъекция через конструктор |
| `HttpClient` через конструктор | Позволяет мокать HTTP в тестах через `Http::fake()` |

---

## Шаг 6 — Биндинг в DI-контейнере

**Файл:** `app/Providers/AppServiceProvider.php`

Добавить биндинг в метод `register()`:

```php
$this->app->bind(
    \App\Application\Ports\Output\FeedbackNotifierInterface::class,
    fn (\Illuminate\Contracts\Foundation\Application $app) => new \App\Infrastructure\Adapters\Output\Notification\TelegramFeedbackNotifier(
        http:      $app->make(\Illuminate\Http\Client\Factory::class),
        logger:    $app->make(\Psr\Log\LoggerInterface::class),
        botToken:  (string) config('services.telegram.bot_token', ''),
        chatId:    (string) config('services.telegram.chat_id', ''),
    ),
);
```

**Важно:** `(string) config(...)` — явное приведение типа устраняет `mixed` под PHPStan level 9.

---

## Шаг 7 — Form Request: `FeedbackRequest`

**Файл:** `app/Infrastructure/Adapters/Input/Web/Requests/FeedbackRequest.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web\Requests;

use App\Application\DTO\FeedbackData;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class FeedbackRequest extends FormRequest
{
    /**
     * Public API — no authorization required.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:2000'],
            'email'   => ['nullable', 'email', 'max:255'],
        ];
    }

    /**
     * Match the project's error response format: { error: { code, message, details } }
     * HTTP 400 — consistent with TranscribeVideoRequest and SearchRequest.
     */
    protected function failedValidation(Validator $validator): void
    {
        $firstError  = $validator->errors()->first();
        $failedField = $validator->errors()->keys()[0] ?? 'message';

        $response = new JsonResponse([
            'error' => [
                'code'    => 'INVALID_FEEDBACK',
                'message' => $firstError,
                'details' => ['field' => $failedField],
            ],
        ], Response::HTTP_BAD_REQUEST);

        throw new HttpResponseException($response);
    }

    public function toFeedbackData(): FeedbackData
    {
        return new FeedbackData(
            message: (string) $this->validated('message'),
            email:   $this->validated('email') !== null
                ? (string) $this->validated('email')
                : null,
        );
    }
}
```

**Три обязательных элемента, требуемых контрактом проекта:**
- `authorize(): true` — публичный API, без аутентификации (паттерн из `TranscribeVideoRequest`).
- `failedValidation()` — переопределён для возврата `{ error: { code, message, details } }` с HTTP **400** (не 422). Это согласовано с `TranscribeVideoRequest` и `SearchRequest` — оба возвращают 400 через `HttpResponseException`. Стандартный Laravel по умолчанию возвращает 422 без переопределения — переопределение обязательно.
- `toFeedbackData()` — маппинг из HTTP-запроса в DTO: паттерн, уже используемый в других Request-классах проекта.

---

## Шаг 8 — Контроллер: `FeedbackController`

**Файл:** `app/Infrastructure/Adapters/Input/Web/FeedbackController.php`

```php
<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web;

use App\Application\UseCases\SendFeedbackHandler;
use App\Infrastructure\Adapters\Input\Web\Requests\FeedbackRequest;
use Illuminate\Http\JsonResponse;

final readonly class FeedbackController
{
    public function __construct(
        private SendFeedbackHandler $handler,
    ) {
    }

    public function __invoke(FeedbackRequest $request): JsonResponse
    {
        $this->handler->handle($request->toFeedbackData());

        return new JsonResponse(['message' => 'Thank you! Your feedback has been received.']);
    }
}
```

**Нет статических фасадов. Нет `Http::post()` напрямую. Нет бизнес-логики.**
Контроллер: принял запрос → делегировал → вернул ответ.

---

## Шаг 9 — Роут

**Файл:** `routes/api.php`

Добавить:

```php
use App\Infrastructure\Adapters\Input\Web\FeedbackController;

$feedbackThrottle = app()->environment('local') ? [] : ['throttle:10,1'];

Route::post('/feedback', FeedbackController::class)
    ->middleware($feedbackThrottle);
```

**Throttle:** 10 запросов в минуту с одного IP в production. В local — без ограничений (согласно уже установленному паттерну в файле).

---

## Шаг 10 — Тесты

### Unit-тест: `SendFeedbackHandlerTest`

**Файл:** `tests/Unit/Application/UseCases/SendFeedbackHandlerTest.php`

| Тест | Проверка |
|---|---|
| `sends feedback with email` | `notify()` вызван 1 раз с `FeedbackData(message, email)` |
| `sends feedback without email` | `notify()` вызван с `email = null` |
| `does not swallow notifier exception` | Если `notify()` бросает — исключение всплывает (адаптер сам глотает, не хэндлер) |

```php
test('sends feedback with email', function () {
    $notifier = Mockery::mock(FeedbackNotifierInterface::class);
    $data = new FeedbackData(message: 'Great product!', email: 'user@example.com');

    $notifier->shouldReceive('notify')->once()->with($data);

    (new SendFeedbackHandler($notifier))->handle($data);
});

test('sends feedback without email', function () {
    $notifier = Mockery::mock(FeedbackNotifierInterface::class);
    $data = new FeedbackData(message: 'Nice!', email: null);

    $notifier->shouldReceive('notify')->once()->with($data);

    (new SendFeedbackHandler($notifier))->handle($data);
});

test('does not swallow notifier exception', function () {
    $notifier = Mockery::mock(FeedbackNotifierInterface::class);
    $notifier->shouldReceive('notify')->andThrow(new \RuntimeException('transport fail'));

    expect(fn () => (new SendFeedbackHandler($notifier))->handle(
        new FeedbackData(message: 'test', email: null)
    ))->toThrow(\RuntimeException::class);
});
```

### Unit-тест: `TelegramFeedbackNotifierTest`

**Файл:** `tests/Unit/Infrastructure/Adapters/Output/Notification/TelegramFeedbackNotifierTest.php`

Этот тест специально покрывает fault-tolerance адаптера — два разных сценария сбоя Telegram:

| Тест | Проверка |
|---|---|
| `logs error and does not throw on non-2xx response` | `Http::response([], 500)` → `logger->error()` вызван, исключение не брошено |
| `logs error and does not throw on transport exception` | `Http::throw()` с ConnectionException → `logger->error()` вызван, исключение не брошено |
| `does not log on successful response` | `Http::response(['ok' => true], 200)` → `logger->error()` не вызван |

```php
test('logs error and does not throw on non-2xx telegram response', function () {
    Http::fake(['api.telegram.org/*' => Http::response([], 500)]);
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once()
        ->with(Mockery::pattern('/non-2xx/'), Mockery::type('array'));

    $notifier = new TelegramFeedbackNotifier(
        http:     app(Factory::class),
        logger:   $logger,
        botToken: 'token',
        chatId:   '123',
    );

    // Must not throw
    $notifier->notify(new FeedbackData(message: 'test', email: null));
});

test('logs error and does not throw on transport exception', function () {
    Http::fake(['api.telegram.org/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout')]);
    $logger = Mockery::mock(LoggerInterface::class);
    $logger->shouldReceive('error')->once()
        ->with(Mockery::pattern('/transport error/'), Mockery::type('array'));

    $notifier = new TelegramFeedbackNotifier(
        http:     app(Factory::class),
        logger:   $logger,
        botToken: 'token',
        chatId:   '123',
    );

    $notifier->notify(new FeedbackData(message: 'test', email: null));
});
```

### Feature-тест: `SendFeedbackTest`

**Файл:** `tests/Feature/Feedback/SendFeedbackTest.php`

> **Примечание по пути:** в текущем проекте реальные feature-тесты лежат в `tests/Feature/*`
> (например, `tests/Feature/Transcribe/`, `tests/Feature/Seo/`, `tests/Feature/Auth/`).
> Путь с двойным `Feature` был ошибкой и исправлен в этом плане.

**HTTP-статус success response: `200 OK`.**
Endpoint синхронный: к моменту ответа popытка отправки уже совершена. `202 Accepted`
семантически означало бы «принято в очередь для последующей обработки» — это неверно.
`200 OK` — осознанный выбор для synchronous fire-and-forget.

| Тест | HTTP-код | Проверка |
|---|---|---|
| `returns 200 with message and email` | 200 | JSON `message` присутствует, Telegram API вызван |
| `returns 200 without email` | 200 | Запрос без `email` проходит валидацию |
| `returns 400 if message is missing` | **400** | `{ error: { code: 'INVALID_FEEDBACK', ... } }` |
| `returns 400 if message exceeds 2000 chars` | **400** | Аналогично |
| `returns 400 if email is invalid` | **400** | Аналогично |
| `returns 200 even if telegram returns 500` | 200 | Non-2xx от Telegram не ломает пользовательский ответ |
| `returns 200 even if telegram is unreachable` | 200 | Transport exception не ломает пользовательский ответ |

> **Важно:** HTTP 400 (не 422) — согласованно с проектным паттерном в `TranscribeVideoRequest`
> и `SearchRequest`, оба используют `Response::HTTP_BAD_REQUEST` в `failedValidation()`.

```php
test('sends feedback to telegram', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response(['ok' => true], 200),
    ]);

    $this->postJson('/api/feedback', [
        'message' => 'Really useful tool!',
        'email'   => 'test@example.com',
    ])
        ->assertStatus(200)
        ->assertJsonPath('message', 'Thank you! Your feedback has been received.');

    Http::assertSentCount(1);
});

test('returns 400 if message is missing', function () {
    $this->postJson('/api/feedback', [])
        ->assertStatus(400)
        ->assertJsonPath('error.code', 'INVALID_FEEDBACK')
        ->assertJsonPath('error.details.field', 'message');
});

test('returns 200 even when telegram returns 500', function () {
    Http::fake([
        'api.telegram.org/*' => Http::response([], 500),
    ]);

    $this->postJson('/api/feedback', ['message' => 'Hello'])
        ->assertStatus(200);
});

test('returns 200 even when telegram is unreachable', function () {
    Http::fake([
        'api.telegram.org/*' => fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'),
    ]);

    $this->postJson('/api/feedback', ['message' => 'Hello'])
        ->assertStatus(200);
});
```

---

## Шаг 11 — Vue: `FeedbackModal.vue`

**Файл:** `resources/js/components/FeedbackModal.vue`

Компонент-модал на Tailwind CSS. Состояния:

| Состояние | UX |
|---|---|
| `idle` | Форма с `<textarea>` и `<input type="email">` |
| `loading` | Кнопка задизейблена со spinner |
| `success` | «Thank you! Your feedback has been sent directly to the developer.» + кнопка закрыть |
| `error` | «Something went wrong. Please try again.» — форма остаётся |

Ключевые моменты реализации:
- `fetch('/api/feedback', { method: 'POST', ... })` — нативный fetch, без axios.
- Клиентская валидация: `message.trim()` проверяется до отправки.
- Поле `email` — если пустое, не отправляется в body (отсутствует, не `null`).
- `defineEmits<{ close: [] }>()` — родительский компонент управляет видимостью.
- Оверлей (`fixed inset-0 bg-black/60`) закрывается по клику вне модала.

---

## Шаг 12 — Vue: `FeedbackButton.vue`

**Файл:** `resources/js/components/FeedbackButton.vue`

Минимальная кнопка-триггер с локальным `isOpen` state. Отдельным компонентом для
переиспользования (footer, страница результата и т.д.):

```
<FeedbackButton /> → открывает <FeedbackModal @close="isOpen = false" />
```

---

## Шаг 13 — Встроить в `NavBar.vue`

**Файл:** `resources/js/components/NavBar.vue`

**Текущее состояние NavBar:** компонент содержит бренд слева + поисковую строку по центру
(`hidden sm:block` — скрыта на mobile). Layout: `flex items-center justify-between`.

**Размещение кнопки:**
- **Desktop (`sm:` и выше):** кнопка-иконка `💬` с текстом «Feedback» добавляется
  справа от поиска. Стиль: `ghost` / `outline` — не доминирует над поиском.
- **Mobile (< `sm`):** поле поиска скрыто, поэтому справа от бренда помещается
  иконка-кнопка (без текста, только `💬` или SVG-иконка) с `aria-label="Send feedback"`.

Конкретно в шаблоне — добавить `<FeedbackButton />` внутри flex-контейнера rightmost,
с `class="ml-3"` для отступа. На mobile будет отображаться как иконка, потому что текст
скрыт через `hidden sm:inline` внутри самого `FeedbackButton`.

Props для NavBar не меняются. Кнопка полностью инкапсулирована в `FeedbackButton`.

---

## Шаг 14 — Проверить Deptrac

Новый адаптер `TelegramFeedbackNotifier` живёт в
`app/Infrastructure/Adapters/Output/Notification/` — Infrastructure-слой.
Зависимость Infrastructure → Application (`FeedbackNotifierInterface`, `FeedbackData`)
разрешена правилами архитектуры. После реализации — `vendor/bin/deptrac analyze`,
ожидаем 0 violations без изменений в `deptrac.yaml`.

---

## Дополнительные соображения

### `mixed` и PHPStan level 9

`config('services.telegram.bot_token')` возвращает `mixed`. Явное `(string)` приведение
в биндинге DI решает проблему. Все публичные свойства `FeedbackData` строго типизированы.

### Почему `FeedbackNotifierInterface` не нарушает YAGNI

AGENTS.md §3 однозначно требует: все внешние сервисы — за портами. YAGNI-исключение
«≥2 реализации» применяется к бизнес-абстракциям, не к портам внешних транспортов.

### Почему нет очередей

Timeout 3 секунды достаточен для Telegram API (~1с в норме). Если Telegram недоступен —
ошибка логируется, пользователь видит успех. Добавление Horizon-job для 3-секундного
HTTP-вызова — YAGNI. При необходимости retry-логики — добавляется в
`TelegramFeedbackNotifier` без изменения интерфейса.

### Запрет статических фасадов — уточнение scope

Запрет AGENTS.md §4 распространяется на **business code**: Application-слой, Domain-слой
и Infrastructure-адаптеры (включая `TelegramFeedbackNotifier`). В адаптере используем
`LoggerInterface` (PSR-3) и инъектированный `HttpClient` — без `Log::` и `Http::`.

В **тестах** (`Http::fake()`, `Http::assertSentCount()`) статические фасады допустимы —
это инструментарий тестового окружения, не бизнес-код. В `routes/api.php` `Route::` фасад
допустим — это bootstrap-файл конфигурации приложения.

### Почему HTTP 400, а не 422 для validation errors

Проект использует HTTP 400 через `HttpResponseException` в `failedValidation()` (видно
в `TranscribeVideoRequest` и `SearchRequest`). Стандарт HTTP формально предпочитает 422
для семантической валидации, но изменение этого паттерна — за рамками текущего scope.
`FeedbackRequest` следует установленному в проекте соглашению.

### Success contract endpoint'а

`200 OK` — осознанный выбор для синхронного fire-and-forget вызова. Ответ:
`{ "message": "Thank you! Your feedback has been received." }` — минимальный и достаточный.
Плоская структура без `data:` обёртки допустима: feedback endpoint не возвращает ресурс,
это команда без состояния. `202 Accepted` семантически неверен — означало бы «принято
в очередь для последующей обработки», что не соответствует синхронному вызову.

### Путь feature-теста

`tests/Feature/Feedback/SendFeedbackTest.php` — корректный путь в рамках текущей структуры
проекта. В репозитории используются директории вида `tests/Feature/Transcribe/`,
`tests/Feature/Seo/`, `tests/Feature/Auth/` без дополнительного вложенного `Feature/`.

### Spam-защита

Throttle `10,1` (10 запросов в минуту с IP) достаточен для v1.0.
Honeypot или reCAPTCHA — за пределами текущего scope.

---

## Checklist (AGENTS.md §11)

- [ ] `TELEGRAM_BOT_TOKEN` и `TELEGRAM_CHAT_ID` добавлены в `.env.example`
- [ ] Ключи читаются через `config()`, не через `env()` напрямую в коде
- [ ] `FeedbackRequest` содержит `authorize(): true`, `failedValidation()` с error envelope и `toFeedbackData()`
- [ ] `failedValidation()` возвращает HTTP 400 с `{ error: { code: 'INVALID_FEEDBACK', message, details } }`
- [ ] `FeedbackNotifierInterface` — порт в Application, не упоминает Telegram
- [ ] `TelegramFeedbackNotifier` — только в Infrastructure
- [ ] `TelegramFeedbackNotifier` обрабатывает `$response->failed()` (non-2xx) + логирует
- [ ] `TelegramFeedbackNotifier` обрабатывает `\Throwable` (transport error) + логирует
- [ ] `TelegramFeedbackNotifier` не бросает исключения наружу
- [ ] Нет статических фасадов (`Log::`, `Http::`) в адаптере и бизнес-коде
- [ ] `FeedbackController` — single-action, только HTTP-слой, возвращает 200
- [ ] Биндинг `FeedbackNotifierInterface → TelegramFeedbackNotifier` зарегистрирован в DI
- [ ] Роут `POST /feedback` добавлен в `routes/api.php` с throttle
- [ ] Unit-тест `SendFeedbackHandlerTest` — 3 кейса (Mockery)
- [ ] Unit-тест `TelegramFeedbackNotifierTest` — 3 кейса: non-2xx логирует, transport-exception логирует, success не логирует
- [ ] Feature-тест `SendFeedbackTest` — 7 кейсов: success, no-email, 400 validations (×3), 500 от Telegram, transport error
- [ ] Feature-тест расположен в `tests/Feature/Feedback/SendFeedbackTest.php`
- [ ] `FeedbackModal.vue` — все 4 состояния (idle/loading/success/error)
- [ ] `FeedbackButton.vue` — mobile: иконка без текста, desktop: иконка + текст
- [ ] `FeedbackButton.vue` встроена в `NavBar.vue`
- [ ] Deptrac — 0 violations
- [ ] PHPStan level 9 — 0 errors
- [ ] PHPCS PSR-12 — 0 violations
- [ ] `composer check` — зелёный

