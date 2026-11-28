<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Ksef\Submission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $company_id
 * @property int $contractor_id
 * @property int $numbering_series_id
 * @property InvoiceStatus $status
 * @property string|null $number
 * @property Carbon $issue_date
 * @property Carbon|null $sale_date
 * @property string $currency
 * @property Carbon|null $due_date
 * @property int|null $payment_method
 * @property string|null $bank_account
 * @property string|null $notes
 * @property string|null $xml
 * @property array{seller: array<string, string|null>, buyer: array<string, string|null>}|null $snapshot
 * @property Carbon|null $issued_at
 * @property string|null $ksef_session_reference
 * @property string|null $ksef_invoice_reference
 * @property string|null $ksef_invoice_hash
 * @property string|null $ksef_number
 * @property int|null $ksef_status_code
 * @property string|null $ksef_status_description
 * @property string|null $ksef_error
 * @property string|null $upo_xml
 * @property int $poll_attempts
 * @property Carbon|null $sent_at
 * @property Carbon|null $accepted_at
 */
#[Fillable(['contractor_id', 'numbering_series_id', 'issue_date', 'sale_date', 'due_date', 'payment_method', 'bank_account', 'notes'])]
class Invoice extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'issue_date' => 'date',
            'sale_date' => 'date',
            'due_date' => 'date',
            'snapshot' => 'array',
            'issued_at' => 'datetime',
            'sent_at' => 'datetime',
            'accepted_at' => 'datetime',
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
     * @return BelongsTo<Contractor, $this>
     */
    public function contractor(): BelongsTo
    {
        return $this->belongsTo(Contractor::class);
    }

    /**
     * @return BelongsTo<NumberingSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(NumberingSeries::class, 'numbering_series_id');
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('position');
    }

    /** The references of the submission, once KSeF accepted the document for processing. */
    public function submission(): ?Submission
    {
        if ($this->ksef_session_reference === null || $this->ksef_invoice_reference === null || $this->ksef_invoice_hash === null) {
            return null;
        }

        return new Submission($this->ksef_session_reference, $this->ksef_invoice_reference, $this->ksef_invoice_hash);
    }

    public function isDraft(): bool
    {
        return $this->status->isEditable();
    }
}
