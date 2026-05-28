<?php

use App\Infrastructure\Console\Commands\GenerateSitemapCommand;
use App\Infrastructure\Console\Commands\ResetWeeklyTrendingCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Regenerate sitemap.xml every day at 03:00 UTC.
Schedule::command(GenerateSitemapCommand::class)->dailyAt('03:00');

// Reset weekly trending sorted set every Monday at 00:00 UTC.
Schedule::command(ResetWeeklyTrendingCommand::class)->weeklyOn(1, '00:00');

// Seed WoW content: 1 video per hour, ~24/day
Schedule::command(\App\Infrastructure\Console\Commands\SeedWowContent::class)->everyMinute();

// Seed Anime content: 1 video per hour, ~24/day
Schedule::command(\App\Infrastructure\Console\Commands\SeedAnimeContent::class)->everyMinute();

// Seed Meme content: 1 video per hour, ~24/day
Schedule::command(\App\Infrastructure\Console\Commands\SeedMemeContent::class)->everyMinute();

// Seed Gaming content: 1 video per hour, ~24/day
Schedule::command(\App\Infrastructure\Console\Commands\SeedGamingContent::class)->everyMinute();

// Seed Tech content: 1-3 videos per run, every minute with Redis::funnel rate limiting
Schedule::command(\App\Infrastructure\Console\Commands\SeedTechContent::class)->everyMinute();

// Seed Science/Education content: 1-5 videos per run, every minute with Redis::funnel rate limiting
Schedule::command(\App\Infrastructure\Console\Commands\SeedScienceContent::class)->everyMinute();

// Seed Search content (yt-dlp ytsearch): 1-3 videos per run, every 5 min
Schedule::command(\App\Infrastructure\Console\Commands\SeedSearchContent::class)->everyFiveMinutes();

// Clean up old workflow data to prevent Redis/PostgreSQL bloat. Runs daily at 04:00 UTC.
Schedule::command(\App\Infrastructure\Console\Commands\CleanupOldWorkflowsCommand::class)->dailyAt('04:00');

