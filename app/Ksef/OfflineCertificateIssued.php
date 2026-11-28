<?php

namespace App\Ksef;

use DateTimeImmutable;
use SensitiveParameter;

/** A new KSeF Offline certificate and the private key made for it: the key exists nowhere else. */
final readonly class OfflineCertificateIssued
{
    public function __construct(
        public string $serialNumber,
        public string $certificatePem,
        #[SensitiveParameter]
        public string $privateKeyPem,
        public ?DateTimeImmutable $expiresAt,
    ) {}
}
