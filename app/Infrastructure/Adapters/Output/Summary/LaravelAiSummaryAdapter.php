<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Summary;

use App\Ai\Agents\YoutubeSummarizerAgent;
use App\Application\Ports\Output\SummaryProviderInterface;
use App\Domain\ValueObjects\SummaryKeyPoint;
use App\Domain\ValueObjects\SummaryOptions;
use App\Domain\ValueObjects\SummaryResult;
use App\Domain\ValueObjects\TranscriptionText;
use App\Shared\Exceptions\SummaryFailedException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Webmozart\Assert\Assert;

final class LaravelAiSummaryAdapter implements SummaryProviderInterface
{
    public function summarize(TranscriptionText $transcriptText, SummaryOptions $options): SummaryResult
    {
        try {
            $response = YoutubeSummarizerAgent::make()->prompt(
                sprintf(
                    "Summarize the following transcript in maximum %d words. Style: %s.\n\nTranscript:\n%s",
                    $options->maxWords(),
                    $options->style(),
                    $transcriptText->value(),
                ),
            );

            // When the agent implements HasStructuredOutput, the SDK returns
            // a StructuredAgentResponse — use toArray() to get the decoded data.
            Assert::isInstanceOf(
                $response,
                StructuredAgentResponse::class,
                'Expected StructuredAgentResponse from YoutubeSummarizerAgent.',
            );

            /** @var array<string, mixed> $data */
            $data = $response->toArray();
            Assert::keyExists($data, 'introduction', 'Missing key: introduction.');
            Assert::string($data['introduction'], 'introduction must be a string.');
            Assert::keyExists($data, 'key_points', 'Missing key: key_points.');
            Assert::isArray($data['key_points'], 'key_points must be an array.');

            $keyPoints = [];

            foreach ($data['key_points'] as $index => $kp) {
                Assert::isArray($kp, sprintf('key_points[%d] must be an array.', $index));
                Assert::keyExists($kp, 'timecode', sprintf('key_points[%d] missing timecode.', $index));
                Assert::keyExists($kp, 'title', sprintf('key_points[%d] missing title.', $index));
                Assert::keyExists($kp, 'details', sprintf('key_points[%d] missing details.', $index));
                Assert::string($kp['timecode'], sprintf('key_points[%d].timecode must be a string.', $index));
                Assert::string($kp['title'], sprintf('key_points[%d].title must be a string.', $index));
                Assert::string($kp['details'], sprintf('key_points[%d].details must be a string.', $index));

                $keyPoints[] = new SummaryKeyPoint(
                    timecode: $kp['timecode'],
                    title: $kp['title'],
                    details: $kp['details'],
                );
            }

            $conclusion = $data['conclusion'] ?? null;

            if ($conclusion !== null) {
                Assert::string($conclusion, 'conclusion must be a string or null.');
            }

            return new SummaryResult(
                introduction: $data['introduction'],
                keyPoints: $keyPoints,
                conclusion: $conclusion,
            );
        } catch (RuntimeException $e) {
            throw new SummaryFailedException(
                'LaravelAiSummaryAdapter failed: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }
}
