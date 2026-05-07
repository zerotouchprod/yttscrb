# AGENTS.md — Rules for AI Agents

This file is mandatory for all AI agents working on this repository (Cline, Cursor, Codex, Loki-mode, Kilo Code, or any other agentic worker).

## 1. Source of Truth

1. The primary product and architecture specification is [`Prd.md`](Prd.md).
2. If implementation requirements conflict with [`Prd.md`](Prd.md), stop and ask for clarification before coding.
3. Do not invent architecture, providers, endpoints, folder structure, or infrastructure outside the PRD unless explicitly requested.
4. Keep [`Prd.md`](Prd.md) updated when architectural decisions change.

## 2. Current Approved Stack

- Backend: Laravel 13.x, PHP 8.5.
- Database: PostgreSQL 16+.
- Queue: Redis 7+ with Laravel Horizon.
- Long-running workflows: `durable-workflow/workflow` (standalone PHP workflow engine, **not** a separate Temporal cluster; uses Redis as backend via `WORKFLOW_CONNECTION=redis`).
- Workflow UI: `durable-workflow/waterline` (embedded as a Laravel library, **no separate Docker service** required).
- Local development: Docker Compose (services: app, postgres, redis, horizon, worker).
- Production: Kubernetes with Helm charts.
- Fast transcription provider for v1: Groq Whisper API.
- Zero-cost first step: `yt-dlp --write-auto-sub --skip-download`.
- Slow/free v2 transcription: self-hosted `whisper.cpp` on Nocix / Phenom II X4 840.
- Summary provider for v1: GPT-4o-mini.
- Frontend: Vue 3 Composition API + TailwindCSS.

## 3. Architecture Rules

The project uses Hexagonal Architecture / Ports & Adapters.

Allowed dependency direction:

```text
Infrastructure → Application → Domain
Infrastructure → Domain is allowed only for domain types/contracts.
Domain → Application is forbidden.
Domain → Infrastructure is forbidden.
Application → Infrastructure is forbidden.
```

Rules:

1. Domain must not know Laravel, Eloquent, Redis, HTTP clients, filesystem, or external APIs.
2. Application layer defines use cases and ports.
3. Infrastructure implements adapters for persistence, external providers, HTTP controllers, queue workers, and workflow activities.
4. External services must be hidden behind interfaces.
5. Do not call Groq, OpenAI, yt-dlp, Redis, or PostgreSQL directly from Domain or Use Case code unless a PRD-approved port allows it.

Required ports include:

- `SubtitleProviderInterface`
- `TranscriptionProviderInterface`
- `SummaryProviderInterface`
- `AudioExtractorInterface`
- `MediaTaskRepositoryInterface`

## 4. Hard Rules

Always follow:

- SOLID
- DRY
- YAGNI
- KISS
- Clean Code
- Clean Architecture
- TDD

Forbidden:

1. Static Laravel facades in business code (`DB`, `Cache`, `Queue`, `Storage`, `Log`, etc.). Use dependency injection.
2. Global helpers such as `app/helpers.php`.
3. Untyped `mixed` unless strictly unavoidable and documented.
4. God objects and large multi-responsibility services.
5. Query Builder inside services/use cases. Use repositories.
6. `dd()`, `dump()`, debug leftovers, or temporary logging in committed code.
7. Hardcoded secrets, API keys, tokens, URLs, or credentials.
8. Eloquent models outside Infrastructure.
9. Provider-specific names in Application/Domain where provider-agnostic names are required.
10. `goto` or flow-jumping constructs in workflow/use-case examples.

## 5. Development Process

For every feature or bugfix:

1. Read [`Prd.md`](Prd.md) sections relevant to the task.
2. Write tests first where code is involved.
3. Implement the smallest change that passes tests.
4. Refactor without changing behavior.
5. Run quality checks.
6. Update docs/PRD if behavior, API, infra, or architecture changed.

Do not implement v1.1+ features unless explicitly requested. If v1.1+ support is mentioned in PRD, keep it as architecture-ready, not production code, unless the task asks for it.

## 6. Testing and Quality Gates

Required checks for PHP/Laravel work:

```bash
vendor/bin/phpstan analyse --level=9
vendor/bin/phpcs --standard=PSR12 app/ tests/
vendor/bin/deptrac analyze
vendor/bin/pest --coverage --min=80
```

Coverage policy:

- Domain: 100%.
- Application: 100%.
- Infrastructure: at least 70%.
- Total CI threshold: 80%.

Testing rules:

1. Domain entities, value objects, enums, and state transitions require unit tests.
2. Use cases require unit tests for every scenario.
3. Controllers require feature tests for critical paths: 202, 400, 401, 404, 409, 429.
4. External providers require contract tests with mocked clients.
5. The full transcription workflow must have at least one full-cycle test with mocked activity adapters.
6. Do not rely on real Groq/OpenAI/YouTube calls in normal CI.

## 7. Workflow and Transcription Rules

The transcription workflow must follow the approved cascade:

