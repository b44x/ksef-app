<?php

namespace App\Pdf;

use App\Invoicing\InvoiceQr;
use App\Invoicing\PaymentLabel;
use App\Invoicing\SdkInvoiceFactory;
use App\Invoicing\Totals;
use App\Models\Invoice;
use App\Models\ReceivedInvoice;
use App\Receiving\ReadInvoice;
use B4x\Ksef\Crypto\Digest;
use B4x\Ksef\Invoice\PaymentMethod;
use B4x\Ksef\Qr\VerificationLinks;
use B4x\Ksef\Support\Nip;

/**
 * Everything the printed invoice shows, as plain data, for an invoice we issued and for one we received. Amounts are
 * the ones the SDK computed (ours) or the issuer wrote (received): nothing is added up here.
 */
final class InvoiceView
{
    public function __construct(private readonly InvoiceQr $qr, private readonly ReadInvoice $reader) {}

    /**
     * @return array<string, mixed>
     */
    public function sales(Invoice $invoice): array
    {
        $invoice->loadMissing(['lines', 'company', 'series']);
        $parties = $invoice->snapshot ?? ['seller' => [], 'buyer' => []];
        $lineData = $invoice->lines->map(fn ($l): array => $l->only(['name', 'quantity', 'unit_net_price', 'vat_rate']))->all();
        $totals = Totals::of($lineData, $invoice->currency);

        return [
            'title' => 'VAT invoice',
            'number' => (string) $invoice->number,
            'issue_date' => $invoice->issue_date->toDateString(),
            'sale_date' => $invoice->sale_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'currency' => $invoice->currency,
            'seller' => self::party($parties['seller']),
            'buyer' => self::party($parties['buyer']),
            'lines' => $invoice->lines->map(fn ($l, int $i): array => [
                'n' => $i + 1,
                'name' => $l->name,
                'unit' => $l->unit ?? '',
                'quantity' => SdkInvoiceFactory::trim($l->quantity),
                'unit_price' => SdkInvoiceFactory::trim($l->unit_net_price),
                'price_kind' => 'net',
                'vat_rate' => $l->vat_rate,
                'net' => $totals['lines'][$i],
            ])->all(),
            'summary' => array_map(static fn (array $b): array => [
                'label' => $b['vat'] !== null ? $b['rate'].'%' : $b['rate'],
                'net' => $b['net'],
                'vat' => $b['vat'],
                'gross' => $b['gross'],
            ], $totals['buckets']),
            'total' => ['net' => $totals['net'], 'vat' => $totals['vat'], 'gross' => $totals['gross']],
            'payment' => [
                'method' => $invoice->payment_method === null ? null : PaymentLabel::of(PaymentMethod::from($invoice->payment_method)),
                'due_date' => $invoice->due_date?->toDateString(),
                'accounts' => $invoice->bank_account === null || $invoice->bank_account === '' ? [] : [$invoice->bank_account],
                'paid' => false,
            ],
            'notes' => $invoice->notes,
            'ksef_number' => $invoice->ksef_number,
            'offline' => $invoice->offline_mode !== null,
            'qr' => $this->qr->codes($invoice),
            'logo' => $invoice->company->logo,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function purchase(ReceivedInvoice $invoice): array
    {
        $details = $this->reader->details($invoice->xml);
        $links = new VerificationLinks($invoice->company->environment->sdk());
        $qr = null;
        if ($details['seller']['tax_id'] !== null && preg_match('/^\d{10}$/', $details['seller']['tax_id']) === 1) {
            $qr = ['verification' => [
                'url' => $links->invoiceUrlFromHash(Nip::of($details['seller']['tax_id'], false), $invoice->issue_date, Digest::sha256Base64($invoice->xml)),
                'caption' => $invoice->ksef_number,
            ], 'issuer' => null];
        }

        return [
            'title' => match ($details['type']) {
                'KOR' => 'Correction invoice',
                'ZAL' => 'Advance invoice',
                'ROZ' => 'Settlement invoice',
                'UPR' => 'Simplified invoice',
                default => 'VAT invoice',
            },
            'number' => $details['number'],
            'issue_date' => $details['issue_date'],
            'sale_date' => $details['sale_date'],
            'due_date' => $details['payment']['due_dates'][0] ?? null,
            'currency' => $details['currency'],
            'seller' => $details['seller'],
            'buyer' => $details['buyer'],
            'lines' => array_map(static fn (array $l): array => [
                'n' => $l['number'],
                'name' => $l['name'],
                'unit' => $l['unit'],
                'quantity' => $l['quantity'],
                'unit_price' => $l['unit_price'],
                'price_kind' => $l['price_kind'],
                'vat_rate' => $l['vat_rate'],
                'net' => $l['net'] ?? $l['gross'],
            ], $details['lines']),
            'summary' => array_map(static fn (array $r): array => ['label' => $r['label'], 'net' => $r['net'], 'vat' => $r['tax'], 'gross' => null], $details['tax_summary']),
            'total' => ['net' => null, 'vat' => null, 'gross' => $details['total']],
            'payment' => $details['payment'] === null ? null : [
                'method' => null,
                'due_date' => $details['payment']['due_dates'][0] ?? null,
                'accounts' => $details['payment']['bank_accounts'],
                'paid' => $details['payment']['is_paid'],
            ],
            'notes' => $details['notes'],
            'ksef_number' => $invoice->ksef_number,
            'offline' => false,
            'qr' => $qr,
            'logo' => null,
        ];
    }

    /**
     * @param  array<string, string|null>  $party
     * @return array{name: string, tax_id: string|null, address: string, email: string|null}
     */
    private static function party(array $party): array
    {
        return [
            'name' => (string) ($party['name'] ?? ''),
            'tax_id' => $party['nip'] ?? null,
            'address' => trim(($party['address'] ?? '').(isset($party['country']) && $party['country'] !== 'PL' ? ' · '.$party['country'] : ''), ' ·'),
            'email' => $party['email'] ?? null,
        ];
    }
}
