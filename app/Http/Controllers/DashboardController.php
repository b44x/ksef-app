<?php

namespace App\Http\Controllers;

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

        return Inertia::render('Dashboard', [
            'details' => [
                'name' => $company->name,
                'nip' => $company->nip,
                'environment' => $company->environment->label(),
                'auth_method' => $company->auth_method->label(),
            ],
        ]);
    }
}
