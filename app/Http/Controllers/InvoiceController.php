<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopedToCompany;
use App\Http\Requests\InvoiceRequest;
use App\Invoicing\InvoiceCheck;
use App\Invoicing\InvoiceRow;
use App\Invoicing\SdkInvoiceFactory;
use App\Invoicing\Totals;
use App\Models\Company;
use App\Models\Invoice;
use B4x\Ksef\Invoice\PaymentMethod;
use B4x\Ksef\Invoice\VatRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class InvoiceController extends Controller
{
    use ScopedToCompany;

    public function index(): Response|RedirectResponse
    {
        $company = Company::current();
        if ($company === null) {
            return redirect()->route('company.edit');
        }

        $invoices = $company->invoices()->with(['contractor:id,name', 'lines'])->orderByDesc('issue_date')->orderByDesc('id')->get();

        return Inertia::render('Invoices', [
            'invoices' => $invoices->map(fn (Invoice $i): array => InvoiceRow::of($i)),
        ]);
    }

    public function create(): Response|RedirectResponse
    {
        $company = Company::current();
        if ($company === null) {
            return redirect()->route('company.edit');
        }

        return $this->form($company, null);
    }

    public function edit(Invoice $invoice): Response
    {
        $this->authorizeOwnership($invoice);

        return $this->form($invoice->company, $invoice->load(['lines', 'company', 'contractor', 'series']));
    }

    public function store(InvoiceRequest $request): RedirectResponse
    {
        $company = Company::current();
        abort_if($company === null, 404);

        $invoice = DB::transaction(function () use ($request, $company): Invoice {
            $invoice = new Invoice($request->safe()->except('lines'));
            $invoice->company()->associate($company);
            $invoice->save();
            $this->saveLines($invoice, $request->validated('lines'));

            return $invoice;
        });

        return redirect()->route('invoices.edit', $invoice)->with('status', __('Draft saved.'));
    }

    public function update(InvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeOwnership($invoice);
        abort_unless($invoice->isDraft(), 403);

        DB::transaction(function () use ($request, $invoice): void {
            $invoice->update($request->safe()->except('lines'));
            $invoice->lines()->delete();
            $this->saveLines($invoice, $request->validated('lines'));
        });

        return redirect()->route('invoices.edit', $invoice)->with('status', __('Draft saved.'));
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        $this->authorizeOwnership($invoice);
        abort_unless($invoice->isDraft(), 403);
        $invoice->delete();

        return redirect()->route('invoices.index')->with('status', __('Draft deleted.'));
    }

    /** The totals of the lines as typed so far, from the SDK, without saving anything. */
    public function preview(Request $request): JsonResponse
    {
        $data = $request->validate([
            'currency' => ['nullable', 'string', 'size:3'],
            'lines' => ['present', 'array', 'max:1000'],
            ...InvoiceRequest::lineRules('lines.*.'),
        ]);

        return response()->json(Totals::of($data['lines'], $data['currency'] ?? 'PLN'));
    }

    private function form(Company $company, ?Invoice $invoice): Response
    {
        return Inertia::render('InvoiceForm', [
            'invoice' => $invoice === null ? null : [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'contractor_id' => $invoice->contractor_id,
                'numbering_series_id' => $invoice->numbering_series_id,
                'issue_date' => $invoice->issue_date->toDateString(),
                'sale_date' => $invoice->sale_date?->toDateString() ?? '',
                'due_date' => $invoice->due_date?->toDateString() ?? '',
                'payment_method' => $invoice->payment_method === null ? '' : (string) $invoice->payment_method,
                'bank_account' => $invoice->bank_account ?? '',
                'notes' => $invoice->notes ?? '',
                'lines' => $invoice->lines->map(fn ($l): array => [
                    'name' => $l->name,
                    'unit' => $l->unit ?? '',
                    'quantity' => SdkInvoiceFactory::trim($l->quantity),
                    'unit_net_price' => SdkInvoiceFactory::trim($l->unit_net_price),
                    'vat_rate' => $l->vat_rate,
                ]),
                'problems' => app(InvoiceCheck::class)->problems($invoice),
                'preview_number' => $invoice->series->preview($invoice->issue_date),
            ],
            'seller' => [
                'name' => $company->name,
                'nip' => $company->nip,
                'address' => trim($company->address_line1.', '.$company->address_line2, ', '),
                'email' => $company->email,
            ],
            'today' => now()->toDateString(),
            'defaultSeriesId' => $company->numberingSeries()->where('is_default', true)->value('id'),
            'contractors' => $company->contractors()->orderBy('name')->get(['id', 'name', 'nip', 'address_line1', 'address_line2', 'country']),
            'series' => $company->numberingSeries()->orderBy('name')->get(['id', 'name']),
            'products' => $company->products()->orderBy('name')->get(['id', 'name', 'unit', 'net_price', 'vat_rate']),
            'vatRates' => array_map(static fn (VatRate $r): string => $r->value, VatRate::cases()),
            'paymentMethods' => array_map(static fn (PaymentMethod $m): array => ['value' => (string) $m->value, 'label' => self::methodLabel($m)], PaymentMethod::cases()),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function saveLines(Invoice $invoice, array $lines): void
    {
        foreach ($lines as $position => $line) {
            $invoice->lines()->create([...$line, 'position' => $position + 1, 'unit' => $line['unit'] ?? null]);
        }
    }

    private static function methodLabel(PaymentMethod $method): string
    {
        return match ($method) {
            PaymentMethod::Cash => __('Cash'),
            PaymentMethod::Card => __('Card'),
            PaymentMethod::Voucher => __('Voucher'),
            PaymentMethod::Cheque => __('Cheque'),
            PaymentMethod::Credit => __('Credit'),
            PaymentMethod::BankTransfer => __('Bank transfer'),
            PaymentMethod::Mobile => __('Mobile payment'),
        };
    }
}
