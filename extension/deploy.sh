#!/usr/bin/env bash
set -euo pipefail

# =============================================================================
# TubeSum Chrome Extension — Deploy Script
# =============================================================================
#
# Usage:
#   ./deploy.sh                    # Build only (→ dist/)
#   ./deploy.sh --zip              # Build + create .zip for manual upload
#   ./deploy.sh --publish          # Build + zip + upload to Chrome Web Store
#   ./deploy.sh --bump patch       # Bump version (patch|minor|major), build, zip
#   ./deploy.sh --bump minor --publish
#
# Environment variables (for --publish):
#   CHROME_CLIENT_ID       Google Cloud OAuth2 Client ID
#   CHROME_CLIENT_SECRET   Google Cloud OAuth2 Client Secret
#   CHROME_REFRESH_TOKEN   Chrome Web Store API refresh token
#   CHROME_EXTENSION_ID    32-char Chrome Web Store extension ID
#
# First-time setup for Chrome Web Store API:
#   1. Go to https://console.cloud.google.com/apis/credentials
#   2. Create OAuth2 Client ID (Desktop application)
#   3. Enable "Chrome Web Store API" in API Library
#   4. Get refresh token:
#      https://accounts.google.com/o/oauth2/auth?client_id=...&redirect_uri=urn:ietf:wg:oauth:2.0:oob&response_type=code&scope=https://www.googleapis.com/auth/chromewebstore
#   5. Exchange code for refresh token:
#      curl -d "client_id=...&client_secret=...&code=...&grant_type=authorization_code&redirect_uri=urn:ietf:wg:oauth:2.0:oob" https://oauth2.googleapis.com/token
# =============================================================================

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$SCRIPT_DIR"

VERSION=$(node -p "require('./manifest.json').version")
ZIP_NAME="tubesum-extension-v${VERSION}.zip"
BUILD_DIR="dist"

# ---- Flags ----
DO_ZIP=false
DO_PUBLISH=false
BUMP_TYPE=""

for arg in "$@"; do
  case $arg in
    --zip) DO_ZIP=true ;;
    --publish) DO_PUBLISH=true; DO_ZIP=true ;;
    --bump)
      shift
      BUMP_TYPE="${1:-}"
      if [[ ! "$BUMP_TYPE" =~ ^(patch|minor|major)$ ]]; then
        echo "ERROR: --bump requires one of: patch, minor, major"
        exit 1
      fi
      ;;
  esac
  shift 2>/dev/null || true
done

