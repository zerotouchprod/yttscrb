---
name: tubesumapp-prod-fix
description: Use when tubesum.app production is broken, returning errors, or not processing YouTube links. Covers SSH access, Kubernetes diagnostics, log inspection, endpoint verification, and common failure patterns (yt-dlp, secrets, pods, Sentry, stuck workflows).
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
│   sentry-cli issues list --org 4510580181827584 --project 4510580205879376 --status unresolved
│   OR: https://sentry.io → project "yttscrb"
│
└─ Step 5: Verify secrets
    ssh root@154.47.146.43 'kubectl get secret yttscrb-secrets -n prod -o jsonpath="{.data}" | python3 -c "import sys,json,base64; d=json.load(sys.stdin); [print(f\"{k}: present\") if v else print(f\"{k}: empty\") for k,v in d.items()]"'
```

## Common Failure Patterns

### 1. Groq API "operation aborted by callback" (HTTP 0, errno 42)

**Symptom:** `RuntimeException: Groq API request failed (HTTP 0, errno 42): operation aborted by callback`

**Root cause:** `CURLFile` points to a non-existent file. In PHP 8.5, curl returns `CURLE_ABORTED_BY_CALLBACK` (errno 42) instead of `CURLE_READ_ERROR` (26) or `CURLE_FILE_COULDNT_READ_FILE` (37) when the file doesn't exist.

**Why file is missing:** The audio file was downloaded to `/tmp` on one worker pod. During deploy/pod restart, `/tmp` is wiped. The durable-workflow resumes `GroqTranscriberActivity` on a new pod, but the file is gone.

**Diagnose from inside pod:**
```bash
# Test curl with non-existent file — should return errno 42
kubectl exec -n prod deployment/yttscrb-worker -- php -r "
\$ch = curl_init();
\$postFields = ['file' => new CURLFile('/tmp/nonexistent.mp3'), 'model' => 'whisper-large-v3-turbo'];
curl_setopt(\$ch, CURLOPT_URL, 'https://api.groq.com/openai/v1/audio/transcriptions');
curl_setopt(\$ch, CURLOPT_POSTFIELDS, \$postFields);
curl_setopt(\$ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.getenv('GROQ_API_KEY')]);
curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
curl_exec(\$ch);
echo 'errno=' . curl_errno(\$ch) . ' error=' . curl_error(\$ch) . "\n";
"
```

**Fix:**
1. Add `file_exists()` check BEFORE creating `CURLFile` — yields clear error instead of confusing errno 42
2. Include `curl_errno()` in error message for diagnostics
3. Make `GroqTranscriberActivity` re-download audio if file is missing (pass `$youtubeUrl` alongside `$audioPath`)
4. Use persistent storage (`storage/app/temp`) instead of `/tmp`

**Code pattern:**
```php
// In GroqWhisperAdapter::doRequest()
if (! file_exists($audioPath) || ! is_readable($audioPath)) {
    throw new RuntimeException("Audio file not found: $audioPath");
}

// In GroqTranscriberActivity::execute()
if (! file_exists($audioPath) && $youtubeUrl !== null) {
    $extractor = Container::getInstance()->make(AudioExtractorInterface::class);
    $audioFile = $extractor->extract(new YouTubeUrl($youtubeUrl));
    $audioPath = $audioFile->path();
}
```

### 2. Stuck durable-workflow (infinite retry loop)

**Symptom:** Same error keeps appearing in Sentry every 2 minutes, workflow never completes or fails. Events keep accumulating (150+ events).

**Root cause:** The `Activity` class has `$tries = PHP_INT_MAX` and `$maxExceptions = PHP_INT_MAX`, causing infinite retries. When a workflow argument signature changes, old in-flight workflows can't use new features (e.g., re-download logic).

**Diagnose:**
```bash
# Find stuck workflows (status = 'waiting', created hours ago)
kubectl exec -n prod deployment/yttscrb-app -- php artisan tinker --execute="
\$stuck = DB::table('workflows')->where('status', '!=', 'completed')
    ->where('status', '!=', 'failed')
    ->where('created_at', '<', now()->subHours(1))
    ->get();
