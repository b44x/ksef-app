<?php

namespace App\Ksef;

use App\Models\Company;

/**
 * Everything the application asks of KSeF. The rest of the app depends on this interface, never on a particular SDK:
 * {@see SdkKsefGateway} is the adapter for b44x/ksef-php, and the only place that knows about it.
 */
interface KsefGateway
{
    /**
     * Checks, without changing anything in KSeF, that the company can work with it: the credentials, the login, the
     * limits and the permissions.
     */
    public function check(Company $company): ConnectionReport;

    /**
     * Hands an issued invoice's FA(3) document to KSeF. "Accepted for processing" is all this promises: the verdict
     * comes from {@see self::status()}.
     *
     * @throws SubmissionRefused when KSeF stored nothing (sending again is safe)
     * @throws SubmissionUncertain when the connection failed on the way (sending again is safe too)
     */
    public function submit(Company $company, string $xml): Submission;

    /** Asks KSeF for its verdict on a submitted invoice. Does not wait. */
    public function status(Company $company, Submission $submission): SubmissionStatus;

    /** The official confirmation of receipt (UPO) as XML, or null while it is not available. */
    public function upo(Company $company, Submission $submission): ?string;

    /**
     * The purchase invoices (the company is the buyer) KSeF stored since a moment. One export at most: when KSeF cuts it
     * short the batch says where to continue.
     */
    public function purchases(Company $company, \DateTimeImmutable $from): PurchaseBatch;
}
