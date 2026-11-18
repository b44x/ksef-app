<?php

namespace App\Livewire;

use App\Enums\AuthMethod;
use App\Enums\KsefEnvironment;
use App\Ksef\KsefGateway;
use App\Models\Company;
use B4x\Ksef\Exception\ValidationException;
use B4x\Ksef\Support\Nip;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Connects the company to KSeF: who it is, which environment, how it signs in, and a check that it all works.
 * Secrets typed here are saved encrypted and are never shown again.
 */
#[Layout('layouts.app')]
#[Title('Company')]
class Onboarding extends Component
{
    public string $name = '';

    public string $nip = '';

    public string $environment = 'test';

    public string $authMethod = 'token';

    public string $token = '';

    public string $certificate = '';

    public string $privateKey = '';

    public string $passphrase = '';

    /** @var list<array{name: string, outcome: string, message: string, details: list<string>}>|null */
    public ?array $report = null;

    public bool $reportOk = false;

    public bool $hasSecrets = false;

    public function mount(): void
    {
        $company = Company::query()->first();
        if ($company === null) {
            return;
        }

        $this->name = $company->name;
        $this->nip = $company->nip;
        $this->environment = $company->environment->value;
        $this->authMethod = $company->auth_method->value;
        $this->hasSecrets = $company->credentials !== null;
    }

    public function save(KsefGateway $gateway): void
    {
        $company = Company::query()->first();
        $method = AuthMethod::tryFrom($this->authMethod);

        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'digits:10', function (string $attribute, mixed $value, \Closure $fail): void {
                try {
                    Nip::of((string) $value);
                } catch (ValidationException) {
                    $fail('This is not a valid NIP (check the digits).');
                }
            }, Rule::unique('companies', 'nip')->ignore($company?->id)],
            'environment' => ['required', Rule::enum(KsefEnvironment::class)],
            'authMethod' => ['required', Rule::enum(AuthMethod::class)],
            // A secret is required the first time; later a blank field keeps the stored one.
            'token' => [Rule::requiredIf($method === AuthMethod::Token && ! $this->keepsSecrets($company, $method)), 'max:4096'],
            'certificate' => [Rule::requiredIf($method === AuthMethod::Certificate && ! $this->keepsSecrets($company, $method)), 'max:20000'],
            'privateKey' => [Rule::requiredIf($method === AuthMethod::Certificate && ! $this->keepsSecrets($company, $method)), 'max:20000'],
            'passphrase' => ['max:1000'],
        ]);

        $company ??= new Company;
        $company->fill([
            'name' => $this->name,
            'nip' => $this->nip,
            'environment' => KsefEnvironment::from($this->environment),
            'auth_method' => $method,
            'credentials' => $this->credentials($company, $method),
        ])->save();

        // The secrets live in the database now; they must not stay in the page.
        $this->reset(['token', 'certificate', 'privateKey', 'passphrase']);
        $this->hasSecrets = $company->credentials !== null;

        $this->runCheck($gateway, $company);
    }

    public function check(KsefGateway $gateway): void
    {
        $company = Company::query()->first();
        if ($company !== null) {
            $this->runCheck($gateway, $company);
        }
    }

    private function runCheck(KsefGateway $gateway, Company $company): void
    {
        $report = $gateway->check($company);
        $this->reportOk = $report->ok();
        $this->report = array_map(static fn ($c): array => [
            'name' => $c->name,
            'outcome' => $c->outcome->value,
            'message' => $c->message,
            'details' => $c->details,
        ], $report->checks);
    }

    /** True when a blank secret field is fine: the company already has this kind of credentials stored. */
    private function keepsSecrets(?Company $company, ?AuthMethod $method): bool
    {
        return $company !== null && $company->auth_method === $method && $company->credentials !== null;
    }

    /**
     * @return array<string, string>|null
     */
    private function credentials(Company $company, ?AuthMethod $method): ?array
    {
        if ($method === AuthMethod::Manual) {
            return null;
        }
        if ($this->keepsSecrets($company->exists ? $company : null, $method)
            && $this->token === '' && $this->certificate === '' && $this->privateKey === '') {
            return $company->credentials;
        }

        return match ($method) {
            AuthMethod::Token => ['token' => trim($this->token)],
            AuthMethod::Certificate => array_filter([
                'certificate' => trim($this->certificate),
                'private_key' => trim($this->privateKey),
                'passphrase' => $this->passphrase,
            ], static fn (string $v): bool => $v !== ''),
            default => null,
        };
    }

    public function render()
    {
        return view('livewire.onboarding', [
            'environments' => KsefEnvironment::cases(),
            'methods' => AuthMethod::cases(),
        ]);
    }
}
