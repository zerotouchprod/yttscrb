# YTTSCRB

YouTube Transcriber & Summarizer Micro-SaaS.

The source of truth for product and architecture is [`Prd.md`](Prd.md). Agent rules are in [`AGENTS.md`](AGENTS.md).

## Current Status

Laravel 13 / PHP 8.5 application for local YouTube transcription and summarization. Current v1 flow is public: paste a YouTube URL, wait for async processing, then view transcript, summary, history, latest item, and download TXT.

## Local Development

```bash
composer install
cp .env.example .env
php artisan key:generate
docker compose up --build -d
docker compose exec -T app php artisan migrate --force
```

Then open:

- App: `http://localhost:8080`
- Horizon: `http://localhost:8080/horizon`
- Waterline: `http://localhost:8080/waterline`

Before starting, fill these values in `.env`:

```env
APP_KEY=
GROQ_API_KEY=
OPENAI_API_KEY=
```

Required local services are defined in [`docker-compose.yml`](docker-compose.yml): `app`, `postgres`, `redis`, `horizon`, and `worker`.

### Local usage flow

1. Open `http://localhost:8080`
2. Paste a public YouTube URL
3. Wait for status to move from `pending` / `processing` to `completed`
4. Read summary and transcript
5. Download transcript as TXT if needed

### Notes

- v1 is public: no registration and no `X-API-Key` for the app API.
- `worker` uses `php artisan queue:work redis --queue=default` for background processing.
- `waterline` is embedded in the app, not a separate Docker service.

## Quality Gates

```bash
vendor/bin/phpstan analyse --level=9 --memory-limit=512M
vendor/bin/phpcs --standard=PSR12 app/ tests/
vendor/bin/deptrac analyze
vendor/bin/pest --coverage --min=80
```

## Production Deployment

Kubernetes deployment baseline is under [`helm/yttscrb`](helm/yttscrb). Secrets must be provided through Kubernetes Secrets, not ConfigMaps.
