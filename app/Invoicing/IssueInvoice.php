<?php

namespace App\Invoicing;

use App\Enums\InvoiceStatus;
use App\Enums\OfflineMode;
use App\Ksef\KsefClientFactory;
use App\Models\Company;
use App\Models\Invoice;
use B4x\Ksef\Exception\ValidationException;
use B4x\Ksef\Invoice\Invoice as SdkInvoice;
use B4x\Ksef\Invoice\InvoiceDocument;
use B4x\Ksef\Qr\OfflineCertificate;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

/**
 * Turns a draft into an invoice: takes the number from the series, builds the FA(3) document with the SDK (which
 * validates it against the schema) and freezes both. It is all one transaction, so a refusal at any step gives the
 * number back: the series never has a gap.
 */
final class IssueInvoice
{
    public function __construct(
        private readonly InvoiceCheck $check,
        private readonly SdkInvoiceFactory $factory,
        private readonly KsefClientFactory $clients,
        private readonly OfflineDeadlines $deadlines,
    ) {}

    /**
     * @param  OfflineMode|null  $offline  issue without KSeF, with the QR codes, to deliver later (needs the Offline certificate)
     * @param  DateTimeInterface|null  $anchor  when the failure or unavailability ended, if it has; the deadline counts from it
     *
     * @throws InvoiceNotReady when something stands in the way (the reasons are in words)
     */
    public function __invoke(Invoice $invoice, ?OfflineMode $offline = null, ?DateTimeInterface $anchor = null): Invoice
    {
        return DB::transaction(function () use ($invoice, $offline, $anchor): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->with(['company', 'contractor', 'series', 'lines'])->findOrFail($invoice->id);

            if ($invoice->status !== InvoiceStatus::Draft) {
                throw new InvoiceNotReady([__('This invoice has been issued already.')]);
            }

            $problems = $this->check->problems($invoice);
            if ($problems !== []) {
                throw new InvoiceNotReady($problems);
            }

            if ($offline !== null && ! $invoice->company->canIssueOffline()) {
                throw new InvoiceNotReady([__('Request the Offline certificate first (Company settings): it confirms you as the issuer in QR code II.')]);
            }

            $number = $invoice->series->issue($invoice->issue_date);

            try {
                $sdk = $this->factory->make($invoice, $number);
                $issued = $offline === null ? $this->online($sdk) : $this->offline($invoice->company, $sdk);
            } catch (ValidationException $e) {
                // Rolls the number back with everything else.
                throw new InvoiceNotReady($e->violations !== [] ? $e->violations : [$e->getMessage()]);
            }

            $invoice->forceFill([
                'number' => $number,
                'xml' => $issued['xml'],
                'snapshot' => $this->snapshot($invoice),
                'status' => InvoiceStatus::Issued,
                'issued_at' => now(),
                'offline_mode' => $offline,
                'offline_anchor_date' => $offline?->needsAnchor() ? $anchor : null,
                'offline_deadline' => $offline === null ? null : $this->deadlines->for($offline, $invoice->issue_date, $anchor),
                'verification_url' => $issued['verification_url'],
                'issuer_url' => $issued['issuer_url'],
            ])->save();

            return $invoice;
        });
    }

    /**
     * @return array{xml: string, verification_url: string|null, issuer_url: string|null}
     */
    private function online(SdkInvoice $invoice): array
    {
        return ['xml' => InvoiceDocument::fromInvoice($invoice)->xml, 'verification_url' => null, 'issuer_url' => null];
    }

    /**
     * Issues the invoice without talking to KSeF: the document, and the two links for the QR codes (the second one signed
     * with the Offline certificate).
     *
     * @return array{xml: string, verification_url: string|null, issuer_url: string|null}
     */
    private function offline(Company $company, SdkInvoice $invoice): array
    {
        $certificate = new OfflineCertificate((string) ($company->offline_certificate['certificate'] ?? ''), (string) ($company->offline_certificate['private_key'] ?? ''));
        $offline = $this->clients->make($company)->issueOfflineInvoice($invoice, $certificate);

        return ['xml' => $offline->document->xml, 'verification_url' => $offline->verificationUrl, 'issuer_url' => $offline->issuerUrl];
    }

    /**
     * @return array{seller: array<string, string|null>, buyer: array<string, string|null>}
     */
    private function snapshot(Invoice $invoice): array
    {
        $company = $invoice->company;
        $buyer = $invoice->contractor;

        return [
            'seller' => [
                'name' => $company->name,
                'nip' => $company->nip,
                'address' => trim($company->address_line1.', '.$company->address_line2, ', '),
                'email' => $company->email,
            ],
            'buyer' => [
                'name' => $buyer->name,
                'nip' => $buyer->nip,
                'address' => trim($buyer->address_line1.', '.$buyer->address_line2, ', '),
                'country' => $buyer->country,
                'email' => $buyer->email,
            ],
        ];
    }
}
