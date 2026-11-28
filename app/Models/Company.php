<?php

namespace App\Models;

use App\Enums\AuthMethod;
use App\Enums\KsefEnvironment;
use App\Pdf\TemplateSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The company invoices are issued for and received by. Every business record belongs to one; an installation shows a
 * single company for now, but nothing in the data model assumes that.
 *
 * @property int $id
 * @property string $name
 * @property string $nip
 * @property string|null $address_line1
 * @property string|null $address_line2
 * @property string|null $email
 * @property KsefEnvironment $environment
 * @property AuthMethod $auth_method
 * @property array<string, string>|null $credentials
 * @property array{certificate: string, private_key: string}|null $offline_certificate
 * @property Carbon|null $offline_certificate_expires_at
 * @property Carbon|null $purchases_synced_until
 * @property Carbon|null $purchases_synced_at
 * @property string|null $purchases_sync_error
 * @property string|null $notify_email
 * @property string|null $logo
 * @property int|null $default_payment_days
 * @property int|null $default_payment_method
 * @property string|null $default_bank_account
 * @property string|null $default_notes
 * @property bool $default_cash_accounting
 * @property bool $notify_offline
 * @property bool $notify_purchases
 * @property bool $notify_rejected
 * @property Carbon|null $last_digest_at
 */
#[Fillable(['name', 'nip', 'address_line1', 'address_line2', 'email', 'environment', 'auth_method', 'credentials'])]
#[Hidden(['credentials', 'offline_certificate'])]
class Company extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'environment' => KsefEnvironment::class,
            'auth_method' => AuthMethod::class,
            'credentials' => 'encrypted:array',
            'offline_certificate' => 'encrypted:array',
            'offline_certificate_expires_at' => 'datetime',
            'purchases_synced_until' => 'datetime',
            'purchases_synced_at' => 'datetime',
            'default_cash_accounting' => 'boolean',
            'notify_offline' => 'boolean',
            'notify_purchases' => 'boolean',
            'notify_rejected' => 'boolean',
            'last_digest_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<KsefToken, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(KsefToken::class);
    }

    /** The company this installation works for (one for now; several later). */
    public static function current(): ?self
    {
        return self::query()->first();
    }

    /**
     * @return HasMany<Contractor, $this>
     */
    public function contractors(): HasMany
    {
        return $this->hasMany(Contractor::class);
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<NumberingSeries, $this>
     */
    public function numberingSeries(): HasMany
    {
        return $this->hasMany(NumberingSeries::class);
    }

    /**
     * @return HasMany<RecurringInvoice, $this>
     */
    public function recurringInvoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class);
    }

    /**
     * @return HasMany<InvoiceTemplate, $this>
     */
    public function templates(): HasMany
    {
        return $this->hasMany(InvoiceTemplate::class);
    }

    /**
     * The settings of the template that prints invoices unless one is chosen: the default one, or the built-in look.
     *
     * @return array<string, mixed>
     */
    public function defaultTemplateSettings(): array
    {
        $default = $this->templates()->where('is_default', true)->first();

        return $default?->resolved() ?? TemplateSettings::defaults();
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<ReceivedInvoice, $this>
     */
    public function receivedInvoices(): HasMany
    {
        return $this->hasMany(ReceivedInvoice::class);
    }

    /**
     * Until when a login made by hand still works: the SDK keeps the tokens of a signed login in the database until its
     * refresh token expires. Null when this company does not sign in by hand, or has no valid login.
     */
    public function signedInUntil(): ?Carbon
    {
        if ($this->auth_method !== AuthMethod::Manual) {
            return null;
        }

        $until = $this->tokens()->where('expires_at', '>', now())->max('expires_at');

        return $until === null ? null : Carbon::parse((string) $until);
    }

    /** Whether invoices can be issued offline: there is an Offline certificate that has not expired. */
    public function canIssueOffline(): bool
    {
        return $this->offline_certificate !== null
            && ($this->offline_certificate_expires_at === null || $this->offline_certificate_expires_at->isFuture());
    }

    /** The company signs in by hand and has no valid login: nothing can be sent or fetched until the person signs. */
    public function needsSigningIn(): bool
    {
        return $this->auth_method === AuthMethod::Manual && $this->signedInUntil() === null;
    }

    /** A stored credential value (for example "token", "certificate", "private_key"), or null. */
    public function credential(string $key): ?string
    {
        $value = ($this->credentials ?? [])[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
