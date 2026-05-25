# SEO + Virality Sprint — Implementation Plan

> **Date:** 2026-05-25
> **Status:** Ready for Implementation
> **Context:** Growth-focused sprint. SEO foundation is ~80% done. This plan closes the remaining gaps and adds viral distribution features.

---

## Audit: What Is Already Done

### SEO Foundation ✅ Fully Implemented

| Component | Status | File |
|-----------|--------|------|
| `spatie/laravel-sitemap` | ✅ Installed | `composer.json` |
| `GenerateSitemapCommand` | ✅ Implemented | `app/Infrastructure/Console/Commands/GenerateSitemapCommand.php` |
| Sitemap scheduler (daily 03:00 UTC) | ✅ Scheduled | `routes/console.php` |
| `robots.txt` with `Sitemap:` directive | ✅ Done | `public/robots.txt` |
| `Disallow` for `/api/`, `/horizon/`, `/waterline/` | ✅ Done | `public/robots.txt` |
| SEO `<title>` on transcript pages | ✅ Done | `resources/views/transcript.blade.php` |
| Meta `description` + `canonical` | ✅ Done | `resources/views/transcript.blade.php` |
| `robots: index, follow` on public pages | ✅ Done | All public Blade views |
| Open Graph tags (type/title/description/url/site_name) | ✅ Done | `resources/views/transcript.blade.php` |
| Twitter Card (`summary_large_image`, title, description) | ✅ Done | `resources/views/transcript.blade.php` |
| JSON-LD Structured Data | ⚠️ Partial — `Article` type, not `VideoObject` | `resources/views/transcript.blade.php` |

### Critical Gaps

| Gap | Impact | Priority |
|-----|--------|----------|
| `og:image` / `twitter:image` absent | Social share cards are blank → zero virality on share | 🔴 Critical |
| JSON-LD `@type: Article` instead of `VideoObject` | No rich snippets in Google search (no thumbnail, duration, embed preview) | 🔴 Critical |
| OG Image Generator not implemented | Cannot generate branded share images | 🟡 Medium |

---

## Phase 1: SEO Completion (Day 1)

### Task 1.1 — Schema.org `VideoObject` + `og:image` Tag

**What:** Replace `@type: Article` with `@type: VideoObject` in JSON-LD. Add `og:image` and `twitter:image` pointing to YouTube's thumbnail (zero infrastructure, immediate effect).

**File:** `resources/views/transcript.blade.php`

**Changes:**

1. Replace JSON-LD block:

```blade
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "VideoObject",
    "name": {{ Js::from($task->title() . ' — Transcript & AI Summary') }},
    "description": {{ Js::from($metaDescription) }},
    "url": {{ Js::from($canonicalUrl) }},
    "thumbnailUrl": "https://img.youtube.com/vi/{{ $task->videoId() }}/maxresdefault.jpg",
    "uploadDate": "{{ $task->completedAt()?->format('c') }}",
    "duration": "{{ $iso8601Duration }}",
    "embedUrl": "https://www.youtube.com/embed/{{ $task->videoId() }}",
    "publisher": {
        "@type": "Organization",
        "name": "TubeSum",
        "url": {{ Js::from(url('/')) }}
    },
    "mainEntityOfPage": {
        "@type": "WebPage",
        "@id": {{ Js::from($canonicalUrl) }}
    }
}
</script>
```

2. Add `og:image` and `twitter:image` to the `<head>`:

```blade
<meta property="og:image" content="https://img.youtube.com/vi/{{ $task->videoId() }}/maxresdefault.jpg">
<meta property="og:image:width" content="1280">
<meta property="og:image:height" content="720">
<meta name="twitter:image" content="https://img.youtube.com/vi/{{ $task->videoId() }}/maxresdefault.jpg">
```

**Controller change (`PublicTranscriptController`):**

```php
// Convert duration_sec to ISO 8601 (e.g. 754 → "PT12M34S")
$durationSec = $task->durationSec() ?? 0;
$iso8601Duration = sprintf(
    'PT%dM%dS',
    intdiv($durationSec, 60),
    $durationSec % 60,
);

return view('transcript', [
    // ...existing fields...
    'iso8601Duration' => $iso8601Duration,
]);
```

**Result:** Google rich snippets show thumbnail + duration in search. Twitter/Telegram share cards show YouTube thumbnail immediately — no extra infrastructure needed.

---

### Task 1.2 — OG Image Generator (Branded Share Cards)

**What:** Generate a branded OG image by compositing text over the YouTube thumbnail using `intervention/image` v3 (GD driver — no Node.js, no Puppeteer, no headless Chrome).

**Architecture:**

