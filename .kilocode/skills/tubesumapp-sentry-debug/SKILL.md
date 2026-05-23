---
name: tubesumapp-sentry-debug
description: Use when diagnosing production errors via Sentry API for tubesum.app. Covers API token setup, issue listing, event inspection, log correlation, and the full debug-and-fix workflow from Sentry alert to code fix.
---

# Sentry Debug & Fix Workflow for tubesum.app

## Overview

Use Sentry API to pull production errors from `tubesum.app`, correlate with Kubernetes logs, and drive bug fixes. Project: `php-laravel` (org `4510580181827584`, project `4510580205879376`).

**REQUIRED BACKGROUND:** You MUST understand [tubesumapp-prod-fix](.kilocode/skills/tubesumapp-prod-fix/SKILL.md) before using this skill.

## Sentry API Access

### Auth Token

DSN key (from `yttscrb-secrets`) only sends events — does NOT read. For API read access, use an **Auth Token**:

```
Token: <YOUR_SENTRY_AUTH_TOKEN>
Scope: project:read, event:read, issue:read, member:read
```

Create new tokens at: `https://sentry.io/settings/account/api/auth-tokens/`

### Quick Reference

```bash
TOKEN="sntryu_..."
ORG="4510580181827584"
PROJ="4510580205879376"
BASE="https://sentry.io/api/0"
```

## Command Recipes

### 1. List Recent Issues (Top 5)

```bash
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/projects/$ORG/$PROJ/issues/?limit=5&sort=date&statsPeriod=24h" | \
  python3 -c "
import sys,json
for i in json.load(sys.stdin):
    print(f\"[{i['status']:12s}] {i['title'][:100]}\")
    print(f\"  events={i['count']}  first={i.get('firstSeen','')[:19]}  last={i.get('lastSeen','')[:19]}\")
    print(f\"  level={i.get('level','?')}  culprit={i.get('culprit','')[:80]}\")
    print()
"
```

### 2. Filter Issues by Query

```bash
# By error type
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/projects/$ORG/$PROJ/issues/?limit=10&query=RuntimeException&statsPeriod=7d" | \
  python3 -c "import sys,json; [print(f\"[{i['status']}] {i['title'][:130]}\") for i in json.load(sys.stdin)]"

# By time range (use &start=...&end=... ISO8601)
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/projects/$ORG/$PROJ/issues/?limit=5&statsPeriod=24h&query=is:unresolved" | \
  python3 -c "import sys,json; [print(f\"events={i['count']:3d}  {i['title'][:120]}\") for i in json.load(sys.stdin)]"
```

### 3. Get Issue Details (Full Stacktrace)

```bash
ISSUE_ID="122116236"  # From issue list

# Issue metadata
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/issues/$ISSUE_ID/" | python3 -m json.tool | head -40

# Last event with full exception
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/issues/$ISSUE_ID/events/?limit=1" | \
  python3 -c "
import sys,json
events=json.load(sys.stdin)
if not events: print('No events'); sys.exit()
e=events[0]
print(f\"Time: {e.get('dateCreated','')[:19]}\")
for entry in e.get('entries',[]):
    if entry['type']!='exception': continue
    for exc in entry['data'].get('values',[]):
        print(f\"\\n{exc['type']}: {exc.get('value','')[:200]}\")
        for frame in exc.get('stacktrace',{}).get('frames',[])[-5:]:
            fname = frame.get('filename','?')
            line = frame.get('lineNo','?')
            func = frame.get('function','?')
            print(f\"  {fname}:{line}  {func}()\")
"
```

### 4. Correlate Sentry → Kubernetes Logs

```bash
# Get error message from Sentry
ERROR_MSG=$(curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/issues/$ISSUE_ID/" | python3 -c "import sys,json;print(json.load(sys.stdin)['title'])")

# Search for it in pod logs
ssh root@154.47.146.43 "kubectl logs -n prod deployment/yttscrb-worker --tail=500 | grep -A5 '$(echo $ERROR_MSG | cut -c1-60)'"

# Or search across all yttscrb pods
ssh root@154.47.146.43 "
  for dep in yttscrb-app yttscrb-horizon yttscrb-worker; do
    echo \"=== \$dep ===\"
    kubectl logs -n prod deployment/\$dep --tail=200 | grep -i '$ERROR_MSG' | tail -5
  done
"
```

