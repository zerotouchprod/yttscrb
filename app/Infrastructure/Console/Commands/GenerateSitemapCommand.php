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
        $targetPath = public_path('sitemap.xml');

        // Static pages
        $sitemap->add(
            Url::create(url('/'))
                ->setPriority(1.0)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY),
        );

        $sitemap->add(
            Url::create(url('/history'))
                ->setPriority(0.7)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY),
        );

        $sitemap->add(
            Url::create(url('/trending'))
                ->setPriority(0.7)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY),
        );

        $sitemap->add(
            Url::create(url('/pricing'))
                ->setPriority(0.6)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY),
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

        $directory = dirname($targetPath);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            $this->error('Unable to create sitemap directory: ' . $directory);

            return Command::FAILURE;
        }

        $written = file_put_contents($targetPath, $sitemap->render());

        if ($written === false || ! file_exists($targetPath)) {
            $this->error('Unable to write sitemap.xml to ' . $targetPath);

            return Command::FAILURE;
        }

        $this->info('sitemap.xml generated at ' . $targetPath);

        return Command::SUCCESS;
    }
}
