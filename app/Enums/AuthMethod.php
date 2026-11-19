<?php

namespace App\Enums;

enum AuthMethod: string
{
    case Token = 'token';
    case Certificate = 'certificate';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Token => __('KSeF token'),
            self::Certificate => __('Certificate and private key (PEM)'),
            self::Manual => __('Signature made outside the app (trusted profile)'),
        };
    }
}