# ---- 1. Bump version (if requested) ----
if [[ -n "$BUMP_TYPE" ]]; then
  echo "[1/5] Bumping version ($BUMP_TYPE)..."
  OLD_VERSION="$VERSION"
  node -e "
    const [major, minor, patch] = '${OLD_VERSION}'.split('.').map(Number);
    let newVersion;
    switch ('${BUMP_TYPE}') {
      case 'major': newVersion = \`\${major + 1}.0.0\`; break;
      case 'minor': newVersion = \`\${major}.\${minor + 1}.0\`; break;
      case 'patch': newVersion = \`\${major}.\${minor}.\${patch + 1}\`; break;
    }
    const fs = require('fs');
    const manifest = JSON.parse(fs.readFileSync('manifest.json', 'utf8'));
    const pkg = JSON.parse(fs.readFileSync('package.json', 'utf8'));
    manifest.version = newVersion;
    pkg.version = newVersion;
    fs.writeFileSync('manifest.json', JSON.stringify(manifest, null, 2) + '\n');
    fs.writeFileSync('package.json', JSON.stringify(pkg, null, 2) + '\n');
    console.log(newVersion);
  "
  VERSION=$(node -p "require('./manifest.json').version")
  ZIP_NAME="tubesum-extension-v${VERSION}.zip"
  echo "   Version: $OLD_VERSION → $VERSION"

  # Update version in Popup.vue
  sed -i "s/v[0-9]\+\.[0-9]\+\.[0-9]\+/v${VERSION}/g" src/popup/Popup.vue
fi

# ---- 2. Install dependencies ----
echo "[2/5] Installing dependencies..."
npm install --silent

# ---- 3. Build ----
echo "[3/5] Building extension..."
npm run build

# ---- 4. Create ZIP ----
if $DO_ZIP; then
  echo "[4/5] Creating ZIP archive: ${ZIP_NAME}..."
  rm -f "$ZIP_NAME"
  cd "$BUILD_DIR"
  zip -r "../${ZIP_NAME}" . -x "*.map" > /dev/null
  cd ..
  ZIP_SIZE=$(du -h "$ZIP_NAME" | cut -f1)
  echo "   Created: ${ZIP_NAME} (${ZIP_SIZE})"
fi

# ---- 5. Publish to Chrome Web Store ----
if $DO_PUBLISH; then
  echo "[5/5] Publishing to Chrome Web Store..."

  # Validate credentials
  REQUIRED_VARS=(CHROME_CLIENT_ID CHROME_CLIENT_SECRET CHROME_REFRESH_TOKEN CHROME_EXTENSION_ID)
  MISSING=()
  for var in "${REQUIRED_VARS[@]}"; do
    if [[ -z "${!var:-}" ]]; then
      MISSING+=("$var")
    fi
  done
  if [[ ${#MISSING[@]} -gt 0 ]]; then
    echo "ERROR: Missing environment variables: ${MISSING[*]}"
    echo "Set them in .env file or export them before running."
    exit 1
  fi

  # Get access token
  echo "   Getting OAuth2 access token..."
  ACCESS_TOKEN=$(curl -s -X POST https://oauth2.googleapis.com/token \
    -d "client_id=${CHROME_CLIENT_ID}" \
    -d "client_secret=${CHROME_CLIENT_SECRET}" \
    -d "refresh_token=${CHROME_REFRESH_TOKEN}" \
    -d "grant_type=refresh_token" \
    -d "redirect_uri=urn:ietf:wg:oauth:2.0:oob" | grep -o '"access_token":"[^"]*"' | cut -d'"' -f4)

  if [[ -z "$ACCESS_TOKEN" ]]; then
    echo "ERROR: Failed to obtain access token. Check your credentials."
    exit 1
  fi

  # Upload extension
  echo "   Uploading extension (ID: ${CHROME_EXTENSION_ID})..."
  UPLOAD_RESPONSE=$(curl -s -X PUT \
    -H "Authorization: Bearer ${ACCESS_TOKEN}" \
    -H "x-goog-api-version: 2" \
    -T "$ZIP_NAME" \
    "https://www.googleapis.com/upload/chromewebstore/v1.1/items/${CHROME_EXTENSION_ID}")

  UPLOAD_STATUS=$(echo "$UPLOAD_RESPONSE" | grep -o '"uploadState":"[^"]*"' | cut -d'"' -f4)

  if [[ "$UPLOAD_STATUS" == "SUCCESS" ]]; then
    echo "   Upload successful!"

    # Publish (make publicly available)
    echo "   Publishing extension..."
    PUBLISH_RESPONSE=$(curl -s -X POST \
      -H "Authorization: Bearer ${ACCESS_TOKEN}" \
      -H "x-goog-api-version: 2" \
      -H "Content-Length: 0" \
      "https://www.googleapis.com/chromewebstore/v1.1/items/${CHROME_EXTENSION_ID}/publish")

    PUBLISH_STATUS=$(echo "$PUBLISH_RESPONSE" | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
    echo "   Publish status: ${PUBLISH_STATUS}"

    if [[ "$PUBLISH_STATUS" == "OK" ]]; then
      echo "   ✓ Extension v${VERSION} published to Chrome Web Store!"
    else
      echo "   ⚠ Published but status unclear (may be under review)."
    fi
  else
    echo "   ERROR: Upload failed."
    echo "   Response: $UPLOAD_RESPONSE"
    exit 1
  fi
fi

# ---- Summary ----
echo ""
echo "==========================================="
echo "  TubeSum Extension v${VERSION} — Deploy Summary"
echo "==========================================="
echo "  Build dir:  ${BUILD_DIR}/"
if $DO_ZIP; then
  echo "  ZIP:        ${ZIP_NAME}"
fi
if $DO_PUBLISH; then
  echo "  Published:  Chrome Web Store"
fi
echo "  Done."
echo ""
echo "Next steps for manual upload:"
echo "  1. Go to https://chrome.google.com/webstore/devconsole"
echo "  2. Upload ${ZIP_NAME}"
echo "  3. Submit for review"
echo "==========================================="
