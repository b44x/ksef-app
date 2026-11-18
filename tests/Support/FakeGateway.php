<?php

namespace Tests\Support;

use App\Ksef\CheckOutcome;
use App\Ksef\ConnectionCheck;
use App\Ksef\ConnectionReport;
use App\Ksef\KsefGateway;
use App\Models\Company;

/** A KSeF that answers as told, and remembers what it was asked. */
final class FakeGateway implements KsefGateway
{
    /** @var list<Company> */
    public array $checked = [];

    public function __construct(private readonly bool $healthy = true) {}

    public function check(Company $company): ConnectionReport
    {
        $this->checked[] = $company;

        return new ConnectionReport([
            new ConnectionCheck('Login', $this->healthy ? CheckOutcome::Ok : CheckOutcome::Failed, $this->healthy ? 'logged in' : 'refused', $this->healthy ? [] : ['check the token']),
        ]);
    }
}
