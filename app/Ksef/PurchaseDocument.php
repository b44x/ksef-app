<?php

namespace App\Ksef;

/** One purchase invoice as KSeF stores it: its KSeF number and the FA(3) document. */
final readonly class PurchaseDocument
{
    public function __construct(public string $ksefNumber, public string $xml) {}
}
