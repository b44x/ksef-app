<?php

namespace App\Enums;

use App\Models\Invoice;

/** How much of an issued invoice has been paid. Being late is separate: see {@see Invoice::isOverdue()}. */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => __('Unpaid'),
            self::Partial => __('Partly paid'),
            self::Paid => __('Paid'),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Unpaid => 'neutral',
            self::Partial => 'warning',
            self::Paid => 'success',
        };
    }
}
