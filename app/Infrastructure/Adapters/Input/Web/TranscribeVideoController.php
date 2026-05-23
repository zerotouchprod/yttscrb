<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Input\Web;

use App\Application\Ports\Output\MediaTaskRepositoryInterface;
use App\Application\Ports\Output\SubtitleProviderInterface;
use App\Application\UseCases\TranscribeVideoHandler;
use App\Domain\Entities\MediaTask;
use App\Domain\ValueObjects\TranscriptionStatus;
use App\Domain\ValueObjects\YouTubeUrl;
use App\Infrastructure\Adapters\Input\Web\Requests\TranscribeVideoRequest;
use App\Infrastructure\Adapters\Input\Web\Resources\LatestMediaTaskResource;
use App\Infrastructure\Adapters\Input\Web\Resources\MediaTaskCollection;
use App\Infrastructure\Adapters\Input\Web\Resources\MediaTaskCreatedResource;
use App\Infrastructure\Adapters\Input\Web\Resources\MediaTaskResource;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

final class TranscribeVideoController extends Controller
{
    public function __construct(
        private readonly TranscribeVideoHandler $handler,
        private readonly SubtitleProviderInterface $subtitleProvider,
        private readonly MediaTaskRepositoryInterface $mediaTaskRepository,
    ) {
    }

