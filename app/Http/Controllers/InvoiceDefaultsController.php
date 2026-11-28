<?php

namespace App\Http\Controllers;

use App\Models\Company;
use B4x\Ksef\Invoice\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** What a new invoice starts with: payment term, payment method, bank account and the text at the bottom. */
class InvoiceDefaultsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $company = Company::current();
        abort_if($company === null, 404);

        $data = $request->validate([
            'default_payment_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'default_payment_method' => ['nullable', Rule::in(array_map(static fn (PaymentMethod $m): int => $m->value, PaymentMethod::cases()))],
            'default_bank_account' => ['nullable', 'string', 'min:10', 'max:34'],
            'default_notes' => ['nullable', 'string', 'max:3500'],
        ]);
        $company->forceFill(array_map(static fn (mixed $v): mixed => $v === '' ? null : $v, $data))->save();

        return back()->with('status', __('The defaults were saved.'));
    }
}
