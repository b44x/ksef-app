<?php

namespace App\Ksef;

use App\Models\Company;
use B4x\Ksef\Doctor\CheckStatus;
use B4x\Ksef\Doctor\Doctor;
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
}
