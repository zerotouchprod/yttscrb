# PRD Restructure Plan — YTTSCRB

> **Purpose:** Convert the current all-in-one [`Prd.md`](../Prd.md) into a clearer set of documents with strict MVP focus.
>
> **Important:** This is a planning document only. It does not change [`Prd.md`](../Prd.md) by itself.

## 1. Problem Summary

The current [`Prd.md`](../Prd.md) is technically strong but mixes four document types:

1. Product Requirements Document (problem, users, user flows, scope, metrics).
2. Technical Design (Laravel, Hexagonal Architecture, Temporal, providers, DB, API).
3. Execution Plan (phases, dependencies, DoD).
4. Ops Runbook (Docker Compose, Kubernetes, Helm, CI/CD, deployment).

This makes MVP prioritization harder and increases the risk of implementing infrastructure and optimization before validating core product value.

## 2. Target Document Structure

Create three primary documents plus one optional ops document:

| Document | Responsibility | Source sections from current PRD |
|---|---|---|
| `docs/product/prd.md` | Product problem, ICP, JTBD, scope, UX flows, success metrics | Sections 1, parts of 7, business rules from 5.3 |
| `docs/technical/tech-design.md` | Architecture, domain model, workflow, API contract, data model, provider abstraction | Sections 2–8, 12 |
| `plans/mvp-execution-plan.md` | Stage A / Stage B plan, task ordering, DoD | Sections 10, 14, 15 |
| `docs/operations/deployment.md` | Docker Compose, Kubernetes, Helm, CI/CD, infra runbook | Sections 9, 13 |

Keep root [`Prd.md`](../Prd.md) as either:

- a short index linking to the split documents, or
- the canonical product PRD only.

Recommended: **make root [`Prd.md`](../Prd.md) the canonical product PRD**, and move technical/ops details out.

## 2.1 Migration Map: Old Sections → New Files

Use this map during extraction to avoid losing content.

| Current [`Prd.md`](../Prd.md) section | Destination | Notes |
|---|---|---|
| 1. Vision & Product Goals | `docs/product/prd.md` or root [`Prd.md`](../Prd.md) | Keep ICP, JTBD, scope; remove implementation details |
| 2. Tech Stack | `docs/technical/tech-design.md` | Keep only stack decisions; link from product PRD if needed |
| 2.1 Provider Abstraction Strategy | `docs/technical/tech-design.md` | Keep provider boundaries; mark v1.1+ explicitly |
| 2.2 Queues: Laravel Horizon + Redis | `docs/technical/tech-design.md` and `docs/operations/deployment.md` | Design goes technical; deployment config goes ops |
| 2.3 Temporal | `docs/technical/tech-design.md` | Keep workflow rationale and Temporal/Horizon boundary |
| 3. Architecture | `docs/technical/tech-design.md` | Keep Hexagonal Architecture, directory layout, deptrac |
| 4. Domain Model | `docs/technical/tech-design.md` | Keep entities, VOs, enums |
| 5. Database Schema | `docs/technical/tech-design.md` | Keep schema; business rules may be referenced from product PRD |
| 5.3 Business Rules | `docs/product/prd.md` + `docs/technical/tech-design.md` | Product owns rules; technical doc owns implementation details |
| 6. Business Pipeline | `docs/technical/tech-design.md` | Keep Temporal workflow and activities |
| 7. REST API Design | `docs/technical/tech-design.md` | Product PRD should only describe user-facing behavior |
| 8. Quality Gates | `plans/mvp-execution-plan.md` + [`AGENTS.md`](../AGENTS.md) | Keep enforced rules in agents file too |
| 9. GitLab CI Pipeline | `docs/operations/deployment.md` | CI/CD belongs to ops |
| 10. Execution Plan | `plans/mvp-execution-plan.md` | Rewrite into Stage A / Stage B |
| 11. Testing | `plans/mvp-execution-plan.md` + `docs/technical/tech-design.md` | Technical test strategy + execution checklist |
| 12. Assumptions & Constraints | Split | Product constraints → PRD; technical/security → tech/ops |
| 13. Infrastructure & Deployment | `docs/operations/deployment.md` | Docker, Helm, Kubernetes, CI deploy |
| 14. Roadmap | `plans/mvp-execution-plan.md` | Remove false precision if needed |
| 15. DoD | `plans/mvp-execution-plan.md` + [`AGENTS.md`](../AGENTS.md) | Keep completion checklist in agents file |
| 16. Hard Rules | [`AGENTS.md`](../AGENTS.md) + `docs/technical/tech-design.md` | Agents must enforce these |
| 17. Team | Optional product appendix | Keep only if useful |

