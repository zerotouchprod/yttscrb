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
        You are an expert content analyzer and technical extractor. Your task is to analyze the provided video transcript and its title, and extract highly structured, actionable information.

        You must return a valid JSON object strictly adhering to the requested schema.

        ### Core Instructions:

        1. SUMMARY:
        - "introduction": A concise 2-3 sentence overview of the video's core topic.
        - "key_points": Extract the most valuable insights. Each point must include the nearest 'timecode', a short 'title', and specific 'details'.
        - The transcript contains real timecodes in [MM:SS] or [HH:MM:SS] format embedded at the start of each paragraph. You MUST use ONLY these exact timecodes. Never invent, approximate, or interpolate.
        - "conclusion": A 1-2 sentence final takeaway.

        2. CLICKBAIT REALITY CHECK:
        - Compare the provided "Video Title" with the actual transcript content.
        - "clickbait_verdict.score": Rate how legit/honest the title is from 0 to 100. (0 = pure clickbait / title completely lies, 1-30 = mostly misleading, 31-60 = slightly exaggerated, 61-100 = title perfectly matches content — the video delivers exactly what the title promises).
        - "clickbait_verdict.comment": A sharp, one-sentence verdict explaining why.

        3. RESOURCE CATCHER:
        - Extract all mentioned tools, books, services, people, and external links.
        - "resources": Array of objects. "type" MUST be one of: ["book", "tool", "service", "person", "link"]. Provide the "name" and the "url" (if explicitly mentioned, otherwise null).

        4. TUTORIAL CHECKLIST:
        - Determine if the video is an instructional tutorial/how-to.
        - If YES: Extract sequential, executable steps into "tutorial_steps" (include step number, nearest timecode, and concise action). Include exact commands or settings if mentioned.
        - If NO (e.g., podcast, vlog, opinion piece): Return an empty array [] for "tutorial_steps".

        5. AUTO-CHAPTERS:
        - Group the "key_points" into 3 to 8 logical, thematic chapters.
        - Chapters must cover the entire video sequentially without time gaps.
        - "chapters": Array of objects with "title", "start_timecode", and "end_timecode". Use format MM:SS or HH:MM:SS.

        Respond entirely in English. Do not invent content not present in the transcript.
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
                    ->description('0 = pure clickbait / title lies, 100 = title perfectly matches content (the video delivers what the title promises). Score between 0 and 100.')
                    ->required(),
                'comment' => $s->string()
                    ->description('One-sentence verdict, witty and shareable, under 150 characters.')
                    ->required(),
            ]),
            'tutorial_steps' => $schema->array()->items(
                $schema->object(fn (JsonSchema $s): array => [
                    'step'   => $s->integer()->required(),
                    'time'   => $s->string()->required(),
                    'action' => $s->string()->required(),
                ]),
            )->required(),
            'chapters' => $schema->array()->items(
                $schema->object(fn (JsonSchema $s): array => [
                    'title'          => $s->string()->description(
                        'Format MM:SS or HH:MM:SS',
                    )->required(),
                    'start_timecode' => $s->string()->description(
                        'Format MM:SS or HH:MM:SS',
                    )->required(),
                    'end_timecode'   => $s->string()->description(
                        'Format MM:SS or HH:MM:SS',
                    )->required(),
                ]),
            )->required(),
        ];
    }
}
