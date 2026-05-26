# Production Incident Postmortem — 2026-05-26

## Timeline (UTC+2)

| Time | Event |
|---|---|
| ~10:00 | Начинается массовое накопление stuck workflows (seed-команды работают, но транскрипции застревают) |
| ~15:52 | Redis master начинает падать: OOMKilled (192Mi лимит, 163MB RDB + fork для BGSAVE ~326MB) |
| 16:06 | Обнаружена 500 на tubesum.app |
| 16:08 | Redis memory limit увеличен до 512Mi (Helm revision 85), поды перезапущены |
| 16:09 | Все GET-эндпоинты восстановлены (200) |
| 17:26 | `/history` всё ещё 500 — PostgreSQL тоже OOMKilled (192Mi лимит) |
| 18:10 | PostgreSQL memory limit увеличен до 512Mi (Helm revision 86) |
| 18:12 | Массовая очистка: удалено 1,426 stuck workflows, 20,452 exceptions, 16,950 logs, 1,421 Redis locks, 1,575 media_tasks помечены как failed |
| 18:13 | Полное восстановление: все 9 подов Running, все эндпоинты 200 |

## Root Cause

**Каскадный OOM на инфраструктурных компонентах из-за недостаточных лимитов памяти.**

1. **Redis (192Mi)** — данные выросли до 163MB. При каждом подключении реплики Redis делает fork для BGSAVE (copy-on-write), удваивая потребление памяти (~326MB), что превышает лимит 192Mi → OOMKill.
2. **PostgreSQL (192Mi)** — shared_buffers + wal_buffers + connection overhead + working memory для 5,000+ failed tasks привели к превышению лимита → OOMKill.
3. **1,426 durable-workflow застряли в `waiting`** — пока Redis и PostgreSQL были недоступны, workflow не могли ни выполниться, ни упасть. После восстановления они не перезапустились автоматически.
4. **1,575 media_tasks застряли в `processing`** — соответствующие workflow не обрабатывались.

## Impact

- **Даунтайм:** ~2 часа (16:06–18:13) с перерывами
- **Потерянные данные:** 1,575 транскрипций помечены как failed (могут быть перезапущены)
- **Очищено:** 1,426 workflows, ~37K записей в логах/exceptions
- **Ручное вмешательство:** 4 Helm upgrade, 3 ручные очистки БД, 2 перезапуска подов

## Что сделано для восстановления

1. `helm/yttscrb/values.yaml` — добавлены `redis.master.resources` и `redis.replica.resources` (512Mi/256Mi)
2. `helm/yttscrb/values.yaml` — добавлены `postgresql.primary.resources` (512Mi/256Mi)
3. Массовая очистка stuck workflows через tinker
4. Очистка Redis unique job locks (`laravel_unique_job:*`)
5. Перезапуск worker и horizon

## Prevention Plan

### 1. Увеличить лимиты памяти (✅ сделано)

| Компонент | Было | Стало |
|---|---|---|
| Redis master | 192Mi | 512Mi |
| Redis replica | 192Mi | 512Mi |
| PostgreSQL | 192Mi | 512Mi |

**Но этого недостаточно.** Нужны автоматические механизмы защиты.

### 2. Redis: настроить `maxmemory` и политику eviction

Добавить в `helm/yttscrb/values.yaml`:

```yaml
redis:
  master:
    extraFlags:
      - "--maxmemory 450mb"
      - "--maxmemory-policy allkeys-lru"
```

Это предотвратит OOM даже если данные вырастут: Redis начнёт вытеснять старые ключи по LRU вместо краша.

### 3. PostgreSQL: настроить `shared_buffers`

```yaml
postgresql:
  primary:
    extendedConfiguration: |
      shared_buffers = 128MB
      effective_cache_size = 384MB
      work_mem = 4MB
      maintenance_work_mem = 64MB
```

### 4. Добавить мониторинг и алерты

- **Prometheus + AlertManager** на K3s для алертов по memory usage >80%
- **Sentry** уже подключён — добавить алерт на spike ошибок
- **Healthcheck cron:** каждые 5 минут проверять `/up` и `/api/history`, алертить в Telegram при 5xx

### 5. Автоматическая очистка stuck workflows

Добавить scheduled command в [`routes/console.php`](routes/console.php):

```php
// Auto-cleanup stuck workflows older than 2 hours — every 30 minutes
Schedule::command(\App\Infrastructure\Console\Commands\CleanupStuckWorkflowsCommand::class)
    ->everyThirtyMinutes();
```

Команда должна:
- Находить workflows в `waiting`/`running` старше 2 часов
- Удалять их и связанные данные (signals, timers, exceptions, logs, relationships)
- Помечать соответствующие media_tasks как failed
- Очищать Redis locks

### 6. Circuit breaker для seed-команд

Если количество `processing` media_tasks превышает порог (например, 50), seed-команды должны пропускать создание новых задач вместо бесконечного накопления.

### 7. Graceful degradation для worker

Если workflow не может подключиться к Redis >3 попыток — пометить media_task как failed и остановить retry, вместо бесконечного цикла.

---

## Commits

| Commit | Описание |
|---|---|
| `d0549a0` | fix: increase Redis memory limit from 192Mi to 512Mi |
| `9602186` | fix: increase PostgreSQL memory limit from 192Mi to 512Mi |