foreach (\$stuck as \$w) echo \"ID=\$w->id status=\$w->status class=\$w->class\n\";
"

# Check recent exceptions
kubectl exec -n prod deployment/yttscrb-app -- php artisan tinker --execute="
\$e = DB::table('workflow_exceptions')->orderBy('id','desc')->limit(10)->get();
foreach (\$e as \$ex) echo \"wf=\$ex->stored_workflow_id class=\$ex->class at \$ex->created_at\n\";
"

# Find Redis unique job locks preventing cleanup
kubectl exec -n prod deployment/yttscrb-app -- php artisan tinker --execute="
\$redis = app()->make('redis');
\$keys = \$redis->keys('*GroqTranscriberActivity*');
echo json_encode(\$keys);
"
```

**Kill stuck workflows (full cleanup):**
```bash
# Step 1: Delete from DB (respecting FK order)
kubectl exec -n prod deployment/yttscrb-app -- php artisan tinker --execute="
\$ids = [104, 108]; // workflow IDs to kill
DB::table('workflow_signals')->whereIn('stored_workflow_id', \$ids)->delete();
DB::table('workflow_timers')->whereIn('stored_workflow_id', \$ids)->delete();
DB::table('workflow_exceptions')->whereIn('stored_workflow_id', \$ids)->delete();
DB::table('workflow_logs')->whereIn('stored_workflow_id', \$ids)->delete();
DB::table('workflow_relationships')->where(function(\$q) use (\$ids) {
    \$q->whereIn('parent_workflow_id', \$ids)->orWhereIn('child_workflow_id', \$ids);
})->delete();
DB::table('workflows')->whereIn('id', \$ids)->delete();
"

# Step 2: Clear Redis unique job locks
kubectl exec -n prod deployment/yttscrb-app -- php artisan tinker --execute="
\$redis = app()->make('redis');
foreach (\$redis->keys('*GroqTranscriber*') as \$k) \$redis->del(\$k);
"

# Step 3: Restart workers to clear in-memory state
kubectl rollout restart deployment/yttscrb-horizon -n prod
kubectl rollout restart deployment/yttscrb-worker -n prod
```

### 3. Activity signature change breaks in-flight workflows

**Symptom:** `ArgumentCountError: Too few arguments to function execute(), 1 passed and exactly 2 expected`

**Root cause:** Adding a required parameter to an Activity's `execute()` method. Old workflows store activity arguments when dispatched; when resumed after deploy, they call `execute()` with old arguments.

**Fix:** Make new parameters optional with `= null` defaults:
```php
// BEFORE (breaks old workflows)
public function execute(string $audioPath, string $youtubeUrl): Result

