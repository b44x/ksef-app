<?php

namespace App\Ksef;

use App\Models\Company;
use B4x\Ksef\Api\InvoiceDateType;
use B4x\Ksef\Api\InvoiceSubjectType;
use B4x\Ksef\Doctor\CheckStatus;
use B4x\Ksef\Doctor\Doctor;
use B4x\Ksef\Exception\KsefException;
use B4x\Ksef\Exception\ManualSignatureRequiredException;
use B4x\Ksef\Exception\SubmissionOutcomeUnknownException;
use B4x\Ksef\Latarnia\AvailabilityMessage;
use B4x\Ksef\Latarnia\AvailabilityStatus;
use B4x\Ksef\Status\InvoiceSubmission;
use B4x\Ksef\Support\SystemClock;
use DateTimeImmutable;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use RuntimeException;
use Throwable;
use ZipArchive;

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
        } catch (ManualSignatureRequiredException) {
            throw new SigningRequired;
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
        try {
            $invoice = $this->clients->make($company)->invoiceStatus($this->sdk($submission));
        } catch (ManualSignatureRequiredException) {
            throw new SigningRequired;
        }
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
        } catch (ManualSignatureRequiredException) {
            throw new SigningRequired;
        } catch (KsefException) {
            // Not available yet; it is asked for again later.
            return null;
        }
    }

    private function sdk(Submission $submission): InvoiceSubmission
    {
        return new InvoiceSubmission($submission->sessionReference, $submission->invoiceReference, $submission->invoiceHash);
    }

    public function purchases(Company $company, DateTimeImmutable $from): PurchaseBatch
    {
        $client = $this->clients->make($company);
        $to = new DateTimeImmutable;
        $path = tempnam(sys_get_temp_dir(), 'ksef-export-');
        if ($path === false) {
            throw new RuntimeException('No temporary file could be created for the export.');
        }

        try {
            $package = $client->exportInvoices(InvoiceSubjectType::Buyer, InvoiceDateType::PermanentStorage, $from, $to, $path);
            $documents = $package->invoiceCount > 0 ? $this->documents($path) : [];
        } catch (ManualSignatureRequiredException) {
            throw new SigningRequired;
        } finally {
            @unlink($path);
        }

        // Everything up to the high-water mark is known to be stored; with no invoices at all, stop a little before now,
        // because an invoice accepted this very moment may not be storable yet (a repeat is harmless: it is stored once).
        $until = $package->isTruncated
            ? ($package->continueFrom ?? $from)
            : ($package->permanentStorageHwmDate ?? max($from, $to->modify('-10 minutes')));

        return new PurchaseBatch($documents, $until, $package->isTruncated ? $package->continueFrom : null);
    }

    /**
     * The invoices in an export ZIP: `{ksefNumber}.xml` entries. Read from the archive, never extracted to disk, and
     * only the entries that look like what KSeF sends.
     *
     * @return list<PurchaseDocument>
     */
    private function documents(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The export from KSeF could not be opened.');
        }

        $documents = [];
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                $stat = $zip->statIndex($i);
                if (preg_match('/^([0-9A-Za-z-]{35})\.xml$/', $name, $m) !== 1 || $stat === false || $stat['size'] > 3_000_000) {
                    continue;
                }
                $xml = $zip->getFromIndex($i);
                if ($xml !== false) {
                    $documents[] = new PurchaseDocument($m[1], $xml);
                }
            }
        } finally {
            $zip->close();
        }

        return $documents;
    }

    public function signingRequest(Company $company): SigningRequest
    {
        try {
            $request = $this->clients->make($company)->createAuthenticationRequest();
        } catch (KsefException $e) {
            throw new SigningRefused($e->getMessage(), 0, $e);
        }

        return new SigningRequest($request->xml, $request->expiresAt);
    }

    public function completeSigning(Company $company, string $signedXml): void
    {
        try {
            $this->clients->make($company)->authenticateWithSignedRequest($signedXml);
        } catch (KsefException $e) {
            // ValidationException ("not signed", "another company") and AuthenticationException (KSeF refused the signature)
            // both explain themselves.
            throw new SigningRefused($e->getMessage(), 0, $e);
        }
    }

    public function availability(Company $company): Availability
    {
        $client = $this->clients->make($company);
        $report = $client->availability();

        return new Availability(
            match ($report->status) {
                AvailabilityStatus::Available => AvailabilityState::Available,
                AvailabilityStatus::Maintenance => AvailabilityState::Maintenance,
                AvailabilityStatus::Failure => AvailabilityState::Failure,
                AvailabilityStatus::TotalFailure => AvailabilityState::TotalFailure,
            },
            array_map(static fn (AvailabilityMessage $m): array => [
                'title' => $m->title,
                'text' => $m->text,
                'start' => $m->start->format(DATE_ATOM),
                'end' => $m->end?->format(DATE_ATOM),
            ], $report->messages),
            new DateTimeImmutable,
        );
    }
}
