<?php

namespace App\Invoicing;

use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Makes the invoices of a recurring schedule: a draft with the same buyer, series and lines and today's date, or an
 * issued invoice when the schedule says so (sending is never automatic). Runs once per due date: missed runs (the
 * scheduler was off) are not made up, the schedule just moves past today.
 */
final class GenerateRecurring
{
    public function __construct(private readonly IssueInvoice $issue) {}

    /** The scheduled run: nothing happens unless the schedule is active and due. */
    public function __invoke(RecurringInvoice $schedule, CarbonImmutable $today): ?Invoice
    {
        return DB::transaction(function () use ($schedule, $today): ?Invoice {
            $schedule = RecurringInvoice::query()->lockForUpdate()->findOrFail($schedule->id);
            if (! $schedule->active || $schedule->next_run_on->startOfDay()->greaterThan($today)) {
                return null;
            }

            $invoice = $this->make($schedule, $today);

            $next = CarbonImmutable::instance($schedule->next_run_on);
            do {
                $next = $schedule->frequency->after($next, $schedule->anchor_day);
            } while ($next->lessThanOrEqualTo($today));

            $schedule->forceFill([
                'next_run_on' => $next,
                'generated_count' => $schedule->generated_count + 1,
                'last_run_at' => now(),
                'active' => $schedule->ends_on === null || $next->lessThanOrEqualTo($schedule->ends_on),
            ])->save();

            return $invoice;
        });
    }

    /** One invoice from the schedule, dated $today, whatever the schedule's own dates are ("make one now"). */
    public function make(RecurringInvoice $schedule, CarbonImmutable $today): Invoice
    {
        $invoice = new Invoice([
            'contractor_id' => $schedule->contractor_id,
            'numbering_series_id' => $schedule->numbering_series_id,
            'invoice_template_id' => $schedule->invoice_template_id,
            'issue_date' => $today->toDateString(),
            'sale_date' => $today->toDateString(),
            'due_date' => $schedule->payment_days === null ? null : $today->addDays($schedule->payment_days)->toDateString(),
            'payment_method' => $schedule->payment_method,
            'bank_account' => $schedule->bank_account,
            'notes' => $schedule->notes,
        ]);
        $invoice->company_id = $schedule->company_id;
        $invoice->recurring_invoice_id = $schedule->id;
        $invoice->save();

        foreach ($schedule->lines as $position => $line) {
            $invoice->lines()->create($line + ['position' => $position + 1]);
        }

        if (! $schedule->auto_issue) {
            $schedule->forceFill(['last_error' => null])->save();

            return $invoice;
        }

        try {
            $invoice = ($this->issue)($invoice);
            $schedule->forceFill(['last_error' => null])->save();
        } catch (InvoiceNotReady $e) {
            // Left as a draft for a person to fix; the schedule goes on.
            $schedule->forceFill(['last_error' => implode(' ', $e->problems)])->save();
        }

        return $invoice;
    }
}
