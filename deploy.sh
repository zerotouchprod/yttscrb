#!/usr/bin/env bash
#
# deploy.sh — Build, push & deploy yttscrb to K3s cluster via Helm
#
# Prerequisites on the deployment host:
#   - Docker (logged into ghcr.io)
#   - kubectl (KUBECONFIG defaults to /etc/rancher/k3s/k3s.yaml)
#   - Helm 3.x
#   - Git clone of this repo at ~/apps/yttscrb
#
# Environment variables for first deploy (secret auto-creation):
#   YTSCRB_GROQ_API_KEY    Groq API key (starts with gsk_)
#   YTSCRB_OPENAI_API_KEY  OpenAI API key (starts with sk-)
#
# Usage:
#   ./deploy.sh                         # Build from HEAD, push, deploy
#   ./deploy.sh --tag 47f9503           # Deploy an already-pushed tag
#   KUBECONFIG=/path ./deploy.sh        # Use custom kubeconfig
#
# Troubleshooting:
#   - "context deadline exceeded" → check KUBECONFIG & cluster health
#   - "password authentication failed" → DB_PASSWORD mismatch between secret and values.yaml
#   - "ERR AUTH" → REDIS_PASSWORD in secret but Redis auth is disabled
#   - "Unsupported cipher" → APP_KEY in secret is not valid base64:... format
#

set -euo pipefail

# ── Configuration ──────────────────────────────────────────────
REPO_DIR="${REPO_DIR:-$HOME/apps/yttscrb}"
NAMESPACE="prod"
RELEASE="yttscrb"
IMAGE="ghcr.io/zerotouchprod/yttscrb"
KUBECONFIG="${KUBECONFIG:-/etc/rancher/k3s/k3s.yaml}"
HELM_CHART="${REPO_DIR}/helm/yttscrb"
SECRET_NAME="yttscrb-secrets"

# Ensure KUBECONFIG is exported for kubectl + helm subprocesses
export KUBECONFIG

# ── Pre-flight checks ─────────────────────────────────────────
check_cmd() {
    command -v "$1" >/dev/null 2>&1 || {
        echo "ERROR: '$1' is required but not found" >&2
        exit 1
    }
}

check_cmd docker
check_cmd kubectl
check_cmd helm
check_cmd git

cd "$REPO_DIR"

# ── Determine tag & build image ───────────────────────────────
SKIP_PUSH=false
if [[ "${1:-}" == --tag ]]; then
    TAG="${2:-}"
    if [[ -z "$TAG" ]]; then
        echo "ERROR: --tag requires a value" >&2
        exit 1
    fi
    SKIP_PUSH=true
    echo "=== Using existing tag: $TAG ==="
else
    git pull origin main
    TAG=$(git rev-parse --short HEAD)
    echo "=== Building from commit: $TAG ==="

    echo "=== Building Docker image ==="
    docker build -f Dockerfile.dev \
        -t "$IMAGE:$TAG" \
        -t "$IMAGE:latest" \
        .
fi

