<?php

namespace App\Http\Requests;

use App\Models\Company;
use B4x\Ksef\Exception\ValidationException;
use B4x\Ksef\Support\Nip;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ContractorRequest extends FormRequest
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
        $current = $this->route('contractor');

        return [
            'name' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'digits:10', function (string $attribute, mixed $value, Closure $fail): void {
                try {
                    Nip::of((string) $value);
                } catch (ValidationException) {
                    $fail(__('This is not a valid NIP (check the digits).'));
                }
            }, Rule::unique('contractors', 'nip')->where('company_id', Company::current()?->id)->ignore($current)],
            'country' => ['required', 'alpha:ascii', 'size:2'],
            'address_line1' => ['required', 'string', 'max:255'],
            'address_line2' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'country' => strtoupper((string) $this->input('country', 'PL')),
            'nip' => $this->filled('nip') ? preg_replace('/\D/', '', (string) $this->input('nip')) : null,
        ]);
    }
}
