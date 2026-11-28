<?php

namespace App\Console\Commands;

use App\Ksef\AvailabilityMonitor;
use App\Models\Company;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ksef:check-availability')]
#[Description('Ask the Ministry\'s availability service whether KSeF is up, and keep the answer for the pages')]
class CheckAvailabilityCommand extends Command
{
    public function handle(AvailabilityMonitor $monitor): int
    {
        $company = Company::current();
        if ($company === null) {
            return self::SUCCESS;
        }

        $availability = $monitor->refresh($company);
        $this->info('KSeF: '.$availability->state->value);

        return self::SUCCESS;
    }
}