1. Step 0: Try subtitles with `SubtitleExtractorActivity` using yt-dlp auto subtitles.
2. Step 1: If no subtitles, download audio with `AudioDownloaderActivity`.
3. Step 2: Use `GroqTranscriberActivity` as v1 fast track.
4. Step 3: Use `NocixWhisperActivity` only as v2 slow/free fallback or when explicitly requested.
5. Step 4: Generate summary with `AiSummaryActivity`.
6. Step 5: Persist result with `PersistResultActivity`.
7. Always cleanup temporary audio with `CleanupActivity`.

The workflow engine is `durable-workflow/workflow` running on Redis (`WORKFLOW_CONNECTION=redis`). For this repository's local v1 setup, background processing runs through Redis workers (`php artisan queue:work redis --queue=default`) and Horizon. There is **no separate Temporal server**.

Workflow code rules:

1. Use `yield from` when delegating to private generator methods.
2. Do not use `goto`.
3. Keep workflow steps explicit and deterministic.
4. Use `WorkflowId = transcribe-{taskId}`.
5. Use idempotent activities and request IDs where providers support them.
6. For slow Nocix jobs, document and surface `estimated_completion_sec: 7200`.

## 8. API Rules

Follow the API contract in [`Prd.md`](Prd.md), especially:

- `POST /api/transcribe`
- `GET /api/transcribe/{id}`
- `GET /api/transcribe/{id}/download`
- `GET /api/history`
- `GET /api/history/latest`

Rules:

1. v1.0 is public: endpoints do not require registration or `X-API-Key`.
2. Responses must use the documented JSON shapes.
3. Error responses must use `{ "error": { "code", "message", "details" } }`.
4. Do not change status names without updating PRD and tests.
5. History must support latest video display.
6. Deduplication for public v1 must use completed `video_id`.

## 9. Data and Business Rules

1. Free tier limit: 10 completed transcriptions/month.
2. Only `completed` tasks consume monthly quota.
3. Failed/processing tasks do not consume quota.
4. Same completed `video_id` returns existing result in public v1.
5. Failed tasks can be retried.
6. Temporary audio files must be deleted after workflow completion/failure.
7. Transcript and summary retention follows PRD retention policy.
8. `failed_at` and `completed_at` must be kept consistent with status transitions.

## 10. Infrastructure Rules

Local development:

1. Must run through Docker Compose.
2. Required services: **app**, **postgres**, **redis**, **horizon**, **worker** (background queue worker, runs `php artisan queue:work redis --queue=default`).
3. `waterline` UI is embedded in the app — **no separate Docker service** needed.
4. Do **not** add a standalone Temporal server (`temporalio/auto-setup`) — the project uses `durable-workflow/workflow` directly on Redis.
5. Do not require host-installed PostgreSQL, Redis, or a Temporal cluster.

Production:

1. Deploy through Kubernetes.
2. Helm charts are required for app, horizon, workflow-worker, ingress, configmap, HPA, PDB.
3. PostgreSQL and Redis must be represented as Kubernetes-managed services/subcharts or documented external managed services.
4. The workflow engine worker (`php artisan workflow:work`) is a separate Kubernetes Deployment — not a Temporal cluster.
5. Secrets must be Kubernetes Secrets, not ConfigMaps.
6. App, Horizon, and workflow workers must be independently scalable.

## 11. Provider Rules

Default v1 providers:

- Transcription: Groq Whisper API.
- Summary: OpenAI GPT-4o-mini.
- Subtitles: yt-dlp auto subtitles.

Rules:

1. `TRANSCRIPTION_PROVIDER=groq` must work out of the box.
2. Provider-specific code belongs in Infrastructure adapters only.
3. Do not hardcode Deepgram as default.
4. Do not add v1.1+ providers to runtime code unless explicitly requested. The DI `match` example in `Prd.md` section 2.1 shows **architectural reference** — in v1.0 only the `groq` branch must be implemented; v1.1+ branches (`whisper_api`, `runpod_whisper`, `assemblyai`) are stubs that throw `InvalidProviderException` until explicitly requested.
5. `whisper.cpp` on Phenom II X4 840 must be built without AVX/AVX2:

```bash
LLAMA_NO_AVX=1 LLAMA_NO_AVX2=1
```

## 12. Completion Checklist

Before claiming work is complete, verify:

- [ ] Relevant PRD sections were followed.
- [ ] Tests were added/updated.
- [ ] Quality commands were run or explicitly documented as not runnable.
- [ ] No hardcoded secrets.
- [ ] No Laravel facades in business code.
- [ ] No provider-specific leakage into Domain/Application.
- [ ] API responses still match PRD.
- [ ] Workflow remains deterministic and uses `yield from` correctly.
- [ ] Docker/Helm changes are reflected in documentation when infra changes.
- [ ] [`Prd.md`](Prd.md) updated if behavior or architecture changed.

## 13. When to Stop

Stop and ask the user before proceeding if:

1. A requirement contradicts [`Prd.md`](Prd.md).
2. A change requires choosing between multiple providers or deployment strategies.
3. A v1.1+ feature is needed to complete a v1 task.
4. A quality gate fails and the fix is not obvious.
5. You need real API credentials or external service access.
6. You would need to delete or rewrite large parts of the architecture.
