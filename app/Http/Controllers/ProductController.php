<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopedToCompany;
use App\Http\Requests\ProductRequest;
use App\Models\Company;
use App\Models\Product;
use B4x\Ksef\Invoice\VatRate;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    use ScopedToCompany;

    public function index(): Response|RedirectResponse
    {
        $company = Company::current();
        if ($company === null) {
            return redirect()->route('company.edit');
        }

        return Inertia::render('Products', [
            'products' => $company->products()->orderBy('name')->get(['id', 'name', 'unit', 'net_price', 'vat_rate']),
            'vatRates' => array_map(static fn (VatRate $rate): string => $rate->value, VatRate::cases()),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        Company::current()?->products()->create($request->validated());

        return back()->with('status', __('Saved.'));
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $this->authorizeOwnership($product);
        $product->update($request->validated());

        return back()->with('status', __('Saved.'));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->authorizeOwnership($product);
        $product->delete();

        return back()->with('status', __('Deleted.'));
    }
}
