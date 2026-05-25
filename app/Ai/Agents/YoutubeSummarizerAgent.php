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

        6. FLASHCARDS (for study/memorization):
        - Extract 5-15 question-answer pairs suitable for Anki/flashcard study.
        - Each card: "question" (based on a key concept from the video), "answer" (concise, directly from the transcript), "source_timecode" (where the answer appears), "difficulty" (one of: "easy", "medium", "hard").
        - Focus on factual, testable knowledge — definitions, steps, comparisons, numbers.
        - If the video has no study-worthy content (pure entertainment, vlog), return an empty array.
        - "flashcards": Array of objects with "question", "answer", "source_timecode", "difficulty".

        7. HIGHLIGHTS (best moments):
        - Pick the 3 most impactful, surprising, funny, or insightful moments from the video.
        - These are NOT the same as key_points — they are emotional/entertainment peaks: the shocking revelation, the joke, the mic-drop quote, the "aha!" moment.
        - Each highlight: "timecode", "title" (short label), "why_notable" (one sentence explaining why this moment stands out), "category" (one of: "surprise", "insight", "humor", "revelation", "quote").
        - "highlights": Array of objects with "timecode", "title", "why_notable", "category".

        8. CONTENT META:
        - Evaluate the transcript's complexity and return:
        - "complexity": One of "beginner", "intermediate", "advanced", "expert".
        - "reading_time_minutes": Estimated reading time in minutes (based on ~200 words per minute).
        - "jargon_density": One of "low", "moderate", "high".
        - "target_audience": A 1-2 sentence description of who this video is for (e.g., "Software developers with basic Kubernetes experience").
        - "content_meta": Object with "complexity", "reading_time_minutes", "jargon_density", "target_audience".

        9. BLOG POST:
        - Write a structured blog article based on the transcript's key insights.
        - Style: informative, conversational, 400-600 words total.
        - Structure:
        - "title": A compelling blog title (not the video title).
        - "sections": Array of 3-5 sections, each with a clear "heading" (H2 level) and "body" (1-3 paragraphs).
        - Do NOT rehash the transcript verbatim. Synthesise and rewrite for a blog reader.
        - Return: "blog_post": {"title": "...", "sections": [{"heading": "...", "body": "..."}]}

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
            'flashcards' => $schema->array()->items(
                $schema->object(fn (JsonSchema $s): array => [
                    'question'        => $s->string()->required(),
                    'answer'          => $s->string()->required(),
                    'source_timecode' => $s->string()->required(),
                    'difficulty'      => $s->string()->description(
                        'One of: easy, medium, hard',
                    )->required(),
                ]),
            )->required(),
            'highlights' => $schema->array()->items(
                $schema->object(fn (JsonSchema $s): array => [
                    'timecode'    => $s->string()->required(),
                    'title'       => $s->string()->required(),
                    'why_notable' => $s->string()->required(),
                    'category'    => $s->string()->description(
                        'One of: surprise, insight, humor, revelation, quote',
                    )->required(),
                ]),
            )->required(),
            'content_meta' => $schema->object(fn (JsonSchema $s): array => [
                'complexity'           => $s->string()->description(
                    'One of: beginner, intermediate, advanced, expert',
                )->required(),
                'reading_time_minutes' => $s->integer()->required(),
                'jargon_density'       => $s->string()->description(
                    'One of: low, moderate, high',
                )->required(),
                'target_audience'      => $s->string()->required(),
            ]),
            'blog_post' => $schema->object(fn (JsonSchema $s): array => [
                'title'    => $s->string()->required(),
                'sections' => $s->array()->items(
                    $schema->object(fn (JsonSchema $s): array => [
                        'heading' => $s->string()->required(),
                        'body'    => $s->string()->required(),
                    ]),
                )->required(),
            ]),
        ];
    }
}
