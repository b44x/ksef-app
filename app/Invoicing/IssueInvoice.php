<?php

namespace App\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use B4x\Ksef\Exception\ValidationException;
use B4x\Ksef\Invoice\InvoiceDocument;
use Illuminate\Support\Facades\DB;

/**
 * Turns a draft into an invoice: takes the number from the series, builds the FA(3) document with the SDK (which
 * validates it against the schema) and freezes both. It is all one transaction, so a refusal at any step gives the
 * number back: the series never has a gap.
 */
final class IssueInvoice
{
    public function __construct(private readonly InvoiceCheck $check, private readonly SdkInvoiceFactory $factory) {}

    /**
     * @throws InvoiceNotReady when something stands in the way (the reasons are in words)
     */
    public function __invoke(Invoice $invoice): Invoice
    {
        return DB::transaction(function () use ($invoice): Invoice {
            $invoice = Invoice::query()->lockForUpdate()->with(['company', 'contractor', 'series', 'lines'])->findOrFail($invoice->id);

            if ($invoice->status !== InvoiceStatus::Draft) {
                throw new InvoiceNotReady([__('This invoice has been issued already.')]);
            }

            $problems = $this->check->problems($invoice);
            if ($problems !== []) {
                throw new InvoiceNotReady($problems);
            }

            $number = $invoice->series->issue($invoice->issue_date);

            try {
                $document = InvoiceDocument::fromInvoice($this->factory->make($invoice, $number));
            } catch (ValidationException $e) {
                // Rolls the number back with everything else.
                throw new InvoiceNotReady($e->violations !== [] ? $e->violations : [$e->getMessage()]);
            }

            $invoice->forceFill([
                'number' => $number,
                'xml' => $document->xml,
                'snapshot' => $this->snapshot($invoice),
                'status' => InvoiceStatus::Issued,
                'issued_at' => now(),
            ])->save();

            return $invoice;
        });
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
