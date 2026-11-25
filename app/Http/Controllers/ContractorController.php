<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContractorRequest;
use App\Models\Company;
use App\Models\Contractor;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ContractorController extends Controller
{
    public function index(): Response|RedirectResponse
    {
        $company = Company::current();
        if ($company === null) {
            return redirect()->route('company.edit');
        }

        return Inertia::render('Contractors', [
            'contractors' => $company->contractors()->orderBy('name')->get(['id', 'name', 'nip', 'country', 'address_line1', 'address_line2', 'email']),
        ]);
    }

    public function store(ContractorRequest $request): RedirectResponse
    {
        Company::current()?->contractors()->create($request->validated());

        return back();
    }

    public function update(ContractorRequest $request, Contractor $contractor): RedirectResponse
    {
        $this->authorizeOwnership($contractor);
        $contractor->update($request->validated());

        return back();
    }

    public function destroy(Contractor $contractor): RedirectResponse
    {
        $this->authorizeOwnership($contractor);
        $contractor->delete();

        return back();
    }

    /** A contractor of another company is not reachable, even by guessing its id. */
    private function authorizeOwnership(Contractor $contractor): void
    {
        abort_unless($contractor->company_id === Company::current()?->id, 404);
    }
}
