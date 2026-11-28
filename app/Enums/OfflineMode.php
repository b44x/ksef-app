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

    public function label(): string
    {
        return match ($this) {
            self::Offline24 => __('Offline24 (my choice)'),
            self::Unavailability => __('KSeF unavailable (announced)'),
            self::Emergency => __('KSeF failure'),
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Offline24 => __('Deliver to KSeF by the end of the next business day after the issue date.'),
            self::Unavailability => __('Deliver by the end of the next business day after the unavailability ends.'),
            self::Emergency => __('Deliver within seven business days after the failure ends.'),
        };
    }

    /** Whether the deadline counts from the end of an announced event rather than from the issue date. */
    public function needsAnchor(): bool
    {
        return $this !== self::Offline24;
    }

    public function sdk(): SdkMode
    {
        return match ($this) {
            self::Offline24 => SdkMode::Offline24,
            self::Unavailability => SdkMode::Unavailability,
            self::Emergency => SdkMode::Emergency,
        };
    }
}
