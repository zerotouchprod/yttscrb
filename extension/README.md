# TubeSum Chrome Extension

AI-powered YouTube summarization — right on the video page.

## Development

```bash
npm install
npm run dev    # HMR development mode
npm run build  # Production build → dist/
```

## Install (development)

1. Open `chrome://extensions`
2. Enable "Developer mode"
3. Click "Load unpacked" → select `dist/` folder

---

## Deployment

### Quick deploy (build + ZIP)

```bash
./deploy.sh --zip
```

This builds the extension and creates `tubesum-extension-v1.0.0.zip` ready for manual upload to [Chrome Web Store Developer Dashboard](https://chrome.google.com/webstore/devconsole).

### Bump version + build + ZIP

```bash
./deploy.sh --bump patch    # 1.0.0 → 1.0.1
./deploy.sh --bump minor    # 1.0.0 → 1.1.0
./deploy.sh --bump major    # 1.0.0 → 2.0.0
```

This bumps the version in `manifest.json`, `package.json` and `Popup.vue`, then builds and zips.

### Full auto-deploy to Chrome Web Store

```bash
# Set credentials (one-time)
export CHROME_CLIENT_ID="your-google-oauth2-client-id.apps.googleusercontent.com"
export CHROME_CLIENT_SECRET="GOCSPX-..."
export CHROME_REFRESH_TOKEN="1//..."
export CHROME_EXTENSION_ID="aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"

# Deploy
./deploy.sh --bump patch --publish
```

This bumps version, builds, uploads to Chrome Web Store, and publishes.

### Setting up Chrome Web Store API credentials

1. Go to [Google Cloud Console → APIs & Services → Credentials](https://console.cloud.google.com/apis/credentials)
2. Create an **OAuth 2.0 Client ID** (Desktop application type)
3. Enable the **Chrome Web Store API** in [API Library](https://console.cloud.google.com/apis/library/chromewebstore.googleapis.com)
4. Get a refresh token:

   **Step A:** Open in browser, authorize, copy the `code` from the redirect URL:
   ```
   https://accounts.google.com/o/oauth2/auth?client_id=YOUR_CLIENT_ID&redirect_uri=urn:ietf:wg:oauth:2.0:oob&response_type=code&scope=https://www.googleapis.com/auth/chromewebstore
   ```

   **Step B:** Exchange code for refresh token:
   ```bash
   curl -s -X POST https://oauth2.googleapis.com/token \
     -d "client_id=YOUR_CLIENT_ID" \
     -d "client_secret=YOUR_CLIENT_SECRET" \
     -d "code=CODE_FROM_STEP_A" \
     -d "grant_type=authorization_code" \
     -d "redirect_uri=urn:ietf:wg:oauth:2.0:oob"
   ```
   Save the `refresh_token` from the response.

5. Get your **Extension ID** from the [Chrome Web Store Developer Dashboard](https://chrome.google.com/webstore/devconsole) (32-character string).
6. Store credentials securely — never commit them to the repository. Use environment variables or a `.env` file (gitignored).

### Manual upload (without API)

1. Run `./deploy.sh --zip`
2. Go to [Chrome Web Store Developer Dashboard](https://chrome.google.com/webstore/devconsole)
3. Click your extension → **Package** → **Upload new package**
4. Select `tubesum-extension-v*.zip`
5. Click **Submit for review**

---

## Architecture

- **Content Script**: Injects "✨ TubeSum" button into YouTube action bar, handles SPA navigation
- **Background Service Worker**: Proxies API calls to tubesum.app, polls async task status
- **Popup (Vue 3)**: Shows summary results — key points, clickbait verdict, CTA to full transcript

## API

Uses tubesum.app REST API:
- `POST /api/transcribe` — create transcription task
- `GET /api/transcribe/{id}` — poll task status
