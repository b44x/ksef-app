<?php

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Ksef\KsefGateway;
use App\Ksef\SubmissionRefused;
use App\Ksef\SubmissionUncertain;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Hands the issued invoice to KSeF. It never retries by itself: whether to send again is the user's call, and sending
 * again is safe (KSeF recognises a duplicate), so a failure just puts the invoice back where it can be sent.
 */
class SendInvoiceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly int $invoiceId) {}

    public function handle(KsefGateway $gateway): void
    {
        $invoice = Invoice::query()->with('company')->find($this->invoiceId);
        // Only an invoice that is waiting to be handed over; a second copy of this job finds nothing to do.
        if ($invoice === null || $invoice->status !== InvoiceStatus::Sending || $invoice->submission() !== null) {
            return;
        }

        try {
            $submission = $gateway->submit($invoice->company, (string) $invoice->xml);
        } catch (SubmissionRefused $e) {
            $this->back($invoice, __('KSeF did not take the invoice: :reason', ['reason' => $e->getMessage()]));

            return;
        } catch (SubmissionUncertain) {
            $this->back($invoice, __('The connection to KSeF failed on the way, so it is not known whether KSeF has the invoice. Send it again: KSeF recognises a duplicate and will not store it twice.'));

            return;
        }

        $invoice->forceFill([
            'ksef_session_reference' => $submission->sessionReference,
            'ksef_invoice_reference' => $submission->invoiceReference,
            'ksef_invoice_hash' => $submission->invoiceHash,
            'ksef_error' => null,
            'poll_attempts' => 0,
        ])->save();

        PollInvoiceStatusJob::dispatch($invoice->id)->delay(now()->addSeconds(3));
    }

    /** A crash must not leave the invoice "sending" for ever: it goes back to where it can be sent again. */
    public function failed(Throwable $exception): void
    {
        $invoice = Invoice::query()->find($this->invoiceId);
        if ($invoice !== null && $invoice->status === InvoiceStatus::Sending && $invoice->submission() === null) {
            $this->back($invoice, __('Sending failed unexpectedly. It is safe to send again.'));
        }
    }

    private function back(Invoice $invoice, string $reason): void
    {
        $invoice->forceFill(['status' => InvoiceStatus::Issued, 'ksef_error' => $reason])->save();
    }
}
