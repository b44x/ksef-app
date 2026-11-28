<?php

namespace App\Enums;

use Carbon\CarbonImmutable;

/** How often a recurring invoice is made. */
enum Frequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function label(): string
    {
        return match ($this) {
            self::Weekly => __('Every week'),
            self::Monthly => __('Every month'),
            self::Quarterly => __('Every quarter'),
            self::Yearly => __('Every year'),
        };
    }

    /**
     * The run after $from. Months keep the anchor day where the month has it, and end on the last day where it does not
     * (31 January, 28 February, 31 March), so a series never drifts earlier.
     */
    public function after(CarbonImmutable $from, int $anchorDay): CarbonImmutable
    {
        if ($this === self::Weekly) {
            return $from->addWeek();
        }

        $next = $from->startOfMonth()->addMonthsNoOverflow(match ($this) {
            self::Monthly => 1,
            self::Quarterly => 3,
            default => 12,
        });

        return $next->day(min($anchorDay, $next->daysInMonth));
    }
}