```
GET /og/{task_id}.jpg
    → OgImageController
        → MediaTaskRepositoryInterface::findById()
        → Download YouTube thumbnail (HTTP, cached to storage/app/og/thumbs/)
        → Compose: thumbnail + semi-transparent gradient + title text + "TubeSum" watermark
        → Cache result to storage/app/og/{task_id}.jpg (permanent, no TTL)
        → Serve as image/jpeg
```

**Files to create/modify:**

| # | File | Action |
|---|------|--------|
| 1 | `app/Infrastructure/Adapters/Input/Web/OgImageController.php` | **Create** |
| 2 | `routes/web.php` | **Modify** — add `GET /og/{taskId}.jpg` |
| 3 | `resources/views/transcript.blade.php` | **Modify** — replace YouTube thumbnail URL with `/og/{task_id}.jpg` in `og:image` |

**OgImageController logic:**

```php
final class OgImageController
{
    public function __construct(
        private readonly MediaTaskRepositoryInterface $repository,
    ) {}

    public function __invoke(string $taskId): Response
    {
        $task = $this->repository->findById($taskId);

        if ($task === null || $task->status() !== TranscriptionStatus::Completed) {
            abort(404);
        }

        $cachePath = storage_path('app/og/' . $taskId . '.jpg');

        if (!file_exists($cachePath)) {
            $this->generate($task, $cachePath);
        }

        return response()->file($cachePath, ['Content-Type' => 'image/jpeg']);
    }

    private function generate(MediaTask $task, string $targetPath): void
    {
        // 1. Download YouTube thumbnail
        $thumbUrl = 'https://img.youtube.com/vi/' . $task->videoId()?->value() . '/mqdefault.jpg';
        $thumbData = file_get_contents($thumbUrl);

        // 2. Compose image via intervention/image v3
        $manager = new ImageManager(new GdDriver());
        $image = $manager->read($thumbData);
        $image->scale(width: 1200); // OG standard width

        // 3. Semi-transparent gradient overlay (bottom 40%)
        $overlayHeight = (int) ($image->height() * 0.45);
        $overlay = $manager->create($image->width(), $overlayHeight);
        $overlay->fill('rgba(0,0,0,0.72)');
        $image->place($overlay, 'bottom-left', 0, 0);

        // 4. Title text
        $title = mb_strimwidth($task->title() ?? '', 0, 80, '…');
        $image->text($title, 40, $image->height() - $overlayHeight + 30, function (FontFactory $font) {
            $font->size(26);
            $font->color('#FFFFFF');
            $font->wrap(1120);
        });

        // 5. "TubeSum" watermark
        $image->text('tubesum.app', 40, $image->height() - 20, function (FontFactory $font) {
            $font->size(16);
            $font->color('rgba(255,255,255,0.55)');
        });

        // 6. Save
        if (!is_dir(dirname($targetPath))) {
            mkdir(dirname($targetPath), 0755, true);
        }

        $image->toJpeg(quality: 88)->save($targetPath);
    }
}
```

**Cleanup:** Add a scheduled command `CleanOgImagesCommand` (`og:clean`) that deletes OG images for tasks deleted/DMCA-removed. Run weekly.

**Dependency:**

```bash
composer require intervention/image
```

> `intervention/image` v3 uses GD by default when Imagick is unavailable — safe for all Docker environments. No Node.js required.

---

## Phase 2: Virality (Day 2–3)

### Task 2.1 — Twitter Thread Generator *(Zero-Cost Triad pattern)*

**What:** AI formats `key_points` as a ready-to-post Twitter thread (4–7 tweets, ≤280 chars each, final tweet includes backlink to TubeSum). User clicks "Copy Thread" → posts under their name → followers click link → traffic.

**Architecture (identical to Zero-Cost Triad pattern):**

```
YoutubeSummarizerAgent (single LLM call)
    → existing: introduction, key_points, conclusion, resources, clickbait_verdict, tutorial_steps, chapters
    → NEW: twitter_thread: string[]
```

**Files:**

| # | File | Action | Layer |
|---|------|--------|-------|
| 1 | `app/Domain/ValueObjects/TwitterThread.php` | **Create** — VO wrapping `string[]` | Domain |
| 2 | `tests/Unit/Domain/ValueObjects/TwitterThreadTest.php` | **Create** | Test |
| 3 | `app/Domain/ValueObjects/SummaryResult.php` | **Modify** — add `?TwitterThread $twitterThread = null` | Domain |
| 4 | `tests/Unit/Domain/ValueObjects/SummaryResultTest.php` | **Modify** | Test |
| 5 | `app/Ai/Agents/YoutubeSummarizerAgent.php` | **Modify** — add section 6 to `instructions()` + `twitter_thread` to `schema()` | AI Agent |
| 6 | `app/Infrastructure/Adapters/Output/Summary/LaravelAiSummaryAdapter.php` | **Modify** — parse `twitter_thread[]` | Infrastructure |
| 7 | `app/Infrastructure/Adapters/Input/Web/Resources/SummaryResource.php` | **Modify** — serialize `twitter_thread` | Presentation |
| 8 | `resources/js/components/ThreadGenerator.vue` | **Create** | Frontend |
| 9 | `resources/js/components/TaskStatusCard.vue` | **Modify** — embed ThreadGenerator in Summary tab | Frontend |

