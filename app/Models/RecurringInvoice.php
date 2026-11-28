<?php

namespace App\Models;

use App\Enums\Frequency;
use App\Invoicing\GenerateRecurring;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An invoice that repeats: who, what, how often. {@see GenerateRecurring} makes the invoices.
 *
 * @property int $id
 * @property int $company_id
 * @property int $contractor_id
 * @property int $numbering_series_id
 * @property int|null $invoice_template_id
 * @property string $name
 * @property Frequency $frequency
 * @property int $anchor_day
 * @property Carbon $next_run_on
 * @property Carbon|null $ends_on
 * @property bool $active
 * @property bool $auto_issue
 * @property int|null $payment_days
 * @property int|null $payment_method
 * @property string|null $bank_account
 * @property string|null $notes
 * @property list<array{name: string, unit: string|null, quantity: string, unit_net_price: string, vat_rate: string}> $lines
 * @property int $generated_count
 * @property Carbon|null $last_run_at
 * @property string|null $last_error
 */
class RecurringInvoice extends Model
{
    /** @var list<string> */
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'frequency' => Frequency::class,
            'next_run_on' => 'date',
            'ends_on' => 'date',
            'active' => 'boolean',
            'auto_issue' => 'boolean',
            'lines' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }
}
