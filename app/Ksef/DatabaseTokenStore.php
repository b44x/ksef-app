<?php

namespace App\Ksef;

use App\Models\Company;
use App\Models\KsefToken;
use B4x\Ksef\Auth\TokenStore;

/**
 * Keeps the SDK's tokens in the database, encrypted with the application key, per company.
 */
final class DatabaseTokenStore implements TokenStore
{
    public function __construct(private readonly Company $company) {}

    public function get(string $key): ?string
    {
        $token = KsefToken::query()
            ->where('company_id', $this->company->getKey())
            ->where('token_key', $key)
            ->where('expires_at', '>', now())
            ->first();

        return $token?->payload;
    }

    public function set(string $key, string $value, int $ttlSeconds): void
    {
        KsefToken::query()->updateOrCreate(
            ['company_id' => $this->company->getKey(), 'token_key' => $key],
            ['payload' => $value, 'expires_at' => now()->addSeconds($ttlSeconds)],
        );
    }

    public function delete(string $key): void
    {
        KsefToken::query()
            ->where('company_id', $this->company->getKey())
            ->where('token_key', $key)
            ->delete();
    }
}
