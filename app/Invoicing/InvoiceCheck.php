<?php

namespace App\Invoicing;

use App\Models\Invoice;
use B4x\Ksef\Exception\ValidationException;
use B4x\Ksef\Invoice\InvoiceValidator;

/** What stands in the way of issuing a draft, in words, from the SDK's own rules. */
final class InvoiceCheck
{
    public function __construct(private readonly SdkInvoiceFactory $factory) {}

    /**
     * @return list<string>
     */
    public function problems(Invoice $invoice): array
    {
        $problems = [];
        $company = $invoice->company;

        if ($company->address_line1 === null || $company->address_line1 === '') {
            $problems[] = __('Fill in the company address first.');
        }
        if ($invoice->lines->isEmpty()) {
            $problems[] = __('Add at least one line.');
        }
        if ($invoice->isCorrection()) {
            if (trim((string) $invoice->correction_reason) === '') {
                $problems[] = __('Give the reason for the correction.');
            }
            if (! $invoice->corrected?->canBeCorrected()) {
                $problems[] = __('The corrected invoice must be accepted by KSeF first.');
            }
        }
        if ($problems !== [] || $invoice->isProforma()) {
            return $problems; // a proforma is not an FA(3) document: nothing more to check
        }

        try {
            // The real number is taken from the series only when the invoice is issued; the check uses the next one.
            $sdk = $this->factory->make($invoice, $invoice->number ?? $invoice->series->preview($invoice->issue_date));
        } catch (ValidationException $e) {
            return $e->violations;
        }

        return InvoiceValidator::violations($sdk);
    }
}
