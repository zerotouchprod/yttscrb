<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class SummaryResult
{
    /**
     * @param SummaryKeyPoint[] $keyPoints
     * @param ResourceItem[]    $resources
     * @param TutorialStep[]    $tutorialSteps
     * @param SummaryChapter[]  $chapters
     * @param Flashcard[]       $flashCards
     * @param HighlightMoment[] $highlights
     * @param string[]          $topics
     * @param BlogPost|null     $blogPost
     * @param LinkedInPost|null $linkedInPost
     */
    public function __construct(
        private string $introduction,
        private array $keyPoints,
        private ?string $conclusion = null,
        private array $resources = [],
        private ?ClickbaitVerdict $clickbaitVerdict = null,
        private array $tutorialSteps = [],
        private array $chapters = [],
        private array $flashCards = [],
        private array $highlights = [],
        private ?ContentMeta $contentMeta = null,
        private array $topics = [],
        private ?BlogPost $blogPost = null,
        private ?LinkedInPost $linkedInPost = null,
    ) {
    }

    public function introduction(): string
    {
        return $this->introduction;
    }

    /**
     * @return SummaryKeyPoint[]
     */
    public function keyPoints(): array
    {
        return $this->keyPoints;
    }

    public function conclusion(): ?string
    {
        return $this->conclusion;
    }

    /**
     * @return ResourceItem[]
     */
    public function resources(): array
    {
        return $this->resources;
    }

    public function clickbaitVerdict(): ?ClickbaitVerdict
    {
        return $this->clickbaitVerdict;
    }

    /**
     * @return TutorialStep[]
     */
    public function tutorialSteps(): array
    {
        return $this->tutorialSteps;
    }

    /**
     * @return SummaryChapter[]
     */
    public function chapters(): array
    {
        return $this->chapters;
    }

    /**
     * @return Flashcard[]
     */
    public function flashCards(): array
    {
        return $this->flashCards;
    }

    /**
     * @return HighlightMoment[]
     */
    public function highlights(): array
    {
        return $this->highlights;
    }

    public function contentMeta(): ?ContentMeta
    {
        return $this->contentMeta;
    }

    /**
     * @return string[]
     */
    public function topics(): array
    {
        return $this->topics;
    }

    public function blogPost(): ?BlogPost
    {
        return $this->blogPost;
    }

    public function linkedInPost(): ?LinkedInPost
    {
        return $this->linkedInPost;
    }

    /**
     * Сериализация для хранения в JSONB-колонке.
     *
     * @internal Used only by persistence layer. HTTP serialization is handled by
     *           {@see \App\Infrastructure\Adapters\Input\Web\Resources\SummaryResource}.
     *
     * @return array{
     *     introduction: string,
     *     key_points: array<int, array{timecode: string, title: string, details: string}>,
     *     conclusion: string|null,
     *     resources: array<int, array{type: string, name: string, url: string|null}>,
     *     clickbait_verdict: array{score: int, comment: string}|null,
     *     tutorial_steps: array<int, array{step: int, time: string, action: string}>,
     *     chapters: array<int, array{title: string, start_timecode: string, end_timecode: string}>,
     *     flashcards: array<int, array{question: string, answer: string, source_timecode: string, difficulty: string}>,
     *     highlights: array<int, array{timecode: string, title: string, why_notable: string, category: string}>,
     *     content_meta: array{complexity: string, reading_time_minutes: int, jargon_density: string, target_audience: string}|null,
     *     topics: string[],
     *     blog_post: array{title: string, sections: array<int, array{heading: string, body: string}>}|null,
     *     linkedin_post: array{hook: string, body: string, call_to_action: string}|null
     * }
     */
    public function toArray(): array
    {
        return [
            'introduction'      => $this->introduction,
            'key_points'        => array_map(fn (SummaryKeyPoint $kp) => $kp->toArray(), $this->keyPoints),
            'conclusion'        => $this->conclusion,
            'resources'         => array_map(fn (ResourceItem $r) => $r->toArray(), $this->resources),
            'clickbait_verdict' => $this->clickbaitVerdict?->toArray(),
            'tutorial_steps'    => array_map(fn (TutorialStep $s) => $s->toArray(), $this->tutorialSteps),
            'chapters'          => array_map(fn (SummaryChapter $ch) => $ch->toArray(), $this->chapters),
            'flashcards'        => array_map(fn (Flashcard $fc) => $fc->toArray(), $this->flashCards),
            'highlights'        => array_map(fn (HighlightMoment $hm) => $hm->toArray(), $this->highlights),
            'content_meta'      => $this->contentMeta?->toArray(),
            'topics'            => $this->topics,
            'blog_post'         => $this->blogPost?->toArray(),
            'linkedin_post'     => $this->linkedInPost?->toArray(),
        ];
    }

    /**
     * Восстановление из JSONB-массива (используется репозиторием при чтении из БД).
     *
     * @param array{
     *     introduction: string,
     *     key_points: array<int,array{timecode: string, title: string, details: string}>,
     *     conclusion?: string|null,
     *     resources?: array<int, array{type: string, name: string, url?: string|null}>,
     *     clickbait_verdict?: array{score: int, comment: string}|null,
     *     tutorial_steps?: array<int, array{step: int, time: string, action: string}>,
     *     chapters?: array<int, array{title: string, start_timecode: string, end_timecode: string}>,
     *     flashcards?: array<int, array{question: string, answer: string, source_timecode: string, difficulty?: string}>,
     *     highlights?: array<int, array{timecode: string, title: string, why_notable: string, category?: string}>,
     *     content_meta?: array{complexity: string, reading_time_minutes: int, jargon_density: string, target_audience: string}|null,
     *     topics?: string[],
     *     blog_post?: array{title: string, sections: array<int, array{heading: string, body: string}>}|null,
     *     linkedin_post?: array{hook: string, body: string, call_to_action: string}|null
     * } $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            introduction: $data['introduction'],
            keyPoints: array_map(
                fn (array $kp) => SummaryKeyPoint::fromArray($kp),
                $data['key_points'],
            ),
            conclusion: $data['conclusion'] ?? null,
            resources: array_map(
                fn (array $r) => ResourceItem::fromArray($r),
                $data['resources'] ?? [],
            ),
            clickbaitVerdict: isset($data['clickbait_verdict'])
                ? ClickbaitVerdict::fromArray($data['clickbait_verdict'])
                : null,
            tutorialSteps: array_map(
                fn (array $s) => TutorialStep::fromArray($s),
                $data['tutorial_steps'] ?? [],
            ),
            chapters: array_map(
                fn (array $ch) => SummaryChapter::fromArray($ch),
                $data['chapters'] ?? [],
            ),
            flashCards: array_map(
                fn (array $fc) => Flashcard::fromArray($fc),
                $data['flashcards'] ?? [],
            ),
            highlights: array_map(
                fn (array $hm) => HighlightMoment::fromArray($hm),
                $data['highlights'] ?? [],
            ),
            contentMeta: isset($data['content_meta'])
                ? ContentMeta::fromArray($data['content_meta'])
                : null,
            topics: $data['topics'] ?? [],
            blogPost: isset($data['blog_post'])
                ? BlogPost::fromArray($data['blog_post'])
                : null,
            linkedInPost: isset($data['linkedin_post'])
                ? LinkedInPost::fromArray($data['linkedin_post'])
                : null,
        );
    }
}
