<?php

namespace App\Actions;

use App\Enums\AuthMethod;
use App\Enums\KsefEnvironment;
use App\Models\Company;

/**
 * Creates or updates the company from the validated form. A blank secret keeps the stored one, as long as the kind of
 * credentials has not changed; the form never receives the stored secrets.
 */
final class SaveCompany
{
    /**
     * @param  array<string, mixed>  $data  the validated form
     */
    public function __invoke(array $data): Company
    {
        $company = Company::query()->first() ?? new Company;
        $method = AuthMethod::from($data['auth_method']);

        $company->fill([
            'name' => $data['name'],
            'nip' => $data['nip'],
            'address_line1' => $data['address_line1'],
            'address_line2' => $data['address_line2'] ?? null,
            'email' => $data['email'] ?? null,
            'environment' => KsefEnvironment::from($data['environment']),
            'auth_method' => $method,
            'credentials' => $this->credentials($company, $method, $data),
        ])->save();

        return $company;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>|null
     */
    private function credentials(Company $company, AuthMethod $method, array $data): ?array
    {
        if ($method === AuthMethod::Manual) {
            return null;
        }

        $token = trim((string) ($data['token'] ?? ''));
        $certificate = trim((string) ($data['certificate'] ?? ''));
        $privateKey = trim((string) ($data['private_key'] ?? ''));

        if (self::keeps($company, $method) && $token === '' && $certificate === '' && $privateKey === '') {
            return $company->credentials;
        }

        if ($method === AuthMethod::Token) {
            return ['token' => $token];
        }

        return array_filter([
            'certificate' => $certificate,
            'private_key' => $privateKey,
            'passphrase' => (string) ($data['passphrase'] ?? ''),
        ], static fn (string $v): bool => $v !== '');
    }

    /** True when the company already has this kind of credentials stored, so blank secret fields are fine. */
    public static function keeps(?Company $company, ?AuthMethod $method): bool
    {
        return $company !== null && $company->exists && $company->auth_method === $method && $company->credentials !== null;
    }
}
