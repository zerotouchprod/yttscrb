# Taxonomy Pages + LinkedIn Post Generator — Implementation Plan

> **Date:** 2026-05-25
> **Status:** Ready for Implementation
> **Context:** Two-sprint growth feature. LinkedIn Post = 3-hour Zero-Cost Triad extension. Taxonomy System = 3-day programmatic SEO foundation (auto-generated `/topic/{slug}` and `/speaker/{slug}` pages).

---

## Why These Two Features

### LinkedIn Post Generator

Twitter Thread Generator is planned for today's SEO sprint (Task 2.1). LinkedIn is not — yet the B2B audience on LinkedIn actively shares podcast/webinar summaries and is more monetisable. The implementation is identical in architecture: one more output field in the AI agent, one more VO, one more Vue component. Cost: zero extra API calls.

### Topic / Speaker Pages (Programmatic SEO)

This is the highest-leverage long-term traffic engine available. Auto-generated, indexed, user-intent-matching pages like:

- `/topic/machine-learning` → all transcripts tagged "Machine Learning"
- `/speaker/lex-fridman` → all transcripts from Lex Fridman's channel

This is the exact playbook used by Genius.com (artist pages), G2 (category pages), and every major content aggregator. Each new transcription automatically expands the SEO surface without any manual content work.

**Key architectural decision:** One polymorphic `taxonomies` table with a `type` column (`topic | speaker`) instead of separate tables. This avoids model proliferation and allows shared controller/view/repository logic.

---

## Phase 1: LinkedIn Post Generator (~3 hours)

### What the AI generates

A structured LinkedIn post:

- **Hook** (2 lines): the opening that appears before "See more…" collapse. Must create curiosity.
- **Body** (3–5 paragraphs): key insights, conversational but professional tone. 1 000–1 500 characters.
- **CTA** (1 line): "Full AI summary + transcript → [URL]" (frontend replaces `[URL]` at render time).

