<?php

namespace App\Http\Requests;

use App\Actions\SaveCompany;
use App\Enums\AuthMethod;
use App\Enums\KsefEnvironment;
use App\Models\Company;
use B4x\Ksef\Exception\ValidationException;
use B4x\Ksef\Support\Nip;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $company = Company::query()->first();
        $method = AuthMethod::tryFrom((string) $this->input('auth_method'));
        // A secret is required the first time; later a blank field keeps the stored one.
        $needs = fn (AuthMethod $kind): bool => $method === $kind && ! SaveCompany::keeps($company, $method);

        return [
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['required', 'digits:10', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    Nip::of((string) $value);
                } catch (ValidationException) {
                    $fail(__('This is not a valid NIP (check the digits).'));
                }
            }, Rule::unique('companies', 'nip')->ignore($company?->id)],
            'environment' => ['required', Rule::enum(KsefEnvironment::class)],
            'auth_method' => ['required', Rule::enum(AuthMethod::class)],
            'token' => [Rule::requiredIf($needs(AuthMethod::Token)), 'nullable', 'string', 'max:4096'],
            'certificate' => [Rule::requiredIf($needs(AuthMethod::Certificate)), 'nullable', 'string', 'max:20000'],
            'private_key' => [Rule::requiredIf($needs(AuthMethod::Certificate)), 'nullable', 'string', 'max:20000'],
            'passphrase' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
