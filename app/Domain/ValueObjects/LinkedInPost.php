<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects;

final readonly class LinkedInPost
{
    public function __construct(
        private string $hook,
        private string $body,
        private string $callToAction,
    ) {
    }

    public function hook(): string
    {
        return $this->hook;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function callToAction(): string
    {
        return $this->callToAction;
    }

    /**
     * @return array{hook: string, body: string, call_to_action: string}
     */
    public function toArray(): array
    {
        return [
            'hook'           => $this->hook,
            'body'           => $this->body,
            'call_to_action' => $this->callToAction,
        ];
    }

    /**
     * @param array{hook: string, body: string, call_to_action: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            hook:          $data['hook'],
            body:          $data['body'],
            callToAction:  $data['call_to_action'],
        );
    }
}
