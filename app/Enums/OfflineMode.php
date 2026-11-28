<?php

namespace App\Enums;

use B4x\Ksef\Offline\OfflineMode as SdkMode;

/** Why an invoice was issued without KSeF; each reason has its own delivery deadline. */
enum OfflineMode: string
{
    /** Your own choice, any time: deliver by the end of the next business day. */
    case Offline24 = 'offline24';

    /** KSeF was announced as unavailable: deliver by the end of the next business day after it ends. */
    case Unavailability = 'unavailability';

    /** A KSeF failure: deliver within seven business days after it ends. */
    case Emergency = 'emergency';

    /** A total failure announced in the media: the invoice is issued outside KSeF, with no QR codes and no certificate. */
    case TotalFailure = 'total_failure';

    public function label(): string
    {
        return match ($this) {
            self::Offline24 => __('Offline24 (my choice)'),
            self::Unavailability => __('KSeF unavailable (announced)'),
            self::Emergency => __('KSeF failure'),
            self::TotalFailure => __('KSeF total failure (announced)'),
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Offline24 => __('Deliver to KSeF by the end of the next business day after the issue date.'),
            self::Unavailability => __('Deliver by the end of the next business day after the unavailability ends.'),
            self::Emergency => __('Deliver within seven business days after the failure ends.'),
            self::TotalFailure => __('No QR codes and no Offline certificate. Deliver within seven business days after the failure ends, unless the Ministry says otherwise.'),
        };
    }

    /** Whether the deadline counts from the end of an announced event rather than from the issue date. */
    public function needsAnchor(): bool
    {
        return $this !== self::Offline24;
    }

    /** Whether the invoice carries the QR codes, which need the Offline certificate. A total failure has none. */
    public function usesQr(): bool
    {
        return $this !== self::TotalFailure;
    }

    public function sdk(): SdkMode
    {
        return match ($this) {
            self::Offline24 => SdkMode::Offline24,
            self::Unavailability => SdkMode::Unavailability,
            self::Emergency, self::TotalFailure => SdkMode::Emergency,
        };
    }
}
