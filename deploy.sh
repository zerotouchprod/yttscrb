#!/usr/bin/env bash
#
# deploy.sh — Build, push & deploy yttscrb to K3s cluster via Helm
#
# Prerequisites on the deployment host:
#   - Docker (logged into ghcr.io)
#   - kubectl (with KUBECONFIG=/etc/rancher/k3s/k3s.yaml or equivalent)
#   - Helm 3.x
#   - Git clone of this repo at ~/apps/yttscrb
#
# Usage:
#   ./deploy.sh                    # Build from HEAD, push, deploy
#   ./deploy.sh --tag 47f9503      # Deploy an already-pushed tag
#

set -euo pipefail

# ── Configuration ──────────────────────────────────────────────
REPO_DIR="${REPO_DIR:-$HOME/apps/yttscrb}"
NAMESPACE="prod"
RELEASE="yttscrb"
IMAGE="ghcr.io/zerotouchprod/yttscrb"
KUBECONFIG="${KUBECONFIG:-/etc/rancher/k3s/k3s.yaml}"
HELM_CHART="${REPO_DIR}/helm/yttscrb"

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