Total: 1 200–1 800 characters (fits within LinkedIn's organic reach sweet spot).

### Files

| # | File | Action | Layer |
|---|------|--------|-------|
| 1 | `app/Domain/ValueObjects/LinkedInPost.php` | **Create** | Domain |
| 2 | `tests/Unit/Domain/ValueObjects/LinkedInPostTest.php` | **Create** | Test |
| 3 | `app/Domain/ValueObjects/SummaryResult.php` | **Modify** — add `?LinkedInPost $linkedInPost = null` | Domain |
| 4 | `tests/Unit/Domain/ValueObjects/SummaryResultTest.php` | **Modify** | Test |
| 5 | `app/Ai/Agents/YoutubeSummarizerAgent.php` | **Modify** — add section 10 to `instructions()` + `linkedin_post` to `schema()` | AI Agent |
| 6 | `app/Infrastructure/Adapters/Output/Summary/LaravelAiSummaryAdapter.php` | **Modify** — parse `linkedin_post` | Infrastructure |
| 7 | `app/Infrastructure/Adapters/Input/Web/Resources/SummaryResource.php` | **Modify** — serialize `linkedin_post` | Presentation |
| 8 | `resources/js/components/LinkedInPostExporter.vue` | **Create** | Frontend |
| 9 | `resources/js/components/TaskStatusCard.vue` | **Modify** — add LinkedIn tab | Frontend |

### `LinkedInPost` VO

```php
final readonly class LinkedInPost
{
    public function __construct(
        private string $hook,
        private string $body,
        private string $callToAction,
    ) {}

    public function hook(): string { return $this->hook; }
    public function body(): string { return $this->body; }
    public function callToAction(): string { return $this->callToAction; }

    /** @return array{hook: string, body: string, call_to_action: string} */
    public function toArray(): array
    {
        return [
            'hook'            => $this->hook,
            'body'            => $this->body,
            'call_to_action'  => $this->callToAction,
        ];
    }

    /** @param array{hook: string, body: string, call_to_action: string} $data */
    public static function fromArray(array $data): self
    {
        return new self(
            hook:           $data['hook'],
            body:           $data['body'],
            callToAction:   $data['call_to_action'],
        );
    }
}
```

### Prompt section to add (in `instructions()`)

```
10. LINKEDIN POST:
    Write a LinkedIn post based on the transcript's key insights.
    Audience: professionals (managers, engineers, founders, researchers).
    Style: informative yet conversational. No corporate buzzwords.
    Structure:
    - "hook": First 2 lines (max 200 characters total). Must be punchy and make the reader click "See more".
      Do NOT start with "I" or generic openers like "In today's video…".
    - "body": 3–5 short paragraphs (1 000–1 500 characters total). Each paragraph max 3 sentences.
      Use line breaks between paragraphs. Emojis are allowed sparingly (0–3 per post).
    - "call_to_action": Final line. Must end with "→ [URL]" (the app replaces [URL] at render time).
    Return: "linkedin_post": {"hook": "...", "body": "...", "call_to_action": "..."}
```

### Schema addition

```php
'linkedin_post' => $schema->object(fn (JsonSchema $s): array => [
    'hook'           => $s->string()->description('First 2 lines, max 200 chars, creates curiosity')->required(),
    'body'           => $s->string()->description('3–5 paragraphs, 1000–1500 chars, professional tone')->required(),
    'call_to_action' => $s->string()->description('Final CTA line ending with → [URL]')->required(),
]),
```

### `LinkedInPostExporter.vue` behaviour

- Preview pane: renders `hook + body + callToAction` with real `[URL]` substituted.
- Character counter badge (LinkedIn penalises algorithmically posts > 3 000 chars).
- **"Copy Post"** → clipboard, joins all sections with `\n\n`.
- **"Open LinkedIn"** → `https://www.linkedin.com/shareArticle?mini=true&text=` with hook pre-filled (body too long for URL, user pastes manually after opening LinkedIn).
- Note under the button: "Paste the full post in LinkedIn after clicking 'Share'."

---

## Phase 2: Unified Taxonomy System (~3 days)

### Architecture overview

```
yt-dlp metadata (channel_name)  ──────────────────────────┐
                                                           ▼
AI output (topics[])  ────────────────────►  TaxonomyTaggingActivity
                                                           │
                                                           ▼
                                              taxonomies table
                                              (type, name, slug)
                                                           │
                                              media_task_taxonomies
                                              (pivot)
                                                           │
                                            ┌──────────────┴──────────────┐
                                            ▼                             ▼
                                   /topic/{slug}                 /speaker/{slug}
                                   (Blade, SEO-indexed)          (Blade, SEO-indexed)
```

### Phase 2.0 — Database (Day 1 morning)

#### Migration 1: `taxonomies` table

```sql
CREATE TABLE taxonomies (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    type        VARCHAR(20) NOT NULL,          -- 'topic' | 'speaker'
    name        VARCHAR(255) NOT NULL,
    slug        VARCHAR(255) NOT NULL,
    description TEXT,
    video_count INTEGER NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT NOW(),

    CONSTRAINT taxonomies_type_check CHECK (type IN ('topic', 'speaker')),
    CONSTRAINT taxonomies_slug_unique UNIQUE (slug)
);

CREATE INDEX idx_taxonomies_type ON taxonomies(type);
CREATE INDEX idx_taxonomies_video_count ON taxonomies(video_count DESC);
```

> **Slug uniqueness note:** `topic/machine-learning` and `speaker/machine-learning` would both produce slug `machine-learning`. The UNIQUE constraint on `slug` is therefore scoped: the slug need only be unique within its type. Concretely, use a composite UNIQUE on `(type, slug)`.

#### Migration 2: `media_task_taxonomies` pivot

```sql
CREATE TABLE media_task_taxonomies (
    media_task_id UUID NOT NULL REFERENCES media_tasks(id) ON DELETE CASCADE,
    taxonomy_id   UUID NOT NULL REFERENCES taxonomies(id)  ON DELETE CASCADE,
    PRIMARY KEY (media_task_id, taxonomy_id)
);

CREATE INDEX idx_mtt_taxonomy_id ON media_task_taxonomies(taxonomy_id);
```

#### Migration 3: `channel_name` and `channel_slug` on `media_tasks`

```sql
ALTER TABLE media_tasks
    ADD COLUMN channel_name VARCHAR(255),
    ADD COLUMN channel_slug VARCHAR(255);

CREATE INDEX idx_media_tasks_channel_slug ON media_tasks(channel_slug);
```

### Phase 2.1 — Domain (Day 1)

| # | File | Action |
|---|------|--------|
| 1 | `app/Domain/ValueObjects/TaxonomyType.php` | **Create** — Enum `Topic = 'topic'`, `Speaker = 'speaker'` |
| 2 | `app/Domain/Entities/Taxonomy.php` | **Create** — Entity with `id`, `type: TaxonomyType`, `name`, `slug`, `videoCount` |
| 3 | `app/Application/Ports/Output/TaxonomyRepositoryInterface.php` | **Create** — port definition |
| 4 | `tests/Unit/Domain/Entities/TaxonomyTest.php` | **Create** |

#### `TaxonomyType` Enum

```php
enum TaxonomyType: string
{
    case Topic   = 'topic';
    case Speaker = 'speaker';

    public function routePrefix(): string
    {
        return match($this) {
            self::Topic   => 'topic',
            self::Speaker => 'speaker',
        };
    }

    public function label(): string
    {
        return match($this) {
            self::Topic   => 'Topic',
            self::Speaker => 'Speaker',
        };
    }
}
```

#### `TaxonomyRepositoryInterface`

```php
interface TaxonomyRepositoryInterface
{
    /**
     * Find existing taxonomy by type+slug or create it.
     * Increments video_count on every task attachment.
     */
    public function findOrCreate(TaxonomyType $type, string $name): Taxonomy;

    public function findByTypeAndSlug(TaxonomyType $type, string $slug): ?Taxonomy;

    /** @return Taxonomy[] */
    public function paginateByType(TaxonomyType $type, int $page, int $perPage): array;

    /** @return int Total count of taxonomies of given type */
    public function countByType(TaxonomyType $type): int;

    /**
     * Attach a taxonomy to a media task. Idempotent — safe to call multiple times.
     * Increments taxonomy.video_count by 1 on first attach (not on duplicate).
     */
    public function attachToTask(string $taskId, Taxonomy $taxonomy): void;

    /**
     * Paginate media tasks for a given taxonomy.
     *
     * @return array{data: array<int, mixed>, total: int}
     */
    public function paginateTasksByTaxonomy(Taxonomy $taxonomy, int $page, int $perPage): array;
}
```

### Phase 2.2 — AI Extension for Topics (Day 1)

Add topics extraction as a Zero-Cost Triad output (no extra API call).

| # | File | Action |
|---|------|--------|
| 1 | `app/Ai/Agents/YoutubeSummarizerAgent.php` | **Modify** — add section 11 + `topics` to schema |
| 2 | `app/Domain/ValueObjects/SummaryResult.php` | **Modify** — add `string[] $topics = []` |
| 3 | `app/Infrastructure/Adapters/Output/Summary/LaravelAiSummaryAdapter.php` | **Modify** — parse `topics[]` |
| 4 | `app/Infrastructure/Adapters/Input/Web/Resources/SummaryResource.php` | **Modify** — serialize `topics` |

#### Prompt section (add to `instructions()` after LinkedIn Post)

```
11. TOPICS:
    Return 2–5 short topic tags that best describe the video's subject matter.
    Rules:
    - Use lowercase (e.g. "machine learning", not "Machine Learning").
    - Each tag MUST be 1–4 words maximum. No sentences.
    - Tags must be general enough to group multiple videos (not specific to this single video).
    - Good examples: "kubernetes", "startup funding", "fitness", "python", "behavioural economics".
    - Bad examples: "How Elon Musk became rich" (too specific), "interesting stuff" (too vague).
    - Return as JSON array: "topics": ["tag1", "tag2", ...]
```

#### Schema addition

```php
'topics' => $schema->array()
    ->items($schema->string()->description('Lowercase topic tag, 1–4 words'))
    ->required(),
```

### Phase 2.3 — Workflow Activity (Day 2 morning)

| # | File | Action |
|---|------|--------|
| 1 | `app/Infrastructure/Adapters/Output/Workflow/Activities/TaxonomyTaggingActivity.php` | **Create** |
| 2 | `app/Infrastructure/Adapters/Output/Workflow/Workflows/TranscribeVideoWorkflow.php` | **Modify** — add `yield` after `PersistResultActivity` |
| 3 | `tests/Feature/Infrastructure/Activities/TaxonomyTaggingActivityTest.php` | **Create** |

#### `TaxonomyTaggingActivity` logic

```php
final class TaxonomyTaggingActivity
{
    public function __construct(
        private readonly MediaTaskRepositoryInterface $taskRepository,
        private readonly TaxonomyRepositoryInterface $taxonomyRepository,
    ) {}

    public function tag(string $taskId): void
    {
        $task = $this->taskRepository->findByIdOrFail($taskId);

        // Speaker taxonomy — from channel_name stored during audio download
        $channelName = $task->channelName();
        if ($channelName !== null && $channelName !== '') {
            $taxonomy = $this->taxonomyRepository->findOrCreate(TaxonomyType::Speaker, $channelName);
            $this->taxonomyRepository->attachToTask($taskId, $taxonomy);
        }

        // Topic taxonomies — from AI output stored in summary JSONB
        $summary = $task->summary();
        if ($summary !== null) {
            foreach ($summary->topics() as $topicName) {
                $taxonomy = $this->taxonomyRepository->findOrCreate(TaxonomyType::Topic, $topicName);
                $this->taxonomyRepository->attachToTask($taskId, $taxonomy);
            }
        }
    }
}
```

**Idempotency:** `attachToTask()` uses `INSERT ... ON CONFLICT DO NOTHING` on the pivot's PK. `video_count` is incremented only on actual insert (via trigger or conditional logic in the repository). Safe to retry.

**Workflow integration:**

```php
// After PersistResultActivity
yield $this->taxonomyTaggingActivity->tag($taskId);
```

**Activity timeout:** 30 sec. Retry: 3 (1s, 5s, 10s). No heartbeat needed.

### Phase 2.4 — Infrastructure: Persistence (Day 2)

| # | File | Action |
|---|------|--------|
| 1 | `app/Infrastructure/Adapters/Output/Persistence/TaxonomyEloquentRepository.php` | **Create** |
| 2 | `app/Models/TaxonomyModel.php` | **Create** — Eloquent model (Infrastructure only) |
| 3 | `app/Models/MediaTaskTaxonomyModel.php` | **Create** — pivot model |
| 4 | `tests/Feature/Infrastructure/TaxonomyEloquentRepositoryTest.php` | **Create** |

#### Slug normalisation (inside `findOrCreate`)

```php
$slug = Str::slug($name); // "Machine Learning" → "machine-learning"
```

Slug collision within same type is prevented by the composite UNIQUE `(type, slug)`. Collision across types is allowed (different routes).

#### `paginateTasksByTaxonomy` query

```sql
SELECT mt.*
FROM media_tasks mt
INNER JOIN media_task_taxonomies mtt ON mtt.media_task_id = mt.id
WHERE mtt.taxonomy_id = ?
  AND mt.status = 'completed'
  AND (mt.is_dmca_removed IS NULL OR mt.is_dmca_removed = false)
ORDER BY mt.completed_at DESC
LIMIT ? OFFSET ?;
```

### Phase 2.5 — Controllers & Routes (Day 2–3)

| # | File | Action |
|---|------|--------|
| 1 | `app/Infrastructure/Adapters/Input/Web/TaxonomyController.php` | **Create** |
| 2 | `routes/web.php` | **Modify** |
| 3 | `resources/views/taxonomy.blade.php` | **Create** |
| 4 | `resources/views/topics-index.blade.php` | **Create** |
| 5 | `resources/views/transcript.blade.php` | **Modify** — add topic/speaker badges |
| 6 | `tests/Feature/TaxonomyControllerTest.php` | **Create** |

#### New routes

```php
// Taxonomy pages: topic and speaker channels — SEO-indexed
Route::get('/topics', [TaxonomyController::class, 'topicsIndex'])->name('topics.index');
Route::get('/topic/{slug}', [TaxonomyController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+')
    ->defaults('type', 'topic')
    ->name('topic.show');
Route::get('/speaker/{slug}', [TaxonomyController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+')
    ->defaults('type', 'speaker')
    ->name('speaker.show');
```

#### `TaxonomyController`

```php
final class TaxonomyController extends Controller
{
    public function __construct(
        private readonly TaxonomyRepositoryInterface $taxonomyRepository,
    ) {}

    /** GET /topics — directory of all topic tags sorted by video_count DESC */
    public function topicsIndex(): View
    {
        $topics = $this->taxonomyRepository->paginateByType(TaxonomyType::Topic, page: 1, perPage: 100);
        return view('topics-index', ['topics' => $topics]);
    }

    /** GET /topic/{slug} and GET /speaker/{slug} */
    public function show(string $slug, string $type): View
    {
        $taxonomyType = TaxonomyType::from($type);
        $taxonomy = $this->taxonomyRepository->findByTypeAndSlug($taxonomyType, $slug);

        if ($taxonomy === null) {
            abort(404);
        }

        $page = max(1, (int) request()->query('page', 1));
        $result = $this->taxonomyRepository->paginateTasksByTaxonomy($taxonomy, $page, perPage: 15);

        return view('taxonomy', [
            'taxonomy' => $taxonomy,
            'tasks'    => $result['data'],
            'total'    => $result['total'],
            'page'     => $page,
            'perPage'  => 15,
        ]);
    }
}
```

#### `taxonomy.blade.php` SEO structure

```blade
<title>{{ $taxonomy->name() }} Transcripts & AI Summaries — TubeSum</title>
<meta name="description" content="Browse {{ $taxonomy->videoCount() }} video transcripts and AI summaries tagged '{{ $taxonomy->name() }}'. Free, no signup.">
<link rel="canonical" href="{{ url('/' . $taxonomy->type()->routePrefix() . '/' . $taxonomy->slug()) }}">
<meta property="og:title" content="{{ $taxonomy->name() }} — TubeSum">
```

Page body: breadcrumb (`Home → Topics → Machine Learning`) + grid of transcript cards (same card component as `/history`).

Pagination: simple prev/next with `?page=N` links (server-rendered, crawlable by Google).

### Phase 2.6 — Backfill Command (Day 3)

Existing completed transcriptions have no taxonomy data. A one-time artisan command re-processes them.

| # | File | Action |
|---|------|--------|
| 1 | `app/Infrastructure/Console/Commands/BackfillTaxonomiesCommand.php` | **Create** |

```php
// artisan taxonomy:backfill [--dry-run] [--limit=500]
// For each completed media_task with no taxonomy entries:
//   1. Read summary->topics() from existing JSONB — attach topic taxonomies (free, no AI call).
//   2. Read channel_name from media_tasks — attach speaker taxonomy (free).
//   3. If channel_name is null AND topics is empty → skip (no data to tag from).
// Does NOT re-run AI. Uses data already stored in the JSONB summary column.
```

**Cost: zero.** All data is already in the database. The command is a pure DB read/write operation.

### Phase 2.7 — Sitemap & Discovery (Day 3)

| # | File | Action |
|---|------|--------|
| 1 | `app/Infrastructure/Console/Commands/GenerateSitemapCommand.php` | **Modify** |
| 2 | `resources/views/transcript.blade.php` | **Modify** — add tags below video title |

#### Sitemap inclusion

Only add taxonomy pages where `video_count >= 3` to avoid thin-content pages in the sitemap.

```php
// Inside GenerateSitemapCommand
$sitemap->add(Url::create('/topics')->setPriority(0.6)->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY));

foreach ($this->taxonomyRepository->paginateByType(TaxonomyType::Topic, 1, 1000) as $taxonomy) {
    if ($taxonomy->videoCount() < 3) {
        continue;
    }
    $sitemap->add(
        Url::create('/topic/' . $taxonomy->slug())
            ->setPriority(0.5)
            ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
    );
}
// Same loop for Speaker
```

#### Topic badges on transcript pages

Add below the video title in `transcript.blade.php`:

```blade
@if ($task->summary()?->topics())
    <div class="flex flex-wrap gap-2 mt-2">
        @foreach ($task->summary()->topics() as $topic)
            <a href="{{ url('/topic/' . Str::slug($topic)) }}"
               class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-600 px-2 py-1 rounded-full transition">
                #{{ $topic }}
            </a>
        @endforeach
        @if ($task->channelSlug())
            <a href="{{ url('/speaker/' . $task->channelSlug()) }}"
               class="text-xs bg-blue-50 hover:bg-blue-100 text-blue-600 px-2 py-1 rounded-full transition">
                {{ $task->channelName() }}
            </a>
        @endif
    </div>
@endif
```

---

## Acceptance Criteria

### LinkedIn Post

- [ ] `LinkedInPost` VO: `hook`, `body`, `callToAction` — unit tests for `toArray/fromArray`.
- [ ] `SummaryResult` serialization round-trip includes `linkedin_post`.
- [ ] `YoutubeSummarizerAgent` schema: `linkedin_post` object is present.
- [ ] `SummaryResource` serializes `linkedin_post`.
- [ ] `LinkedInPostExporter.vue`: copy, opens LinkedIn, substitutes `[URL]`, char count badge.
- [ ] `composer check` passes (PHPStan 9, PSR-12, Deptrac, Pest).

### Taxonomy System

- [ ] Migrations run cleanly on a fresh DB (`php artisan migrate`).
- [ ] `taxonomies` table has composite UNIQUE on `(type, slug)`.
- [ ] `TaxonomyTaggingActivity` is idempotent: calling it twice on the same task produces no duplicate rows and no double-increment of `video_count`.
- [ ] `GET /topic/{slug}` returns 200 with paginated transcript list.
- [ ] `GET /topic/nonexistent` returns 404.
- [ ] `GET /speaker/{slug}` returns 200.
- [ ] `GET /topics` returns the full topic directory.
- [ ] Topic badges appear on `transcript.blade.php` for transcripts that have topics.
- [ ] `taxonomy:backfill` command processes all completed tasks without errors.
- [ ] Sitemap includes only taxonomy pages with `video_count >= 3`.
- [ ] Architecture boundaries preserved: no Domain → Infrastructure references.
- [ ] `composer check` passes.

---

## Execution Order

```
Day 1 AM  — Phase 1: LinkedInPost VO + YoutubeSummarizerAgent + SummaryResult + tests
Day 1 PM  — Phase 1: LaravelAiSummaryAdapter + SummaryResource + LinkedInPostExporter.vue
Day 2 AM  — Phase 2.0: Migrations + Phase 2.1: Domain (TaxonomyType, Taxonomy, Port)
Day 2 PM  — Phase 2.2: AI topics extension + Phase 2.3: TaxonomyTaggingActivity + Workflow
Day 3 AM  — Phase 2.4: TaxonomyEloquentRepository + Models
Day 3 PM  — Phase 2.5: TaxonomyController + Routes + Blade views
Day 3 EOD — Phase 2.6: BackfillCommand + Phase 2.7: Sitemap + transcript badges
            Run composer check. Fix. Ship.
```

---

## Deferred

| Item | Reason |
|------|--------|
| Speaker Pages in sitemap with `video_count >= 5` (higher threshold) | Speaker pages need more content density to avoid thin-content penalty. Start at 3, raise to 5 after observing quality. |
| `/topics` page pagination | 100 topics per page is enough for launch. Add infinite scroll when > 200 topics exist. |
| Topic editing / moderation UI | AI tags are good but occasionally wrong. Manual curation is v2. |
| Read-Along Player | Requires word-level timestamp storage (schema change + large JSON). Separate epic. |
| Multi-platform (Vimeo, TikTok) | Vimeo: fast follow (long content). TikTok/Reels: deliberately excluded (short content, no business value for summarisation). |

