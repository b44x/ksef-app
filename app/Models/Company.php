<?php

namespace App\Models;

use App\Enums\AuthMethod;
use App\Enums\KsefEnvironment;
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
 * @property Carbon|null $purchases_synced_until
 * @property Carbon|null $purchases_synced_at
 * @property string|null $purchases_sync_error
 */
#[Fillable(['name', 'nip', 'address_line1', 'address_line2', 'email', 'environment', 'auth_method', 'credentials'])]
#[Hidden(['credentials'])]
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
            'purchases_synced_until' => 'datetime',
            'purchases_synced_at' => 'datetime',
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
