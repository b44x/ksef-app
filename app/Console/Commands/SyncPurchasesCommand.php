<?php

namespace App\Console\Commands;

use App\Enums\AuthMethod;
use App\Jobs\SyncPurchasesJob;
use App\Models\Company;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('ksef:sync-purchases')]
#[Description('Queue the synchronisation of purchase invoices for every company that has already done the first one')]
class SyncPurchasesCommand extends Command
{
    public function handle(): int
    {
        // The first synchronisation is the user's call (how far back to look); from then on it runs by itself.
        $companies = Company::query()->whereNotNull('purchases_synced_until')->where('auth_method', '!=', AuthMethod::Manual->value)->get();

        foreach ($companies as $company) {
            SyncPurchasesJob::dispatch($company->id);
        }

        $this->info("Queued {$companies->count()} synchronisation(s).");

        return self::SUCCESS;
    }
}
