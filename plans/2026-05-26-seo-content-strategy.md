# SEO-стратегия наполнения БД транскрипциями YouTube

## Текущее состояние

- **138 видео** в публичной библиотеке (completed + title + не DMCA)
- **4 seeder-а**: anime (6 каналов), wow (6), meme (6), gaming (10) = 28 каналов
- **Темп**: до 96 новых видео/сутки (4 seeder-а × 24 часа)
- **SEO-страницы**: `/v/{slug}` — серверный рендеринг для Google

## Цель

**1000+ видео к концу июня 2026** (~34 дня, ~30 видео/день).

При текущем темпе 96/день — достижимо за 10 дней. Но реальный темп ниже (~10-15/день), потому что:
- Каналы ограничены по количеству новых видео
- Многие видео — стримы (too long), Shorts (too short), или members-only
- API rate limits Groq/yt-dlp

## SEO-принципы для TubeSum

### 1. Каждая транскрипция = SEO-лендинг

Страница `/v/{slug}` содержит:
- `<title>` = название видео + "— TubeSum"
- `<meta description>` = первые 160 символов introduction из AI-саммари
- `canonical URL`
- Полный транскрипт с таймкодами (уникальный контент)
- AI-саммари: key_points, resources, tutorial_steps
- Структурированные данные (хлебные крошки, VideoObject schema)

### 2. Ключевые слова из заголовков видео

Каждое видео приносит:
- Точное совпадение по названию (brand query)
- Long-tail keywords из транскрипта (естественная речь)
- Topic-кластеры через taxonomy (теги)

### 3. Свежесть контента (Freshness Signal)

Google учитывает:
- `lastmod` в sitemap.xml (ежедневная регенерация)
- Дату публикации на странице
- Регулярность пополнения (crawl budget растёт)

## Стратегия расширения

### Фаза 1: Максимальное покрытие YouTube-ниш (текущая)

Добавляем seeder-ы под все крупные тематики:

| Ниша | Каналы | Статус |
|------|--------|--------|
| Аниме | Gigguk, TheAnimeMan, MothersBasement, GlassReflection, ChibiReviews, SuperEyepatchWolf | ✅ |
| WoW/MMO | Bellular, Asmongold, Hazelnutty, Soulsobreezy, Xaryu, Savix | ✅ |
| Мемы/Вирус | PewDiePie, KSI, MrBeast, penguinz0, Ludwig, jacksepticeye | ✅ |
| Игры | IGN, Gamespot, GameRanx, DigitalFoundry, SkillUp, ACG, dunkey, Strat-Edgy, SummoningSalt | ✅ |
| **Технологии** | MKBHD, LinusTechTips, Dave2D, JerryRigEverything, Mrwhosetheboss, Austin Evans | 🔜 |
| **Наука/Образование** | Veritasium, Vsauce, Kurzgesagt, SmarterEveryDay, MarkRober, TomScott | 🔜 |
| **Музыка** | Anthony Fantano, Rick Beato, Polyphonic, Middle8, Adam Neely, 12tone | 🔜 |
| **Кино/Сериалы** | CinemaWins, Nerdwriter1, LessonsFromTheScreenplay, EveryFrameAPainting, FoldingIdeas, PatrickH Willems | 🔜 |
| **Спорт** | JxmyHighroller, ThinkingBasketball, SecretBase, TifoFootball, TheAthleticFC, FootballDaily | 🔜 |
| **История/Политика** | Oversimplified, Johnny Harris, CaspianReport, VisualPolitik, KingsAndGenerals, HistoriaCivilis | 🔜 |

### Фаза 2: Увеличение глубины (3-5 видео с канала)

Сейчас: 1 видео за запуск (playlist-end 3, берём первое подходящее).

Изменить на: 3-5 видео за запуск через `--playlist-end 10`, обрабатывать все подходящие.

```php
// Вместо break после первого найденного:
foreach ($videos as $video) {
    if (count($dispatched) >= 3) break; // до 3 видео за раз
    // ... dispatch ...
}
```

**Критично: Rate Limiting.** При переходе на 3-5 видео за запуск, 4 сидера × 3-5 джоб = 12-20 одновременных задач в очереди. Groq/OpenAI API выдаст `429 Too Many Requests`.

Решение — Horizon Rate Limiting через `Redis::funnel`:

```php
// В консольной команде перед dispatch:
Redis::funnel('groq-transcription')
    ->limit(3)          // макс 3 одновременных запроса
    ->releaseAfter(60)  // повтор через 60 секунд
    ->then(function () use ($task) {
        $this->transcribeHandler->handle($task);
    });
```

Или использовать встроенный Horizon `RateLimited` middleware в `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue'     => ['default'],
            'balance'   => 'auto',
            'maxJobs'   => 0,
            'rateLimits' => [
                'groq-api' => [
                    'enabled' => true,
                    'maxJobs' => 5,
                    'timeWindow' => 60, // 5 джоб в минуту
                ],
            ],
        ],
    ],
],
```

### Фаза 3: Ключевые слова через встроенный поиск yt-dlp (без API-ключей)

YouTube Data API v3 **не нужен**. `yt-dlp` умеет искать сам, бесплатно и безлимитно:

