<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Reports\CsvWriter;
use App\Reports\ReportPeriod;
use App\Reports\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Sales, VAT, customers and receivables for a period, and the sales register as a CSV file. */
class ReportController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        $company = Company::current();
        if ($company === null) {
            return redirect()->route('company.edit');
        }

        $period = ReportPeriod::resolve($request->only(['period', 'from', 'to']));
        $report = new SalesReport($company);

        return Inertia::render('Reports', [
            'period' => ['key' => $period->key, 'from' => $period->from->toDateString(), 'to' => $period->to->toDateString()],
            'totals' => $report->totals($period->from, $period->to),
            'byRate' => $report->byRate($period->from, $period->to),
            'customers' => $report->topCustomers($period->from, $period->to),
            'monthly' => $report->monthly($period->to->isFuture() ? CarbonImmutable::today() : $period->to),
            'receivables' => $report->receivables(),
        ]);
    }

    /** The sales register of the period, one line per invoice. */
    public function register(Request $request): StreamedResponse
    {
        $company = Company::current();
        abort_if($company === null, 404);
        $period = ReportPeriod::resolve($request->only(['period', 'from', 'to']));

        $rows = [];
        foreach ((new SalesReport($company))->invoices($period->from, $period->to) as $i) {
            $rows[] = [
                $i->number, $i->issue_date->toDateString(), $i->sale_date?->toDateString(), $i->due_date?->toDateString(),
                $i->contractor->name, $i->contractor->nip, $i->currency, $i->net_total, $i->vat_total, $i->gross_total,
                $i->paidAmount()->toString(2), $i->status->value, $i->ksef_number,
            ];
        }
        $csv = CsvWriter::render(['Number', 'Issue date', 'Sale date', 'Due date', 'Buyer', 'Buyer NIP', 'Currency', 'Net', 'VAT', 'Gross', 'Paid', 'Status', 'KSeF number'], $rows);

        return response()->streamDownload(function () use ($csv): void {
            echo $csv;
        }, 'sales-'.$period->from->toDateString().'_'.$period->to->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=utf-8']);
    }
}
