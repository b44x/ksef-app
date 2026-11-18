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
            self::Token => 'KSeF token',
            self::Certificate => 'Certificate and private key (PEM)',
            self::Manual => 'Signature made outside the app (trusted profile)',
        };
    }
}
