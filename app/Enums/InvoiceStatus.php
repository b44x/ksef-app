<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    /** Being prepared: freely editable, has no number yet, nothing was sent. */
    case Draft = 'draft';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
        };
    }
}
