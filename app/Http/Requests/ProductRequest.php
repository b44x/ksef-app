<?php

namespace App\Http\Requests;

use B4x\Ksef\Invoice\VatRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:20'],
            'net_price' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,2'],
            'vat_rate' => ['required', Rule::enum(VatRate::class)],
        ];
    }
}
