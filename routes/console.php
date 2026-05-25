<?php

use App\Infrastructure\Console\Commands\GenerateSitemapCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Regenerate sitemap.xml every day at 03:00 UTC.
Schedule::command(GenerateSitemapCommand::class)->dailyAt('03:00');

// Seed WoW content: 1 video per hour, ~24/day
Schedule::command(\App\Infrastructure\Console\Commands\SeedWowContent::class)->hourly();

