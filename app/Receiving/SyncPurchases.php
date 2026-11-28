<?php

namespace App\Receiving;

use App\Ksef\KsefGateway;
use App\Ksef\PurchaseDocument;
use App\Models\Company;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Brings the company's purchase invoices from KSeF into the application. Incremental: it continues where the last run
 * stopped, and a repeat is harmless, because an invoice is stored once, by its KSeF number.
 */
final class SyncPurchases
{
    /** Exports in one run; KSeF cuts a large export short, so a big backlog takes several. */
    private const MAX_ROUNDS = 20;

    /** How far back the first synchronisation looks when the user did not say. */
    public const DEFAULT_LOOKBACK_DAYS = 90;

    public function __construct(private readonly KsefGateway $gateway, private readonly ReadInvoice $reader) {}

    /**
     * @return int how many invoices are new
     *
     * @throws Throwable what went wrong, after it was noted on the company; the next run starts from the same place
     */
    public function __invoke(Company $company, ?DateTimeImmutable $since = null): int
    {
        $from = $company->purchases_synced_until !== null
            ? DateTimeImmutable::createFromInterface($company->purchases_synced_until)
            : ($since ?? new DateTimeImmutable('-'.self::DEFAULT_LOOKBACK_DAYS.' days'));

        $new = 0;
        try {
            for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
                $batch = $this->gateway->purchases($company, $from);
                $new += $this->store($company, $batch->documents);

                // The place is saved after every export, so an interrupted run does not start over.
                $from = $batch->continueFrom ?? $batch->syncedUntil;
                $company->forceFill(['purchases_synced_until' => $batch->syncedUntil])->save();

                if ($batch->continueFrom === null) {
                    break;
                }
            }
        } catch (Throwable $e) {
            $company->forceFill(['purchases_sync_error' => $e->getMessage()])->save();

            throw $e;
        }

        $company->forceFill(['purchases_synced_at' => now(), 'purchases_sync_error' => null])->save();

        return $new;
    }

    /**
     * @param  list<PurchaseDocument>  $documents
     */
    private function store(Company $company, array $documents): int
    {
        $new = 0;
        foreach ($documents as $document) {
            try {
                $summary = $this->reader->summary($document->xml);
            } catch (Throwable $e) {
                // One document the reader cannot make sense of must not stop the rest. It is logged, and will be seen again.
                Log::warning('A purchase invoice from KSeF could not be read.', ['ksef_number' => $document->ksefNumber, 'error' => $e->getMessage()]);

                continue;
            }

            $invoice = $company->receivedInvoices()->firstOrNew(['ksef_number' => $document->ksefNumber]);
            if (! $invoice->exists) {
                $invoice->received_at = now();
                $new++;
            }
            $invoice->fill([...$summary, 'xml' => $document->xml])->save();
        }

        return $new;
    }
}
