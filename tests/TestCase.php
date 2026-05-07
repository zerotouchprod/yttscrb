<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $basePath = Application::inferBasePath();
        $envPath = $basePath . '/.env';

        if (! file_exists($envPath) && file_exists($basePath . '/.env.example')) {
            copy($basePath . '/.env.example', $envPath);
        }

        /** @var Application $app */
        $app = require $basePath . '/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
