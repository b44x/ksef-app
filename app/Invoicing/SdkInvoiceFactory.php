<?php

namespace App\Invoicing;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use B4x\Ksef\Invoice\Address;
use B4x\Ksef\Invoice\Buyer;
use B4x\Ksef\Invoice\BuyerIdentifier;
use B4x\Ksef\Invoice\CorrectedInvoice;
use B4x\Ksef\Invoice\Correction;
use B4x\Ksef\Invoice\CorrectionType;
use B4x\Ksef\Invoice\Invoice as SdkInvoice;
use B4x\Ksef\Invoice\InvoiceLine as SdkLine;
use B4x\Ksef\Invoice\Payment;
use B4x\Ksef\Invoice\PaymentMethod;
use B4x\Ksef\Invoice\Seller;
use B4x\Ksef\Invoice\VatRate;
use B4x\Ksef\Support\Nip;
use DateTimeImmutable;

/**
 * Turns an invoice of this application into the SDK's invoice. This is the one place that knows both shapes; the SDK
 * then does what it is good at: the totals, the validation, the FA(3) XML.
 */
final class SdkInvoiceFactory
{
    public function make(Invoice $invoice, string $number): SdkInvoice
    {
        $company = $invoice->company;
        $contractor = $invoice->contractor;

        $builder = SdkInvoice::builder()
            ->number($number)
            ->issueDate($invoice->issue_date)
            ->currency($invoice->currency)
            ->seller(new Seller(
                Nip::of($company->nip),
                $company->name,
                new Address('PL', (string) $company->address_line1, $company->address_line2),
                $company->email,
            ))
            ->buyer(new Buyer(
                $contractor->nip !== null ? BuyerIdentifier::nip(Nip::of($contractor->nip)) : BuyerIdentifier::none(),
                $contractor->name,
                new Address($contractor->country, $contractor->address_line1, $contractor->address_line2),
                $contractor->email,
            ));

        if ($invoice->sale_date !== null) {
            $builder->saleDate($invoice->sale_date);
        }
        if ($invoice->notes !== null && $invoice->notes !== '') {
            $builder->footer($invoice->notes);
        }
        if ($invoice->due_date !== null) {
            $builder->payment(Payment::dueOn(
                DateTimeImmutable::createFromInterface($invoice->due_date),
                $invoice->payment_method !== null ? PaymentMethod::from($invoice->payment_method) : PaymentMethod::BankTransfer,
                $invoice->bank_account !== null && $invoice->bank_account !== '' ? [$invoice->bank_account] : [],
            ));
        }

        if ($invoice->isCorrection()) {
            $original = $invoice->corrected;
            $builder->correction(new Correction(
                [new CorrectedInvoice(DateTimeImmutable::createFromInterface($original->issue_date), (string) $original->number, $original->ksef_number)],
                CorrectionType::from($invoice->correction_type),
                $invoice->correction_reason,
            ));
            // The corrected invoice as it stood, then as it should be: the totals are the difference.
            foreach ($invoice->stateBefore() as $line) {
                $builder->addLine($this->line($line, $invoice->currency)->asBefore());
            }
        }

        foreach ($invoice->lines as $line) {
            $builder->addLine($this->line($line, $invoice->currency));
        }

        return $builder->build();
    }

    private function line(InvoiceLine $line, string $currency): SdkLine
    {
        return SdkLine::of(
            $line->name,
            self::trim($line->quantity),
            $line->unit,
            self::trim($line->unit_net_price),
            VatRate::from($line->vat_rate),
            $currency,
        );
    }

    /** Database decimals come with trailing zeros ("1.000000"); the SDK takes the plain number. */
    public static function trim(string $decimal): string
    {
        return str_contains($decimal, '.') ? rtrim(rtrim($decimal, '0'), '.') : $decimal;
    }
}
