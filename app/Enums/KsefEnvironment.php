<?php

namespace App\Enums;

use B4x\Ksef\Environment;

enum KsefEnvironment: string
{
    case Test = 'test';
    case Demo = 'demo';
    case Production = 'production';

    public function sdk(): Environment
    {
        return match ($this) {
            self::Test => Environment::Test,
            self::Demo => Environment::Demo,
            self::Production => Environment::Production,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Test => __('TEST (no real data)'),
            self::Demo => __('DEMO (pre-production)'),
            self::Production => __('Production (real invoices)'),
        };
    }
}
