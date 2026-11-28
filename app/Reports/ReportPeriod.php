<?php

namespace App\Reports;

use Carbon\CarbonImmutable;

/** A reporting period: a named one relative to today, or a custom range. Always whole days, from <= to. */
final class ReportPeriod
{
    public const KEYS = ['this_month', 'last_month', 'this_quarter', 'last_quarter', 'this_year', 'last_year', 'custom'];

    private function __construct(public readonly string $key, public readonly CarbonImmutable $from, public readonly CarbonImmutable $to) {}

    /**
     * @param  array<string, mixed>  $input  `period`, and `from` / `to` (Y-m-d) for a custom one
     */
    public static function resolve(array $input, ?CarbonImmutable $today = null): self
    {
        $today ??= CarbonImmutable::today();
        $key = in_array($input['period'] ?? null, self::KEYS, true) ? (string) $input['period'] : 'this_month';

        if ($key === 'custom') {
            $from = self::date($input['from'] ?? null);
            $to = self::date($input['to'] ?? null);
            if ($from !== null && $to !== null) {
                return $from->greaterThan($to) ? new self('custom', $to, $from) : new self('custom', $from, $to);
            }
            $key = 'this_month'; // an incomplete custom range is not a range
        }

        return match ($key) {
            'last_month' => new self($key, $today->subMonthNoOverflow()->startOfMonth(), $today->subMonthNoOverflow()->endOfMonth()),
            'this_quarter' => new self($key, $today->startOfQuarter(), $today->endOfQuarter()),
            'last_quarter' => new self($key, $today->subQuarterNoOverflow()->startOfQuarter(), $today->subQuarterNoOverflow()->endOfQuarter()),
            'this_year' => new self($key, $today->startOfYear(), $today->endOfYear()),
            'last_year' => new self($key, $today->subYear()->startOfYear(), $today->subYear()->endOfYear()),
            default => new self('this_month', $today->startOfMonth(), $today->endOfMonth()),
        };
    }

    private static function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $value);
        } catch (\InvalidArgumentException) {
            return null;
        }

        // 2026-02-30 has the shape of a date and is not one: Carbon would roll it over to March.
        return $date?->format('Y-m-d') === $value ? $date->startOfDay() : null;
    }
}
