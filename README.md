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

### Infrastructure Summary

| Component | Location |
|-----------|----------|
| K3s Cluster | `root@154.47.146.43` (single-node) |
| Kubeconfig | `/etc/rancher/k3s/k3s.yaml` |
| Helm Release | `yttscrb` in namespace `prod` |
| Ingress | Traefik (`tubesum.app`) |
| Image Registry | `ghcr.io/zerotouchprod/yttscrb` |
| Source Repo | `git@github.com:zerotouchprod/yttscrb.git` (on server: `~/apps/yttscrb`) |

### Prerequisites (on deployment host)

- Docker logged into `ghcr.io` (`docker login ghcr.io`)
- `kubectl` with `KUBECONFIG=/etc/rancher/k3s/k3s.yaml`
- Helm 3.x with Bitnami repo: `helm repo add bitnami https://charts.bitnami.com/bitnami`
- Git SSH access to `github.com:zerotouchprod/yttscrb`

### Kubernetes Secrets

Create the secret **before** first deploy or when rotating keys:

```bash
kubectl create secret generic yttscrb-secrets -n prod \
  --from-literal=APP_KEY="$(php artisan key:generate --show)" \
  --from-literal=DB_PASSWORD="secret" \
  --from-literal=REDIS_PASSWORD="" \
  --from-literal=GROQ_API_KEY="gsk_..." \
  --from-literal=OPENAI_API_KEY="sk-..."
```

Notes:
- `DB_PASSWORD` must match [`values.yaml`](helm/yttscrb/values.yaml) → `postgresql.auth.password`
- `REDIS_PASSWORD` must be empty because Redis auth is disabled (`redis.auth.enabled: false`)
- `APP_KEY` must be a valid Laravel 32-byte base64 key (use `php artisan key:generate --show`)
- Template: [`helm/yttscrb/templates/secrets.example.yaml`](helm/yttscrb/templates/secrets.example.yaml)

### GHCR Image Pull Secret

```bash
kubectl create secret docker-registry ghcr-creds -n prod \
  --docker-server=ghcr.io \
  --docker-username=YOUR_GITHUB_USER \
  --docker-password=YOUR_GH_PAT
```

### Deploy

Use the automated script [`deploy.sh`](deploy.sh):

```bash
# Full build + push + deploy (from HEAD):
./deploy.sh

# Deploy an already-pushed image tag:
./deploy.sh --tag 47f9503
```

Or manual Helm:

```bash
cd ~/apps/yttscrb
git pull origin main
TAG=$(git rev-parse --short HEAD)

docker build -f Dockerfile.dev -t ghcr.io/zerotouchprod/yttscrb:$TAG .
docker push ghcr.io/zerotouchprod/yttscrb:$TAG

cd helm/yttscrb
helm dep update
helm upgrade yttscrb . -n prod --set image.tag=$TAG --wait
```

### Verify

```bash
curl -s -o /dev/null -w '%{http_code}' https://tubesum.app/        # → 200
curl -s -o /dev/null -w '%{http_code}' https://tubesum.app/api/history  # → 200
kubectl get pods -n prod | grep yttscrb  # All Running
```

### Rollback

```bash
helm rollback yttscrb -n prod
```

Secrets must be provided through Kubernetes Secrets, not ConfigMaps.
