<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A purchase invoice KSeF holds for the company (the company is the buyer).
 *
 * @property int $id
 * @property int $company_id
 * @property string $ksef_number
 * @property string $xml
 * @property string $number
 * @property string $type
 * @property Carbon $issue_date
 * @property string $seller_name
 * @property string|null $seller_nip
 * @property string $currency
 * @property string $gross
 * @property Carbon $received_at
 * @property Carbon|null $read_at
 */
#[Fillable(['ksef_number', 'xml', 'number', 'type', 'issue_date', 'seller_name', 'seller_nip', 'currency', 'gross', 'received_at'])]
#[Hidden(['xml'])]
class ReceivedInvoice extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['issue_date' => 'date', 'received_at' => 'datetime', 'read_at' => 'datetime', 'gross' => 'decimal:2'];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
