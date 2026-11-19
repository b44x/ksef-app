<?php

namespace App\Models;

use App\Enums\AuthMethod;
use App\Enums\KsefEnvironment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The company invoices are issued for and received by. Every business record belongs to one; an installation shows a
 * single company for now, but nothing in the data model assumes that.
 *
 * @property int $id
 * @property string $name
 * @property string $nip
 * @property KsefEnvironment $environment
 * @property AuthMethod $auth_method
 * @property array<string, string>|null $credentials
 */
#[Fillable(['name', 'nip', 'environment', 'auth_method', 'credentials'])]
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
        ];
    }

    /**
     * @return HasMany<KsefToken, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(KsefToken::class);
    }

    /** A stored credential value (for example "token", "certificate", "private_key"), or null. */
    public function credential(string $key): ?string
    {
        $value = ($this->credentials ?? [])[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
