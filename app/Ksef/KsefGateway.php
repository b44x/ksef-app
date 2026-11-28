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
     * @throws SigningRequired when the company signs in by hand and has no valid login
     */
    public function submit(Company $company, string $xml): Submission;

    /**
     * Asks KSeF for its verdict on a submitted invoice. Does not wait.
     *
     * @throws SigningRequired when the company signs in by hand and has no valid login
     */
    public function status(Company $company, Submission $submission): SubmissionStatus;

    /**
     * The official confirmation of receipt (UPO) as XML, or null while it is not available.
     *
     * @throws SigningRequired when the company signs in by hand and has no valid login
     */
    public function upo(Company $company, Submission $submission): ?string;

    /**
     * Step one of signing in by hand: the login document for the person to sign outside the application.
     *
     * @throws SigningRefused when the company does not sign in by hand, or KSeF could not be reached
     */
    public function signingRequest(Company $company): SigningRequest;

    /**
     * Step two: the document comes back signed. KSeF checks the signature and the login lasts (and is kept) until its
     * refresh token expires.
     *
     * @throws SigningRefused when the document is not a signed request for this company, or KSeF refuses the signature
     */
    public function completeSigning(Company $company, string $signedXml): void;

    /**
     * The purchase invoices (the company is the buyer) KSeF stored since a moment. One export at most: when KSeF cuts it
     * short the batch says where to continue.
     *
     * @throws SigningRequired when the company signs in by hand and has no valid login
     */
    public function purchases(Company $company, \DateTimeImmutable $from): PurchaseBatch;
}
