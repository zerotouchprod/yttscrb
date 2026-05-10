<?php

declare(strict_types=1);

namespace App\Infrastructure\Workflow\Activities;

use Illuminate\Container\Container;
use Illuminate\Filesystem\Filesystem;
use Workflow\Activity;

final class CleanupActivity extends Activity
{
    public function execute(string $audioPath): void
    {
        /** @var Filesystem $filesystem */
        $filesystem = Container::getInstance()->make(Filesystem::class);

        if ($filesystem->exists($audioPath)) {
            $filesystem->delete($audioPath);
        }
    }
}
