<?php

declare(strict_types=1);

namespace Tests\Feature\Feature\Auth;

use App\Application\Ports\Output\WorkflowDispatcherInterface;
use App\Domain\Entities\MediaTask;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RegisterUserTest extends TestCase
{
    use RefreshDatabase;

    public function testGuestCanOpenApplicationWithoutRegistration(): void
    {
        $dispatcher = new class () implements WorkflowDispatcherInterface {
            public function dispatch(MediaTask $task): void
            {
            }
        };

        $this->app->instance(WorkflowDispatcherInterface::class, $dispatcher);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('TubeSum')
            ->assertDontSee('Register');
    }
}
