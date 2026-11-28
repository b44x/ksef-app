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
        $invoice->loadMissing(['lines', 'company', 'series', 'corrected']);
        $parties = $invoice->snapshot ?? ['seller' => [], 'buyer' => []];
        $lineData = $invoice->lines->map(fn ($l): array => $l->only(['name', 'quantity', 'unit_net_price', 'vat_rate']))->all();
        // A correction shows the corrected invoice as it stood, then as it is now; its sums are the difference.
        $beforeData = $invoice->isCorrection() ? $invoice->stateBefore()->map(fn ($l): array => $l->only(['name', 'quantity', 'unit_net_price', 'vat_rate']))->all() : [];
        $rate = $invoice->currency !== 'PLN' && $invoice->exchange_rate !== null ? SdkInvoiceFactory::trim($invoice->exchange_rate) : null;
        $totals = Totals::of($lineData, $invoice->currency, $beforeData, $rate);
        $row = static fn (array $l, string $net, string $state): array => [
            'name' => $l['name'],
            'unit' => $l['unit'] ?? '',
            'quantity' => SdkInvoiceFactory::trim($l['quantity']),
            'unit_price' => SdkInvoiceFactory::trim($l['unit_net_price']),
            'price_kind' => 'net',
            'vat_rate' => $l['vat_rate'],
            'net' => $net,
            'state' => $state,
        ];
        $lines = [];
        foreach ($invoice->isCorrection() ? $invoice->stateBefore() : [] as $l) {
            $lines[] = $row($l->only(['name', 'unit', 'quantity', 'unit_net_price', 'vat_rate']), Totals::of([$l->only(['name', 'quantity', 'unit_net_price', 'vat_rate'])], $invoice->currency)['lines'][0], 'before');
        }
        foreach ($invoice->lines as $i => $l) {
            $lines[] = $row($l->only(['name', 'unit', 'quantity', 'unit_net_price', 'vat_rate']), $totals['lines'][$i], $invoice->isCorrection() ? 'after' : 'current');
        }
        foreach ($lines as $n => &$line) {
            $line = ['n' => $n + 1] + $line;
        }
        unset($line);

        return [
            'title' => $invoice->isCorrection() ? 'Correction invoice' : 'VAT invoice',
            'number' => (string) $invoice->number,
            'issue_date' => $invoice->issue_date->toDateString(),
            'sale_date' => $invoice->sale_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'currency' => $invoice->currency,
            'seller' => self::party($parties['seller']),
            'buyer' => self::party($parties['buyer']),
            'lines' => $lines,
            'correction' => $invoice->isCorrection() ? ['of_number' => (string) $invoice->corrected->number, 'of_date' => $invoice->corrected->issue_date->toDateString(), 'reason' => $invoice->correction_reason] : null,
            'summary' => array_map(static fn (array $b): array => [
                'label' => $b['vat'] !== null ? $b['rate'].'%' : $b['rate'],
                'net' => $b['net'],
                'vat' => $b['vat'],
                'gross' => $b['gross'],
            ], $totals['buckets']),
            'total' => ['net' => $totals['net'], 'vat' => $totals['vat'], 'gross' => $totals['gross']],
            'exchange' => $rate === null ? null : ['rate' => $rate, 'date' => $invoice->exchange_rate_date?->toDateString(), 'vat_pln' => $totals['vat_pln']],
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
