<?php

namespace App\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use B4x\Ksef\Crypto\Digest;
use B4x\Ksef\Exception\ValidationException;
use B4x\Ksef\Qr\QrCodeRenderer;
use B4x\Ksef\Qr\VerificationLinks;
use B4x\Ksef\Support\Nip;

/**
 * The QR codes of an invoice's visualisation, as SVG. KOD I verifies the invoice in KSeF (under it: the KSeF number, or
 * OFFLINE until there is one); KOD II, on offline invoices only, confirms the issuer (under it: CERTYFIKAT).
 */
final class InvoiceQr
{
    /**
     * @return array{verification: string, issuer: string|null}|null null while there is nothing to verify yet: an online
     *                                                               invoice KSeF has not accepted
     */
    public function for(Invoice $invoice): ?array
    {
        $codes = $this->codes($invoice);
        if ($codes === null) {
            return null;
        }

        $renderer = new QrCodeRenderer(220);

        return [
            'verification' => $renderer->svg($codes['verification']['url'], $codes['verification']['caption']),
            'issuer' => $codes['issuer'] === null ? null : $renderer->svg($codes['issuer']['url'], $codes['issuer']['caption']),
        ];
    }

    /**
     * What goes into the codes and under them, for a visualisation that draws and captions them itself (the PDF).
     *
     * @return array{verification: array{url: string, caption: string}, issuer: array{url: string, caption: string}|null}|null
     */
    public function codes(Invoice $invoice): ?array
    {
        if ($invoice->offline_mode?->usesQr() === false) {
            return null; // a total failure: no codes, by the rules
        }

        $offline = $invoice->offline_mode !== null;
        $accepted = $invoice->status === InvoiceStatus::Accepted && $invoice->ksef_number !== null;
        if (! $offline && ! $accepted) {
            return null;
        }

        try {
            $links = new VerificationLinks($invoice->company->environment->sdk());
            $verificationUrl = $invoice->verification_url ?? $links->invoiceUrlFromHash(
                Nip::of($invoice->company->nip),
                $invoice->issue_date,
                Digest::sha256Base64((string) $invoice->xml),
            );

            return [
                'verification' => ['url' => $verificationUrl, 'caption' => $links->label($accepted ? $invoice->ksef_number : null)],
                'issuer' => $offline && $invoice->issuer_url !== null ? ['url' => $invoice->issuer_url, 'caption' => $links->certificateLabel()] : null,
            ];
        } catch (ValidationException) {
            // A KSeF number that does not look like one, or data the links cannot be made from: no code is better than a
            // wrong one, and the page must not break over it.
            return null;
        }
    }
}
