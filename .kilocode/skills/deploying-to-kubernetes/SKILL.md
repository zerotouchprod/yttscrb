---
name: deploying-to-kubernetes
description: Use when deploying yttscrb to the K3s Kubernetes cluster, building Docker images, pushing to GHCR, upgrading Helm releases, or troubleshooting deployment failures like CrashLoopBackOff or secret misconfiguration
---

# Deploying to Kubernetes

## Overview

Deploy the yttscrb Laravel application to the K3s cluster at `root@154.47.146.43` using Docker build → GHCR push → Helm upgrade. This skill covers the full cycle and all known pitfalls.

## When to Use

- Deploying new code from `main` branch to production (`tubesum.app`)
- Rolling back a broken deployment
- Debugging `CrashLoopBackOff`, `ImagePullBackOff`, or secret-related startup failures
- Rotating API keys or updating secrets

**Do NOT use for:** local development (`docker compose up`), code changes, or testing.

## Infrastructure Reference

| Resource | Value |
|----------|-------|
| SSH Host | `root@154.47.146.43` |
| K3s Kubeconfig | `KUBECONFIG=/etc/rancher/k3s/k3s.yaml` |
| Namespace | `prod` |
| Helm Release | `yttscrb` |
| Helm Chart | `~/apps/yttscrb/helm/yttscrb` |
| Image | `ghcr.io/zerotouchprod/yttscrb` |
| Ingress | `tubesum.app` (Traefik) |
| Git Remote | `git@github.com:zerotouchprod/yttscrb.git` |

## Quick Reference

```bash
# Full deploy (build + push + helm)
ssh root@154.47.146.43 'cd ~/apps/yttscrb && ./deploy.sh'

# Deploy existing tag only
ssh root@154.47.146.43 'cd ~/apps/yttscrb && ./deploy.sh --tag 47f9503'

# Helm rollback
ssh root@154.47.146.43 'KUBECONFIG=/etc/rancher/k3s/k3s.yaml helm rollback yttscrb -n prod'

# Check pod status
ssh root@154.47.146.43 'KUBECONFIG=/etc/rancher/k3s/k3s.yaml kubectl get pods -n prod | grep yttscrb'

# View logs
ssh root@154.47.146.43 'KUBECONFIG=/etc/rancher/k3s/k3s.yaml kubectl logs -n prod deployment/yttscrb-app --tail=50'

# Health check
curl -s -o /dev/null -w '%{http_code}' https://tubesum.app/
```

## Core Pattern: Manual Deploy Without deploy.sh

When `deploy.sh` is unavailable, use this sequence:

```bash
ssh root@154.47.146.43
cd ~/apps/yttscrb
git pull origin main
TAG=$(git rev-parse --short HEAD)

docker build -f Dockerfile.dev -t ghcr.io/zerotouchprod/yttscrb:$TAG .
docker push ghcr.io/zerotouchprod/yttscrb:$TAG
docker push ghcr.io/zerotouchprod/yttscrb:latest

export KUBECONFIG=/etc/rancher/k3s/k3s.yaml
cd helm/yttscrb
helm dep update
helm upgrade yttscrb . -n prod --set image.tag=$TAG --timeout 5m --wait

kubectl rollout status deployment/yttscrb-app -n prod --timeout=120s
```

## Secret Management

Secrets live in a Kubernetes Secret (`yttscrb-secrets` in `prod`), NOT in ConfigMap or `.env`.

### Required Keys

| Key | Source/Format | Critical Rule |
|-----|---------------|---------------|
| `APP_KEY` | `php artisan key:generate --show` | Must be valid 32-byte base64 key |
| `DB_PASSWORD` | Must match `values.yaml` → `postgresql.auth.password` | Currently `secret` |
| `REDIS_PASSWORD` | Must be empty `""` when `redis.auth.enabled: false` | **Empty, not a placeholder** |
| `GROQ_API_KEY` | Groq Console | Starts with `gsk_` |
| `OPENAI_API_KEY` | OpenAI Platform | Starts with `sk-` |

