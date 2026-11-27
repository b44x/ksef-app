<?php

namespace App\Models;

use App\Enums\NumberReset;
use App\Invoicing\NumberFormat;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property string $format
 * @property NumberReset $reset
 * @property int $last_number
 * @property string|null $last_period
 * @property bool $is_default
 */
#[Fillable(['name', 'format', 'reset', 'is_default'])]
class NumberingSeries extends Model
{
    protected $table = 'numbering_series';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['reset' => NumberReset::class, 'is_default' => 'boolean'];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** Takes the next number of the series for an invoice issued on the date. Never hands out the same number twice. */
    public function issue(DateTimeInterface $date): string
    {
        return DB::transaction(function () use ($date): string {
            $series = self::query()->lockForUpdate()->findOrFail($this->id);
            $period = $series->reset->period($date);

            $series->last_number = $series->last_period === $period ? $series->last_number + 1 : 1;
            $series->last_period = $period;
            $series->save();

            return NumberFormat::render($series->format, $series->last_number, $date);
        });
    }

    /** What the next number will look like, without taking it. */
    public function preview(DateTimeInterface $date): string
    {
        $next = $this->last_period === $this->reset->period($date) ? $this->last_number + 1 : 1;

        return NumberFormat::render($this->format, $next, $date);
    }
}
