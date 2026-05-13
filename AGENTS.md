# AGENTS.md — Project Rules for AI Agents

These rules are mandatory for any AI agent working in this repository.

---

## 1. Source of Truth

1. The primary product and architecture specification is [`Prd.md`](Prd.md).
2. If a requested change conflicts with `Prd.md`, stop and ask for clarification before coding.
3. Do not invent architecture, providers, endpoints, workflows, infrastructure, or folder structure outside what is already defined unless the user explicitly requests it.
4. If behavior, API contract, architecture, or infrastructure changes, update the relevant documentation, including `Prd.md` when needed.

---

## 2. Current Project Baseline

- Backend: Laravel 13.x, PHP 8.5.
- Database: PostgreSQL 16+.
- Queue and cache backend: Redis 7+.
- Queue monitoring: Laravel Horizon.
- Long-running workflows: `durable-workflow/workflow` using Redis backend.
- Workflow UI: `durable-workflow/waterline`, embedded into the Laravel app.
- Local development: Docker Compose.
- Production: Kubernetes with Helm charts.
- Frontend: Vue 3 Composition API + TailwindCSS.

Treat this baseline as the default runtime contract unless the user explicitly changes it.

---

## 3. Architecture Rules

The project follows Hexagonal Architecture / Ports & Adapters.

Allowed dependency direction:

```text
Infrastructure → Application → Domain
Infrastructure → Domain is allowed only for domain types/contracts.
Domain → Application is forbidden.
Domain → Infrastructure is forbidden.
Application → Infrastructure is forbidden.
```

Rules:

1. Domain must not depend on Laravel, Eloquent, Redis, HTTP clients, filesystem, or external APIs.
2. Application defines use cases, DTOs, and ports.
3. Infrastructure implements controllers, persistence, external service adapters, queue/workflow activities, and presentation serializers.
4. External services must always be hidden behind Application ports.
5. Do not bypass ports from Domain or Application code.

Required ports include:

- `SubtitleProviderInterface`
- `TranscriptionProviderInterface`
- `SummaryProviderInterface`
- `AudioExtractorInterface`
- `MediaTaskRepositoryInterface`

---

## 4. Coding Rules

Always follow:

- SOLID
- DRY
- YAGNI
- KISS
- Clean Code
- Clean Architecture
- Strong typing first

Forbidden:

1. Static Laravel facades in business code.
2. Global helpers such as `app/helpers.php`.
3. Untyped `mixed` unless strictly unavoidable and documented.
4. God objects and multi-responsibility services.
5. Query Builder inside use cases or business services; use repositories.
6. `dd()`, `dump()`, debug leftovers, commented-out code, or temporary logging in committed code.
7. Hardcoded secrets, API keys, tokens, credentials, or environment-specific URLs.
8. Eloquent models outside Infrastructure.
9. Provider-specific naming leaking into Application or Domain where provider-agnostic names are expected.
10. `goto` or similar flow-jumping constructs.
11. Merge conflict markers, placeholder implementations, fake stubs presented as done, or unfinished TODO blocks in delivered work unless explicitly requested.

---

## 5. API and Presentation Rules

1. Follow the API contract described in `Prd.md`.
2. Do not silently change response shapes, field names, status names, or error structures.
3. Error responses must use:

```json
{
  "error": {
    "code": "...",
    "message": "...",
    "details": {}
  }
}
```

4. Presentation-layer concerns belong to Infrastructure.
5. Do not use Domain persistence-oriented serialization as an HTTP contract unless that is explicitly the intended public API contract.
6. If multiple endpoints expose the same resource shape, prefer a shared presentation abstraction instead of duplicated manual arrays.

---

## 6. Workflow and Background Processing Rules

1. Long-running orchestration must use `durable-workflow/workflow` on Redis.
2. Keep workflow code deterministic and explicit.
3. Use `yield from` when delegating to private generator methods inside workflows.
4. Activities must be idempotent where possible.
5. Cleanup of temporary resources must always be considered in workflow design.
6. Do not introduce a separate Temporal cluster or other workflow runtime unless explicitly requested.

---

## 7. Infrastructure Rules

Local development:

1. The project must run through Docker Compose.
2. Required services are `app`, `postgres`, `redis`, `horizon`, and `worker`.
3. `waterline` is embedded in the app and is not a separate container.
4. Do not require host-installed PostgreSQL, Redis, or external workflow infrastructure.

Production:

1. Deploy through Kubernetes.
2. Helm charts are required for application components.
3. Secrets must be stored as Kubernetes Secrets, not ConfigMaps.
4. App, Horizon, and workflow workers must remain independently scalable.

---

## 8. Testing and Quality Gates

Every meaningful code change must preserve or improve test coverage and must not weaken static analysis or architecture boundaries.

Primary acceptance rule:

- `composer check` **must always pass**.
- A task is **not complete** if `composer check` fails.
- Treat a green `composer check` as a mandatory acceptance criterion, not an optional cleanup step.

`composer check` currently runs:

```bash
@php vendor/bin/phpstan analyse --level=9 --no-progress
@php vendor/bin/phpcs --standard=PSR12 app/ tests/
@php vendor/bin/deptrac analyze
@php vendor/bin/pest --compact
```

Additional rules:

1. Add or update tests when behavior changes.
2. Do not claim success without running relevant verification or explicitly stating why it could not be run.
3. Fix root causes, not only test symptoms.
4. Keep Domain and Application highly covered; do not accept regressions lightly.
5. If a quality gate fails and the fix is not obvious, stop and surface it clearly.

---

## 9. Git / PR / Commit Rules

1. Prefer small, atomic, reviewable changes.
2. Keep commits logically grouped by purpose.
3. Do not mix unrelated refactors with feature or bugfix work unless necessary and explained.
4. Before considering work ready for review or merge, ensure the branch contains no debug code, no dead experimental files, and no unresolved conflicts.
5. Do not describe work as finished, merged-ready, or production-ready if verification is still failing.
6. If documentation, config, or tests must change together with code, include them in the same change set.
7. Preserve a clean diff: avoid gratuitous formatting churn and unrelated file edits.

---

## 10. When to Stop and Ask

Stop and ask the user before proceeding if:

1. A request contradicts `Prd.md`.
2. A change requires a product or architecture decision that is not already settled.
3. Real credentials, external access, or deployment access are required.
4. A quality gate fails and the correct fix is unclear.
5. The task would require large-scale deletion or architectural rewrites beyond the stated scope.
6. You cannot preserve the public contract without a visible product decision.

---

## 11. Definition of Done

Do not consider a task complete until all of the following are true:

- Relevant `Prd.md` constraints were respected.
- Architecture boundaries were preserved.
- Tests were added or updated where needed.
- Documentation was updated when behavior or contracts changed.
- No debug leftovers or temporary hacks remain.
- `composer check` passes.
