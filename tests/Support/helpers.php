<?php

declare(strict_types=1);

/**
 * Remove common leading whitespace from heredoc/nowdoc strings in tests.
 *
 * When SRT/VTT fixtures are indented inside test methods, the leading
 * whitespace must be stripped before passing the content to the parser.
 */
function dedent(string $text): string
{
    $lines  = explode("\n", $text);
    $indent = PHP_INT_MAX;

    foreach ($lines as $line) {
        if (trim($line) === '') {
            continue;
        }
        $indent = min($indent, strlen($line) - strlen(ltrim($line)));
    }

    if ($indent === PHP_INT_MAX) {
        return $text;
    }

    return implode(
        "\n",
        array_map(
            fn($l) => strlen($l) >= $indent ? substr($l, $indent) : $l,
            $lines,
        ),
    );
}
