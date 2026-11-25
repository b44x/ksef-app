<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A buyer (or, later, a seller) the company deals with.
 *
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string|null $nip
 * @property string $country
 * @property string $address_line1
 * @property string|null $address_line2
 * @property string|null $email
 */
#[Fillable(['name', 'nip', 'country', 'address_line1', 'address_line2', 'email'])]
class Contractor extends Model
{
    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
