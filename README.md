# YTTSCRB

YouTube Transcriber & Summarizer Micro-SaaS.

The source of truth for product and architecture is [`Prd.md`](Prd.md). Agent rules are in [`AGENTS.md`](AGENTS.md).

## Current Status

Initial Laravel 13 / PHP 8.5 foundation with Hexagonal Architecture directories, Domain tests, Docker Compose local services, and a Helm chart skeleton.

## Local Development

```bash
composer install
cp .env.example .env
php artisan key:generate
docker compose up --build
```

Required local services are defined in [`docker-compose.yml`](docker-compose.yml): `app`, `postgres`, `redis`, `horizon`, `temporal`, `temporal-ui`, and `temporal-worker`.

## Quality Gates

```bash
vendor/bin/phpstan analyse --level=9
vendor/bin/phpcs --standard=PSR12 app/ tests/
vendor/bin/deptrac analyze
vendor/bin/pest --coverage --min=80
```

## Production Deployment

Kubernetes deployment baseline is under [`helm/yttscrb`](helm/yttscrb). Secrets must be provided through Kubernetes Secrets, not ConfigMaps.
