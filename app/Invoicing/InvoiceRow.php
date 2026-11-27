<?php

namespace App\Invoicing;

use App\Models\Invoice;

/** How an invoice appears in a list: the few facts a row needs, with the gross total from the SDK. */
final class InvoiceRow
{
    /**
     * @return array{id: int, number: string|null, status: string, status_label: string, issue_date: string, contractor: string, currency: string, gross: string}
     */
    public static function of(Invoice $invoice): array
    {
        $lines = $invoice->lines->map(fn ($l): array => [
            'name' => $l->name,
            'quantity' => $l->quantity,
            'unit_net_price' => $l->unit_net_price,
            'vat_rate' => $l->vat_rate,
        ])->all();

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'issue_date' => $invoice->issue_date->toDateString(),
            'contractor' => $invoice->contractor->name,
            'currency' => $invoice->currency,
            'gross' => Totals::of($lines, $invoice->currency)['gross'],
        ];
    }
}
