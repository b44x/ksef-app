<?php

namespace App\Invoicing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;

/** How an invoice appears in a list: the few facts a row needs, with the gross total from the SDK. */
final class InvoiceRow
{
    /**
     * @return array{id: int, number: string|null, status: string, status_label: string, status_tone: string, offline: bool, offline_deadline: string|null, issue_date: string, contractor: string, currency: string, gross: string, payment_status: string|null, payment_label: string|null, payment_tone: string|null, outstanding: string|null, due_date: string|null, overdue: bool, is_correction: bool, is_proforma: bool}
     */
    public static function of(Invoice $invoice): array
    {
        // The sum fixed when it was issued; a draft has none yet, so it is worked out from its lines.
        $gross = $invoice->gross_total ?? Totals::of(
            $invoice->lines->map(fn ($l): array => $l->only(['name', 'quantity', 'unit_net_price', 'vat_rate']))->all(),
            $invoice->currency,
            $invoice->isCorrection() ? $invoice->stateBefore()->map(fn ($l): array => $l->only(['name', 'quantity', 'unit_net_price', 'vat_rate']))->all() : [],
        )['gross'];
        $issued = $invoice->isSale();

        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'status' => $invoice->status->value,
            'status_label' => $invoice->status->label(),
            'status_tone' => $invoice->status->tone(),
            'offline' => $invoice->offline_mode !== null,
            'offline_deadline' => $invoice->status === InvoiceStatus::Issued ? $invoice->offline_deadline?->toDateString() : null,
            'issue_date' => $invoice->issue_date->toDateString(),
            'contractor' => $invoice->contractor->name,
            'currency' => $invoice->currency,
            'gross' => $gross,
            'payment_status' => $issued ? $invoice->paymentStatus()->value : null,
            'payment_label' => $issued ? $invoice->paymentStatus()->label() : null,
            'payment_tone' => $issued ? $invoice->paymentStatus()->tone() : null,
            'outstanding' => $issued ? $invoice->outstanding()->toString(2) : null,
            'due_date' => $invoice->due_date?->toDateString(),
            'overdue' => $invoice->isOverdue(),
            'is_correction' => $invoice->isCorrection(),
            'is_proforma' => $invoice->isProforma(),
        ];
    }
}
