<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceStatus;
use App\Invoicing\InvoiceRow;
use App\Models\Company;
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

        $recent = $company->invoices()->with(['contractor:id,name', 'lines'])->orderByDesc('issue_date')->orderByDesc('id')->limit(5)->get();

        return Inertia::render('Dashboard', [
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
}