### Create/Update Secret

```bash
kubectl create secret generic yttscrb-secrets -n prod \
  --from-literal=APP_KEY="$(php artisan key:generate --show)" \
  --from-literal=DB_PASSWORD="secret" \
  --from-literal=REDIS_PASSWORD="" \
  --from-literal=GROQ_API_KEY="gsk_..." \
  --from-literal=OPENAI_API_KEY="sk-..." \
  --dry-run=client -o yaml | kubectl replace --force -f -
```

After updating secrets, restart deployments:
```bash
kubectl rollout restart deployment/yttscrb-app -n prod
kubectl rollout restart deployment/yttscrb-horizon -n prod
kubectl rollout restart deployment/yttscrb-worker -n prod
```

## Common Mistakes

### 1. PLACEHOLDER SECRETS IN PRODUCTION

**Symptom:** `CrashLoopBackOff` with `SQLSTATE[08006] password authentication failed` or `ERR AUTH <password> called without any password configured`

**Root cause:** Secret values are `replace-me` or other placeholders instead of real credentials.

**Fix:** Recreate the secret with real values. Check the actual DB password:
```bash
kubectl get secret yttscrb-postgresql -n prod -o jsonpath='{.data.password}' | base64 -d
```

### 2. INVALID APP_KEY LENGTH

**Symptom:** HTTP 500 with `Unsupported cipher or incorrect key length. Supported ciphers are: aes-128-cbc, aes-256-cbc`

**Root cause:** `APP_KEY` is not a valid 32-byte base64-encoded string. The `base64:` prefix is required.

**Fix:** Generate a proper key:
```bash
docker run --rm ghcr.io/zerotouchprod/yttscrb:latest php artisan key:generate --show
```

### 3. REDIS AUTH WITH DISABLED AUTH

**Symptom:** `RedisException: ERR AUTH <password> called without any password configured`

**Root cause:** `REDIS_PASSWORD` is set to a non-empty value (e.g., `replace-me`) but `redis.auth.enabled: false` in `values.yaml`.

**Fix:** Set `REDIS_PASSWORD=""` (empty) in the secret. Do NOT set it to any non-empty value.

### 4. DB_PASSWORD MISMATCH

**Symptom:** `FATAL: password authentication failed for user "yttscrb"`

**Root cause:** `DB_PASSWORD` in the app secret does not match the PostgreSQL password set by the Bitnami subchart (`postgresql.auth.password` in `values.yaml`).

**Fix:** Ensure `DB_PASSWORD` in the secret equals `postgresql.auth.password` from `values.yaml` (currently `secret`).

### 5. FORGETTING export KUBECONFIG

**Symptom:** `helm list` returns "Kubernetes cluster unreachable" or `connection refused` on `localhost:8080`

**Fix:** Always `export KUBECONFIG=/etc/rancher/k3s/k3s.yaml` before kubectl/helm commands.

## Verification Checklist

After every deploy, verify:

```bash
# 1. All pods Running (not CrashLoopBackOff, not ImagePullBackOff)
kubectl get pods -n prod | grep yttscrb

# 2. Main page returns 200
curl -s -o /dev/null -w '%{http_code}' https://tubesum.app/

# 3. API returns 200
curl -s -o /dev/null -w '%{http_code}' https://tubesum.app/api/history

# 4. No ERROR in recent logs
kubectl logs -n prod deployment/yttscrb-app --tail=20 | grep -c ERROR
```

## Troubleshooting Flowchart

```
Pod status?
├─ CrashLoopBackOff → Check logs (kubectl logs)
│   ├─ "password authentication failed" → Secret DB_PASSWORD mismatch
│   ├─ "ERR AUTH" (Redis) → REDIS_PASSWORD not empty
│   └─ "Unsupported cipher" → Invalid APP_KEY
├─ ImagePullBackOff → Check ghcr-creds secret exists in namespace
├─ ErrImagePull → Check image tag exists on ghcr.io
└─ Running but 500 → Check APP_KEY validity, run health check curl
```
