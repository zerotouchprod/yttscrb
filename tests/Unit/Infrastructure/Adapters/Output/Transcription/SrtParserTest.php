<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Output\Transcription\SrtParser;

describe('SrtParser', function (): void {

    beforeEach(function (): void {
        $this->parser = new SrtParser();
    });

    // -----------------------------------------------------------------------
    // Basic SRT parsing
    // -----------------------------------------------------------------------

    it('parses a simple SRT file into timecoded lines', function (): void {
        $srt = <<<'SRT'
        1
        00:00:00,080 --> 00:00:04,000
        Hello world

        2
        00:00:04,200 --> 00:00:08,000
        This is a test

        SRT;

        $result = $this->parser->parse(dedent($srt));

        expect($result)->toContain('[00:00]')
            ->toContain('Hello world')
            ->toContain('This is a test');
    });

    it('strips HTML tags from cue text', function (): void {
        $srt = <<<'SRT'
        1
        00:00:01,000 --> 00:00:03,000
        <c.colorE5E5E5>coloured text</c.colorE5E5E5>

        SRT;

        $result = $this->parser->parse(dedent($srt));

        expect($result)->toContain('coloured text')
            ->not->toContain('<c.');
    });

    it('returns an empty string for empty input', function (): void {
        expect($this->parser->parse(''))->toBe('');
        expect($this->parser->parse("\n\n"))->toBe('');
    });

    // -----------------------------------------------------------------------
    // YouTube roll-up deduplication
    // -----------------------------------------------------------------------

    it('merges YouTube roll-up subtitles within the 3-second window', function (): void {
        $srt = <<<'SRT'
        1
        00:00:01,000 --> 00:00:05,000
        я пакажу вам

        2
        00:00:01,480 --> 00:00:05,559
        я покажу вам как

        3
        00:00:03,360 --> 00:00:07,200
        я покажу вам как играть

        4
        00:00:10,000 --> 00:00:14,000
        new sentence here

        SRT;

        $result = $this->parser->parse(dedent($srt));
        $lines  = array_filter(explode("\n", $result));

        // Roll-up (cues 1-3) should produce exactly ONE line, not three
        $rollupLines = array_filter($lines, fn($l) => str_contains($l, 'покажу'));
        expect(count($rollupLines))->toBe(1);

        // The surviving text must be the last (most complete) form
        expect(reset($rollupLines))->toContain('я покажу вам как играть');

        // Intermediate / first form must NOT appear in dedicated lines
        expect($result)->not->toContain('я пакажу вам');
    });

    it('does not merge cues that start more than 3 seconds apart', function (): void {
        $srt = <<<'SRT'
        1
        00:00:00,000 --> 00:00:03,000
        first sentence

        2
        00:00:05,000 --> 00:00:08,000
        second sentence

        SRT;

        $result = $this->parser->parse(dedent($srt));
        $lines  = array_values(array_filter(explode("\n", $result)));

        // Both cues are more than 3 s apart — both survive
        expect($result)->toContain('first sentence')
            ->toContain('second sentence');
    });

    // -----------------------------------------------------------------------
    // HH:MM:SS formatting for long videos
    // -----------------------------------------------------------------------

    it('formats timecodes as MM:SS for short videos', function (): void {
        $srt = <<<'SRT'
        1
        00:05:30,000 --> 00:05:34,000
        mid video text

        SRT;

        $result = $this->parser->parse(dedent($srt));

        expect($result)->toStartWith('[05:30]');
    });

    it('formats timecodes as HH:MM:SS for videos one hour or longer', function (): void {
        $srt = <<<'SRT'
        1
        01:15:30,000 --> 01:15:34,000
        podcast content

        SRT;

        $result = $this->parser->parse(dedent($srt));

        // gmdate('H:i:s') zero-pads hours → "01:15:30"
        expect($result)->toStartWith('[01:15:30]');
    });

    // -----------------------------------------------------------------------
    // 12-second paragraph grouping (token optimisation)
    // -----------------------------------------------------------------------

    it('groups cues within 12 seconds into a single annotated paragraph', function (): void {
        // Cues spaced 5 s apart so they don't trigger roll-up (>3 s) but stay within 12 s
        $cues = '';
        for ($i = 0; $i < 3; $i++) {
            $sec   = $i * 5;
            $start = sprintf('00:00:%02d,000', $sec);
            $end   = sprintf('00:00:%02d,000', $sec + 2);
            $cues .= ($i + 1) . "\n{$start} --> {$end}\nline {$i}\n\n";
        }

        $result = $this->parser->parse($cues);
        $lines  = explode("\n", $result)
                |> array_filter(...)
                |> array_values(...);

        // 3 cues at 0, 5, 10 s → span 10 s < 12 s → single output paragraph
        expect(count($lines))->toBe(1)
            ->and($lines[0])->toStartWith('[00:00]')
            ->and($lines[0])->toContain('line 0')
            ->and($lines[0])->toContain('line 2');
    });

    it('emits a new paragraph when cues span more than 12 seconds', function (): void {
        $srt = <<<'SRT'
        1
        00:00:00,000 --> 00:00:02,000
        first block

        2
        00:00:13,000 --> 00:00:15,000
        second block

        SRT;

        $result = $this->parser->parse(dedent($srt));
        $lines  = array_values(array_filter(explode("\n", $result)));

        expect(count($lines))->toBe(2)
            ->and($lines[0])->toStartWith('[00:00]')
            ->and($lines[1])->toStartWith('[00:13]');
    });

    // -----------------------------------------------------------------------
    // WEBVTT input (yt-dlp also outputs VTT)
    // -----------------------------------------------------------------------

    it('parses a WEBVTT file correctly', function (): void {
        $vtt = <<<'VTT'
        WEBVTT
        Kind: captions
        Language: en

        1
        00:00:00.080 --> 00:00:04.160
        hello from vtt

        VTT;

        $result = $this->parser->parse(dedent($vtt));

        expect($result)->toContain('hello from vtt')
            ->not->toContain('WEBVTT')
            ->not->toContain('Kind:');
    });
});