// AFTER (backward compatible)
public function execute(string $audioPath, ?string $youtubeUrl = null): Result
```

### 4. yt-dlp: output file not found

**Symptom:** `RuntimeException: yt-dlp completed but output file not found`

**Root cause:** `--print filename` flag in `YoutubeDlAudioExtractor` prevents actual file download. Fixed in commit `5114463` by removing the flag.

**Check:** File exists at `app/Infrastructure/Adapters/Output/YoutubeDl/YoutubeDlAudioExtractor.php` and has NO `--print filename` in the command.

### 5. yt-dlp: JavaScript runtime warning

**Symptom:** `WARNING: [youtube] No supported JavaScript runtime could be found`

**Impact:** Non-critical. yt-dlp falls back to android VR API. Some formats may be missing but audio extraction still works.

### 6. Pod CrashLoopBackOff

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

### 7. Secrets misconfiguration

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

### 8. Workflow stuck / not processing

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

## Curl Diagnostics (in-pod testing)

Run PHP curl tests directly inside the worker pod to isolate network/API issues:

```bash
# Test Groq API connectivity
kubectl exec -n prod deployment/yttscrb-worker -- php -r "
\$ch = curl_init('https://api.groq.com/openai/v1/models');
curl_setopt(\$ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt(\$ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer '.getenv('GROQ_API_KEY')]);
curl_setopt(\$ch, CURLOPT_TIMEOUT, 10);
\$r = curl_exec(\$ch);
echo 'errno='.curl_errno(\$ch).' error='.curl_error(\$ch).' http='.curl_getinfo(\$ch, CURLINFO_HTTP_CODE).\"\n\";
"

# Test multipart upload with CURLFile
kubectl exec -n prod deployment/yttscrb-worker -- php -r "
file_put_contents('/tmp/test.mp3', str_repeat('x', 1024));
\$ch = curl_init();
curl_setopt_array(\$ch, [
    CURLOPT_URL => 'https://api.groq.com/openai/v1/audio/transcriptions',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['model' => 'whisper-large-v3-turbo', 'file' => new CURLFile('/tmp/test.mp3')],
    CURLOPT_HTTPHEADER => ['Authorization: Bearer '.getenv('GROQ_API_KEY')],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
\$r = curl_exec(\$ch);
echo 'errno='.curl_errno(\$ch).' error='.curl_error(\$ch).' http='.curl_getinfo(\$ch, CURLINFO_HTTP_CODE).\"\n\";
unlink('/tmp/test.mp3');
"

# Test pcntl_alarm interaction with curl
kubectl exec -n prod deployment/yttscrb-worker -- php -r "
pcntl_alarm(960); // simulate workflow heartbeat
file_put_contents('/tmp/test.mp3', str_repeat('x', 1024));
\$ch = curl_init();
curl_setopt_array(\$ch, [
    CURLOPT_URL => 'https://api.groq.com/openai/v1/audio/transcriptions',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => ['model' => 'whisper-large-v3-turbo', 'file' => new CURLFile('/tmp/test.mp3')],
    CURLOPT_HTTPHEADER => ['Authorization: Bearer '.getenv('GROQ_API_KEY')],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
]);
\$r = curl_exec(\$ch);
echo 'errno='.curl_errno(\$ch).' error='.curl_error(\$ch).' http='.curl_getinfo(\$ch, CURLINFO_HTTP_CODE).\"\n\";
unlink('/tmp/test.mp3');
"
```

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

# Database: check PHP version and extensions on worker
kubectl exec -n prod deployment/yttscrb-worker -- php -r "
echo 'PHP: '.PHP_VERSION.\"\n\";
echo 'CURLOPT_NOSIGNAL='.(defined('CURLOPT_NOSIGNAL')?CURLOPT_NOSIGNAL:'NOT DEFINED').\"\n\";
"
```

## Sentry Integration

Sentry catches unhandled exceptions automatically. Check `https://sentry.io` for project "yttscrb".

**SENTRY_DSN is in Kubernetes secret** — if missing, add it:
```bash
kubectl patch secret yttscrb-secrets -n prod \
  -p "{\"data\":{\"SENTRY_DSN\":\"$(echo -n 'https://...@....ingest.de.sentry.io/...' | base64 -w0)\"}}"
kubectl rollout restart deployment/yttscrb-app deployment/yttscrb-horizon deployment/yttscrb-worker -n prod
```

## Durable Workflow Database Tables Reference

| Table | Purpose |
|-------|---------|
| `workflows` | Main workflow state (status, class, arguments) |
| `workflow_logs` | Activity execution log (index, class, result) |
| `workflow_exceptions` | Exception records for failed activities |
| `workflow_signals` | Pending signals for workflows |
| `workflow_timers` | Pending timers for workflows |
| `workflow_relationships` | Parent-child workflow relationships |

**Key Redis keys:**
- `laravel_unique_job:<ActivityClass>:<workflowId>:<index>` — ShouldBeUnique locks
- `laravel-workflow-overlap:<workflowId>:activity` — Overlap prevention locks
