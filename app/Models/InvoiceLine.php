<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $invoice_id
 * @property int $position
 * @property string $name
 * @property string|null $unit
 * @property string $quantity
 * @property string $unit_net_price
 * @property string $vat_rate
 */
#[Fillable(['position', 'name', 'unit', 'quantity', 'unit_net_price', 'vat_rate'])]
class InvoiceLine extends Model
{
    public $timestamps = false;

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
