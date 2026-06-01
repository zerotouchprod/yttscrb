<?php

declare(strict_types=1);

namespace App\Infrastructure\Adapters\Output\YoutubeDl;

use RuntimeException;

final class YtDlpProcessRunner
{
    public function __construct(
        private readonly int $timeoutSec = 300,
    ) {
    }

    /**
     * Run a shell command via proc_open and return stdout, stderr, exit code, and timeout flag.
     *
     * @return array{stdout: string, stderr: string, exitCode: int, timedOut: bool}
     */
    public function run(string $command): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],  // stdin
            1 => ['pipe', 'w'],  // stdout
            2 => ['pipe', 'w'],  // stderr
        ];

        $process = proc_open($command, $descriptors, $pipes);

        if (! is_resource($process)) {
            throw new RuntimeException('Failed to start yt-dlp process.');
        }

        fclose($pipes[0]);

        $stdout = '';
        $stderr = '';
        $timedOut = false;

        // Set non-blocking streams
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $deadline = time() + $this->timeoutSec;

        while (time() < $deadline) {
            $status = proc_get_status($process);

            if (! $status['running']) {
                break;
            }

            $read = [$pipes[1], $pipes[2]];
            $write = null;
            $except = null;

            // Suppress EINTR warnings — stream_select can be interrupted
            // by signals in containerised PHP processes (e.g. Horizon).
            $result = @stream_select($read, $write, $except, 1);

            if ($result === false) {
                // EINTR or other transient error — retry on next loop iteration.
                continue;
            }

            if ($result > 0) {
                foreach ($read as $pipe) {
                    $data = stream_get_contents($pipe);
                    if ($data !== false) {
                        if ($pipe === $pipes[1]) {
                            $stdout .= $data;
                        } else {
                            $stderr .= $data;
                        }
                    }
                }
            }
        }

        $status = proc_get_status($process);

        if ($status['running']) {
            // Timeout: kill the process
            proc_terminate($process, 9);
            $status = proc_get_status($process);
            $timedOut = true;
        }

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        return [
            'stdout' => $stdout,
            'stderr' => $stderr,
            'exitCode' => $timedOut ? $status['exitcode'] : $exitCode,
            'timedOut' => $timedOut,
        ];
    }
}
