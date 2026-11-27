<?php

namespace App\Invoicing;

use DateTimeInterface;

/**
 * Turns a number pattern into an invoice number. Tokens: {N} the counter, {NN} {NNN} {NNNN} the counter padded with
 * zeros, {YYYY} {YY} the year, {MM} the month. Example: "FV/{NNN}/{MM}/{YYYY}" gives "FV/007/03/2026".
 */
final class NumberFormat
{
    public static function render(string $format, int $number, DateTimeInterface $date): string
    {
        $out = preg_replace_callback('/\{(N+)\}/', static fn (array $m): string => str_pad((string) $number, strlen($m[1]), '0', STR_PAD_LEFT), $format);

        return strtr((string) $out, [
            '{YYYY}' => $date->format('Y'),
            '{YY}' => $date->format('y'),
            '{MM}' => $date->format('m'),
        ]);
    }

    /** A pattern is usable when it has a counter and no unknown tokens. */
    public static function isValid(string $format): bool
    {
        $rest = preg_replace('/\{N+\}|\{YYYY\}|\{YY\}|\{MM\}/', '', $format, -1, $count) ?? '';

        return preg_match('/\{N+\}/', $format) === 1 && ! str_contains($rest, '{') && ! str_contains($rest, '}');
    }
}
