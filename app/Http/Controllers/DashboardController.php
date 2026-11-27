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
}
