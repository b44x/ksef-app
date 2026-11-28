<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopedToCompany;
use App\Models\Invoice;
use App\Models\Payment;
use B4x\Ksef\Support\Decimal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The payment register of an issued invoice: what came in, when. The invoice itself never changes. */
class PaymentController extends Controller
{
    use ScopedToCompany;

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeOwnership($invoice);
        abort_if($invoice->isDraft() || $invoice->isProforma() || $invoice->gross_total === null, 404);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'paid_on' => ['required', 'date_format:Y-m-d'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        if (Decimal::of((string) $data['amount'])->compare($invoice->outstanding()) > 0) {
            return back()->withErrors(['amount' => __('That is more than is left to pay (:left).', ['left' => $invoice->outstanding()->toString(2)])]);
        }

        $payment = new Payment($data);
        $payment->company_id = $invoice->company_id;
        $invoice->payments()->save($payment);

        return back()->with('status', __('The payment was recorded.'));
    }

    /** Everything that is left, paid today: the usual case, one click. */
    public function settle(Invoice $invoice): RedirectResponse
    {
        $this->authorizeOwnership($invoice);
        abort_if($invoice->isDraft() || $invoice->isProforma() || $invoice->gross_total === null, 404);

        $left = $invoice->outstanding();
        if (! $left->isZero()) {
            $payment = new Payment(['amount' => $left->toString(2), 'paid_on' => now()->toDateString()]);
            $payment->company_id = $invoice->company_id;
            $invoice->payments()->save($payment);
        }

        return back()->with('status', __('The invoice is marked as paid.'));
    }

    public function destroy(Payment $payment): RedirectResponse
    {
        $this->authorizeOwnership($payment);
        $payment->delete();

        return back()->with('status', __('Deleted.'));
    }
}
