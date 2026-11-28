<?php

namespace App\Http\Requests;

use App\Models\Company;
use B4x\Ksef\Invoice\PaymentMethod;
use B4x\Ksef\Invoice\VatRate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InvoiceRequest extends FormRequest
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
        $company = Company::current()?->id;

        return [
            'contractor_id' => ['required', Rule::exists('contractors', 'id')->where('company_id', $company)],
            'numbering_series_id' => ['required', Rule::exists('numbering_series', 'id')->where('company_id', $company)],
            'issue_date' => ['required', 'date_format:Y-m-d'],
            'sale_date' => ['nullable', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:issue_date'],
            'payment_method' => ['nullable', Rule::in(array_map(static fn (PaymentMethod $m): int => $m->value, PaymentMethod::cases()))],
            'bank_account' => ['nullable', 'string', 'min:10', 'max:34'],
            'notes' => ['nullable', 'string', 'max:3500'],
            'invoice_template_id' => ['nullable', Rule::exists('invoice_templates', 'id')->where('company_id', $company)],
            'lines' => ['required', 'array', 'min:1', 'max:1000'],
            ...self::lineRules('lines.*.'),
        ];
    }

    /**
     * The rules of one invoice line; the live totals use the same ones, so they cannot drift apart.
     *
     * @return array<string, mixed>
     */
    public static function lineRules(string $prefix): array
    {
        return [
            $prefix.'name' => ['required', 'string', 'max:512'],
            $prefix.'unit' => ['nullable', 'string', 'max:20'],
            $prefix.'quantity' => ['required', 'numeric', 'gt:0', 'max:999999999999', 'decimal:0,6'],
            $prefix.'unit_net_price' => ['required', 'numeric', 'min:0', 'max:999999999999', 'decimal:0,8'],
            $prefix.'vat_rate' => ['required', Rule::enum(VatRate::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Empty form fields arrive as empty strings; for dates and the optional text they mean "not given".
        $this->merge(array_map(
            static fn (mixed $v): mixed => $v === '' ? null : $v,
            $this->only(['sale_date', 'due_date', 'payment_method', 'bank_account', 'notes', 'invoice_template_id']),
        ));
    }
}
