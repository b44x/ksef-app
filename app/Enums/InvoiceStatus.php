<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    /** Being prepared: freely editable, has no number yet, nothing was sent. */
    case Draft = 'draft';

    /** Numbered and frozen: the FA(3) document exists and is ready to be sent to KSeF. */
    case Issued = 'issued';

    /** Handed to KSeF; waiting for its verdict. */
    case Sending = 'sending';

    /** KSeF stored the invoice and gave it its KSeF number. */
    case Accepted = 'accepted';

    /** KSeF refused the invoice. The number stays used; the way on is a corrected copy. */
    case Rejected = 'rejected';

    /** Only a draft can be changed or deleted; what was issued is a legal document. */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Draft'),
            self::Issued => __('Issued'),
            self::Sending => __('Sending'),
            self::Accepted => __('Accepted by KSeF'),
            self::Rejected => __('Rejected by KSeF'),
        };
    }

    /** The colour of the badge: waiting is amber, done is green, refused is red. */
    public function tone(): string
    {
        return match ($this) {
            self::Draft, self::Sending => 'warning',
            self::Issued => 'primary',
            self::Accepted => 'success',
            self::Rejected => 'danger',
        };
    }
}
