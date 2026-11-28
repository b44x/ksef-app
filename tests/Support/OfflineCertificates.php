<?php

namespace Tests\Support;

/** A self-signed EC P-256 certificate and key, good enough to sign KOD II links in tests. */
final class OfflineCertificates
{
    /**
     * @return array{0: string, 1: string} the certificate and the private key, PEM
     */
    public static function make(string $nip = '5265877635'): array
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $csr = openssl_csr_new(['commonName' => 'Offline '.$nip, 'organizationIdentifier' => 'VATPL-'.$nip], $key, ['digest_alg' => 'sha256']);
        $certificate = openssl_csr_sign($csr, null, $key, 730, ['digest_alg' => 'sha256'], random_int(1, 1_000_000_000));
        openssl_x509_export($certificate, $certPem);
        openssl_pkey_export($key, $keyPem);

        return [$certPem, $keyPem];
    }
}