## 3. Revised MVP Scope

### Stage A — MVP Launch

Keep only features required to validate demand:

- User registration / login.
- Submit YouTube URL.
- Async processing.
- Transcript output.
- Summary output.
- History page.
- Latest video widget.
- TXT export.
- Basic observability.
- One cloud ASR provider: Groq Whisper API.
- Optional zero-cost subtitles only if it does not delay launch.

### Stage B — Scale / Cost Optimization

Move these out of MVP implementation scope:

- Self-hosted `whisper.cpp` on Nocix.
- Slow queue UX.
- Advanced provider fallback chain.
- Advanced Kubernetes hardening beyond required v1 Helm deploy (Kubernetes/Helm baseline remains required in v1 because production runs only on Kubernetes).
- Load testing as a separate hardening stage.
- Advanced retry attempts and queue fairness beyond basic abuse limits.
- Waterline / Temporal UI production tuning.

## 4. Product Metrics to Add

Add a **Success Metrics** section to product PRD:

| Metric | Meaning | Target for MVP validation |
|---|---|---|
| Activation rate | Registered users who submit at least one URL | Define after first launch cohort |
| Completion rate | Submitted tasks that reach `completed` | Track from day 1 |
| Median time-to-result | Time from submit to completed | Track separately for subtitles and Groq |
| Subtitle hit rate | Share of jobs solved by subtitles | Used for cost optimization decisions |
| Fail rate | Jobs ending in `failed` | Alert if rising |
| Cost per completed transcription | ASR + summary cost per completed task | Must be visible in admin metrics |
| TXT export rate | Completed tasks with TXT download | Measures downstream value |
| 7-day retention | Users returning within 7 days | Core product signal |

Do not add hard numeric targets until baseline usage exists.

## 5. Product UX / Auth Decisions to Clarify

The current API-key model is developer-oriented. For consumer micro-SaaS, clarify one of these:

### Option A — Consumer-first auth (recommended)

- Email + password or magic link.
- Session cookie / Laravel Sanctum SPA auth.
- API key exists only internally or for future developer API.
- History and latest video are user-facing dashboard features.

### Option B — Developer-first API product

- API key in `X-API-Key` is primary.
- UI is secondary / demo client.
- History is API-driven.

Recommendation: **Option A** for MVP unless the product is explicitly API-first.

## 6. Contradictions to Resolve During Restructure

### 6.1 Duplicate contract for deduplication

Current ambiguity:

- duplicate task response is described as `409 Conflict / 200 OK`.

Decision needed:

- Use **200 OK** when returning existing completed result.
- Reserve **409 Conflict** for active duplicate processing task, if needed.

Recommended contract:

| Scenario | HTTP | Behavior |
|---|---|---|
| Same completed `video_id + user_id` | 200 | Return existing task/result |
| Same active task | 202 | Return active task id/status |
| Failed previous task | 202 | Create retry attempt |

### 6.2 Status model vs slow queue

Do not introduce status string `processing (slow queue)`.

Keep status enum:

- `pending`
- `processing`
- `completed`
- `failed`

Add separate fields:

```json
{
  "status": "processing",
  "processing_tier": "slow",
  "queue_mode": "free",
  "estimated_completion_sec": 7200
}
```

### 6.3 Retry and WorkflowId conflict

Current conflict:

- Retry described as same `task_id`.
- WorkflowId = `transcribe-{taskId}`.

Recommended model:

- `media_tasks.id` is stable logical task id.
- Add `attempt_no` or child table `media_task_attempts`.
- WorkflowId = `transcribe-{taskId}-attempt-{attemptNo}`.
- Latest attempt is shown in API.

### 6.4 Polling vs SSE

For MVP choose one mechanism:

- **MVP:** polling only.
- **Later:** SSE/WebSockets if needed.

Remove `Polling / SSE` mixed wording from MVP sections.

### 6.5 JTBD timestamps

JTBD promises timestamped text.

Decision:

- If timestamps are MVP: add transcript segments with `start_sec`, `end_sec`, `text`.
- If not MVP: change JTBD wording to avoid promising timestamps.

Recommended: keep basic segments in data model if Groq returns timestamps cheaply; UI can still render plain text first.

## 7. Abuse Protection to Add

Add rules beyond monthly completed quota:

| Rule | MVP value |
|---|---|
| Max active tasks per user | 1–2 active tasks |
| Submit rate limit | e.g. 5 submissions / 10 minutes |
| Max video duration before queue | 2 hours |
| Failed-task retry cooldown | e.g. 1 retry per failed task per short interval |
| URL validation before yt-dlp | Required to reduce SSRF risk |
| Quota counted on completion | Already defined, keep it |

Do not rely only on `10 completed/month`; failed/processing spam must be throttled.

## 8. Proposed Refactor Steps

### Task 1 — Create document directories

Create:

- `docs/product/`
- `docs/technical/`
- `docs/operations/`
- `plans/`

### Task 2 — Extract product PRD

Move product-only content to `docs/product/prd.md` or root [`Prd.md`](../Prd.md):

- Vision
- ICP
- JTBD
- MVP scope
- User journeys
- Success metrics
- Business rules
- Abuse limits
- Auth UX decision

### Task 3 — Extract technical design

Move to `docs/technical/tech-design.md`:

- Hexagonal architecture
- Ports/adapters
- Domain model
- DB schema
- API contract
- Workflow
- Provider strategy
- Quality gates

### Task 4 — Extract operations document

Move to `docs/operations/deployment.md`:

- Docker Compose
- Dockerfiles
- Redis/Horizon
- Temporal/Waterline
- Kubernetes
- Helm
- CI/CD deploy stages

### Task 5 — Rewrite execution plan into two stages

Create `plans/mvp-execution-plan.md`:

- Stage A: MVP Launch
- Stage B: Scale / Cost Optimization

Remove optimistic sprint wording if it creates false precision.

### Task 6 — Resolve contradictions

Apply the decisions from section 6:

- Deduplication contract.
- `processing_tier` / `queue_mode`.
- Retry attempt model.
- Polling-only MVP.
- Timestamp promise.

### Task 7 — Update AGENTS.md references

Update [`AGENTS.md`](../AGENTS.md) to reference the new split documents:

- Product source: `docs/product/prd.md` or root [`Prd.md`](../Prd.md)
- Technical source: `docs/technical/tech-design.md`
- Ops source: `docs/operations/deployment.md`
- Execution source: `plans/mvp-execution-plan.md`

## 9. Stage A Acceptance Criteria (User Outcome)

Stage A MVP is acceptable when a real user can complete this flow without developer help:

1. User registers or signs in.
2. User submits a valid public YouTube URL.
3. System creates an async transcription task.
4. User can leave and return to the app.
5. User can see task status in history.
6. Completed task displays:
   - video title,
   - transcript,
   - summary,
   - created/completed timestamps.
7. User can download TXT transcript.
8. Latest completed video appears in the latest video widget.
9. Failed task shows a human-readable error and a retry option.
10. Abuse controls prevent unlimited active/failed submissions.

Operationally, Stage A is acceptable when:

- Median successful task flow is observable from submit to completion.
- Cost per completed transcription can be calculated.
- Completion/failure rates are visible in logs or admin metrics.
- No real external provider calls are required in CI.
- MVP deploy can run locally through Docker Compose.

## 10. Restructure Acceptance Criteria

The restructure is complete when:

- Product PRD can be read without implementation details.
- Technical design can be handed to backend/frontend agents.
- Operations doc can be handed to DevOps agent.
- MVP scope clearly excludes Stage B cost optimization.
- No section contradicts provider, auth, status, retry, or deduplication choices.
- [`AGENTS.md`](../AGENTS.md) points agents to the correct documents.

## 11. Decision Log

Before executing the restructure, fill this table.

| Decision | Options | Recommended | Owner | Status |
|---|---|---|---|---|
| Root [`Prd.md`](../Prd.md) role | Product PRD / Index page | Product PRD | Product owner | Open |
| MVP auth model | Consumer sessions/Sanctum / API-key-first | Consumer sessions/Sanctum | Product owner + backend lead | Open |
| Transcript timestamps in v1.0 | Segments in data model / Plain text only | Segments in data model, plain text UI first | Product owner + backend lead | Open |

Decision records should be written as short dated notes:

```markdown
### 2026-05-07 — Auth model

Decision: Consumer-first auth with Laravel Sanctum SPA sessions.
Reason: Product is consumer micro-SaaS with dashboard/history/latest-video UX.
Impact: API keys move to future developer API scope.
```

## 12. Recommended Next Action

Before editing [`Prd.md`](../Prd.md), decide:

1. Should root [`Prd.md`](../Prd.md) remain the product PRD, or become an index?
2. Should MVP auth be consumer-first sessions/Sanctum, or API-key-first?
3. Should transcript timestamps be part of v1.0 data model?

After those decisions, execute this plan in Code mode.