**Prompt section to add (in `instructions()`):**

```
6. TWITTER THREAD:
   Format the key insights as 4 to 7 numbered tweets. Rules:
   - Each tweet MUST be ≤ 280 characters including spaces and punctuation.
   - Number each tweet: "1/", "2/", etc.
   - Be punchy, not academic. Short sentences preferred.
   - Final tweet must end with: "Full summary → [URL]" (the app will replace [URL] at render time).
   - Return as JSON array: "twitter_thread": ["tweet1", "tweet2", ...]
```

**Schema addition:**

```php
'twitter_thread' => $schema->array()->items($schema->string())->required(),
```

**`TwitterThread` VO:**

```php
final readonly class TwitterThread
{
    /** @param string[] $tweets */
    public function __construct(
        private array $tweets,
    ) {}

    /** @return string[] */
    public function tweets(): array
    {
        return $this->tweets;
    }

    /** @return array{tweets: string[]} */
    public function toArray(): array
    {
        return ['tweets' => $this->tweets];
    }

    /** @param array{tweets: string[]} $data */
    public static function fromArray(array $data): self
    {
        return new self($data['tweets']);
    }
}
```

**`ThreadGenerator.vue` behaviour:**
- List of tweet cards (numbered, char count badge)
- "Copy All" button → clipboard API → joins tweets with `\n\n`
- "Open Twitter" button → `https://twitter.com/intent/tweet?text=` with first tweet pre-filled
- Replaces `[URL]` in final tweet with the actual public page URL

---

### Task 2.2 — Blog Post Generator *(Zero-Cost Triad pattern)*

**What:** AI generates a structured long-form blog article (H2 sections, 400–600 words) from the transcript. Target: content creators who want to repurpose their YouTube content for Substack, Ghost, Medium.

**Files:**

| # | File | Action | Layer |
|---|------|--------|-------|
| 1 | `app/Domain/ValueObjects/BlogPost.php` | **Create** — VO with `string $title`, `BlogSection[] $sections` | Domain |
| 2 | `app/Domain/ValueObjects/BlogSection.php` | **Create** — VO with `string $heading`, `string $body` | Domain |
| 3 | `tests/Unit/Domain/ValueObjects/BlogPostTest.php` | **Create** | Test |
| 4 | `app/Domain/ValueObjects/SummaryResult.php` | **Modify** — add `?BlogPost $blogPost = null` | Domain |
| 5 | `app/Ai/Agents/YoutubeSummarizerAgent.php` | **Modify** — add section 7 to `instructions()` + `blog_post` to `schema()` | AI Agent |
| 6 | `app/Infrastructure/Adapters/Output/Summary/LaravelAiSummaryAdapter.php` | **Modify** — parse `blog_post` | Infrastructure |
| 7 | `app/Infrastructure/Adapters/Input/Web/Resources/SummaryResource.php` | **Modify** — serialize `blog_post` | Presentation |
| 8 | `resources/js/components/BlogPostExporter.vue` | **Create** | Frontend |
| 9 | `resources/js/components/TaskStatusCard.vue` | **Modify** — add Blog Post tab | Frontend |

**Prompt section to add (in `instructions()`):**

```
7. BLOG POST:
   Write a structured blog article based on the transcript's key insights.
   Style: informative, conversational, 400–600 words total.
   Structure:
   - "title": A compelling blog title (not the video title).
   - "sections": Array of 3–5 sections, each with a clear "heading" (H2 level) and "body" (1–3 paragraphs).
   Do NOT rehash the transcript verbatim. Synthesise and rewrite for a blog reader.
   Return: "blog_post": {"title": "...", "sections": [{"heading": "...", "body": "..."}]}
```

**`BlogPostExporter.vue` behaviour:**
- Preview pane rendering Markdown-style headings + body
- "Copy Markdown" → formats as `## Heading\n\nBody\n\n`
- "Copy HTML" → formats as `<h2>Heading</h2><p>Body</p>`
- "Download .md" → Blob download

---

## Phase 3: Embeddable Widget (Day 4)

