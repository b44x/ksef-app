<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    /** Being prepared: freely editable, has no number yet, nothing was sent. */
    case Draft = 'draft';

    /** Numbered and frozen: the FA(3) document exists and is ready to be sent to KSeF. */
    case Issued = 'issued';

    /** Only a draft can be changed or deleted; what was issued is a legal document. */
    public function isEditable(): bool
    {
        return match ($this) {
            self::Draft => true,
            self::Issued => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Issued => __('Issued'),
        };
    }
}
