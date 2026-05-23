<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class TutorialStep
{
    public function __construct(
        public int $step,
        public string $time,
        public string $action,
    ) {
    }

    /**
     * @return array{step: int, time: string, action: string}
     */
    public function toArray(): array
    {
        return [
            'step'   => $this->step,
            'time'   => $this->time,
            'action' => $this->action,
        ];
    }

    /**
     * @param array{step: int, time: string, action: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            step: (int) $data['step'],
            time: $data['time'],
            action: $data['action'],
        );
    }
}
