<?php

namespace App\Ksef;

enum CheckOutcome: string
{
    case Ok = 'ok';
    case Warning = 'warning';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
