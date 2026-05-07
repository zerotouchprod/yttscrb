# First Implementation Slice Plan — YTTSCRB

Date: 2026-05-07

## Repository Inspection Summary

The repository currently contains documentation only: `AGENTS.md`, `Prd.md`, `README.md`, and planning files under `plans/`. There is no Laravel application skeleton yet, no `composer.json`, no Docker Compose config, no Helm chart, and no test suite. PHP 8.5, Composer, and Docker are available locally.

## Relevant Source-of-Truth Rules

- `Prd.md` remains the source of truth; `plans/2026-05-07-prd-restructure-plan.md` is advisory only and must not be executed as a documentation restructure.
- Backend must be Laravel 13.x on PHP 8.5, PostgreSQL 16+, Redis 7+, Horizon, `durable-workflow/workflow`, and `durable-workflow/waterline`.
- Local development must run through Docker Compose with `app`, `postgres`, `redis`, `horizon`, `temporal`, `temporal-ui` / Waterline, and `temporal-worker` services.
- Production v1 must include Kubernetes/Helm baseline; Kubernetes/Helm is not postponed.
- Architecture must be Hexagonal: Infrastructure → Application → Domain, with no Domain/Application dependency on Laravel, Eloquent, Redis, Temporal, filesystem, HTTP clients, or providers.
- Required ports: `SubtitleProviderInterface`, `TranscriptionProviderInterface`, `SummaryProviderInterface`, `AudioExtractorInterface`, and `MediaTaskRepositoryInterface`.
- TDD is mandatory; no implementation slice is complete without tests and quality gates or documented blockers.

## First Slice Goal

Create a minimal but production-aligned foundation that makes future feature work possible without violating the PRD:

1. Generate a Laravel 13 application skeleton in the repository root.
2. Add development and quality dependencies needed for the approved stack.
3. Add Hexagonal directory skeleton and Deptrac boundaries.
4. Add Docker Compose and Dockerfile for local v1 services.
5. Add Helm chart skeleton for Kubernetes v1 deployment.
6. Add the first tests before domain implementation.

## TDD Scope for First Code

Start with Domain-only tests because they are independent of Laravel infrastructure:

1. `TranscriptionStatus` transition rules.
2. `YouTubeUrl` validation and video id extraction.
3. `TranscriptionText` non-empty invariant.
4. `MediaTask` lifecycle transitions: create → processing → completed / failed.

## Implementation Steps

1. Use Composer to create/install the Laravel 13 skeleton without overwriting source-of-truth documents unnecessarily.
2. Add Pest, PHPStan, PHPCS, Deptrac, Horizon, workflow, Waterline, and required Laravel/Vue/Tailwind dependencies where available.
3. Add initial test files for Domain behavior and run them to confirm RED state.
4. Implement the minimal Domain objects to pass tests.
5. Add `deptrac.yaml`, `docker-compose.yml`, `Dockerfile.dev`, and `helm/yttscrb` skeleton.
6. Run available quality checks:
   - `vendor/bin/pest`
   - `vendor/bin/phpstan analyse --level=9`
   - `vendor/bin/phpcs --standard=PSR12 app/ tests/`
   - `vendor/bin/deptrac analyze`
7. Commit atomically after verification.

## Explicit Non-Goals for This Slice

- No real Groq/OpenAI/YouTube calls.
- No v2 Nocix runtime implementation.
- No PRD restructure.
- No API behavior changes outside what is needed for project foundation.
- No frontend feature implementation yet beyond preserving Laravel/Vite readiness.
