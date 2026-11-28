<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopedToCompany;
use App\Http\Requests\ContractorRequest;
use App\Lookup\LookupFailed;
use App\Lookup\WhiteList;
use App\Models\Company;
use App\Models\Contractor;
use B4x\Ksef\Exception\ValidationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContractorController extends Controller
{
    use ScopedToCompany;

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

    /** Company data for a NIP from the Ministry of Finance's register, to fill the form. */
    public function lookup(Request $request, WhiteList $register): JsonResponse
    {
        $nip = (string) preg_replace('/\D/', '', (string) $request->query('nip'));

        try {
            $found = $register->find($nip);
        } catch (ValidationException) {
            return response()->json(['message' => __('This is not a valid NIP (check the digits).')], 422);
        } catch (LookupFailed $e) {
            return response()->json(['message' => $e->getMessage()], 502);
        }

        return $found === null
            ? response()->json(['message' => __('No taxpayer with this NIP was found in the register.')], 404)
            : response()->json($found);
    }

    public function store(ContractorRequest $request): RedirectResponse
    {
        Company::current()?->contractors()->create($request->validated());

        return back()->with('status', __('Saved.'));
    }

    public function update(ContractorRequest $request, Contractor $contractor): RedirectResponse
    {
        $this->authorizeOwnership($contractor);
        $contractor->update($request->validated());

        return back()->with('status', __('Saved.'));
    }

    public function destroy(Contractor $contractor): RedirectResponse
    {
        $this->authorizeOwnership($contractor);
        $contractor->delete();

        return back()->with('status', __('Deleted.'));
    }
}
