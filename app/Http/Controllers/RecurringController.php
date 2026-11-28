<?php

namespace App\Http\Controllers;

use App\Enums\Frequency;
use App\Http\Controllers\Concerns\ScopedToCompany;
use App\Invoicing\GenerateRecurring;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Invoices that repeat: made from an invoice you already have, then run by the scheduler. */
class RecurringController extends Controller
{
    use ScopedToCompany;

    public function index(): Response|RedirectResponse
    {
        $company = Company::current();
        if ($company === null) {
            return redirect()->route('company.edit');
        }

        return Inertia::render('Recurring', [
            'schedules' => $company->recurringInvoices()->with('contractor:id,name')->orderByDesc('active')->orderBy('next_run_on')->get()->map(fn (RecurringInvoice $r): array => [
                'id' => $r->id,
                'name' => $r->name,
                'contractor' => $r->contractor->name,
                'frequency' => $r->frequency->value,
                'frequency_label' => $r->frequency->label(),
                'next_run_on' => $r->next_run_on->toDateString(),
                'ends_on' => $r->ends_on?->toDateString(),
                'active' => $r->active,
                'auto_issue' => $r->auto_issue,
                'generated_count' => $r->generated_count,
                'last_error' => $r->last_error,
                'lines' => count($r->lines),
            ])->all(),
            'frequencies' => array_map(fn (Frequency $f): array => ['value' => $f->value, 'label' => $f->label()], Frequency::cases()),
        ]);
    }

    /** A schedule from an invoice: its buyer, series, lines and payment terms are what repeats. */
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeOwnership($invoice);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'frequency' => ['required', Rule::enum(Frequency::class)],
            'next_run_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:next_run_on'],
            'auto_issue' => ['required', 'boolean'],
        ]);
        $first = CarbonImmutable::parse($data['next_run_on']);
        $invoice->loadMissing('lines');

        $schedule = new RecurringInvoice([
            'contractor_id' => $invoice->contractor_id,
            'numbering_series_id' => $invoice->numbering_series_id,
            'invoice_template_id' => $invoice->invoice_template_id,
            'name' => $data['name'],
            'frequency' => $data['frequency'],
            'anchor_day' => $first->day,
            'next_run_on' => $first,
            'ends_on' => $data['ends_on'] ?? null,
            'auto_issue' => $data['auto_issue'],
            'payment_days' => $invoice->due_date === null ? null : max(0, (int) $invoice->issue_date->diffInDays($invoice->due_date)),
            'payment_method' => $invoice->payment_method,
            'bank_account' => $invoice->bank_account,
            'notes' => $invoice->notes,
            'lines' => $invoice->lines->map(fn ($l): array => $l->only(['name', 'unit', 'quantity', 'unit_net_price', 'vat_rate']))->all(),
        ]);
        $schedule->company_id = $invoice->company_id;
        $schedule->save();

        return redirect()->route('recurring.index')->with('status', __('The invoice will be repeated.'));
    }

    public function update(Request $request, RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorizeOwnership($recurring);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'frequency' => ['required', Rule::enum(Frequency::class)],
            'next_run_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:next_run_on'],
            'auto_issue' => ['required', 'boolean'],
            'active' => ['required', 'boolean'],
        ]);

        $recurring->forceFill([...$data, 'anchor_day' => CarbonImmutable::parse($data['next_run_on'])->day, 'ends_on' => $data['ends_on'] ?? null])->save();

        return back()->with('status', __('Saved.'));
    }

    /** One invoice now, dated today; the schedule itself does not move. */
    public function run(RecurringInvoice $recurring, GenerateRecurring $generate): RedirectResponse
    {
        $this->authorizeOwnership($recurring);
        $invoice = $generate->make($recurring, CarbonImmutable::today());

        return redirect()->route($invoice->isDraft() ? 'invoices.edit' : 'invoices.show', $invoice)->with('status', __('The invoice was made.'));
    }

    public function destroy(RecurringInvoice $recurring): RedirectResponse
    {
        $this->authorizeOwnership($recurring);
        $recurring->delete();

        return back()->with('status', __('Deleted.'));
    }
}
