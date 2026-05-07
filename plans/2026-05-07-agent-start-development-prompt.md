# Agent Prompt — Start YTTSCRB Development

Use this prompt for a fresh AI development agent (Cline, Cursor, Codex, Loki-mode, Kilo Code, or similar) to start implementation from the current repository state.

---

## Prompt

You are an autonomous senior full-stack engineering agent working on the YTTSCRB project.

Your task is to start development of the YouTube Transcriber & Summarizer Micro-SaaS from the existing repository.

Before writing code, read and follow these documents in this order:

1. `AGENTS.md` — mandatory rules for all AI agents.
2. `Prd.md` — current source of truth for product, architecture, API, workflow, infrastructure, and delivery scope.
3. `plans/2026-05-07-prd-restructure-plan.md` — planning context for future documentation cleanup. Do not execute the restructure unless explicitly asked.

Important: `Prd.md` is currently still the source of truth. The restructure plan is advisory and future-facing.

## Non-negotiable Constraints

Follow these rules strictly:

1. Use Laravel 13.x and PHP 8.5.
2. Use PostgreSQL 16+.
3. Use Redis 7+ and Laravel Horizon for Laravel queues.
4. Use Temporal via `durable-workflow/workflow` for long-running workflows.
5. Use `durable-workflow/waterline` for Temporal UI.
6. Use Vue 3 Composition API + TailwindCSS for frontend.
7. Use Docker Compose for local development.
8. Production deployment for v1 **must be Kubernetes with Helm charts**. Do not postpone Kubernetes/Helm out of v1.
9. Use Groq Whisper API as the v1 fast transcription provider.
10. First transcription step must try subtitles with `yt-dlp --write-auto-sub --skip-download`.
11. Self-hosted `whisper.cpp` on Nocix / Phenom II X4 840 is v2/slow-track architecture unless explicitly requested for implementation.
12. Follow Hexagonal Architecture / Ports & Adapters.
13. Follow TDD, SOLID, DRY, YAGNI, KISS, Clean Code, Clean Architecture.
14. Do not use Laravel facades in business code.
15. Do not hardcode secrets or provider credentials.
16. Do not call external providers directly from Domain or Application use cases.

## Required v1 Scope

Implement v1 around this user flow:

1. User registers or signs in.
2. User submits a public YouTube URL.
3. System creates an async transcription task.
4. Workflow tries subtitles first.
5. If no subtitles, workflow downloads audio.
6. Workflow transcribes with Groq Whisper API.
7. Workflow generates summary with GPT-4o-mini.
8. Workflow persists result.
9. User sees status, transcript, summary, history, latest video, and TXT export.
10. Local development works through Docker Compose.
11. Production-ready manifests are provided through Helm charts for Kubernetes.

## Required v1 Infrastructure

Local development must include Docker Compose services for:

- app
- postgres
- redis
- horizon
- temporal
- temporal-ui / waterline
- temporal-worker

Production v1 must include Helm chart(s) for:

- app deployment/service
- horizon deployment
- temporal-worker deployment
- ingress
- configmap
- secrets references
- HPA where appropriate
- PDB where appropriate
- PostgreSQL/Redis/Temporal as subcharts or documented external managed services

Kubernetes is not optional for v1 because production will run only on Kubernetes.

## Architecture Requirements

Implement these layers:

```text
app/
├── Domain/
├── Application/
└── Infrastructure/
```

Allowed dependency direction:

```text
Infrastructure → Application → Domain
Infrastructure → Domain is allowed only for domain types/contracts.
Domain → Application is forbidden.
Domain → Infrastructure is forbidden.
Application → Infrastructure is forbidden.
```

Required ports:

- `SubtitleProviderInterface`
- `TranscriptionProviderInterface`
- `SummaryProviderInterface`
- `AudioExtractorInterface`
- `MediaTaskRepositoryInterface`

Provider-specific code must be in Infrastructure adapters only.

## Workflow Requirements

The `TranscribeVideoWorkflow` must follow this cascade:

1. `SubtitleExtractorActivity`
2. `AudioDownloaderActivity`
3. `GroqTranscriberActivity`
4. `AiSummaryActivity`
5. `PersistResultActivity`
6. `CleanupActivity`

Rules:

- Always cleanup temporary audio files.
- Use `yield from` when delegating to private generator methods.
- Do not use `goto`.
- Keep workflow deterministic.
- Use `WorkflowId = transcribe-{taskId}` unless implementing retries with attempt numbers.
- If retry attempts are implemented, use `WorkflowId = transcribe-{taskId}-attempt-{attemptNo}`.

## API Requirements

Implement API contracts from `Prd.md`, especially:

- `POST /api/auth/register`
- `POST /api/transcribe`
- `GET /api/transcribe/{id}`
- `GET /api/transcribe/{id}/download`
- `GET /api/history`
- `GET /api/history/latest`

Rules:

- All endpoints except registration require authentication.
- Use the documented JSON response shapes.
- Use structured errors:

```json
{
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable message",
    "details": {}
  }
}
```

- History must support latest video display.
- Deduplication must use completed `video_id + user_id`.

## Testing and Quality Gates

Use TDD.

Required checks:

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

Do not rely on real Groq/OpenAI/YouTube calls in normal CI. Use mocked clients and contract tests.

## Recommended Execution Order

Start with small, verifiable increments:

1. Inspect repository state and confirm existing files.
2. Create/update project skeleton only as required by `Prd.md` and `AGENTS.md`.
3. Create Docker Compose and Dockerfiles for local v1 environment.
4. Create Laravel app structure and configure PostgreSQL, Redis, Horizon.
5. Create Hexagonal directories and Deptrac config.
6. Implement Domain model and tests.
7. Implement Application ports and use cases with tests.
8. Implement Infrastructure persistence adapters and migrations.
9. Implement API auth/register and transcribe endpoints.
10. Implement Temporal workflow and activities with mocked provider tests.
11. Implement Vue UI for submit/status/history/latest/TXT export.
12. Add Helm chart skeleton for Kubernetes v1 production deployment.
13. Run quality gates.
14. Update docs if any behavior diverges from `Prd.md`.

## Stop Conditions

Stop and ask for clarification if:

1. `Prd.md` and `AGENTS.md` conflict.
2. A required provider credential is needed.
3. A task would require implementing v2 functionality not explicitly requested.
4. Quality gates fail and the fix is not obvious.
5. You need to choose between multiple auth/product strategies not already decided.
6. Kubernetes production requirements conflict with the current MVP scope.

## Expected First Response From Agent

The agent should not immediately implement everything. It should first:

1. Summarize the relevant rules it found in `AGENTS.md` and `Prd.md`.
2. Inspect the repository state.
3. Produce a short implementation plan for the first development slice.
4. Start with tests and minimal implementation.

Suggested first slice:

- Project scaffolding + Docker Compose + base Laravel dependencies + CI/quality config.

Do not skip tests and do not skip Kubernetes/Helm planning for v1.
