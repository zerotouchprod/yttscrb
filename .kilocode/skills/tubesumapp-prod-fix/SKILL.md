---
name: tubesumapp-prod-fix
description: Use when tubesum.app production is broken, returning errors, or not processing YouTube links. Covers SSH access, Kubernetes diagnostics, log inspection, endpoint verification, and common failure patterns (yt-dlp, secrets, pods, Sentry).
---

# tubesum.app Production Diagnostics & Fix

## Overview

Systematic approach to diagnose and fix production issues on `tubesum.app` (K3s cluster at `root@154.47.146.43`, namespace `prod`, Helm release `yttscrb`).

**REQUIRED BACKGROUND:** You MUST understand [deploying-to-kubernetes](.kilocode/skills/deploying-to-kubernetes/SKILL.md) before using this skill.

**See also:** [tubesumapp-sentry-debug](.kilocode/skills/tubesumapp-sentry-debug/SKILL.md) for Sentry API queries and error correlation.

## Infrastructure Reference

| Resource | Value |
|----------|-------|
| SSH | `root@154.47.146.43` |
| Kubeconfig | `KUBECONFIG=/etc/rancher/k3s/k3s.yaml` |
| Namespace | `prod` |
| Repo | `~/apps/yttscrb` |
| Domain | `https://tubesum.app` |
| Sentry DSN | In `yttscrb-secrets` secret |

## Endpoint Checklist

All endpoints should return HTTP 200 (except POST which returns 200/201/409):

```bash
# GET endpoints (should all be 200)
curl -s -o /dev/null -w '%{http_code}' https://tubesum.app/
curl -s -o /dev/null -w '%{http_code}' https://tubesum.app/up
curl -s -o /dev/null -w '%{http_code}' https://tubesum.app/api/history
curl -s -o /dev/null -w '%{http_code}' https://tubesum.app/api/history/latest
curl -s -o /dev/null -w '%{http_code}' 'https://tubesum.app/api/search?q=test'
curl -s -o /dev/null -w '%{http_code}' https://tubesum.app/api/docs/openapi.json

# POST transcribe (functional test)
curl -s -X POST https://tubesum.app/api/transcribe \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"youtube_url":"https://www.youtube.com/watch?v=dQw4w9WgXcQ"}'
```

## Diagnostic Flow

```
User reports: "not working"
│
├─ Step 1: Check pod health
│   ssh root@154.47.146.43 'kubectl get pods -n prod | grep yttscrb'
│   Expected: 4+ pods all Running (app×2, horizon×1, worker×1)
│
├─ Step 2: Check worker logs (most failures are here)
│   ssh root@154.47.146.43 'kubectl logs -n prod deployment/yttscrb-worker --tail=50'
│   Look for: RuntimeException, ERROR, FAIL
│
├─ Step 3: Test transcribe endpoint directly
│   curl -s -X POST https://tubesum.app/api/transcribe ...
│   Check response: task_id + status field
│
├─ Step 4: Check Sentry (if configured)
│   https://sentry.io → project "yttscrb"
│
└─ Step 5: Verify secrets
    ssh root@154.47.146.43 'kubectl get secret yttscrb-secrets -n prod -o jsonpath="{.data}" | python3 -c "import sys,json,base64; d=json.load(sys.stdin); [print(f\"{k}: present\") if v else print(f\"{k}: empty\") for k,v in d.items()]"'
```

## Common Failure Patterns

### 1. yt-dlp: output file not found

**Symptom:** `RuntimeException: yt-dlp completed but output file not found`

**Root cause:** `--print filename` flag in `YoutubeDlAudioExtractor` prevents actual file download. Fixed in commit `5114463` by removing the flag.

**Check:** File exists at `app/Infrastructure/Adapters/Output/YoutubeDl/YoutubeDlAudioExtractor.php` and has NO `--print filename` in the command.

### 2. yt-dlp: JavaScript runtime warning

**Symptom:** `WARNING: [youtube] No supported JavaScript runtime could be found`

**Impact:** Non-critical. yt-dlp falls back to android VR API. Some formats may be missing but audio extraction still works.

### 3. Pod CrashLoopBackOff

**Symptom:** Pods not starting, `CrashLoopBackOff` status

**Diagnose:**
```bash
kubectl logs -n prod deployment/yttscrb-app --tail=50
kubectl describe pod -n prod <pod-name>
```

**Common causes:**
- `password authentication failed` → DB_PASSWORD mismatch between secret and PostgreSQL
- `ERR AUTH` → REDIS_PASSWORD set when Redis auth is disabled
- `Unsupported cipher` → Invalid APP_KEY format (must be `base64:...`)

### 4. Secrets misconfiguration

**Check all required secret keys:**
```bash
kubectl get secret yttscrb-secrets -n prod -o jsonpath='{.data}' | \
  python3 -c "
import sys,json,base64
d=json.load(sys.stdin)
required=['APP_KEY','DB_PASSWORD','GROQ_API_KEY','OPENAI_API_KEY','DEEPSEEK_API_KEY','SENTRY_DSN']
for k in required:
    v = base64.b64decode(d.get(k,'')).decode()
    print(f'{k}: {\"EMPTY!\" if not v else \"present (\"+str(len(v))+\" chars)\"}')"
```

### 5. Workflow stuck / not processing

**Symptom:** Transcribe accepted but never completes

**Diagnose:**
```bash
# Check Horizon dashboard
kubectl port-forward -n prod deployment/yttscrb-horizon 8080:80
# Then open http://localhost:8080/horizon

# Check worker logs for activity
kubectl logs -n prod deployment/yttscrb-worker --tail=100 | grep -E '(RUNNING|FAIL|ERROR)'
```

**Common causes:**
- Worker pod not connected to Redis
- Durable workflow queue backed up
- API keys exhausted (Groq/OpenAI rate limits)

## Quick Fix Commands

```bash
# Full redeploy (build + push + helm)
ssh root@154.47.146.43 'cd ~/apps/yttscrb && ./deploy.sh'

# Restart all yttscrb pods (no rebuild)
ssh root@154.47.146.43 'kubectl rollout restart deployment/yttscrb-app deployment/yttscrb-horizon deployment/yttscrb-worker -n prod'

# View recent errors in all pods
ssh root@154.47.146.43 'kubectl logs -n prod deployment/yttscrb-worker --tail=30 | grep -i error; kubectl logs -n prod deployment/yttscrb-app --tail=30 | grep -i error'

# Check secret values (masked)
ssh root@154.47.146.43 'kubectl get secret yttscrb-secrets -n prod -o jsonpath="{.data.APP_KEY}" | base64 -d | cut -c1-20'

# Helm rollback to previous revision
ssh root@154.47.146.43 'KUBECONFIG=/etc/rancher/k3s/k3s.yaml helm rollback yttscrb -n prod'

# Helm history
ssh root@154.47.146.43 'KUBECONFIG=/etc/rancher/k3s/k3s.yaml helm history yttscrb -n prod'
```

## Sentry Integration

Sentry catches unhandled exceptions automatically. Check `https://sentry.io` for project "yttscrb".

**SENTRY_DSN is in Kubernetes secret** — if missing, add it:
```bash
kubectl patch secret yttscrb-secrets -n prod \
  -p "{\"data\":{\"SENTRY_DSN\":\"$(echo -n 'https://...@....ingest.de.sentry.io/...' | base64 -w0)\"}}"
kubectl rollout restart deployment/yttscrb-app deployment/yttscrb-horizon deployment/yttscrb-worker -n prod
```
