<?php

namespace App\Console\Commands;

use App\Invoicing\GenerateRecurring;
use App\Models\RecurringInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('invoices:generate-recurring')]
#[Description('Make the invoices of recurring schedules that are due today')]
class GenerateRecurringCommand extends Command
{
    public function handle(GenerateRecurring $generate): int
    {
        $today = CarbonImmutable::today();
        $made = 0;

        foreach (RecurringInvoice::query()->where('active', true)->whereDate('next_run_on', '<=', $today->toDateString())->get() as $schedule) {
            try {
                $made += $generate($schedule, $today) === null ? 0 : 1;
            } catch (\Throwable $e) {
                // One broken schedule must not stop the others; it will be tried again tomorrow.
                $schedule->forceFill(['last_error' => $e->getMessage()])->save();
                $this->error("{$schedule->name}: {$e->getMessage()}");
            }
        }

        $this->info("Made {$made} invoice(s).");

        return self::SUCCESS;
    }
}
