<?php

namespace App\Http\Requests;

use App\Enums\NumberReset;
use App\Invoicing\NumberFormat;
use App\Models\Company;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class NumberingSeriesRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('numbering_series', 'name')->where('company_id', Company::current()?->id)->ignore($this->route('series'))],
            'format' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, Closure $fail): void {
                if (! NumberFormat::isValid((string) $value)) {
                    $fail(__('The pattern needs a counter such as {NNN} and may only use {N}, {YYYY}, {YY} and {MM}.'));
                }
            }],
            'reset' => ['required', Rule::enum(NumberReset::class)],
            'is_default' => ['boolean'],
        ];
    }
}
