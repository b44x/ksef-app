<?php

namespace App\Jobs;

use App\Models\Company;
use App\Receiving\SyncPurchases;
use DateTimeImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Synchronises the purchase invoices of one company in the background. One at a time per company. */
class SyncPurchasesJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(public readonly int $companyId, public readonly ?string $since = null) {}

    public function uniqueId(): string
    {
        return (string) $this->companyId;
    }

    /** The cache key that says a synchronisation is running; the page shows a spinner while it is set. */
    public static function runningKey(int $companyId): string
    {
        return 'purchases-sync-running:'.$companyId;
    }

    public function handle(SyncPurchases $sync): void
    {
        $company = Company::query()->find($this->companyId);
        if ($company === null) {
            return;
        }

        Cache::put(self::runningKey($company->id), true, now()->addMinutes(15));
        try {
            $sync($company, $this->since === null ? null : new DateTimeImmutable($this->since));
        } catch (Throwable) {
            // Already noted on the company, for the page to show; a failed job would only add noise to the queue.
        } finally {
            Cache::forget(self::runningKey($company->id));
        }
    }
}
