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
        - The transcript contains real timecodes in [MM:SS] or [HH:MM:SS] format embedded at the
          start of each paragraph. You MUST use ONLY these exact timecodes when referencing moments
          in the video. Never invent, approximate, or interpolate timecodes.
        - Extract 5-10 key points that represent the most important moments in the transcript.
          For each key point, copy the nearest timecode that precedes the relevant content.
          For videos under 1 hour use MM:SS format (e.g. "03:45").
          For videos 1 hour or longer use HH:MM:SS format (e.g. "01:15:30").
        - Each key point must have: timecode (MM:SS or HH:MM:SS), a short title, and a 1-2 sentence detail.
        - Write a brief conclusion if the transcript has a clear takeaway.
        - Respond entirely in English.
        - Do not invent content not present in the transcript.

        ## Resource Extraction
        - Scan the transcript for mentions of books, tools, libraries, services, software, people
          (authors, speakers, experts), and external links. Extract each into the "resources" array.
        - For each resource, classify it as one of: "book", "tool", "service", "person", "link".
        - If a URL is explicitly mentioned or strongly implied (e.g. "github.com/foo"), include it
          in the "url" field. Otherwise set url to null.
        - Only include resources explicitly referenced by the speakers. Do not invent.

        ## Clickbait Assessment (only if a video title is provided below)
        - If a "Video Title" is present in the prompt, compare it against the actual transcript content.
        - Score from 0 to 100: 0 = total clickbait / title lies completely, 100 = title perfectly
          matches content. If no title is provided, omit the clickbait_verdict field entirely.
        - Write a one-sentence verdict comment that is sharp and specific. If the title is misleading,
          explain why in a witty, quotable way. If the title is honest, acknowledge it.
        - The verdict comment should be under 150 characters and suitable for sharing as a screenshot.
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
            'resources'  => $schema->array()->items(
                $schema->object(fn (JsonSchema $s): array => [
                    'type' => $s->string()->description(
                        'One of: book, tool, service, person, link',
                    )->required(),
                    'name' => $s->string()->required(),
                    'url'  => $s->string()->nullable(),
                ]),
            )->required(),
            'clickbait_verdict' => $schema->object(fn (JsonSchema $s): array => [
                'score'   => $s->integer()
                    ->description('0 = pure clickbait, 100 = title perfectly matches content. Score between 0 and 100.')
                    ->required(),
                'comment' => $s->string()
                    ->description('One-sentence verdict, witty and shareable, under 150 characters.')
                    ->required(),
            ]),
        ];
    }
}
