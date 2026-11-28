<?php

namespace App\Http\Controllers;

use App\Actions\SaveCompany;
use App\Enums\AuthMethod;
use App\Enums\KsefEnvironment;
use App\Http\Requests\CompanyRequest;
use App\Ksef\KsefGateway;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CompanyController extends Controller
{
    public function edit(): Response
    {
        $company = Company::query()->first();

        return Inertia::render('Company', [
            // The stored secrets never leave the server: the form only learns that some are saved.
            'company' => $company === null ? null : [
                'name' => $company->name,
                'nip' => $company->nip,
                'address_line1' => $company->address_line1 ?? '',
                'address_line2' => $company->address_line2 ?? '',
                'email' => $company->email ?? '',
                'environment' => $company->environment->value,
                'auth_method' => $company->auth_method->value,
                'has_secrets' => $company->credentials !== null,
            ],
            'offline' => $company === null ? null : ['has' => $company->offline_certificate !== null, 'expires_at' => $company->offline_certificate_expires_at?->toIso8601String(), 'usable' => $company->canIssueOffline()],
            'notifications' => $company === null ? null : ['email' => $company->notify_email ?? '', 'offline' => $company->notify_offline, 'purchases' => $company->notify_purchases, 'rejected' => $company->notify_rejected],
            'environments' => array_map(fn (KsefEnvironment $e): array => ['value' => $e->value, 'label' => $e->label()], KsefEnvironment::cases()),
            'methods' => array_map(fn (AuthMethod $m): array => ['value' => $m->value, 'label' => $m->label()], AuthMethod::cases()),
        ]);
    }

    public function update(CompanyRequest $request, SaveCompany $save, KsefGateway $gateway): RedirectResponse
    {
        $company = $save($request->validated());

        return redirect()->route('company.edit')->with('report', ConnectionCheckController::present($gateway->check($company)));
    }
}
