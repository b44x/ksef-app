<?php

namespace App\Ksef;

use DateTimeImmutable;

/** The login document KSeF wants signed, and until when it can be signed (ten minutes). */
final readonly class SigningRequest
{
    public function __construct(public string $xml, public DateTimeImmutable $expiresAt) {}
}