# ── Create or validate Kubernetes secret ──────────────────────
create_secret_if_missing() {
    if kubectl get secret "$SECRET_NAME" -n "$NAMESPACE" >/dev/null 2>&1; then
        echo "=== Secret '$SECRET_NAME' found ==="
        return 0
    fi

    echo "=== Secret '$SECRET_NAME' not found in namespace '$NAMESPACE' — creating... ==="

    # API keys from environment (both required for first deploy)
    local groq_key="${YTSCRB_GROQ_API_KEY:-${GROQ_API_KEY:-}}"
    local openai_key="${YTSCRB_OPENAI_API_KEY:-${OPENAI_API_KEY:-}}"

    local missing_vars=()
    if [[ -z "$groq_key" ]]; then
        missing_vars+=("YTSCRB_GROQ_API_KEY  (Groq API key, starts with gsk_)")
    fi
    if [[ -z "$openai_key" ]]; then
        missing_vars+=("YTSCRB_OPENAI_API_KEY  (OpenAI API key, starts with sk-)")
    fi

    if [[ ${#missing_vars[@]} -gt 0 ]]; then
        echo "ERROR: Cannot create secret — missing environment variables:" >&2
        for var in "${missing_vars[@]}"; do
            echo "  $var" >&2
        done
        echo "" >&2
        echo "Set them and re-run:" >&2
        echo "  export YTSCRB_GROQ_API_KEY=gsk_..." >&2
        echo "  export YTSCRB_OPENAI_API_KEY=sk-..." >&2
        echo "  ./deploy.sh" >&2
        return 1
    fi

    # Generate APP_KEY from the freshly built Docker image
    echo "Generating APP_KEY from Docker image..."
    local app_key
    app_key=$(docker run --rm "$IMAGE:$TAG" php artisan key:generate --show 2>/dev/null) || true

    if [[ -z "$app_key" ]] || [[ ! "$app_key" =~ ^base64: ]]; then
        echo "ERROR: Failed to generate valid APP_KEY from Docker image." >&2
        echo "Raw output: ${app_key:-<empty>}" >&2
        echo "Make sure the image builds correctly and 'php artisan key:generate --show' works." >&2
        return 1
    fi

    echo "  APP_KEY: ${app_key:0:30}... (generated)"

    # DB_PASSWORD must match postgresql.auth.password in values.yaml
    local db_password="secret"
    echo "  DB_PASSWORD: ${db_password} (from values.yaml)"

    # Create the secret (do NOT add REDIS_PASSWORD — Redis auth is disabled)
    kubectl create secret generic "$SECRET_NAME" -n "$NAMESPACE" \
        --from-literal=APP_KEY="$app_key" \
        --from-literal=DB_PASSWORD="$db_password" \
        --from-literal=GROQ_API_KEY="$groq_key" \
        --from-literal=OPENAI_API_KEY="$openai_key" \
        --dry-run=client -o yaml | kubectl apply -f -

    echo "=== Secret '$SECRET_NAME' created successfully ==="
}

create_secret_if_missing

# ── Validate secret contents ──────────────────────────────────
echo "=== Validating secret '$SECRET_NAME' ==="

# Validate APP_KEY format (must be base64:... that decodes to exactly 32 bytes)
APP_KEY=$(kubectl get secret "$SECRET_NAME" -n "$NAMESPACE" -o jsonpath="{.data.APP_KEY}" 2>/dev/null | base64 -d)
if [[ ! "$APP_KEY" =~ ^base64: ]]; then
    echo "ERROR: APP_KEY in secret '$SECRET_NAME' does not start with 'base64:'." >&2
    echo "Current value: ${APP_KEY:0:30}..." >&2
    echo "Regenerate with: php artisan key:generate --show" >&2
    exit 1
fi
KEY_BYTES=$(echo "${APP_KEY#base64:}" | base64 -d 2>/dev/null | wc -c)
if [[ "$KEY_BYTES" -ne 32 ]]; then
    echo "ERROR: APP_KEY base64-decodes to $KEY_BYTES bytes, expected 32." >&2
    echo "Regenerate with: php artisan key:generate --show" >&2
    exit 1
fi
echo "  APP_KEY: valid (32 bytes)"

# Validate DB_PASSWORD exists
DB_PW=$(kubectl get secret "$SECRET_NAME" -n "$NAMESPACE" -o jsonpath="{.data.DB_PASSWORD}" 2>/dev/null | base64 -d)
if [[ -z "$DB_PW" ]]; then
    echo "ERROR: DB_PASSWORD key is missing or empty in secret '$SECRET_NAME'." >&2
    exit 1
fi
echo "  DB_PASSWORD: present"

# Validate GROQ_API_KEY exists and looks plausible
GROQ_K=$(kubectl get secret "$SECRET_NAME" -n "$NAMESPACE" -o jsonpath="{.data.GROQ_API_KEY}" 2>/dev/null | base64 -d)
if [[ -z "$GROQ_K" ]]; then
    echo "ERROR: GROQ_API_KEY key is missing or empty in secret '$SECRET_NAME'." >&2
    exit 1
fi
echo "  GROQ_API_KEY: present (${#GROQ_K} chars, starts with ${GROQ_K:0:4})"

# Validate OPENAI_API_KEY exists and looks plausible
OPENAI_K=$(kubectl get secret "$SECRET_NAME" -n "$NAMESPACE" -o jsonpath="{.data.OPENAI_API_KEY}" 2>/dev/null | base64 -d)
if [[ -z "$OPENAI_K" ]]; then
    echo "ERROR: OPENAI_API_KEY key is missing or empty in secret '$SECRET_NAME'." >&2
    exit 1
fi
echo "  OPENAI_API_KEY: present (${#OPENAI_K} chars, starts with ${OPENAI_K:0:3})"

# Warn about REDIS_PASSWORD presence (Redis auth is disabled)
if kubectl get secret "$SECRET_NAME" -n "$NAMESPACE" -o jsonpath="{.data.REDIS_PASSWORD}" >/dev/null 2>&1; then
    REDIS_PW=$(kubectl get secret "$SECRET_NAME" -n "$NAMESPACE" -o jsonpath="{.data.REDIS_PASSWORD}" 2>/dev/null | base64 -d)
    if [[ -n "$REDIS_PW" ]]; then
        echo "  WARNING: REDIS_PASSWORD is set to a non-empty value — Redis auth is disabled, this will break Horizon/Worker."
        echo "  Remove with: kubectl patch secret $SECRET_NAME -n $NAMESPACE --type json -p='[{\"op\":\"remove\",\"path\":\"/data/REDIS_PASSWORD\"}]'"
    fi
fi

echo "  Secret validation: OK"

# ── Push to GHCR (skip if --tag used) ─────────────────────────
if [[ "$SKIP_PUSH" == "false" ]]; then
    echo "=== Pushing to GHCR ==="
    docker push "$IMAGE:$TAG"
    docker push "$IMAGE:latest"
else
    echo "=== Skipping push (--tag mode) ==="
fi

# ── Helm deploy ────────────────────────────────────────────────
echo "=== Updating Helm dependencies ==="
helm dep update "$HELM_CHART"

echo "=== Upgrading Helm release '$RELEASE' in namespace '$NAMESPACE' ==="
helm upgrade "$RELEASE" "$HELM_CHART" \
    --namespace "$NAMESPACE" \
    --set image.tag="$TAG" \
    --timeout 5m \
    --wait

# ── Verify rollout ─────────────────────────────────────────────
echo "=== Checking rollout status ==="
kubectl rollout status "deployment/${RELEASE}-app" -n "$NAMESPACE" --timeout=120s
kubectl rollout status "deployment/${RELEASE}-horizon" -n "$NAMESPACE" --timeout=120s
kubectl rollout status "deployment/${RELEASE}-worker" -n "$NAMESPACE" --timeout=120s

# ── Quick health check ─────────────────────────────────────────
echo "=== Health check ==="
HTTP_MAIN=$(curl -s -o /dev/null -w '%{http_code}' https://tubesum.app/ 2>/dev/null || echo "ERR")
HTTP_API=$(curl -s -o /dev/null -w '%{http_code}' https://tubesum.app/api/history 2>/dev/null || echo "ERR")

echo "Main page: $HTTP_MAIN"
echo "API:       $HTTP_API"

if [[ "$HTTP_MAIN" == "200" && "$HTTP_API" == "200" ]]; then
    echo "=== Deploy SUCCESS ==="
else
    echo "=== WARNING: health check returned unexpected status ==="
fi

# ── Pod summary ────────────────────────────────────────────────
echo ""
echo "=== Pod status ==="
kubectl get pods -n "$NAMESPACE" | grep "$RELEASE"
