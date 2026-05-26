// Content script: injects "✨ TubeSum" button into YouTube's action bar.
// Handles SPA navigation via yt-navigate-finish event.

const BUTTON_ID = 'tubesum-inject-button';
const TOAST_ID = 'tubesum-toast';

// === Button Injection ===

function findActionBar(): Element | null {
  // YouTube's action bar is below the video player, contains Like/Share buttons.
  // Selector: the flex container below #top-level-buttons-computed
  const topLevelButtons = document.querySelector('#top-level-buttons-computed');
  return topLevelButtons?.parentElement ?? null;
}

function createTubeSumButton(): HTMLButtonElement {
  const btn = document.createElement('button');
  btn.id = BUTTON_ID;
  btn.className = 'tubesum-btn';
  btn.innerHTML = '✨ TubeSum';
  btn.title = 'Summarize this video with AI';

  // Inline styles (no Tailwind build in content script — use raw CSS)
  btn.style.cssText = `
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 0 16px;
    height: 36px;
    font-size: 14px;
    font-weight: 500;
    font-family: 'YouTube Sans', 'Roboto', sans-serif;
    color: #fff;
    background: linear-gradient(135deg, #6366f1, #8b5cf6);
    border: none;
    border-radius: 9999px;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.1s;
    white-space: nowrap;
  `;

  btn.addEventListener('mouseenter', () => {
    btn.style.opacity = '0.9';
    btn.style.transform = 'scale(1.02)';
  });
  btn.addEventListener('mouseleave', () => {
    btn.style.opacity = '1';
    btn.style.transform = 'scale(1)';
  });

  btn.addEventListener('click', onTubeSumClick);
  return btn;
}

function injectButton(): void {
  // Remove existing button if present (prevents duplicates on SPA navigation)
  document.getElementById(BUTTON_ID)?.remove();

  const actionBar = findActionBar();
  if (!actionBar) {
    // Retry after a short delay — YouTube DOM may still be rendering
    setTimeout(injectButton, 1000);
    return;
  }

  const btn = createTubeSumButton();
  // Insert as first child of the action bar (before Like/Share)
  actionBar.insertBefore(btn, actionBar.firstChild);
}

// === Toast Notification ===

function showToast(message: string, isError = false): void {
  removeToast();

  const toast = document.createElement('div');
  toast.id = TOAST_ID;
  toast.textContent = message;
  toast.style.cssText = `
    position: fixed;
    bottom: 24px;
    left: 50%;
    transform: translateX(-50%);
    padding: 12px 24px;
    border-radius: 12px;
    font-family: 'YouTube Sans', 'Roboto', sans-serif;
    font-size: 14px;
    font-weight: 500;
    color: #fff;
    background: ${isError ? '#dc2626' : '#1e293b'};
    box-shadow: 0 4px 24px rgba(0,0,0,0.5);
    z-index: 99999;
    animation: tubesum-fade-in 0.3s ease;
  `;

  document.body.appendChild(toast);

  // Auto-remove after 5 seconds
  setTimeout(removeToast, 5000);
}

function removeToast(): void {
  document.getElementById(TOAST_ID)?.remove();
}

// Add fade-in animation
const styleSheet = document.createElement('style');
styleSheet.textContent = `
  @keyframes tubesum-fade-in {
    from { opacity: 0; transform: translateX(-50%) translateY(10px); }
    to { opacity: 1; transform: translateX(-50%) translateY(0); }
  }
`;
document.head.appendChild(styleSheet);

// === Click Handler ===

function getCurrentVideoUrl(): string {
  return window.location.href;
}

async function onTubeSumClick(): Promise<void> {
  const url = getCurrentVideoUrl();

  if (!url.includes('watch?v=')) {
    showToast('Could not detect YouTube video URL.', true);
    return;
  }

  showToast('Summarizing with AI...');

  try {
    const response = await chrome.runtime.sendMessage({
      type: 'SUMMARIZE_REQUEST',
      youtubeUrl: url,
    });

    if (response.type === 'SUMMARIZE_SUCCESS') {
      const publicPage = response.data._links?.public_page;
      if (publicPage) {
        showToast('Done! Opening full transcript...');
        // Open the full transcript page in a new tab
        window.open(`https://tubesum.app${publicPage}`, '_blank');
      } else {
        showToast('Summary complete! Open the extension popup to view.');
      }
    } else if (response.type === 'SUMMARIZE_ERROR') {
      showToast(response.error, true);
    }
  } catch (err) {
    showToast('Failed to connect. Please try again.', true);
    console.error('[TubeSum]', err);
  }
}

// Listen for progress messages from background worker
chrome.runtime.onMessage.addListener((message) => {
  if (message.type === 'SUMMARIZE_PROGRESS') {
    showToast(`AI is working... (${message.status})`);
  }
});

// === SPA Navigation Handling ===

// YouTube fires 'yt-navigate-finish' when navigating between videos without page reload.
document.addEventListener('yt-navigate-finish', () => {
  console.log('[TubeSum] SPA navigation detected, re-injecting button.');
  injectButton();
});

// === Initial Injection ===

// Use MutationObserver as a fallback for initial load (before yt-navigate-finish fires)
const observer = new MutationObserver(() => {
  if (!document.getElementById(BUTTON_ID) && findActionBar()) {
    injectButton();
  }
});

observer.observe(document.body, {
  childList: true,
  subtree: true,
});

// Also try immediate injection
if (document.readyState === 'complete' || document.readyState === 'interactive') {
  injectButton();
} else {
  document.addEventListener('DOMContentLoaded', injectButton);
}

console.log('[TubeSum] Content script loaded.');
