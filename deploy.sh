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

# ── Verify Kubernetes secret & APP_KEY ─────────────────────────
SECRET_NAME="yttscrb-secrets"
echo "=== Checking Kubernetes secret '$SECRET_NAME' in namespace '$NAMESPACE' ==="

if ! kubectl get secret "$SECRET_NAME" -n "$NAMESPACE" >/dev/null 2>&1; then
    echo "ERROR: Secret '$SECRET_NAME' not found in namespace '$NAMESPACE'." >&2
    echo "" >&2
    echo "Create it with:" >&2
    echo "  kubectl create secret generic $SECRET_NAME -n $NAMESPACE \\" >&2
    echo "    --from-literal=APP_KEY=\"base64:\$(php artisan key:generate --show | cut -d: -f2-)\" \\" >&2
    echo "    --from-literal=DB_PASSWORD=\"secret\" \\" >&2
    echo "    --from-literal=GROQ_API_KEY=\"<your-key>\" \\" >&2
    echo "    --from-literal=OPENAI_API_KEY=\"<your-key>\"" >&2
    echo "" >&2
    echo "Do NOT add REDIS_PASSWORD — Redis auth is disabled (redis.auth.enabled=false)." >&2
    exit 1
fi

# Validate APP_KEY format (must be base64:... that decodes to exactly 32 bytes)
APP_KEY=$(kubectl get secret "$SECRET_NAME" -n "$NAMESPACE" -o jsonpath="{.data.APP_KEY}" | base64 -d)
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
echo "  APP_KEY: valid"

# Warn about REDIS_PASSWORD presence (Redis auth is disabled)
if kubectl get secret "$SECRET_NAME" -n "$NAMESPACE" -o jsonpath="{.data.REDIS_PASSWORD}" >/dev/null 2>&1; then
    echo "  WARNING: REDIS_PASSWORD key found in secret — Redis auth is disabled, this will break Horizon/Worker."
    echo "  Remove with: kubectl patch secret $SECRET_NAME -n $NAMESPACE --type json -p='[{\"op\":\"remove\",\"path\":\"/data/REDIS_PASSWORD\"}]'"
fi

echo "  OK"

# ── Determine tag ──────────────────────────────────────────────
if [[ "${1:-}" == --tag ]]; then
    TAG="${2:-}"
    if [[ -z "$TAG" ]]; then
        echo "ERROR: --tag requires a value" >&2
        exit 1
    fi
    echo "=== Using existing tag: $TAG ==="
else
    git pull origin main
    TAG=$(git rev-parse --short HEAD)
    echo "=== Building from commit: $TAG ==="

    # ── Build Docker image ─────────────────────────────────────
    echo "=== Building Docker image ==="
    docker build -f Dockerfile.dev \
        -t "$IMAGE:$TAG" \
        -t "$IMAGE:latest" \
        .

    # ── Push to GitHub Container Registry ──────────────────────
    echo "=== Pushing to GHCR ==="
    docker push "$IMAGE:$TAG"
    docker push "$IMAGE:latest"
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
