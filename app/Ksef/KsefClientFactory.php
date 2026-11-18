<?php

namespace App\Ksef;

use App\Enums\AuthMethod;
use App\Models\Company;
use B4x\Ksef\Auth\CertificateCredentials;
use B4x\Ksef\Auth\ContextIdentifier;
use B4x\Ksef\Auth\Credentials;
use B4x\Ksef\Auth\KsefTokenCredentials;
use B4x\Ksef\Auth\ManualCredentials;
use B4x\Ksef\KsefClient;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Builds the SDK client of a company: its environment, its NIP, its credentials and a token store that lives in the
 * database, so that a request does not log in again.
 */
class KsefClientFactory
{
    public function __construct(
        private readonly ClientInterface $http,
        private readonly RequestFactoryInterface $requests,
        private readonly StreamFactoryInterface $streams,
        private readonly LoggerInterface $logger,
    ) {}

    public function make(Company $company): KsefClient
    {
        return KsefClient::builder()
            ->environment($company->environment->sdk())
            ->httpClient($this->http, $this->requests, $this->streams)
            ->context(ContextIdentifier::nip($company->nip))
            ->credentials($this->credentials($company))
            ->tokenStore(new DatabaseTokenStore($company))
            ->logger($this->logger)
            ->build();
    }

    public function credentials(Company $company): Credentials
    {
        return match ($company->auth_method) {
            AuthMethod::Token => new KsefTokenCredentials($company->credential('token') ?? throw new RuntimeException('The company has no KSeF token.')),
            AuthMethod::Certificate => CertificateCredentials::fromPem(
                $company->credential('certificate') ?? throw new RuntimeException('The company has no certificate.'),
                $company->credential('private_key') ?? throw new RuntimeException('The company has no private key.'),
                $company->credential('passphrase'),
            ),
            AuthMethod::Manual => new ManualCredentials,
        };
    }
}
