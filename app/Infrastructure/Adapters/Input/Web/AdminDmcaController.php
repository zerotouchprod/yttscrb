<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\Response;

final class AdminDmcaController extends Controller
{
    public function __construct(
        private readonly MediaTaskRepositoryInterface $repository,
    ) {
    }

    /**
     * Remove a task from public index due to a DMCA takedown request.
     * Protected by ADMIN_TOKEN env variable.
     */
    public function remove(Request $request, string $id): JsonResponse
    {
        $adminToken = config('app.admin_token');

        if (
            ! is_string($adminToken)
            || $adminToken === ''
            || $request->bearerToken() !== $adminToken
        ) {
            return new JsonResponse(
                ['error' => ['code' => 'UNAUTHORIZED', 'message' => 'Invalid admin token.']],
                Response::HTTP_UNAUTHORIZED,
            );
        }

        $task = $this->repository->findById($id);

        if ($task === null) {
            return new JsonResponse(
                ['error' => ['code' => 'TASK_NOT_FOUND', 'message' => 'Task not found.']],
                Response::HTTP_NOT_FOUND,
            );
        }

        $task->removeForDmca();
        $this->repository->save($task);

        return new JsonResponse(['message' => 'Task removed from public index.'], Response::HTTP_OK);
    }
}
