<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Enums\OfflineMode;
use App\Http\Controllers\Concerns\ScopedToCompany;
use App\Http\Requests\InvoiceRequest;
use App\Invoicing\InvoiceCheck;
use App\Invoicing\InvoiceNotReady;
use App\Invoicing\InvoiceQr;
use App\Invoicing\InvoiceRow;
use App\Invoicing\IssueInvoice;
use App\Invoicing\OfflineDeadlines;
use App\Invoicing\SdkInvoiceFactory;
use App\Invoicing\Totals;
use App\Jobs\PollInvoiceStatusJob;
use App\Jobs\SendInvoiceJob;
use App\Ksef\AvailabilityMonitor;
use App\Ksef\AvailabilityState;
use App\Ksef\KsefGateway;
use App\Models\Company;
use App\Models\Invoice;
use B4x\Ksef\Invoice\PaymentMethod;
use B4x\Ksef\Invoice\VatRate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public function edit(Invoice $invoice): Response|RedirectResponse
    {
        $this->authorizeOwnership($invoice);
        if (! $invoice->isDraft()) {
            return redirect()->route('invoices.show', $invoice);
        }

        return $this->form($invoice->company, $invoice->load(['lines', 'company', 'contractor', 'series']));
    }

    public function show(Invoice $invoice): Response|RedirectResponse
    {
        $this->authorizeOwnership($invoice);
        if ($invoice->isDraft()) {
            return redirect()->route('invoices.edit', $invoice);
        }

        $invoice->load(['lines', 'series']);
        $lines = $invoice->lines->map(fn ($l): array => [
            'name' => $l->name,
            'unit' => $l->unit ?? '',
            'quantity' => SdkInvoiceFactory::trim($l->quantity),
            'unit_net_price' => SdkInvoiceFactory::trim($l->unit_net_price),
            'vat_rate' => $l->vat_rate,
        ])->all();

        return Inertia::render('InvoiceShow', [
            'invoice' => [
                'id' => $invoice->id,
                'number' => $invoice->number,
                'status' => $invoice->status->value,
                'status_label' => $invoice->status->label(),
                'status_tone' => $invoice->status->tone(),
                'issue_date' => $invoice->issue_date->toDateString(),
                'sale_date' => $invoice->sale_date?->toDateString(),
                'due_date' => $invoice->due_date?->toDateString(),
                'payment_method' => $invoice->payment_method === null ? null : self::methodLabel(PaymentMethod::from($invoice->payment_method)),
                'bank_account' => $invoice->bank_account,
                'notes' => $invoice->notes,
                'currency' => $invoice->currency,
                'issued_at' => $invoice->issued_at?->toIso8601String(),
                'parties' => $invoice->snapshot,
                'lines' => $lines,
                'xml' => self::pretty((string) $invoice->xml),
                'ksef' => [
                    'number' => $invoice->ksef_number,
                    'code' => $invoice->ksef_status_code,
                    'description' => $invoice->ksef_status_description,
                    'error' => $invoice->ksef_error,
                    'sent_at' => $invoice->sent_at?->toIso8601String(),
                    'accepted_at' => $invoice->accepted_at?->toIso8601String(),
                    'has_upo' => $invoice->upo_xml !== null,
                    'can_check' => $invoice->status === InvoiceStatus::Sending && $invoice->submission() !== null,
                ],
            ],
            'offline' => $invoice->offline_mode === null ? null : [
                'mode' => $invoice->offline_mode->value,
                'label' => $invoice->offline_mode->label(),
                'hint' => $invoice->offline_mode->hint(),
                'needs_anchor' => $invoice->offline_mode->needsAnchor(),
                'anchor_date' => $invoice->offline_anchor_date?->toDateString(),
                'deadline' => $invoice->offline_deadline?->toIso8601String(),
                'overdue' => $invoice->offline_deadline !== null && $invoice->offline_deadline->isPast() && $invoice->status === InvoiceStatus::Issued,
            ],
            'qr' => app(InvoiceQr::class)->for($invoice->loadMissing('company')),
            'canSend' => $invoice->status === InvoiceStatus::Issued && ! $invoice->company->needsSigningIn(),
            'needsSigning' => $invoice->company->needsSigningIn(),
            'totals' => Totals::of($lines, $invoice->currency),
        ]);
    }

    public function issue(Request $request, Invoice $invoice, IssueInvoice $issue): RedirectResponse
    {
        $this->authorizeOwnership($invoice);

        $data = $request->validate([
            'mode' => ['nullable', Rule::enum(OfflineMode::class)],
            'anchor_date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $mode = isset($data['mode']) ? OfflineMode::from($data['mode']) : null;
        $anchor = isset($data['anchor_date']) ? new \DateTimeImmutable($data['anchor_date']) : null;

        try {
            $issued = $issue($invoice, $mode, $anchor);
        } catch (InvoiceNotReady $e) {
            return back()->withErrors(['issue' => implode("\n", $e->problems)]);
        }

        return redirect()->route('invoices.show', $issued)->with('status', __('Invoice issued: :number', ['number' => (string) $issued->number]));
    }

    /** The reason for issuing offline that fits what KSeF is doing right now: an announced failure or maintenance, else the owner's choice. */
    private static function suggestedMode(): OfflineMode
    {
        $company = Company::current();
        $state = $company === null ? null : app(AvailabilityMonitor::class)->current($company)?->state;

        return match ($state) {
            AvailabilityState::Failure, AvailabilityState::TotalFailure => OfflineMode::Emergency,
            AvailabilityState::Maintenance => OfflineMode::Unavailability,
            default => OfflineMode::Offline24,
        };
    }

    /** Says when the failure or unavailability ended, so that the delivery deadline can be counted. */
    public function offlineAnchor(Request $request, Invoice $invoice, OfflineDeadlines $deadlines): RedirectResponse
    {
        $this->authorizeOwnership($invoice);
        abort_unless($invoice->offline_mode?->needsAnchor() === true && $invoice->status === InvoiceStatus::Issued, 403);

        $data = $request->validate(['anchor_date' => ['required', 'date_format:Y-m-d']]);
        $anchor = new \DateTimeImmutable($data['anchor_date']);
        $invoice->forceFill([
            'offline_anchor_date' => $anchor,
            'offline_deadline' => $deadlines->for($invoice->offline_mode, $invoice->issue_date, $anchor),
        ])->save();

        return back()->with('status', __('The delivery deadline was set.'));
    }

    /** Hands every offline invoice that waits for delivery to KSeF. */
    public function sendOffline(): RedirectResponse
    {
        $company = Company::current();
        abort_if($company === null, 404);
        if ($company->needsSigningIn()) {
            return back()->withErrors(['send' => __('Sign in to KSeF first: the signed login has expired or was never made.')]);
        }

        $sent = 0;
        foreach ($company->invoices()->where('status', InvoiceStatus::Issued)->whereNotNull('offline_mode')->pluck('id') as $id) {
            // The same atomic claim as the single button: nothing is handed over twice.
            $claimed = Invoice::query()->whereKey($id)->where('status', InvoiceStatus::Issued)
                ->update(['status' => InvoiceStatus::Sending, 'ksef_error' => null, 'sent_at' => now()]);
            if ($claimed === 1) {
                SendInvoiceJob::dispatch($id);
                $sent++;
            }
        }

        return back()->with('status', trans_choice('{0} No offline invoices are waiting.|{1} Sending one offline invoice to KSeF…|[2,*] Sending :count offline invoices to KSeF…', $sent, ['count' => $sent]));
    }

    /** Hands the issued invoice to KSeF in the background; the page follows its status. */
    public function send(Invoice $invoice): RedirectResponse
    {
        $this->authorizeOwnership($invoice);

        if ($invoice->company->needsSigningIn()) {
            return back()->withErrors(['send' => __('Sign in to KSeF first: the signed login has expired or was never made.')]);
        }

        // One atomic step: two clicks, or two tabs, hand the invoice over once.
        $claimed = Invoice::query()->whereKey($invoice->id)->where('status', InvoiceStatus::Issued)
            ->update(['status' => InvoiceStatus::Sending, 'ksef_error' => null, 'sent_at' => now()]);
        if ($claimed === 0) {
            return back()->withErrors(['send' => __('This invoice is not waiting to be sent.')]);
        }

        SendInvoiceJob::dispatch($invoice->id);

        return back()->with('status', __('Sending to KSeF…'));
    }

    /** Asks KSeF again for its verdict on an invoice that is still being checked. */
    public function check(Invoice $invoice): RedirectResponse
    {
        $this->authorizeOwnership($invoice);
        abort_unless($invoice->status === InvoiceStatus::Sending && $invoice->submission() !== null, 403);

        $invoice->forceFill(['poll_attempts' => 0, 'ksef_error' => null])->save();
        PollInvoiceStatusJob::dispatch($invoice->id);

        return back();
    }

    /** The official confirmation of receipt; fetched from KSeF the first time it is asked for. */
    public function upo(Invoice $invoice, KsefGateway $gateway): StreamedResponse|RedirectResponse
    {
        $this->authorizeOwnership($invoice);
        abort_unless($invoice->status === InvoiceStatus::Accepted && $invoice->submission() !== null, 404);

        if ($invoice->upo_xml === null) {
            $upo = $gateway->upo($invoice->company, $invoice->submission());
            if ($upo === null) {
                return back()->withErrors(['send' => __('The confirmation (UPO) is not available yet. Try again in a moment.')]);
            }
            $invoice->forceFill(['upo_xml' => $upo])->save();
        }

        $name = 'UPO-'.preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $invoice->ksef_number).'.xml';

        return response()->streamDownload(function () use ($invoice): void {
            echo $invoice->upo_xml;
        }, $name, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /** A new draft with the same buyer, series and lines: the way on after a rejection, and a time saver otherwise. */
    public function duplicate(Invoice $invoice): RedirectResponse
    {
        $this->authorizeOwnership($invoice);

        $copy = DB::transaction(function () use ($invoice): Invoice {
            $copy = new Invoice([
                'contractor_id' => $invoice->contractor_id,
                'numbering_series_id' => $invoice->numbering_series_id,
                'issue_date' => now()->toDateString(),
                'payment_method' => $invoice->payment_method,
                'bank_account' => $invoice->bank_account,
                'notes' => $invoice->notes,
            ]);
            $copy->company()->associate($invoice->company);
            $copy->save();
            foreach ($invoice->lines as $line) {
                $copy->lines()->create($line->only(['position', 'name', 'unit', 'quantity', 'unit_net_price', 'vat_rate']));
            }

            return $copy;
        });

        return redirect()->route('invoices.edit', $copy)->with('status', __('A new draft was made from :number.', ['number' => (string) $invoice->number]));
    }

    public function xml(Invoice $invoice): StreamedResponse
    {
        $this->authorizeOwnership($invoice);
        abort_if($invoice->xml === null, 404);

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $invoice->number).'.xml';

        return response()->streamDownload(function () use ($invoice): void {
            echo $invoice->xml;
        }, $name, ['Content-Type' => 'application/xml; charset=utf-8']);
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
            'offline' => [
                'available' => $company->canIssueOffline(),
                'modes' => array_map(fn (OfflineMode $m): array => ['value' => $m->value, 'label' => $m->label(), 'hint' => $m->hint(), 'needs_anchor' => $m->needsAnchor()], OfflineMode::cases()),
                'suggested' => self::suggestedMode()->value,
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

    /** The document indented for reading. Only for display: what is stored and downloaded is byte for byte what KSeF gets. */
    private static function pretty(string $xml): string
    {
        $dom = new \DOMDocument;
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;

        return $dom->loadXML($xml) ? (string) $dom->saveXML() : $xml;
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
