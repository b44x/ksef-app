<?php

namespace Tests\Feature\Ksef;

use App\Enums\AuthMethod;
use App\Enums\KsefEnvironment;
use App\Ksef\KsefClientFactory;
use App\Models\Company;
use B4x\Ksef\Auth\CertificateCredentials;
use B4x\Ksef\Auth\KsefTokenCredentials;
use B4x\Ksef\Auth\ManualCredentials;
use B4x\Ksef\KsefClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class KsefClientFactoryTest extends TestCase
{
    use RefreshDatabase;

    /** @param array<string, string>|null $credentials */
    private function company(AuthMethod $method, ?array $credentials, string $nip = '5265877635'): Company
    {
        return Company::create(['name' => 'Example', 'nip' => $nip, 'environment' => KsefEnvironment::Test, 'auth_method' => $method, 'credentials' => $credentials]);
    }

    public function test_each_authentication_method_gets_its_credentials_and_a_client(): void
    {
        $factory = $this->app->make(KsefClientFactory::class);
        [$certificate, $key] = $this->selfSignedCertificate();

        $token = $this->company(AuthMethod::Token, ['token' => 't']);
        $this->assertInstanceOf(KsefTokenCredentials::class, $factory->credentials($token));
        $this->assertInstanceOf(KsefClient::class, $factory->make($token));

        $cert = $this->company(AuthMethod::Certificate, ['certificate' => $certificate, 'private_key' => $key], '1234563218');
        $this->assertInstanceOf(CertificateCredentials::class, $factory->credentials($cert));

        $manual = $this->company(AuthMethod::Manual, null, '7762811692');
        $this->assertInstanceOf(ManualCredentials::class, $factory->credentials($manual));
    }

    public function test_missing_credentials_are_reported(): void
    {
        $factory = $this->app->make(KsefClientFactory::class);

        $this->expectException(RuntimeException::class);
        $factory->credentials($this->company(AuthMethod::Token, null));
    }

    /**
     * @return array{string, string} certificate and private key, PEM
     */
    private function selfSignedCertificate(): array
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'Test'], $key);
        $certificate = openssl_csr_sign($csr, null, $key, 30);
        openssl_x509_export($certificate, $certificatePem);
        openssl_pkey_export($key, $keyPem);

        return [$certificatePem, $keyPem];
    }
}
