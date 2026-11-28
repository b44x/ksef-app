<?php

namespace App\Ksef;

use App\Models\Company;
use B4x\Ksef\Doctor\CheckStatus;
use B4x\Ksef\Doctor\Doctor;
use B4x\Ksef\Exception\KsefException;
use B4x\Ksef\Exception\SubmissionOutcomeUnknownException;
use B4x\Ksef\Status\InvoiceSubmission;
use B4x\Ksef\Support\SystemClock;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Throwable;

/** The {@see KsefGateway} adapter for b44x/ksef-php. */
class SdkKsefGateway implements KsefGateway
{
    public function __construct(
        private readonly KsefClientFactory $clients,
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
    ) {}

    public function check(Company $company): ConnectionReport
    {
        try {
            $client = $this->clients->make($company);
        } catch (Throwable $e) {
            return new ConnectionReport([new ConnectionCheck(__('Settings'), CheckOutcome::Failed, $e->getMessage())]);
        }

        $certificate = $company->credential('certificate');
        // The login is kept: the tokens it produced serve the next requests, so the check does not end it.
        $report = (new Doctor(
            $client,
            $company->environment->sdk(),
            $this->http,
            $this->requests,
            new SystemClock,
            $certificate,
            revokeSession: false,
        ))->run();

        return new ConnectionReport(array_map(
            static fn ($result): ConnectionCheck => new ConnectionCheck(
                $result->name,
                match ($result->status) {
                    CheckStatus::Ok => CheckOutcome::Ok,
                    CheckStatus::Warning => CheckOutcome::Warning,
                    CheckStatus::Failed => CheckOutcome::Failed,
                    CheckStatus::Skipped => CheckOutcome::Skipped,
                },
                $result->message,
                $result->details,
            ),
            $report->results,
        ));
    }

    public function submit(Company $company, string $xml): Submission
    {
        try {
            $sent = $this->clients->make($company)->sendInvoice($xml);
        } catch (SubmissionOutcomeUnknownException $e) {
            throw new SubmissionUncertain($e->getMessage(), 0, $e);
        } catch (KsefException $e) {
            throw new SubmissionRefused($e->getMessage(), 0, $e);
        } catch (Throwable $e) {
            // The client could not even be built: no credentials, for example.
            throw new SubmissionRefused($e->getMessage(), 0, $e);
        }

        return new Submission($sent->sessionReference, $sent->invoiceReference, $sent->invoiceHash);
    }

    public function status(Company $company, Submission $submission): SubmissionStatus
    {
        $invoice = $this->clients->make($company)->invoiceStatus($this->sdk($submission));
        $status = $invoice->status;
        $number = $invoice->resolvedKsefNumber();

        return match (true) {
            // A duplicate (440) with the original's number is the desired end state: the invoice is in KSeF.
            $status->isAccepted(), $status->isDuplicate() && $number !== null => new SubmissionStatus(SubmissionState::Accepted, $number, $status->code, $status->description),
            $status->isRejected() => new SubmissionStatus(SubmissionState::Rejected, null, $status->code, $status->description, $status->details),
            default => new SubmissionStatus(SubmissionState::Processing, null, $status->code, $status->description),
        };
    }

    public function upo(Company $company, Submission $submission): ?string
    {
        try {
            return $this->clients->make($company)->invoiceUpo($this->sdk($submission))->xml;
        } catch (KsefException) {
            // Not available yet; it is asked for again later.
            return null;
        }
    }

    private function sdk(Submission $submission): InvoiceSubmission
    {
        return new InvoiceSubmission($submission->sessionReference, $submission->invoiceReference, $submission->invoiceHash);
    }
}
