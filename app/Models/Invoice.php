<?php

namespace App\Models;

use App\Enums\InvoiceStatus;
use App\Enums\OfflineMode;
use App\Enums\PaymentStatus;
use App\Ksef\Submission;
use B4x\Ksef\Support\Decimal;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
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
 * @property int|null $recurring_invoice_id
 * @property int|null $corrects_invoice_id
 * @property string $kind
 * @property bool $split_payment
 * @property bool $cash_accounting
 * @property string|null $exemption_basis
 * @property int|null $converted_from_id
 * @property string|null $correction_reason
 * @property int $correction_type
 * @property InvoiceStatus $status
 * @property string|null $number
 * @property Carbon $issue_date
 * @property Carbon|null $sale_date
 * @property string $currency
 * @property string|null $exchange_rate
 * @property Carbon|null $exchange_rate_date
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
#[Fillable(['contractor_id', 'numbering_series_id', 'issue_date', 'sale_date', 'due_date', 'payment_method', 'bank_account', 'notes', 'invoice_template_id', 'correction_reason', 'correction_type', 'currency', 'exchange_rate', 'exchange_rate_date', 'kind', 'split_payment', 'cash_accounting', 'exemption_basis'])]
class Invoice extends Model
{
    /** A new invoice is a draft, whether or not it has been read back from the database yet. */
    protected $attributes = ['status' => 'draft', 'kind' => 'vat'];

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
            'split_payment' => 'boolean',
            'cash_accounting' => 'boolean',
            'exchange_rate' => 'decimal:6',
            'exchange_rate_date' => 'date',
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
     * @return BelongsTo<Invoice, $this>
     */
    public function corrected(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_invoice_id');
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function corrections(): HasMany
    {
        return $this->hasMany(self::class, 'corrects_invoice_id')->orderBy('id');
    }

    /** A proforma: sent to the buyer like an invoice, but not a VAT invoice, not in KSeF and not a sale. */
    public function isProforma(): bool
    {
        return $this->kind === 'proforma';
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function convertedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'converted_from_id');
    }

    public function isCorrection(): bool
    {
        return $this->corrects_invoice_id !== null;
    }

    /** Only an invoice KSeF accepted can be corrected (a correction is sent after the original has its number), and a correction is not corrected itself: the original is. */
    public function canBeCorrected(): bool
    {
        return $this->status === InvoiceStatus::Accepted && $this->ksef_number !== null && ! $this->isCorrection();
    }

    /**
     * What the invoice says after the corrections issued so far: the lines of the latest one that stands (not a draft, not
     * a rejected one), else its own lines. This is the "before" of the next correction.
     *
     * @return Collection<int, InvoiceLine>
     */
    public function currentLines(?int $before = null): Collection
    {
        $latest = $this->corrections()
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Rejected])
            ->when($before !== null, fn ($q) => $q->where('id', '<', $before))
            ->with('lines')->orderByDesc('id')->first();

        return $latest !== null ? $latest->lines : $this->lines;
    }

    /**
     * The lines a correction starts from: how the corrected invoice stood just before this one.
     *
     * @return Collection<int, InvoiceLine>
     */
    public function stateBefore(): Collection
    {
        return $this->corrected->currentLines($this->id);
    }

    /**
     * @return HasMany<InvoiceEmail, $this>
     */
    public function emails(): HasMany
    {
        return $this->hasMany(InvoiceEmail::class)->orderBy('id');
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
        if ($this->gross_total !== null && ! Decimal::of($this->gross_total)->isPositive()) {
            return PaymentStatus::Paid; // a credit or a zero correction: nothing is due
        }
        if ($this->gross_total === null || $paid->isZero()) {
            return PaymentStatus::Unpaid;
        }

        return $this->outstanding()->isZero() ? PaymentStatus::Paid : PaymentStatus::Partial;
    }

    /** A sale that is owed money: not a draft, not a proforma, and not one KSeF refused. */
    public function isSale(): bool
    {
        return ! $this->isDraft() && ! $this->isProforma() && $this->status !== InvoiceStatus::Rejected;
    }

    /** Can go to the buyer as a document: issued (a proforma included), and not refused by KSeF. */
    public function isPrintable(): bool
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
