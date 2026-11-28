<?php

namespace App\Jobs;

use App\Enums\InvoiceStatus;
use App\Ksef\KsefGateway;
use App\Ksef\SubmissionState;
use App\Models\Invoice;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Asks KSeF for its verdict on a submitted invoice, and asks again later while it is still checking: after 3 s, 6 s,
 * 12 s and so on up to a minute. It stops for good when KSeF has answered; if KSeF stays silent for a long time the
 * invoice is left "sending" with a note, and the user can ask again.
 */
class PollInvoiceStatusJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** About half an hour of waiting, with the delays below. */
    public const MAX_ATTEMPTS = 40;

    public function __construct(public readonly int $invoiceId) {}

    public function handle(KsefGateway $gateway): void
    {
        $invoice = Invoice::query()->with('company')->find($this->invoiceId);
        $submission = $invoice?->submission();
        if ($invoice === null || $submission === null || $invoice->status !== InvoiceStatus::Sending) {
            return;
        }

        try {
            $verdict = $gateway->status($invoice->company, $submission);
        } catch (Throwable) {
            // A hiccup on the way is not an answer: ask again.
            $this->again($invoice);

            return;
        }

        $invoice->forceFill(['ksef_status_code' => $verdict->code, 'ksef_status_description' => $verdict->description]);

        match ($verdict->state) {
            SubmissionState::Processing => $this->again($invoice),
            SubmissionState::Accepted => $this->accepted($invoice, $verdict->ksefNumber, $gateway),
            SubmissionState::Rejected => $invoice->forceFill([
                'status' => InvoiceStatus::Rejected,
                'ksef_error' => trim($verdict->description."\n".implode("\n", $verdict->details)),
            ])->save(),
        };
    }

    private function accepted(Invoice $invoice, ?string $ksefNumber, KsefGateway $gateway): void
    {
        $invoice->forceFill([
            'status' => InvoiceStatus::Accepted,
            'ksef_number' => $ksefNumber,
            'ksef_error' => null,
            'accepted_at' => now(),
        ])->save();

        // The UPO may need a moment after acceptance; if it is not there yet it is fetched when asked for.
        $upo = $gateway->upo($invoice->company, $invoice->submission());
        if ($upo !== null) {
            $invoice->forceFill(['upo_xml' => $upo])->save();
        }
    }

    private function again(Invoice $invoice): void
    {
        $invoice->poll_attempts++;

        if ($invoice->poll_attempts >= self::MAX_ATTEMPTS) {
            $invoice->ksef_error = __('KSeF is taking long to answer. Press "Check status" to ask again.');
            $invoice->save();

            return;
        }

        $invoice->save();
        self::dispatch($invoice->id)->delay(now()->addSeconds(min(60, 3 * 2 ** min($invoice->poll_attempts, 5))));
    }
}