```bash
# Топ-5 видео по запросу
yt-dlp "ytsearch5:how to fix kubernetes cluster" --dump-json

# Фильтрация длительности (без Shorts/стримов)
yt-dlp "ytsearch10:linux tutorial beginner" --dump-json \
    --match-filter "duration >= 120 & duration <= 10800"
```

Пул SEO-запросов для поиска:

```
"how to fix X"
"X review 2026"
"X vs Y comparison"
"X tutorial beginner"
"is X worth it"
"X setup guide"
"X benchmark 2026"
"best X for beginners"
```

Реализация: [`SeedSearchContent`](app/Infrastructure/Console/Commands/SeedSearchContent.php) — новый seeder, который:

1. Берёт случайный запрос из пула
2. Выполняет `yt-dlp "ytsearch5:{query}" --dump-json`
3. Фильтрует по длительности (120–10800 сек)
4. Пропускает уже обработанные видео
5. Диспатчит транскрипцию для 1-3 подходящих видео

**Почему не Trending:** Тренды YouTube на 70% состоят из музыкальных клипов, политических скандалов, лайфстайл-влогов и Shorts. Для AI-саммаризатора это мусорный контент — модель либо выдаст галлюцинацию, либо пустой текст. Бюджет токенов направляем на целевые how-to и review запросы.

## Технические улучшения для SEO

### 1. Sitemap с приоритетами

```xml
<url>
  <loc>https://tubesum.app/v/slug</loc>
  <lastmod>2026-05-26</lastmod>
  <changefreq>weekly</changefreq>
  <priority>0.8</priority> <!-- выше для популярных -->
</url>
```

### 2. Внутренняя перелинковка

На странице `/v/{slug}`:
- "Related transcripts" (pg_trgm similarity)
- "More from this topic" (taxonomy)
- "Trending this week"

### 3. Структурированные данные (Schema.org)

Используем `VideoObject` вместо базового `Article`. Google отдаёт предпочтение видео-разметке с `hasPart` (Clip/Chapter) — выводит таймкодированные фрагменты прямо в поисковой выдаче под ссылкой.

```json
{
  "@context": "https://schema.org",
  "@type": "VideoObject",
  "name": "Video Title",
  "description": "AI summary and full transcript",
  "thumbnailUrl": "https://img.youtube.com/vi/VIDEO_ID/mqdefault.jpg",
  "uploadDate": "2026-05-26T12:00:00+00:00",
  "duration": "PT15M30S",
  "contentUrl": "https://www.youtube.com/watch?v=VIDEO_ID",
  "embedUrl": "https://www.youtube.com/embed/VIDEO_ID",
  "publisher": {
    "@type": "Organization",
    "name": "TubeSum",
    "url": "https://tubesum.app"
  },
  "hasPart": [
    {
      "@type": "Clip",
      "name": "Introduction",
      "startOffset": 0,
      "endOffset": 120,
      "url": "https://www.youtube.com/watch?v=VIDEO_ID&t=0"
    },
    {
      "@type": "Clip",
      "name": "Key Point: How Kubernetes Clusters Work",
      "startOffset": 180,
      "endOffset": 420,
      "url": "https://www.youtube.com/watch?v=VIDEO_ID&t=180"
    },
    {
      "@type": "Clip",
      "name": "Step 1: Install kubectl",
      "startOffset": 540,
      "endOffset": 720,
      "url": "https://www.youtube.com/watch?v=VIDEO_ID&t=540"
    }
  ]
}
```

Генерация `hasPart`:
- **Введение** — 0 до первого `keyPoint.timecode` или первых 120 секунд
- **keyPoints** → как `Clip` с таймкодами
- **tutorialSteps** → как `Clip` с таймкодами
- Клипы без точного `endOffset` получают `startOffset + 120` (2 минуты) как оценку

### 4. Open Graph / Twitter Cards

Для шеринга в соцсетях (дополнительный трафик).

### 5. Скорость загрузки

Blade-страницы рендерятся на сервере — Google это любит. Нужно добавить:
- Кеширование `historyPage` (Cache::remember)
- CDN для статики (уже есть Vite build)

## KPI для отслеживания

| Метрика | Сейчас | Цель |
|---------|--------|------|
| Видео в библиотеке | 138 | 1000+ |
| SEO-страниц в индексе Google | ~20-30 | 500+ |
| Органический трафик | ? | Растущий |
| Новых видео/день | 10-15 | 30-50 |
| Разных ниш | 4 | 10+ |
| Каналов | 28 | 60+ |

## Приоритеты реализации

1. 🔜 **Tech seeder** (MKBHD, LTT, etc.) — самые SEO-богатые запросы ("galaxy s26 review", "macbook pro 2026" и т.д.)
2. 🔜 **Образовательный seeder** — long-form content с высококачественными транскриптами
3. 🔜 **VideoObject Schema.org разметка** — замена `Article` на `VideoObject` с `hasPart` (Clip/Chapter)
4. 🔜 **yt-dlp поисковый seeder** — `SeedSearchContent` с `ytsearch:query` вместо YouTube Data API v3
5. 🔜 **3-5 видео за запуск + Horizon Rate Limiting** — утроение темпа с защитой от 429
6. 🔜 **Redis::funnel в сидерах** — ограничение одновременных Groq/OpenAI вызовов
