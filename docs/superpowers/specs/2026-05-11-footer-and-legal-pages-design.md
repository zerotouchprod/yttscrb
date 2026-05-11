# Footer & Legal Pages — Design Spec

**Date:** 2026-05-11
**Status:** Draft
**Scope:** v1.0 (beta)

## 1. Problem

Current state of footers is fragmented:

| Page | Template | Footer |
|------|----------|--------|
| `/` (main) | `welcome.blade.php` → `App.vue` | **Missing** |
| `/history` | `history.blade.php` | Minimal (3 links) |
| `/v/{slug}` | `transcript.blade.php` | Minimal (2 links) |
| `/dmca` | `dmca.blade.php` | **Missing** |

No legal pages exist: Terms of Service, Privacy Policy, Pricing, Contact. This is a red flag for payment processors (Stripe/Paddle) and a legal risk for a service that parses third-party YouTube content.

## 2. Solution Overview

**Approach A (selected): Shared Blade partial + mirrored Vue markup.**

- Create `resources/views/partials/footer.blade.php` — canonical Blade partial with 4-column grid (Brand, Product, Legal, Connect).
- Add identical HTML in `App.vue` before closing `</div>` of root wrapper.
- Replace existing minimal footers in `history.blade.php` and `transcript.blade.php` with `@include('partials.footer')`.
- Add `@include('partials.footer')` to `dmca.blade.php`.
- Create new static pages: `/terms`, `/privacy`, `/pricing`, `/contact`.
- Update `robots.txt` and sitemap command.

## 3. Footer Design

### 3.1 Visual Spec

```
┌──────────────────────────────────────────────────────────┐
│ border-t border-slate-800 bg-[#0f172a]                   │
│ max-w-4xl mx-auto px-4 py-8 sm:py-12                     │
│                                                          │
│ ┌──────────────┬──────────┬──────────┬────────────────┐  │
│ │ TubeSum      │ Product  │ Legal    │ Connect        │  │
│ │ tagline      │ Pricing  │ ToS      │ Twitter (X)    │  │
│ │              │ Library  │ Privacy  │ Contact Support│  │
│ │              │ Extension│ DMCA     │                │  │
│ └──────────────┴──────────┴──────────┴────────────────┘  │
│                                                          │
│ ─────── border-t border-slate-800 ───────                │
│ © 2026 TubeSum.app          Built with Laravel & Tailwind│
└──────────────────────────────────────────────────────────┘
```

- Grid: `grid-cols-2 md:grid-cols-4 gap-8`
- Brand column: `col-span-2 md:col-span-1`
- Colors match existing dark theme: `bg-[#0f172a]`, `border-slate-800`, text `slate-200/400/500`
- Links: `hover:text-blue-400 transition-colors`
- DMCA link: `hover:text-red-400` (distinguished)
- Bottom bar: `text-xs text-slate-500`

### 3.2 Link Targets

| Label | href | Target |
|-------|------|--------|
| Pricing | `/pricing` | _self |
| Public Library | `/history` | _self |
| Chrome Extension | `#` | _self (Soon badge) |
| Terms of Service | `/terms` | _self |
| Privacy Policy | `/privacy` | _self |
| DMCA / Removal | `/dmca` | _self |
| Twitter (X) | `https://x.com/...` | _blank |
| Contact Support | `/contact` | _self |

### 3.3 Responsive Behavior

- Mobile (<768px): 2-column grid (`grid-cols-2`), brand column full width (`col-span-2`).
- Desktop (≥768px): 4-column grid (`md:grid-cols-4`), brand column 1 span (`md:col-span-1`).
- Bottom bar: stacked on mobile (`flex-col`), side-by-side on desktop (`md:flex-row`).

## 4. New Static Pages

### 4.1 Routes (web.php)

```php
Route::get('/terms', function () { return view('terms'); });
Route::get('/privacy', function () { return view('privacy'); });
Route::get('/pricing', function () { return view('pricing'); });
Route::get('/contact', function () { return view('contact'); });
```

