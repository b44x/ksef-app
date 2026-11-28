<?php

namespace App\Ksef;

use DateTimeImmutable;

/** What one export of purchase invoices brought, and where to continue from. */
final readonly class PurchaseBatch
{
    /**
     * @param  list<PurchaseDocument>  $documents
     * @param  DateTimeImmutable|null  $continueFrom  set when KSeF cut the export short: ask again from here
     * @param  DateTimeImmutable  $syncedUntil  everything up to here has been seen; the next synchronisation starts there
     */
    public function __construct(
        public array $documents,
        public DateTimeImmutable $syncedUntil,
        public ?DateTimeImmutable $continueFrom = null,
    ) {}
}
