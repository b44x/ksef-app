<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopedToCompany;
use App\Jobs\SyncPurchasesJob;
use App\Models\Company;
use App\Models\ReceivedInvoice;
use App\Pdf\InvoicePdf;
use App\Pdf\InvoiceView;
use App\Receiving\ReadInvoice;
use App\Receiving\SyncPurchases;
use B4x\Ksef\Exception\KsefException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Purchase invoices: what other companies issued to this one, brought from KSeF. */
class PurchaseController extends Controller
{
    use ScopedToCompany;

    public function index(Request $request): Response|RedirectResponse
    {
        $company = Company::current();
        if ($company === null) {
            return redirect()->route('company.edit');
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'unread' => ['nullable', 'boolean'],
        ]);

        $invoices = $company->receivedInvoices()
            ->when($filters['q'] ?? null, function ($query, string $q): void {
                // A "%" or "_" typed by the user is just a character. "!" is the escape character because the usual backslash
                // is not one in every database.
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $q).'%';
                $query->where(fn ($w) => $w
                    ->whereRaw("seller_name like ? escape '!'", [$like])->orWhereRaw("number like ? escape '!'", [$like])
                    ->orWhereRaw("seller_nip like ? escape '!'", [$like])->orWhereRaw("ksef_number like ? escape '!'", [$like]));
            })
            ->when($filters['from'] ?? null, fn ($query, string $d) => $query->where('issue_date', '>=', $d))
            ->when($filters['to'] ?? null, fn ($query, string $d) => $query->where('issue_date', '<=', $d))
            ->when($filters['unread'] ?? false, fn ($query) => $query->whereNull('read_at'))
            ->orderByDesc('issue_date')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        return Inertia::render('Purchases', [
            'invoices' => $invoices->through(fn (ReceivedInvoice $i): array => [
                'id' => $i->id,
                'number' => $i->number,
                'seller_name' => $i->seller_name,
                'seller_nip' => $i->seller_nip,
                'issue_date' => $i->issue_date->toDateString(),
                'gross' => $i->gross,
                'currency' => $i->currency,
                'type' => $i->type,
                'is_new' => $i->read_at === null,
            ]),
            'filters' => [
                'q' => $filters['q'] ?? '',
                'from' => $filters['from'] ?? '',
                'to' => $filters['to'] ?? '',
                'unread' => (bool) ($filters['unread'] ?? false),
            ],
            'sync' => [
                'started' => $company->purchases_synced_until !== null,
                'at' => $company->purchases_synced_at?->toIso8601String(),
                'error' => $company->purchases_sync_error,
                'running' => Cache::has(SyncPurchasesJob::runningKey($company->id)),
                'needs_signing' => $company->needsSigningIn(),
            ],
            'defaultSince' => now()->subDays(SyncPurchases::DEFAULT_LOOKBACK_DAYS)->toDateString(),
        ]);
    }

    public function show(ReceivedInvoice $invoice, ReadInvoice $reader): Response
    {
        $this->authorizeOwnership($invoice);

        if ($invoice->read_at === null) {
            $invoice->forceFill(['read_at' => now()])->save();
        }

        try {
            $details = $reader->details($invoice->xml);
            $problem = null;
        } catch (KsefException $e) {
            // Still the issuer's document: it can be downloaded even when this version cannot show it.
            $details = null;
            $problem = $e->getMessage();
        }

        return Inertia::render('PurchaseShow', [
            'invoice' => [
                'id' => $invoice->id,
                'ksef_number' => $invoice->ksef_number,
                'number' => $invoice->number,
                'seller_name' => $invoice->seller_name,
                'received_at' => $invoice->received_at->toIso8601String(),
                'xml' => $invoice->xml,
            ],
            'details' => $details,
            'problem' => $problem,
        ]);
    }

    /** Fetches the purchase invoices from KSeF in the background. The first time, from the date the user picked. */
    public function sync(Request $request): RedirectResponse
    {
        $company = Company::current();
        abort_if($company === null, 404);

        $data = $request->validate(['since' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']]);

        if ($company->needsSigningIn()) {
            return back()->withErrors(['sync' => __('Sign in to KSeF first: the signed login has expired or was never made.')]);
        }
        if (Cache::has(SyncPurchasesJob::runningKey($company->id))) {
            return back();
        }

        Cache::put(SyncPurchasesJob::runningKey($company->id), true, now()->addMinutes(15));
        SyncPurchasesJob::dispatch($company->id, $company->purchases_synced_until === null ? ($data['since'] ?? null) : null);

        return back()->with('status', __('Fetching purchase invoices from KSeF…'));
    }

    public function pdf(Request $request, ReceivedInvoice $invoice, InvoiceView $view, InvoicePdf $pdf): HttpResponse
    {
        $this->authorizeOwnership($invoice);

        $locale = $request->validate(['lang' => ['nullable', 'in:pl,en']])['lang'] ?? app()->getLocale();
        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $invoice->ksef_number).'.pdf';

        try {
            $content = $pdf->render($view->purchase($invoice), $locale, $invoice->company->defaultTemplateSettings());
        } catch (KsefException) {
            abort(422, __('This invoice cannot be shown by this version.'));
        }

        return response($content, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.$name.'"']);
    }

    public function xml(ReceivedInvoice $invoice): StreamedResponse
    {
        $this->authorizeOwnership($invoice);

        return response()->streamDownload(function () use ($invoice): void {
            echo $invoice->xml;
        }, preg_replace('/[^A-Za-z0-9._-]+/', '-', $invoice->ksef_number).'.xml', ['Content-Type' => 'application/xml; charset=utf-8']);
    }
}
