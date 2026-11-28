<?php

namespace Tests\Support;

use B4x\Ksef\Invoice\Address;
use B4x\Ksef\Invoice\Buyer;
use B4x\Ksef\Invoice\BuyerIdentifier;
use B4x\Ksef\Invoice\Invoice;
use B4x\Ksef\Invoice\InvoiceDocument;
use B4x\Ksef\Invoice\InvoiceLine;
use B4x\Ksef\Invoice\Seller;
use B4x\Ksef\Invoice\VatRate;
use B4x\Ksef\Support\Nip;

/** A real, schema-valid FA(3) purchase invoice, the way some other company would have issued it to ours. */
final class PurchaseXml
{
    public static function make(string $number = 'FV/12/2026', string $seller = 'Dostawca sp. z o.o.', string $sellerNip = '7762811692', string $buyerNip = '5265877635', string $date = '2026-11-20'): string
    {
        $invoice = Invoice::builder()
            ->number($number)
            ->issueDate($date)
            ->seller(new Seller(Nip::of($sellerNip), $seller, Address::poland('ul. Handlowa 1', '00-950 Warszawa')))
            ->buyer(new Buyer(BuyerIdentifier::nip(Nip::of($buyerNip)), 'Seller sp. z o.o.', Address::poland('ul. Prosta 1', '00-001 Warszawa')))
            ->addLine(InvoiceLine::of('Papier A4', '10', 'szt.', '20.00', VatRate::Rate23))
            ->addLine(InvoiceLine::of('Książka', '2', 'szt.', '49.50', VatRate::Rate5))
            ->build();

        return InvoiceDocument::fromInvoice($invoice)->xml;
    }

    /** A KSeF number in the right shape (35 characters), distinct for each seed. */
    public static function ksefNumber(int $seed = 1): string
    {
        return sprintf('7762811692-20261120-%012X-%02X', $seed, $seed % 256);
    }
}
