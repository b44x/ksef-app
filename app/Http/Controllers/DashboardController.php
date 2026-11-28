<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Invoicing\InvoiceRow;
use App\Models\Company;
use App\Reports\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        $company = Company::query()->first();
        if ($company === null) {
            return redirect()->route('company.edit');
        }

        $recent = $company->invoices()->with(['contractor:id,name', 'lines'])->withSum('payments', 'amount')->orderByDesc('issue_date')->orderByDesc('id')->limit(5)->get();

        $report = new SalesReport($company);
        $month = CarbonImmutable::today();

        return Inertia::render('Dashboard', [
            'setup' => $this->setup($company),
            'kpi' => [
                'sales' => $report->totals($month->startOfMonth(), $month->endOfMonth()),
                'receivables' => $report->receivables(),
                'monthly' => $report->monthly($month),
            ],
            'stats' => [
                'drafts' => $company->invoices()->where('status', InvoiceStatus::Draft)->count(),
                'contractors' => $company->contractors()->count(),
                'products' => $company->products()->count(),
            ],
            'offline' => $this->offlineWaiting($company),
            'recent' => $recent->map(fn ($i): array => InvoiceRow::of($i)),
            'details' => [
                'name' => $company->name,
                'nip' => $company->nip,
                'environment' => $company->environment->label(),
                'environment_key' => $company->environment->value,
                'auth_method' => $company->auth_method->label(),
            ],
        ]);
    }

    /**
     * The offline invoices that still have to reach KSeF, with the nearest deadline: the reminder on the dashboard.
     *
     * @return array{waiting: int, overdue: int, next_deadline: string|null, undated: int}|null
     */
    private function offlineWaiting(Company $company): ?array
    {
        $waiting = $company->invoices()->where('status', InvoiceStatus::Issued)->whereNotNull('offline_mode')->get(['offline_deadline']);
        if ($waiting->isEmpty()) {
            return null;
        }

        $dated = $waiting->whereNotNull('offline_deadline');

        return [
            'waiting' => $waiting->count(),
            'overdue' => $dated->filter(fn ($i) => $i->offline_deadline->isPast())->count(),
            'next_deadline' => $dated->min('offline_deadline')?->toIso8601String(),
            'undated' => $waiting->count() - $dated->count(),
        ];
    }

    /**
     * What is left to do before the first invoice goes out, in the order it makes sense. Empty once everything is done.
     *
     * @return list<array{key: string, label: string, href: string, done: bool}>
     */
    private function setup(Company $company): array
    {
        $steps = [
            ['key' => 'company', 'label' => __('Fill in your company address'), 'href' => '/company', 'done' => $company->address_line1 !== null && $company->address_line1 !== ''],
            ['key' => 'ksef', 'label' => __('Connect to KSeF'), 'href' => '/company', 'done' => $company->credentials !== null || $company->tokens()->exists()],
            ['key' => 'contractor', 'label' => __('Add your first contractor'), 'href' => '/contractors', 'done' => $company->contractors()->exists()],
            ['key' => 'series', 'label' => __('Set up a numbering series'), 'href' => '/series', 'done' => $company->numberingSeries()->exists()],
            ['key' => 'invoice', 'label' => __('Issue your first invoice'), 'href' => '/invoices/create', 'done' => $company->invoices()->where('status', '!=', InvoiceStatus::Draft)->exists()],
        ];

        return array_filter($steps, fn (array $s): bool => ! $s['done']) === [] ? [] : $steps;
    }
}
