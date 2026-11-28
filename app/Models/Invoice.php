<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\OfflineMode;
use App\Enums\PaymentStatus;
use App\Ksef\Submission;
use B4x\Ksef\Support\Decimal;
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
 * @property int|null $invoice_template_id
 * @property InvoiceStatus $status
 * @property string|null $number
 * @property Carbon $issue_date
 * @property Carbon|null $sale_date
 * @property string $currency
 * @property Carbon|null $due_date
 * @property int|null $payment_method
 * @property string|null $bank_account
 * @property string|null $notes
 * @property string|null $net_total
 * @property string|null $vat_total
 * @property string|null $gross_total
 * @property list<array{rate: string, net: string, vat: string|null, gross: string}>|null $vat_summary
 * @property string|null $xml
 * @property array{seller: array<string, string|null>, buyer: array<string, string|null>}|null $snapshot
 * @property Carbon|null $issued_at
 * @property OfflineMode|null $offline_mode
 * @property Carbon|null $offline_anchor_date
 * @property Carbon|null $offline_deadline
 * @property string|null $verification_url
 * @property string|null $issuer_url
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
#[Fillable(['contractor_id', 'numbering_series_id', 'issue_date', 'sale_date', 'due_date', 'payment_method', 'bank_account', 'notes', 'invoice_template_id'])]
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
            'net_total' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'gross_total' => 'decimal:2',
            'vat_summary' => 'array',
            'snapshot' => 'array',
            'issued_at' => 'datetime',
            'offline_mode' => OfflineMode::class,
            'offline_anchor_date' => 'date',
            'offline_deadline' => 'datetime',
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

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_on')->orderBy('id');
    }

    /** What has been paid so far. Uses the sum a list query loaded (withSum) when there is one. */
    public function paidAmount(): Decimal
    {
        $sum = $this->getAttributes()['payments_sum_amount'] ?? $this->payments()->sum('amount');

        return Decimal::of(number_format((float) $sum, 2, '.', ''));
    }

    /** What is still owed: the gross total less the payments (never below zero; an overpayment is shown as paid). */
    public function outstanding(): Decimal
    {
        if ($this->gross_total === null) {
            return Decimal::zero();
        }
        $left = Decimal::of($this->gross_total)->subtract($this->paidAmount());

        return $left->isNegative() ? Decimal::zero() : $left;
    }

    public function paymentStatus(): PaymentStatus
    {
        $paid = $this->paidAmount();
        if ($this->gross_total === null || $paid->isZero()) {
            return PaymentStatus::Unpaid;
        }

        return $this->outstanding()->isZero() ? PaymentStatus::Paid : PaymentStatus::Partial;
    }

    /** A sale that is owed money: not a draft, and not one KSeF refused. */
    public function isSale(): bool
    {
        return ! $this->isDraft() && $this->status !== InvoiceStatus::Rejected;
    }

    /** Past the due date and not paid in full. Only sales can be late, and only with a due date. */
    public function isOverdue(): bool
    {
        return $this->isSale()
            && $this->due_date !== null
            && $this->due_date->endOfDay()->isPast()
            && ! $this->outstanding()->isZero();
    }

    /**
     * @return BelongsTo<InvoiceTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(InvoiceTemplate::class, 'invoice_template_id');
    }

    /**
     * The settings this invoice is printed with: its template if it has one (and the template still exists), else the default.
     *
     * @return array<string, mixed>
     */
    public function printSettings(): array
    {
        return $this->template?->resolved() ?? $this->company->defaultTemplateSettings();
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