### Task 3.1 — Transcript Embed Widget

**What:** A lightweight `<iframe>`-embeddable transcript card that content creators add to their own site. Each embed = organic dofollow backlink + referral traffic. Logo "Powered by TubeSum" links back to the public page.

**Architecture:**

```
GET /embed/{taskId}
    → EmbedController
        → MediaTaskRepositoryInterface::findById()
        → Returns embed.blade.php (standalone, no Vue, inline CSS via Tailwind CDN)
```

**Files:**

| # | File | Action |
|---|------|--------|
| 1 | `app/Infrastructure/Adapters/Input/Web/EmbedController.php` | **Create** |
| 2 | `resources/views/embed.blade.php` | **Create** — lightweight standalone page |
| 3 | `routes/web.php` | **Modify** — add `GET /embed/{taskId}` |
| 4 | `resources/views/transcript.blade.php` | **Modify** — add "Embed" button + copy-snippet modal |

**`embed.blade.php` content:** YouTube thumbnail + title + `introduction` (AI summary) + top 3 key_points + "Full transcript on TubeSum →" link. No JavaScript required. Tailwind CDN for styling. `X-Frame-Options: SAMEORIGIN` replaced with permissive policy for embed routes only.

**Embed snippet shown to users:**

```html
<iframe
  src="https://tubesum.app/embed/{task_id}"
  width="100%"
  height="420"
  frameborder="0"
  loading="lazy"
  title="Video Summary by TubeSum"
></iframe>
```

---

## Deferred to v2.0

| Feature | Reason |
|---------|--------|
| **Podcast RSS Importer** | Heavy MP3 downloads, Whisper file size limits, storage costs — separate epic requiring paid tier first |
| **Cross-transcript Search Page** | Needs critical mass of public transcripts to be valuable |
| **Channel Monitoring (auto-transcription)** | Requires Stripe subscriptions as financial gate |
| **OG Image cleanup scheduled job** | Low urgency — storage is cheap; add when OG images are generated at scale |

---

## Priority Matrix (Final)

| # | Feature | Channel | Library | Complexity | Priority |
|---|---------|---------|---------|------------|----------|
| 1.1 | Schema.org `VideoObject` + `og:image` | SEO | Blade only | 🟢 Trivial | ⭐⭐⭐⭐⭐ |
| 1.2 | OG Image Generator | SEO + Social | `intervention/image` | 🟢 Low | ⭐⭐⭐⭐⭐ |
| 2.1 | Twitter Thread Generator | Viral | Zero-Cost LLM | 🟢 Low | ⭐⭐⭐⭐⭐ |
| 2.2 | Blog Post Generator | New audience | Zero-Cost LLM | 🟢 Low | ⭐⭐⭐⭐ |
| 3.1 | Embeddable Widget | Backlinks | Blade + Tailwind CDN | 🟡 Medium | ⭐⭐⭐⭐ |

---

## Execution Order

```
Day 1 AM — Task 1.1: VideoObject schema + og:image (30 min, one Blade file)
Day 1 PM — Task 1.2: OgImageController + intervention/image
Day 2    — Task 2.1: TwitterThread VO + prompt + ThreadGenerator.vue
Day 3    — Task 2.2: BlogPost VO + prompt + BlogPostExporter.vue
Day 4    — Task 3.1: EmbedController + embed.blade.php + embed snippet UI
```

Tasks 2.1 and 2.2 both extend `YoutubeSummarizerAgent` — do them in one commit to avoid double merge conflicts on the prompt and schema methods.

---

## Acceptance Criteria

- [ ] `composer check` passes (phpstan level 9, phpcs PSR-12, deptrac, pest)
- [ ] `og:image` present on all public transcript pages — verified via `curl -I` + OpenGraph debugger
- [ ] JSON-LD `@type: VideoObject` with `thumbnailUrl`, `duration`, `embedUrl` — verified via Google Rich Results Test
- [ ] `TwitterThread` VO has unit tests (create, tweets(), toArray, fromArray)
- [ ] `BlogPost` + `BlogSection` VOs have unit tests
- [ ] `SummaryResult` tests include `twitterThread` and `blogPost` in serialization round-trip
- [ ] `OgImageController`: returns 404 for non-existent/non-completed tasks
- [ ] `OgImageController`: second request served from file cache (no recomputation)
- [ ] `EmbedController`: returns 404 for DMCA-removed tasks
- [ ] `embed.blade.php`: works inside an `<iframe>` without Vue/JS dependencies
- [ ] Architecture boundaries preserved (no Domain → Infrastructure violations)
- [ ] No `dd()`, `dump()`, hardcoded secrets, or commented-out code committed

