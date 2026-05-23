<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class SummaryResult
{
    /**
     * @param SummaryKeyPoint[] $keyPoints
     * @param ResourceItem[]    $resources
     * @param TutorialStep[]    $tutorialSteps
     */
    public function __construct(
        private string $introduction,
        private array $keyPoints,
        private ?string $conclusion = null,
        private array $resources = [],
        private ?ClickbaitVerdict $clickbaitVerdict = null,
        private array $tutorialSteps = [],
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
     *     tutorial_steps: array<int, array{step: int, time: string, action: string}>
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
     *     tutorial_steps?: array<int, array{step: int, time: string, action: string}>
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
        );
    }
}