### 4.2 Terms of Service (`terms.blade.php`)

- Title: "Terms of Service — TubeSum"
- Meta: `noindex, follow` (legal pages don't need indexing)
- Content sections:
  1. **Acceptance of Terms** — by using the service you agree
  2. **Service Description** — AI transcription/summarization of publicly available YouTube videos
  3. **Fair Use & Rate Limiting** — 10 free transcriptions/month
  4. **Intellectual Property** — users retain rights to their content; transcripts are AI-generated
  5. **DMCA & Content Removal** — link to /dmca, commitment to respond within 48h
  6. **Disclaimer of Warranties** — service provided "as is", no guarantee of accuracy
  7. **Limitation of Liability** — not liable for damages from use of service
  8. **Changes to Terms** — may update, notice via website
  9. **Governing Law** — Czech Republic (EU)

### 4.3 Privacy Policy (`privacy.blade.php`)

- Title: "Privacy Policy — TubeSum"
- Meta: `noindex, follow`
- Content sections:
  1. **Data Collection** — YouTube URLs submitted, timestamps, nothing else
  2. **Cookies** — no tracking cookies in v1; Yandex.Metrika on landing page (anonymized)
  3. **Data Storage** — transcripts and summaries stored; audio files deleted after processing
  4. **Data Sharing** — no third-party sharing except AI providers (Groq/OpenAI) for processing
  5. **Data Retention** — transcripts retained indefinitely (public library); right to deletion via DMCA
  6. **GDPR Rights** — access, rectification, erasure, portability (EU users)
  7. **Contact** — email for privacy concerns
  8. **Changes** — may update

### 4.4 Pricing (`pricing.blade.php`)

- Title: "Pricing — TubeSum"
- Meta: `index, follow`
- Content: Single card
  - **Free Beta**: 10 transcriptions/month, AI summary, full transcript, no signup
  - **Pro (Coming Soon)**: Unlimited, PDF export, priority processing, API access

### 4.5 Contact (`contact.blade.php`)

- Title: "Contact — TubeSum"
- Meta: `noindex, follow`
- Content:
  - Email: `hello@tubesum.app` (or configured env var)
  - Twitter link
  - DMCA email: `dmca@tubesum.app`
  - "For bug reports, feature requests, or general inquiries"

## 5. Files Changed

| File | Action | Description |
|------|--------|-------------|
| `resources/views/partials/footer.blade.php` | **Create** | Canonical Blade footer partial |
| `resources/js/App.vue` | **Modify** | Add footer HTML before closing `</div>` (line 397) |
| `resources/views/history.blade.php` | **Modify** | Replace footer (lines 146-156) with `@include` |
| `resources/views/transcript.blade.php` | **Modify** | Replace footer (lines 176-185) with `@include` |
| `resources/views/dmca.blade.php` | **Modify** | Add `@include` before closing `</body>` |
| `resources/views/terms.blade.php` | **Create** | Terms of Service page |
| `resources/views/privacy.blade.php` | **Create** | Privacy Policy page |
| `resources/views/pricing.blade.php` | **Create** | Pricing page (beta) |
| `resources/views/contact.blade.php` | **Create** | Contact page |
| `routes/web.php` | **Modify** | Add 4 static routes |
| `public/robots.txt` | **Modify** | Add new pages |
| `app/Infrastructure/Console/Commands/GenerateSitemapCommand.php` | **Modify** | Add static pages to sitemap |

## 6. Testing

- [ ] Each page renders with footer and correct links (feature test: 200 status)
- [ ] Footer `@include` works on all Blade templates
- [ ] Footer renders in Vue SPA (visual check)
- [ ] Sitemap command includes new static routes
- [ ] robots.txt allows/denies correct pages

## 7. Quality Gates

- [ ] No hardcoded emails — use `config('mail.from.address')` or env vars
- [ ] No Laravel facades in business code
- [ ] Responsive: mobile 2-col, desktop 4-col
- [ ] All links functional
- [ ] Legal pages have proper `<title>`, `<meta description>`, `<link canonical>`
