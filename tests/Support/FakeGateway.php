<?php

namespace Tests\Support;

use App\Ksef\CheckOutcome;
use App\Ksef\ConnectionCheck;
use App\Ksef\ConnectionReport;
use App\Ksef\KsefGateway;
use App\Ksef\PurchaseBatch;
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

    private int $asked = 0;

    public function __construct(private readonly bool $healthy = true) {}

    public function check(Company $company): ConnectionReport
    {
        $this->checked[] = $company;

        return new ConnectionReport([
            new ConnectionCheck('Login', $this->healthy ? CheckOutcome::Ok : CheckOutcome::Failed, $this->healthy ? 'logged in' : 'refused', $this->healthy ? [] : ['check the token']),
        ]);
    }

    public function submit(Company $company, string $xml): Submission
    {
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
}
