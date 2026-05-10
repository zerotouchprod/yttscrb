<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web;

use App\Application\UseCases\TranscribeVideoHandler;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\TranscriptionStatus;
use App\Domain\ValueObjects\YouTubeUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

final class TranscribeVideoController extends Controller
{
    public function __construct(
        private readonly TranscribeVideoHandler $handler,
    ) {
    }

    public function create(Request $request): JsonResponse
    {
        $youtubeUrl = $request->input('youtube_url');

        if (! is_string($youtubeUrl) || $youtubeUrl === '') {
            return $this->errorResponse(
                'INVALID_YOUTUBE_URL',
                'The provided URL is not a valid YouTube video URL.',
                ['field' => 'youtube_url', 'constraint' => 'format'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        try {
            $url = new YouTubeUrl($youtubeUrl);
        } catch (InvalidArgumentException) {
            return $this->errorResponse(
                'INVALID_YOUTUBE_URL',
                'The provided URL is not a valid YouTube video URL.',
                ['field' => 'youtube_url', 'constraint' => 'format'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Check free tier monthly quota (10 completed transcriptions/month).
        // Deduplication for existing video_id happens in handler->handle() below
        // and does NOT consume quota (PRD §9).
        $completedThisMonth = $this->handler->countCompletedThisMonth();
        if ($completedThisMonth >= 10) {
            $now = new \DateTimeImmutable();
            $firstOfNextMonth = $now->modify('first day of next month')->setTime(0, 0);
            $retryAfter = $firstOfNextMonth->getTimestamp() - $now->getTimestamp();

            return new JsonResponse([
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Monthly limit of 10 transcriptions reached.',
                    'details' => ['limit' => 10, 'used' => $completedThisMonth],
                ],
            ], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => (string) $retryAfter,
            ]);
        }

        $taskId = Uuid::uuid4()->toString();
        $task = MediaTask::create($taskId, $url);

        $storedTask = $this->handler->handle($task);

        if ($storedTask->id() !== $task->id()) {
            // Return full completed payload (same shape as status()) so the frontend
            // can render immediately without an extra polling round-trip.
            return new JsonResponse([
                'task_id'      => $storedTask->id(),
                'status'       => $storedTask->status()->value,
                'youtube_url'  => $storedTask->youtubeUrl()->value(),
                'video_id'     => $storedTask->youtubeUrl()->videoId()->value(),
                'title'        => $storedTask->title(),
                'duration_sec' => $storedTask->durationSec(),
                'result'       => [
                    'transcript' => $storedTask->resultText()?->value(),
                    'summary'    => $storedTask->summary(),
                    'word_count' => $storedTask->resultText()?->wordCount(),
                ],
                'completed_at' => $storedTask->completedAt()?->format('c'),
                '_links'       => [
                    'self'         => "/api/transcribe/{$storedTask->id()}",
                    'download_txt' => "/api/transcribe/{$storedTask->id()}/download",
                ],
            ], Response::HTTP_OK);
        }

        return new JsonResponse([
            'task_id' => $storedTask->id(),
            'status' => $storedTask->status()->value,
            'youtube_url' => $storedTask->youtubeUrl()->value(),
            'created_at' => $storedTask->createdAt()->format('c'),
            '_links' => [
                'status' => "/api/transcribe/{$storedTask->id()}",
            ],
        ], Response::HTTP_ACCEPTED);
    }

    public function status(string $id): JsonResponse
    {
        $task = $this->handler->findTask($id);

        if ($task === null) {
            return $this->errorResponse(
                'TASK_NOT_FOUND',
                'Task not found.',
                [],
                Response::HTTP_NOT_FOUND,
            );
        }

        $response = [
            'task_id' => $task->id(),
            'status' => $task->status()->value,
            'youtube_url' => $task->youtubeUrl()->value(),
            'video_id' => $task->youtubeUrl()->videoId()->value(),
            'title' => $task->title(),
            'created_at' => $task->createdAt()->format('c'),
            '_links' => [
                'self' => "/api/transcribe/{$task->id()}",
            ],
        ];

        if ($task->status() === TranscriptionStatus::Completed) {
            $response['duration_sec'] = $task->durationSec();
            $response['result'] = [
                'transcript' => $task->resultText()?->value(),
                'summary' => $task->summary(),
                'word_count' => $task->resultText()?->wordCount(),
            ];
            $response['completed_at'] = $task->completedAt()?->format('c');
            $response['_links']['download_txt'] = "/api/transcribe/{$task->id()}/download";
        }

        if ($task->status() === TranscriptionStatus::Processing) {
            $response['estimated_completion_sec'] = 90;
        }

        if ($task->status() === TranscriptionStatus::Failed) {
            $response['error_message'] = $task->errorMessage();
            $response['failed_at'] = $task->failedAt()?->format('c');
        }

        return new JsonResponse($response);
    }

    public function download(string $id): Response
    {
        $task = $this->handler->findTask($id);

        if ($task === null || $task->status() !== TranscriptionStatus::Completed) {
            return $this->errorResponse(
                'TASK_NOT_FOUND',
                'Task not found or not completed.',
                [],
                Response::HTTP_NOT_FOUND,
            );
        }

        $transcript = $task->resultText()?->value() ?? '';
        $videoId = $task->youtubeUrl()->videoId()->value();

        return response($transcript, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"transcript-{$videoId}.txt\"",
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min(50, max(1, (int) $request->query('per_page', '15')));
        $rawStatus = $request->query('status');
        $status = is_string($rawStatus) && $rawStatus !== '' ? $rawStatus : null;

        $paginator = $this->handler->listHistory($status, $perPage, $page);

        /** @var list<array{task_id: string, youtube_url: string, title: string|null, status: string, duration_sec: int|null, created_at: string|null, completed_at: string|null}> $data */
        $data = [];
        foreach ($paginator->getCollection() as $task) {
            /** @var \App\Domain\Entities\MediaTask $task */
            $data[] = [
                'task_id' => $task->id(),
                'youtube_url' => $task->youtubeUrl()->value(),
                'title' => $task->title(),
                'status' => $task->status()->value,
                'duration_sec' => $task->durationSec(),
                'created_at' => $task->createdAt()->format('c'),
                'completed_at' => $task->completedAt()?->format('c'),
            ];
        }

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            '_links' => [
                'first' => '/api/history?page=1',
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
                'last' => '/api/history?page=' . $paginator->lastPage(),
            ],
        ]);
    }

    public function latest(): JsonResponse
    {
        $task = $this->handler->findLatestCompleted();

        if ($task === null) {
            return new JsonResponse([
                'task_id' => null,
                'status' => null,
                'message' => 'No completed transcriptions yet.',
            ]);
        }

        return new JsonResponse([
            'task_id' => $task->id(),
            'youtube_url' => $task->youtubeUrl()->value(),
            'title' => $task->title(),
            'status' => $task->status()->value,
            'duration_sec' => $task->durationSec(),
            'result' => [
                'transcript' => $task->resultText()?->value(),
                'summary' => $task->summary(),
                'word_count' => $task->resultText()?->wordCount(),
            ],
            'created_at' => $task->createdAt()->format('c'),
            'completed_at' => $task->completedAt()?->format('c'),
            '_links' => [
                'download_txt' => "/api/transcribe/{$task->id()}/download",
            ],
        ]);
    }

    /**
     * @param array<string, string> $details
     */
    private function errorResponse(string $code, string $message, array $details, int $status): JsonResponse
    {
        return new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ], $status);
    }
}
