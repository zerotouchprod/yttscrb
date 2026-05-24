<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\Summary;

use App\Ai\Agents\YoutubeSummarizerAgent;
use App\Application\Ports\Output\SummaryProviderInterface;
use App\Domain\ValueObjects\ClickbaitVerdict;
use App\Domain\ValueObjects\ContentMeta;
use App\Domain\ValueObjects\Flashcard;
use App\Domain\ValueObjects\HighlightMoment;
use App\Domain\ValueObjects\ResourceItem;
use App\Domain\ValueObjects\SummaryChapter;
use App\Domain\ValueObjects\SummaryKeyPoint;
use App\Domain\ValueObjects\SummaryOptions;
use App\Domain\ValueObjects\SummaryResult;
use App\Domain\ValueObjects\TranscriptionText;
use App\Domain\ValueObjects\TutorialStep;
use App\Shared\Exceptions\SummaryFailedException;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Webmozart\Assert\Assert;

final class LaravelAiSummaryAdapter implements SummaryProviderInterface
{
    public function summarize(TranscriptionText $transcriptText, SummaryOptions $options): SummaryResult
    {
        try {
            $prompt = sprintf(
                "Summarize the following transcript in maximum %d words. Style: %s.\n\nTranscript:\n%s",
                $options->maxWords(),
                $options->style(),
                $transcriptText->value(),
            );

            if ($options->videoTitle() !== null) {
                $prompt .= "\n\nVideo Title: " . $options->videoTitle();
            }

            $response = YoutubeSummarizerAgent::make()->prompt($prompt);

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

            // Parse resources
            Assert::keyExists($data, 'resources', 'Missing key: resources.');
            Assert::isArray($data['resources'], 'resources must be an array.');

            $resources = [];

            foreach ($data['resources'] as $index => $r) {
                Assert::isArray($r, sprintf('resources[%d] must be an array.', $index));
                Assert::keyExists($r, 'type', sprintf('resources[%d] missing type.', $index));
                Assert::keyExists($r, 'name', sprintf('resources[%d] missing name.', $index));
                Assert::string($r['type'], sprintf('resources[%d].type must be a string.', $index));
                Assert::string($r['name'], sprintf('resources[%d].name must be a string.', $index));

                $resources[] = new ResourceItem(
                    type: $r['type'],
                    name: $r['name'],
                    url: isset($r['url']) && is_string($r['url']) ? $r['url'] : null,
                );
            }

            // Parse clickbait verdict (optional — only present when title was provided)
            $clickbaitVerdict = null;

            if (isset($data['clickbait_verdict'])) {
                Assert::isArray($data['clickbait_verdict'], 'clickbait_verdict must be an array.');
                Assert::keyExists($data['clickbait_verdict'], 'score', 'clickbait_verdict missing score.');
                Assert::keyExists($data['clickbait_verdict'], 'comment', 'clickbait_verdict missing comment.');
                Assert::integer($data['clickbait_verdict']['score'], 'clickbait_verdict.score must be an integer.');
                Assert::string($data['clickbait_verdict']['comment'], 'clickbait_verdict.comment must be a string.');

                $clickbaitVerdict = new ClickbaitVerdict(
                    score: $data['clickbait_verdict']['score'],
                    comment: $data['clickbait_verdict']['comment'],
                );
            }

            // Parse tutorial steps
            Assert::keyExists($data, 'tutorial_steps', 'Missing key: tutorial_steps.');
            Assert::isArray($data['tutorial_steps'], 'tutorial_steps must be an array.');

            $tutorialSteps = [];

            foreach ($data['tutorial_steps'] as $index => $s) {
                Assert::isArray($s, sprintf('tutorial_steps[%d] must be an array.', $index));
                Assert::keyExists($s, 'step', sprintf('tutorial_steps[%d] missing step.', $index));
                Assert::keyExists($s, 'time', sprintf('tutorial_steps[%d] missing time.', $index));
                Assert::keyExists($s, 'action', sprintf('tutorial_steps[%d] missing action.', $index));
                Assert::integer($s['step'], sprintf('tutorial_steps[%d].step must be an integer.', $index));
                Assert::string($s['time'], sprintf('tutorial_steps[%d].time must be a string.', $index));
                Assert::string($s['action'], sprintf('tutorial_steps[%d].action must be a string.', $index));

                $tutorialSteps[] = new TutorialStep(
                    step: $s['step'],
                    time: $s['time'],
                    action: $s['action'],
                );
            }

            // Parse chapters
            Assert::keyExists($data, 'chapters', 'Missing key: chapters.');
            Assert::isArray($data['chapters'], 'chapters must be an array.');

            $chapters = [];
            foreach ($data['chapters'] as $index => $ch) {
                Assert::isArray($ch, sprintf('chapters[%d] must be an array.', $index));
                Assert::keyExists($ch, 'title', sprintf('chapters[%d] missing title.', $index));
                Assert::keyExists($ch, 'start_timecode', sprintf('chapters[%d] missing start_timecode.', $index));
                Assert::keyExists($ch, 'end_timecode', sprintf('chapters[%d] missing end_timecode.', $index));
                Assert::string($ch['title'], sprintf('chapters[%d].title must be a string.', $index));
                Assert::string($ch['start_timecode'], sprintf('chapters[%d].start_timecode must be a string.', $index));
                Assert::string($ch['end_timecode'], sprintf('chapters[%d].end_timecode must be a string.', $index));

                $chapters[] = new SummaryChapter(
                    title: $ch['title'],
                    startTimecode: $ch['start_timecode'],
                    endTimecode: $ch['end_timecode'],
                );
            }

            // Parse flashcards
            Assert::keyExists($data, 'flashcards', 'Missing key: flashcards.');
            Assert::isArray($data['flashcards'], 'flashcards must be an array.');

            $flashCards = [];
            foreach ($data['flashcards'] as $index => $fc) {
                Assert::isArray($fc, sprintf('flashcards[%d] must be an array.', $index));
                Assert::keyExists($fc, 'question', sprintf('flashcards[%d] missing question.', $index));
                Assert::keyExists($fc, 'answer', sprintf('flashcards[%d] missing answer.', $index));
                Assert::keyExists($fc, 'source_timecode', sprintf('flashcards[%d] missing source_timecode.', $index));
                Assert::keyExists($fc, 'difficulty', sprintf('flashcards[%d] missing difficulty.', $index));
                Assert::string($fc['question'], sprintf('flashcards[%d].question must be a string.', $index));
                Assert::string($fc['answer'], sprintf('flashcards[%d].answer must be a string.', $index));
                Assert::string($fc['source_timecode'], sprintf('flashcards[%d].source_timecode must be a string.', $index));
                Assert::string($fc['difficulty'], sprintf('flashcards[%d].difficulty must be a string.', $index));

                $flashCards[] = new Flashcard(
                    question: $fc['question'],
                    answer: $fc['answer'],
                    sourceTimecode: $fc['source_timecode'],
                    difficulty: $fc['difficulty'],
                );
            }

            // Parse highlights
            Assert::keyExists($data, 'highlights', 'Missing key: highlights.');
            Assert::isArray($data['highlights'], 'highlights must be an array.');

            $highlights = [];
            foreach ($data['highlights'] as $index => $hm) {
                Assert::isArray($hm, sprintf('highlights[%d] must be an array.', $index));
                Assert::keyExists($hm, 'timecode', sprintf('highlights[%d] missing timecode.', $index));
                Assert::keyExists($hm, 'title', sprintf('highlights[%d] missing title.', $index));
                Assert::keyExists($hm, 'why_notable', sprintf('highlights[%d] missing why_notable.', $index));
                Assert::keyExists($hm, 'category', sprintf('highlights[%d] missing category.', $index));
                Assert::string($hm['timecode'], sprintf('highlights[%d].timecode must be a string.', $index));
                Assert::string($hm['title'], sprintf('highlights[%d].title must be a string.', $index));
                Assert::string($hm['why_notable'], sprintf('highlights[%d].why_notable must be a string.', $index));
                Assert::string($hm['category'], sprintf('highlights[%d].category must be a string.', $index));

                $highlights[] = new HighlightMoment(
                    timecode: $hm['timecode'],
                    title: $hm['title'],
                    whyNotable: $hm['why_notable'],
                    category: $hm['category'],
                );
            }

            // Parse content_meta (optional)
            $contentMeta = null;
            if (isset($data['content_meta'])) {
                Assert::isArray($data['content_meta'], 'content_meta must be an array.');
                Assert::keyExists($data['content_meta'], 'complexity', 'content_meta missing complexity.');
                Assert::keyExists($data['content_meta'], 'reading_time_minutes', 'content_meta missing reading_time_minutes.');
                Assert::keyExists($data['content_meta'], 'jargon_density', 'content_meta missing jargon_density.');
                Assert::keyExists($data['content_meta'], 'target_audience', 'content_meta missing target_audience.');
                Assert::string($data['content_meta']['complexity'], 'content_meta.complexity must be a string.');
                Assert::integer($data['content_meta']['reading_time_minutes'], 'content_meta.reading_time_minutes must be an integer.');
                Assert::string($data['content_meta']['jargon_density'], 'content_meta.jargon_density must be a string.');
                Assert::string($data['content_meta']['target_audience'], 'content_meta.target_audience must be a string.');

                $contentMeta = new ContentMeta(
                    complexity: $data['content_meta']['complexity'],
                    readingTimeMinutes: $data['content_meta']['reading_time_minutes'],
                    jargonDensity: $data['content_meta']['jargon_density'],
                    targetAudience: $data['content_meta']['target_audience'],
                );
            }

            return new SummaryResult(
                introduction: $data['introduction'],
                keyPoints: $keyPoints,
                conclusion: $conclusion,
                resources: $resources,
                clickbaitVerdict: $clickbaitVerdict,
                tutorialSteps: $tutorialSteps,
                chapters: $chapters,
                flashCards: $flashCards,
                highlights: $highlights,
                contentMeta: $contentMeta,
            );
        } catch (RuntimeException $e) {
            throw new SummaryFailedException(
                'LaravelAiSummaryAdapter failed: ' . $e->getMessage(),
                previous: $e,
            );
        }
    }
}
