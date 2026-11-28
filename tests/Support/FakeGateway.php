<?php

namespace Tests\Support;

use App\Ksef\Availability;
use App\Ksef\AvailabilityState;
use App\Ksef\CheckOutcome;
use App\Ksef\ConnectionCheck;
use App\Ksef\ConnectionReport;
use App\Ksef\KsefGateway;
use App\Ksef\OfflineCertificateIssued;
use App\Ksef\OfflineCertificateRefused;
use App\Ksef\PurchaseBatch;
use App\Ksef\SigningRefused;
use App\Ksef\SigningRequest;
use App\Ksef\SigningRequired;
use App\Ksef\Submission;
use App\Ksef\SubmissionRefused;
use App\Ksef\SubmissionState;
use App\Ksef\SubmissionStatus;
use App\Ksef\SubmissionUncertain;
use App\Models\Company;

/** A KSeF that answers as told, and remembers what it was asked. */
final class FakeGateway implements KsefGateway
{
    /** @var list<Company> */
    public array $checked = [];

    /** @var list<string> the documents handed over */
    public array $submitted = [];

    /** @var list<SubmissionStatus> the verdicts to give, one per question; the last one repeats */
    public array $verdicts = [];

    public ?SubmissionRefused $refuse = null;

    public ?SubmissionUncertain $uncertain = null;

    /** When set, asking for the verdict fails with it (a hiccup on the way). */
    public ?\Throwable $statusFails = null;

    public ?string $upo = '<UPO>signed by the Ministry</UPO>';

    /** @var list<PurchaseBatch> the batches of purchase invoices to give, one per export */
    public array $purchaseBatches = [];

    /** @var list<\DateTimeImmutable> where each export was asked to start */
    public array $purchaseFrom = [];

    public ?\Throwable $purchasesFail = null;

    /** @var list<string> the signed documents handed in */
    public array $signed = [];

    public ?SigningRefused $refuseSigning = null;

    /** When set, every call that needs a login fails with it (a company that signs in by hand, with no valid login). */
    public ?SigningRequired $needsSigning = null;

    public Availability $availability;

    public ?\Throwable $availabilityFails = null;

    public int $availabilityAsked = 0;

    /** @var list<bool> whether each document was declared as offline */
    public array $submittedOffline = [];

    public ?OfflineCertificateRefused $refuseCertificate = null;

    public int $certificatesRequested = 0;

    private int $asked = 0;

    public function __construct(private readonly bool $healthy = true)
    {
        $this->availability = new Availability(AvailabilityState::Available);
    }

    public function check(Company $company): ConnectionReport
    {
        $this->checked[] = $company;

        return new ConnectionReport([
            new ConnectionCheck('Login', $this->healthy ? CheckOutcome::Ok : CheckOutcome::Failed, $this->healthy ? 'logged in' : 'refused', $this->healthy ? [] : ['check the token']),
        ]);
    }

    public function submit(Company $company, string $xml, bool $offline = false): Submission
    {
        $this->submittedOffline[] = $offline;
        if ($this->needsSigning !== null) {
            throw $this->needsSigning;
        }
        $this->submitted[] = $xml;
        if ($this->refuse !== null) {
            throw $this->refuse;
        }
        if ($this->uncertain !== null) {
            throw $this->uncertain;
        }

        return new Submission('session-1', 'invoice-ref-1', hash('sha256', $xml));
    }

    public function status(Company $company, Submission $submission): SubmissionStatus
    {
        if ($this->needsSigning !== null) {
            throw $this->needsSigning;
        }
        if ($this->statusFails !== null) {
            throw $this->statusFails;
        }

        $verdicts = $this->verdicts ?: [new SubmissionStatus(SubmissionState::Accepted, '5265877635-20261201-ABCDEF-12', 200, 'Success')];
        $verdict = $verdicts[min($this->asked, count($verdicts) - 1)];
        $this->asked++;

        return $verdict;
    }

    public function upo(Company $company, Submission $submission): ?string
    {
        return $this->upo;
    }

    public function purchases(Company $company, \DateTimeImmutable $from): PurchaseBatch
    {
        $this->purchaseFrom[] = $from;
        if ($this->purchasesFail !== null) {
            throw $this->purchasesFail;
        }

        return array_shift($this->purchaseBatches) ?? new PurchaseBatch([], $from);
    }

    public function signingRequest(Company $company): SigningRequest
    {
        return new SigningRequest('<AuthTokenRequest>to sign</AuthTokenRequest>', new \DateTimeImmutable('+10 minutes'));
    }

    public function completeSigning(Company $company, string $signedXml): void
    {
        if ($this->refuseSigning !== null) {
            throw $this->refuseSigning;
        }
        $this->signed[] = $signedXml;
    }

    public function availability(Company $company): Availability
    {
        $this->availabilityAsked++;
        if ($this->availabilityFails !== null) {
            throw $this->availabilityFails;
        }

        return $this->availability;
    }

    public function requestOfflineCertificate(Company $company): OfflineCertificateIssued
    {
        $this->certificatesRequested++;
        if ($this->refuseCertificate !== null) {
            throw $this->refuseCertificate;
        }

        [$certificate, $key] = OfflineCertificates::make();

        return new OfflineCertificateIssued('FAKE-SERIAL', $certificate, $key, new \DateTimeImmutable('+2 years'));
    }
}