### 5. Issue Statistics

```bash
# Count by status
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/projects/$ORG/$PROJ/issues/?limit=100&statsPeriod=7d" | \
  python3 -c "
import sys,json
from collections import Counter
issues=json.load(sys.stdin)
statuses=Counter(i['status'] for i in issues)
levels=Counter(i.get('level','?') for i in issues)
print(f\"Total: {len(issues)} issues (7d)\")
print(f\"By status: {dict(statuses)}\")
print(f\"By level: {dict(levels)}\")
"
```

## Debug-and-Fix Workflow

```
Sentry alert / user report
│
├─ Step 1: Pull issues from Sentry
│   curl Sentry API → list unresolved issues
│   Identify: title, count, first/last seen, culprit file
│
├─ Step 2: Get stacktrace from Sentry
│   curl Sentry API → event details
│   Extract: exception type, message, file:line, function
│
├─ Step 3: Correlate with Kubernetes logs
│   ssh to server → grep worker logs for same error
│   Get: full context, preceding events, data values
│
├─ Step 4: Root cause analysis
│   Is it a code bug? → Fix in code
│   Is it external (geo-block, rate limit, API down)? → Handle gracefully
│   Is it data (duplicate, null)? → Add validation/upsert
│
├─ Step 5: Fix
│   Write fix → composer check → commit → push → deploy
│
└─ Step 6: Verify
    curl Sentry API → confirm issue count stops growing
    curl tubesum.app → test fixed endpoint
```

## Common Sentry Queries for tubesum.app

| Query | Purpose |
|-------|---------|
| `is:unresolved` | Active issues only |
| `RuntimeException` | yt-dlp and extraction failures |
| `UniqueConstraintViolation` | Database duplicate key errors |
| `RedisException` | Redis connection issues |
| `has:stacktrace` | Issues with full stack traces |
| `statsPeriod:24h` | Last 24 hours only |
| `statsPeriod:7d` | Last 7 days |

## Real Examples from tubesum.app

### Example 1: yt-dlp geo-block (RuntimeException)

```bash
# Get all yt-dlp failures
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/projects/$ORG/$PROJ/issues/?limit=10&query=yt-dlp&statsPeriod=24h" | \
  python3 -c "
import sys,json
for i in json.load(sys.stdin):
    print(f\"events={i['count']}  {i['title'][:130]}\")
"
```

Then SSH to check the actual YouTube error:
```bash
ssh root@154.47.146.43 "kubectl logs -n prod deployment/yttscrb-worker --tail=200 | grep -A1 'exit code 1'"
```

### Example 2: UniqueConstraintViolation

```bash
# Get duplicate key violations
curl -s -H "Authorization: Bearer $TOKEN" \
  "$BASE/projects/$ORG/$PROJ/issues/?limit=5&query=UniqueConstraint&statsPeriod=24h" | \
  python3 -c "import sys,json; [print(f\"events={i['count']}  {i['title'][:130]}\") for i in json.load(sys.stdin)]"
```

Then check which video_id is causing the conflict:
```bash
ssh root@154.47.146.43 "kubectl logs -n prod deployment/yttscrb-worker --tail=300 | grep -oP 'Key \(video_id\)=\(\K[^)]+' | tail -5"
```

## Token Permissions Reference

| Scope | Needed For |
|-------|------------|
| `project:read` | List projects, get project info |
| `issue:read` | List issues, get issue details |
| `event:read` | Get event details, stacktraces |
| `member:read` | Organization member info (rarely needed) |
| `org:read` | List organizations (rarely needed) |

Minimum for debug workflow: `project:read`, `issue:read`, `event:read`

## Tips

1. **Always use `python3 -m json.tool` or inline parser** — Sentry returns large JSON, pipe through Python for readability
2. **Combine with `kubectl logs`** — Sentry shows stacktrace, but Kubernetes logs show the actual error message with data values
3. **Check `statsPeriod`** — default issue list shows all time, add `?statsPeriod=24h` for recent
4. **Issue ID is stable** — same issue type gets same ID, use for tracking fix progress (count should stop growing after fix)
5. **Token in env** — store token in shell variable, never commit to repo
