<?php

namespace App\Ksef;

/** The references KSeF gave for an invoice it accepted for processing. Enough to ask for the verdict later. */
final readonly class Submission
{
    public function __construct(
        public string $sessionReference,
        public string $invoiceReference,
        public string $invoiceHash,
    ) {}
}
