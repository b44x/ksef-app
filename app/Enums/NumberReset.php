<?php

namespace App\Enums;

/** When the invoice counter of a series starts again from 1. */
enum NumberReset: string
{
    case Yearly = 'yearly';
    case Monthly = 'monthly';
    case Never = 'never';

    public function label(): string
    {
        return match ($this) {
            self::Yearly => __('Every year'),
            self::Monthly => __('Every month'),
            self::Never => __('Never'),
        };
    }

    /** The period a date falls into; the counter restarts when it changes. */
    public function period(\DateTimeInterface $date): string
    {
        return match ($this) {
            self::Yearly => $date->format('Y'),
            self::Monthly => $date->format('Y-m'),
            self::Never => '',
        };
    }
}
