<?php

declare(strict_types=1);

namespace App\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Provider;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Promptable;

#[Provider([Lab::DeepSeek, Lab::Groq, Lab::OpenAI, Lab::Anthropic])]
#[Model('deepseek-v4-flash')]
#[Temperature(0.3)]
#[Timeout(120)]
final class YoutubeSummarizerAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    public function instructions(): string
    {
        return <<<'PROMPT'
        You are an expert assistant that summarizes YouTube video transcripts.

        Rules:
        - Write a concise introduction paragraph (2-4 sentences).
        - Extract key points from the transcript and assign each a timecode in "MM:SS" or "HH:MM:SS" format.
          Use the timecodes that appear naturally in the transcript content. If no timecodes
          are present, distribute them proportionally based on transcript position.
          For videos under 1 hour use MM:SS (e.g. "03:45"). For videos 1 hour or longer use HH:MM:SS (e.g. "01:15:30").
        - Each key point must have: timecode (MM:SS or HH:MM:SS), a short title, and a 1-2 sentence detail.
        - Write a brief conclusion if the transcript has a clear takeaway.
        - Respond entirely in English.
        - Do not invent content not present in the transcript.
        PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'introduction' => $schema->string()->required(),
            'key_points'   => $schema->array()->items(
                $schema->object(fn (JsonSchema $s): array => [
                    'timecode' => $s->string()->description(
                        'Format MM:SS (e.g. "03:45") for videos under 1 hour, '
                        . 'or HH:MM:SS (e.g. "01:15:30") for longer videos',
                    )->required(),
                    'title'    => $s->string()->required(),
                    'details'  => $s->string()->required(),
                ]),
            )->required(),
            'conclusion' => $schema->string(),
        ];
    }
}
