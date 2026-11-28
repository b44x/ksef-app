<?php

namespace App\Receiving;

use B4x\Ksef\Reader\InvoiceReader;
use B4x\Ksef\Reader\ReceivedInvoice;
use B4x\Ksef\Reader\ReceivedLine;
use B4x\Ksef\Reader\ReceivedParty;

/**
 * Reads a purchase invoice's FA(3) document with the SDK's reader: the few facts a list needs, and the whole invoice for
 * its page. The amounts are the issuer's, exactly as written: nothing is recalculated.
 */
final class ReadInvoice
{
    public function __construct(private readonly InvoiceReader $reader = new InvoiceReader) {}

    /**
     * @return array{number: string, type: string, issue_date: string, seller_name: string, seller_nip: string|null, currency: string, gross: string}
     */
    public function summary(string $xml): array
    {
        $invoice = $this->reader->read($xml);

        return [
            'number' => $invoice->number,
            'type' => $invoice->type->value,
            'issue_date' => $invoice->issueDate->format('Y-m-d'),
            'seller_name' => $invoice->seller->name,
            'seller_nip' => $invoice->seller->taxId(),
            'currency' => $invoice->currency,
            'gross' => $invoice->total->toString(2),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function details(string $xml): array
    {
        $invoice = $this->reader->read($xml);

        return [
            'number' => $invoice->number,
            'type' => $invoice->type->value,
            'issue_date' => $invoice->issueDate->format('Y-m-d'),
            'sale_date' => $invoice->saleDate?->format('Y-m-d'),
            'currency' => $invoice->currency,
            'total' => $invoice->total->toString(2),
            'seller' => $this->party($invoice->seller),
            'buyer' => $this->party($invoice->buyer),
            'lines' => array_map(fn (ReceivedLine $l): array => [
                'number' => $l->number,
                'name' => $l->name,
                'unit' => $l->unit,
                'quantity' => $l->quantity?->toTrimmedString(),
                'unit_price' => ($l->unitPriceNet ?? $l->unitPriceGross)?->toTrimmedString(2),
                'price_kind' => $l->unitPriceNet !== null ? 'net' : ($l->unitPriceGross !== null ? 'gross' : null),
                'net' => $l->netAmount?->toString(2),
                'gross' => $l->grossAmount?->toString(2),
                'vat_rate' => $l->vatRate,
            ], array_values(array_filter($invoice->lines, static fn (ReceivedLine $l): bool => ! $l->before))),
            'tax_summary' => array_map(static fn ($row): array => [
                'group' => $row->group,
                'label' => self::groupLabel($row->group),
                'net' => $row->net->toString(2),
                'tax' => $row->tax?->toString(2),
            ], $invoice->taxSummary),
            'payment' => $this->payment($invoice),
            'notes' => $invoice->footer,
            'is_correction' => $invoice->type->isCorrection(),
        ];
    }

    /**
     * @return array{name: string, tax_id: string|null, address: string, email: string|null}
     */
    private function party(ReceivedParty $party): array
    {
        $address = $party->address;

        return [
            'name' => $party->name,
            'tax_id' => $party->taxId(),
            'address' => $address === null ? '' : trim($address->line1.', '.$address->line2.' · '.$address->countryCode, ', '),
            'email' => $party->email,
        ];
    }

    /**
     * @return array{paid_on: string|null, due_dates: list<string>, bank_accounts: list<string>, is_paid: bool}|null
     */
    private function payment(ReceivedInvoice $invoice): ?array
    {
        $payment = $invoice->payment;
        if ($payment === null) {
            return null;
        }

        return [
            'paid_on' => $payment->paidOn?->format('Y-m-d'),
            'due_dates' => array_map(static fn ($d): string => $d->format('Y-m-d'), $payment->dueDates),
            'bank_accounts' => $payment->bankAccounts,
            'is_paid' => $payment->isPaid(),
        ];
    }

    /** The tax groups of FA(3) the summary is split into, as people know them. */
    public static function groupLabel(string $group): string
    {
        return match ($group) {
            '1' => '23% / 22%',
            '2' => '8% / 7%',
            '3' => '5%',
            '4' => __('Taxi flat rate'),
            '5' => 'OSS',
            '6_1' => __('0% domestic'),
            '6_2' => __('0% intra-Community'),
            '6_3' => __('0% export'),
            '7' => __('Exempt'),
            '8' => __('Not subject (outside Poland)'),
            '9' => __('Not subject (art. 100)'),
            '10' => __('Reverse charge'),
            '11' => __('Margin scheme'),
            default => $group,
        };
    }
}
