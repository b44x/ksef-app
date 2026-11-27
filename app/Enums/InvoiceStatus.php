<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    /** Being prepared: freely editable, has no number yet, nothing was sent. */
    case Draft = 'draft';

    /** Only a draft can be changed or deleted; what was issued is a legal document. */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Draft => true,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
        };
    }
}
