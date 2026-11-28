<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopedToCompany;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceEmail;
use App\Pdf\InvoicePdf;
use App\Pdf\InvoiceView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** Sends an issued invoice to its buyer by e-mail, as a PDF, and remembers that it did. */
class InvoiceEmailController extends Controller
{
    use ScopedToCompany;

    public function store(Request $request, Invoice $invoice, InvoiceView $view, InvoicePdf $pdf): RedirectResponse
    {
        $this->authorizeOwnership($invoice);
        abort_unless($invoice->isSale(), 404);

        $data = $request->validate([
            'to' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'max:5000'],
            'lang' => ['required', 'in:pl,en'],
        ]);
        $invoice->loadMissing('company');

        $name = preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) $invoice->number).'.pdf';
        $content = $pdf->render($view->sales($invoice), $data['lang'], $invoice->printSettings());

        try {
            Mail::to($data['to'])->send(new InvoiceMail($invoice->company, $data['subject'], $data['message'], $content, $name));
        } catch (\Throwable $e) {
            return back()->withErrors(['email' => __('The message could not be sent: :reason', ['reason' => $e->getMessage()])]);
        }

        $record = new InvoiceEmail(['to' => $data['to'], 'subject' => $data['subject']]);
        $record->company_id = $invoice->company_id;
        $invoice->emails()->save($record);

        return back()->with('status', __('The invoice was sent to :address.', ['address' => $data['to']]));
    }
}
