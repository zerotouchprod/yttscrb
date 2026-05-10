# durable-workflow/workflow — Документация

> Пакет `durable-workflow/workflow` — PHP workflow engine для Laravel.
> Работает на Redis (`WORKFLOW_CONNECTION=redis`), не требует отдельного Temporal-кластера.

---

## Содержание

1. [Workflows](#workflows)
2. [Activities](#activities)
3. [Запуск Workflow](#запуск-workflow)
4. [Передача данных](#передача-данных)
5. [Output](#output)
6. [Models](#models)
7. [Dependency Injection](#dependency-injection)
8. [Workflow Status](#workflow-status)
9. [Workflow ID](#workflow-id)
10. [Signals](#signals)
11. [Inbox](#inbox)
12. [Queries](#queries)
13. [Updates](#updates)
14. [Outbox](#outbox)
15. [Timers](#timers)
16. [Side Effects](#side-effects)
17. [Heartbeats](#heartbeats)
18. [Child Workflows](#child-workflows)
19. [Async Activities](#async-activities)
20. [Concurrency](#concurrency)
21. [Sagas](#sagas)
22. [Events](#events)
23. [Webhooks](#webhooks)
24. [Continue As New](#continue-as-new)
25. [Versioning](#versioning)
26. [Common Mistakes](#common-mistakes)
27. [Constraints](#constraints)

---

## Workflows

Workflow — класс, определяющий последовательность активностей, которые выполняются последовательно, параллельно или смешанно.

```php
use function Workflow\activity;
use Workflow\Workflow;

class MyWorkflow extends Workflow
{
    public function execute()
    {
        $result = yield activity(MyActivity::class);
        return $result;
    }
}
```

Ключевое слово `yield` приостанавливает выполнение workflow и ждёт завершения активности.

**Генерация:**
```bash
php artisan make:workflow MyWorkflow
```

---

## Activities

Activity — единица работы, выполняющая конкретную задачу (API-запрос, обработка данных, отправка email).

```php
use Workflow\Activity;

class MyActivity extends Activity
{
    public function execute()
    {
        // Perform some work...
        return $result;
    }
}
```

> ⚠️ **Конструктор зарезервирован пакетом.** Не объявляй `__construct()` в Activity.
> Сигнатура конструктора пакета: `($index, $now, $storedWorkflow, ...$arguments)`.
> Объявление своего конструктора сломает Activity при запуске.

**Генерация:**
```bash
php artisan make:activity MyActivity
```

---

## Запуск Workflow

```php
use Workflow\WorkflowStub;

$workflow = WorkflowStub::make(MyWorkflow::class);
$workflow->start();
```

- `start()` возвращается сразу, не блокируя запрос
- Workflow выполняется асинхронно через queue worker

**Загрузка существующего workflow по ID:**
```php
$workflow = WorkflowStub::load($id);
```

---

## Передача данных

Через `start()`:
```php
$workflow = WorkflowStub::make(MyWorkflow::class);
$workflow->start('world');
```

В activity через `activity()`:
```php
yield activity(MyActivity::class, $name);
```

**Важно:** Передавайте только небольшие объёмы данных. Для больших — пишите в БД/кэш/файлы и передавайте ключ.

---

## Output

```php
$workflow->output(); // => 'Hello, world!'
```

---

## Models

Eloquent-модели сериализуются через `ModelIdentifier` (только ID, класс, связи, connection).

```php
use App\Models\User;

class MyWorkflow extends Workflow
{
    public function execute(User $user)
    {
        return yield activity(MyActivity::class, $user->name);
    }
}
```

---

## Dependency Injection

### Workflow DI

Можно типизировать зависимости в `execute()` — Laravel инжектит их через Container:

```php
use Illuminate\Contracts\Foundation\Application;

class MyWorkflow extends Workflow
{
    public function execute(Application $app)
    {
        if ($app->runningInConsole()) { /* ... */ }
    }
}
```

### Activity DI

> ⚠️ Конструктор Activity зарезервирован пакетом — не объявляй `__construct()`.

DI в Activity реализуется двумя способами:

**1. Через параметры `execute()` — типизируй зависимость, Laravel разрешит через Container:**

```php
use App\Application\Ports\Output\TranscriptionProviderInterface;
use Workflow\Activity;

class MyActivity extends Activity
{
    public function execute(string $audioPath, TranscriptionProviderInterface $provider): string
    {
        return $provider->transcribe($audioPath);
    }
}
```

**2. Через `Container::getInstance()->make()` — если нужно резолвить внутри метода:**

```php
use App\Application\Ports\Output\SubtitleProviderInterface;
use Illuminate\Container\Container;
use Workflow\Activity;

class MyActivity extends Activity
{
    public function execute(string $youtubeUrl): ?string
    {
        /** @var SubtitleProviderInterface $provider */
        $provider = Container::getInstance()->make(SubtitleProviderInterface::class);

        return $provider->extract($youtubeUrl);
    }
}
```

---

## Workflow Status

```php
$workflow->running(); // true/false
$workflow->status();  // объект статуса
```

**Возможные статусы:**
- `WorkflowCreatedStatus`
- `WorkflowPendingStatus`
- `WorkflowRunningStatus`
- `WorkflowWaitingStatus`
- `WorkflowCompletedStatus`
- `WorkflowFailedStatus`
- `WorkflowContinuedStatus`

**State machine:**
```
Created → Pending → Running → Waiting → Running → ... → Completed/Failed
```

---

## Workflow ID

```php
$workflow = WorkflowStub::make(MyWorkflow::class);
$workflowId = $workflow->id(); // auto-increment integer
```

Внутри activity:
```php
$this->workflowId(); // ID текущего workflow
```

---

## Signals

Позволяют внешнему коду вызывать события в workflow.

**Определение:**
```php
use Workflow\SignalMethod;
use Workflow\Workflow;

class MyWorkflow extends Workflow
{
    private $ready = false;

    #[SignalMethod]
    public function setReady($ready)
    {
        $this->ready = $ready;
    }
}
```

**Вызов сигнала:**
```php
$workflow = WorkflowStub::load($workflowId);
$workflow->setReady(true);
```

**Ожидание сигнала:**
```php
use function Workflow\await;

yield await(fn () => $this->ready);
```

---

## Inbox

Replay-safe коллекция входящих сигналов.

```php
use Workflow\SignalMethod;
use Workflow\Workflow;

class MyWorkflow extends Workflow
{
    #[SignalMethod]
    public function send(string $message): void
    {
        $this->inbox->receive($message);
    }

    public function execute()
    {
        while (true) {
            yield await(fn () => $this->inbox->hasUnread());
            $message = $this->inbox->nextUnread();
        }
    }
}
```

---

## Queries

Позволяют читать состояние workflow без его выполнения.

```php
use Workflow\QueryMethod;
use Workflow\Workflow;

class MyWorkflow extends Workflow
{
    private bool $ready = false;

    #[QueryMethod]
    public function getReady(): bool
    {
        return $this->ready;
    }
}
```

**Вызов:**
```php
$workflow = WorkflowStub::load($workflowId);
$ready = $workflow->getReady();
```

---

## Updates

Комбинация Query + Signal — читает и мутирует состояние.

```php
use Workflow\UpdateMethod;
use Workflow\Workflow;

class MyWorkflow extends Workflow
{
    private bool $ready = false;

    #[UpdateMethod]
    public function updateReady($ready): bool
    {
        $this->ready = $ready;
        return $this->ready;
    }
}
```

---

## Outbox

Replay-safe коллекция исходящих сообщений.

```php
use Workflow\UpdateMethod;
use Workflow\Workflow;

class MyWorkflow extends Workflow
{
    #[UpdateMethod]
    public function receive()
    {
        return $this->outbox->nextUnsent();
    }

    public function execute()
    {
        $count = 0;
        while (true) {
            $count++;
            $this->outbox->send("Message {$count}");
        }
    }
}
```

---

## Timers

Durable-таймеры, переживающие рестарты и сбои.

```php
use function Workflow\timer;

yield timer('5 seconds');
yield timer(30); // 30 секунд
```

**Форматы:** `'5 seconds'`, `'30 minutes'`, `'3 days'`

**Хелперы:**
```php
use function Workflow\{seconds, minutes, days, hours};

yield days(3);
yield hours(2);
```

**Signal + Timer (кто первый):**
```php
use function Workflow\awaitWithTimeout;

$result = yield awaitWithTimeout('5 minutes', fn () => $this->ready);
// true — сигнал получен, false — таймаут
```

**Важно:** Внутри workflow используйте `Workflow\now()`, не `Carbon::now()`.

---

## Side Effects

Closure, выполняющийся только один раз. Результат сохраняется для replay.

```php
use function Workflow\{sideEffect, timer};

$seconds = yield sideEffect(fn () => random_int(60, 120));
yield timer($seconds);
```

**Важно:** Код внутри side effect не должен падать — он не повторяется.

---

## Heartbeats

Для длительных активностей — предотвращают timeout.

```php
class MyActivity extends Activity
{
    public $timeout = 5;

    public function execute()
    {
        while (true) {
            sleep(1);
            $this->heartbeat();
        }
    }
}
```

---

## Child Workflows

Дочерние workflow для декомпозиции сложных процессов.

```php
use function Workflow\child;

class ParentWorkflow extends Workflow
{
    public function execute()
    {
        $result = yield child(ChildWorkflow::class);
    }
}
```

**Сигналинг дочернему workflow:**
```php
$child = child(ChildWorkflow::class);
$childHandle = $this->child();
$childHandle->process('approved');
$result = yield $child;
```

**Множественные дети:**
```php
$child1 = child(ChildWorkflow::class, 'first');
$child2 = child(ChildWorkflow::class, 'second');

$childHandles = $this->children(); // reverse chronological

foreach ($childHandles as $childHandle) {
    $childHandle->process('approved');
}

$results = yield all([$child1, $child2]);
```

**Получение ID дочернего workflow:**
```php
$childHandle = $this->child();
$childId = $childHandle->id();
```

---

## Async Activities

Выполнение группы активностей в контексте отдельного workflow.

```php
use function Workflow\{activity, async};

[$result, $otherResult] = yield async(function () {
    $result = yield activity(Activity::class);
    $otherResult = yield activity(OtherActivity::class, 'other');
    return [$result, $otherResult];
});
```

---

## Concurrency

**Последовательно:**
```php
return [
    yield activity(MyActivity1::class),
    yield activity(MyActivity2::class),
];
```

**Параллельно:**
```php
use function Workflow\{activity, all};

return yield all([
    activity(MyActivity1::class),
    activity(MyActivity2::class),
]);
```

**Смешанно:**
```php
return [
    yield activity(MyActivity1::class),
    yield all([
        async(fn () => [
            yield activity(MyActivity2::class),
            yield activity(MyActivity3::class),
        ]),
        activity(MyActivity4::class),
    ]),
    yield activity(MyActivity6::class),
];
```

---

## Sagas

Паттерн для управления распределёнными транзакциями с компенсациями.

```php
class BookingSagaWorkflow extends Workflow
{
    public function execute()
    {
        try {
            $flightId = yield activity(BookFlightActivity::class);
            $this->addCompensation(fn () => activity(CancelFlightActivity::class, $flightId));

            $hotelId = yield activity(BookHotelActivity::class);
            $this->addCompensation(fn () => activity(CancelHotelActivity::class, $hotelId));
        } catch (Throwable $th) {
            yield from $this->compensate();
            throw $th;
        }
    }
}
```

- `$this->setParallelCompensation(true)` — параллельные компенсации
- `$this->setContinueWithError(true)` — игнорировать ошибки в компенсациях

---

## Events

События жизненного цикла workflow и activities.

**Workflow:**
- `WorkflowStarted` — workflow запущен
- `WorkflowCompleted` — workflow завершён
- `WorkflowFailed` — workflow упал

**Activity:**
- `ActivityStarted` — activity запущена
- `ActivityCompleted` — activity завершена
- `ActivityFailed` — activity упала

**Типичный lifecycle:**
```
WorkflowStarted → ActivityStarted → ActivityCompleted → WorkflowCompleted
```

**С восстановлением:**
```
WorkflowStarted → ActivityStarted → ActivityFailed → ActivityStarted → ActivityCompleted → WorkflowCompleted
```

---

## Webhooks

Позволяют внешним системам запускать workflow и отправлять сигналы через HTTP.

**Регистрация роутов:**
```php
use Workflow\Webhooks;

Webhooks::routes();
```

**Запуск workflow через webhook:**
```php
#[Webhook]
class OrderWorkflow extends Workflow
{
    public function execute($orderId) { /* ... */ }
}
```
```
POST /webhooks/start/order-workflow
```

**Сигнал через webhook:**
```php
#[SignalMethod]
#[Webhook]
public function markAsShipped() { $this->shipped = true; }
```
```
POST /webhooks/signal/order-workflow/{workflowId}/mark-as-shipped
```

**Аутентификация:** `none`, `token`, `signature` (HMAC), `custom`.

---

## Continue As New

Рестарт workflow с новыми аргументами (для предотвращения бесконечного роста истории).

```php
use function Workflow\{activity, continueAsNew};

class CounterWorkflow extends Workflow
{
    public function execute(int $count = 0, int $max = 3)
    {
        $result = yield activity(CountActivity::class, $count);

        if ($count >= $max) {
            return 'workflow_' . $result;
        }

        return yield continueAsNew($count + 1, $max);
    }
}
```

---

## Versioning

Безопасное внесение изменений в запущенные workflow.

```php
use function Workflow\{activity, getVersion};
use Workflow\WorkflowStub;

$version = yield getVersion('my-change-id', WorkflowStub::DEFAULT_VERSION, 1);

if ($version === WorkflowStub::DEFAULT_VERSION) {
    yield activity(OldActivity::class);
} else {
    yield activity(NewActivity::class);
}
```

- `changeId` — уникальный ID точки изменения
- `minSupported` — минимальная поддерживаемая версия
- `maxSupported` — текущая версия для новых executions

---

## Common Mistakes

| ❌ Ошибка | ✅ Правильно |
|-----------|-------------|
| Забыл `yield` перед `activity()` | Всегда `yield activity(...)` |
| `ActivityStub::make()` внутри workflow | `yield activity(MyActivity::class, ...)` — только функция `activity()` |
| `Carbon::now()` в workflow | `Workflow\now()` |
| Прямые HTTP-запросы в workflow | Выносить в Activity |
| `random_int()` без sideEffect | `yield sideEffect(fn () => random_int(...))` |
| Свой `__construct()` в Activity | Не объявлять конструктор — он зарезервирован пакетом |
| Не добавил `use function Workflow\activity;` | Обязательный импорт для функции `activity()` |
| Передача большого объёма данных в args | Писать в БД/кэш, передавать ключ (ID) |
| Saga без `try/catch` + `$this->compensate()` | Оборачивать в `try/catch`, вызывать `yield from $this->compensate()` |
| `$this->addCompensation(fn () => ActivityStub::make(...))` | `$this->addCompensation(fn () => activity(...))` — компенсация должна возвращать `activity()` |

> **Особо важно:** `ActivityStub::make()` — публичный API для запуска activity **вне** workflow (например из контроллера).
> Внутри workflow используй **только** `yield activity(MyActivity::class, ...)`.
> Использование `ActivityStub::make()` в saga-компенсации не вызовет активность корректно.

---

## Constraints

**Workflow (детерминизм):**
- ❌ `Carbon::now()` → ✅ `Workflow\now()`
- ❌ `Auth::user()` → ✅ передать пользователя аргументом
- ❌ Внешние network-запросы → ✅ activity или аргументы
- ❌ `random_int()` без sideEffect → ✅ `sideEffect(fn () => random_int())`

**Activity:**
- ❌ Нет ограничений детерминизма
- ✅ Должны быть идемпотентными (retry-safe)
- ✅ Использовать `Idempotency-Key` для внешних API
