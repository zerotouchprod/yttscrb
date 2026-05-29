<?php

declare(strict_types=1);

use App\Infrastructure\Adapters\Output\YoutubeDl\YtDlpProcessRunner;

test('runner returns stdout on exit code 0', function () {
    $runner = new YtDlpProcessRunner();
    $result = $runner->run('echo "hello world"');

    expect($result['stdout'])->toContain('hello world');
    expect($result['exitCode'])->toBe(0);
});

test('runner captures stderr on exit code non-zero', function () {
    $runner = new YtDlpProcessRunner();
    $result = $runner->run('echo "error message" >&2 && exit 1');

    expect($result['exitCode'])->toBe(1);
    expect($result['stderr'])->toContain('error message');
});

test('runner enforces timeout', function () {
    $runner = new YtDlpProcessRunner(timeoutSec: 2);
    $result = $runner->run('sleep 10');

    expect($result['exitCode'])->not->toBe(0);
    expect($result['timedOut'])->toBeTrue();
});