    public function create(TranscribeVideoRequest $request): JsonResponse
    {
        $youtubeUrl = $request->validated('youtube_url');

        if (! is_string($youtubeUrl)) {
            // Unreachable — validated as required string above.
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

        // Check free tier daily quota (10 completed transcriptions/day per user).
        // User identified by SHA-256 hash of IP address (no auth in v1.0).
        // Deduplication for existing video_id happens in handler->handle() below
        // and does NOT consume quota (PRD §9).
        $userIdentifier = hash('sha256', $request->ip() ?? '127.0.0.1');
        $completedToday = $this->handler->countCompletedToday($userIdentifier);
        if ($completedToday >= 10) {
            $now = new \DateTimeImmutable();
            $tomorrow = $now->modify('tomorrow 00:00:00');
            $retryAfter = $tomorrow->getTimestamp() - $now->getTimestamp();

            return new JsonResponse([
                'error' => [
                    'code' => 'RATE_LIMIT_EXCEEDED',
                    'message' => 'Daily limit of 10 transcriptions reached. Come back tomorrow!',
                    'details' => ['limit' => 10, 'used' => $completedToday],
                ],
            ], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => (string) $retryAfter,
            ]);
        }

        // Check video duration — reject videos longer than the configured maximum
        // to protect against excessive API costs during free MVP phase.
        // Duration is cached per video_id (1 week TTL) since YouTube durations never change.
        $maxDurationSec = (int) config('services.max_video_duration_sec', 1800);
        $videoIdStr = $url->videoId()->value();
        $durationSec = Cache::remember(
            "yt_duration_{$videoIdStr}",
            7 * 24 * 60 * 60,
            fn (): ?int => $this->subtitleProvider->extractDuration($youtubeUrl),
        );
        if ($durationSec !== null && $durationSec > $maxDurationSec) {
            return new JsonResponse([
                'error' => [
                    'code' => 'VIDEO_TOO_LONG',
                    'message' => 'Sorry, we currently only support videos up to 30 minutes long.',
                    'details' => ['max_duration_sec' => $maxDurationSec, 'video_duration_sec' => $durationSec],
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $taskId = Uuid::uuid4()->toString();
        $task = MediaTask::create($taskId, $url);

        $storedTask = $this->handler->handle($task);

        // Store user identifier AFTER handler (which may return existing completed task).
        // Only persist for newly created tasks (same id === no dedup).
        if ($storedTask->id() === $task->id()) {
            $this->handler->saveUserIdentifier($taskId, $userIdentifier);
        }

        if ($storedTask->id() !== $task->id()) {
            // Return full completed payload (same shape as status()) so the frontend
            // can render immediately without an extra polling round-trip.
            $similar = $this->mediaTaskRepository->findSimilar($storedTask->id(), limit: 5);

            return new JsonResponse(
                (new MediaTaskResource($storedTask))->withSimilar($similar)->toArray(request()),
                Response::HTTP_OK,
            );
        }

        return new JsonResponse(
            new MediaTaskCreatedResource($storedTask)->toArray(request()),
            Response::HTTP_ACCEPTED,
        );
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

        $similar = $this->mediaTaskRepository->findSimilar($task->id(), limit: 5);

        return new JsonResponse(
            (new MediaTaskResource($task))->withSimilar($similar)->toArray(request()),
        );
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

    /**
     * GET /api/history
     *
     * Query params:
     *   - page (int, default 1)
     *   - per_page (int, 1-50, default 15)
     *   - status (string|null) — filter by task status; ignored when public=1
     *   - public (string) — when "1", returns only public-completed tasks
     *     (status=completed, title not null, DMCA not removed), ignoring the status param.
     *
     *   Public requests (public=1) are cached for 60 seconds to reduce
     *   database load on the main page.
     */
    public function history(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min(50, max(1, (int) $request->query('per_page', '15')));
        $rawStatus = $request->query('status');
        $status = is_string($rawStatus) && $rawStatus !== '' ? $rawStatus : null;
        $isPublic = $request->query('public') === '1';

        $buildResponse = function () use ($isPublic, $status, $perPage, $page): array {
            $paginator = $isPublic
                ? $this->handler->listPublicCompleted($perPage, $page)
                : $this->handler->listHistory($status, $perPage, $page);

            /** @var \Illuminate\Pagination\LengthAwarePaginator<int, \App\Domain\Entities\MediaTask> $paginator */

            return new MediaTaskCollection($paginator)->toArray(request());
        };

        if ($isPublic) {
            $cacheKey = "public_history_page_{$page}_per{$perPage}";
            /** @var array<string, mixed> $responseData */
            $responseData = Cache::remember($cacheKey, 60, $buildResponse);
        } else {
            $responseData = $buildResponse();
        }

        return new JsonResponse($responseData);
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

        return new JsonResponse(new LatestMediaTaskResource($task)->toArray(request()));
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->query('q');

        if (! is_string($query) || $query === '') {
            return $this->errorResponse(
                'INVALID_QUERY',
                'Search query parameter "q" is required and must be a non-empty string.',
                ['field' => 'q', 'constraint' => 'required'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (mb_strlen($query) < 2) {
            return $this->errorResponse(
                'INVALID_QUERY',
                'Search query must be at least 2 characters.',
                ['field' => 'q', 'constraint' => 'min_length', 'min' => 2],
                Response::HTTP_BAD_REQUEST,
            );
        }

        if (mb_strlen($query) > 100) {
            return $this->errorResponse(
                'INVALID_QUERY',
                'Search query must not exceed 100 characters.',
                ['field' => 'q', 'constraint' => 'max_length', 'max' => 100],
                Response::HTTP_BAD_REQUEST,
            );
        }

        // Reject wildcard-only queries (e.g. "%%%%%%", "____")
        if (
            preg_match('/[^\x00-\x7F\pL\pN]/u', $query) === 0
            && preg_match('/[\pL\pN]/u', $query) === 0
        ) {
            return $this->errorResponse(
                'INVALID_QUERY',
                'Search query must contain at least one letter or digit.',
                ['field' => 'q', 'constraint' => 'non_wildcard'],
                Response::HTTP_BAD_REQUEST,
            );
        }

        $page = max(1, (int) $request->query('page', '1'));
        $perPage = min(50, max(1, (int) $request->query('per_page', '15')));

        $paginator = $this->handler->searchByTitle($query, $perPage, $page);

        return new JsonResponse(
            new MediaTaskCollection($paginator, ['query' => $query], $query)->toArray(request()),
        );
    }

    public function historyPage(Request $request): View
    {
        $page = max(1, (int) $request->query('page', '1'));
        $perPage = 20;

        $paginator = $this->handler->listHistory(null, $perPage, $page);

        return view('history', [
            'tasks'     => $paginator->getCollection(),
            'paginator' => $paginator,
        ]);
    }

    /**
     * @param array<string, string|int> $details
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
