<?php

namespace App\Ksef;

/** How KSeF is doing, as the Ministry's availability service announces it. */
enum AvailabilityState: string
{
    case Available = 'available';

    /** Planned maintenance: KSeF is down for a announced window. */
    case Maintenance = 'maintenance';

    /** A failure: invoices can be issued offline meanwhile. */
    case Failure = 'failure';

    /** A total failure announced in the media: no delivery to KSeF and no QR codes until it ends. */
    case TotalFailure = 'total_failure';

    /** The service itself could not be asked. Says nothing about KSeF; nothing is shown for it. */
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('KSeF is available'),
            self::Maintenance => __('KSeF: planned maintenance'),
            self::Failure => __('KSeF has a failure'),
            self::TotalFailure => __('KSeF: total failure'),
            self::Unknown => __('KSeF status unknown'),
        };
    }

    /** Whether invoices cannot be delivered to KSeF right now. */
    public function isDown(): bool
    {
        return in_array($this, [self::Maintenance, self::Failure, self::TotalFailure], true);
    }
}
