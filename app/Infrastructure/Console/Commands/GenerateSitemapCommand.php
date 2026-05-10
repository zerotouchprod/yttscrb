<?php

declare(strict_types=1);

namespace App\Infrastructure\Console\Commands;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

final class GenerateSitemapCommand extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate sitemap.xml from all public completed transcription pages.';

    public function __construct(
        private readonly MediaTaskRepositoryInterface $repository,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sitemap = Sitemap::create();

        // Static pages
        $sitemap->add(
            Url::create(url('/'))
                ->setPriority(1.0)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY),
        );

        // Public transcript landing pages via repository (no direct Eloquent access).
        foreach ($this->repository->findPublicSlugs() as $row) {
            $entry = Url::create(url('/v/' . $row['slug']))
                ->setPriority(0.8)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_YEARLY);

            $lastMod = $row['updated_at'] ?? $row['completed_at'];
            if ($lastMod !== null) {
                $entry->setLastModificationDate(new \DateTime($lastMod));
            }

            $sitemap->add($entry);
        }

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('sitemap.xml generated at ' . public_path('sitemap.xml'));

        return Command::SUCCESS;
    }
}
