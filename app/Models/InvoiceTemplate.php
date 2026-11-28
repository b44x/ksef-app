<?php

namespace App\Models;

use App\Pdf\TemplateSettings;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property string $name
 * @property bool $is_default
 * @property array<string, mixed> $settings
 */
#[Fillable(['name', 'settings'])]
class InvoiceTemplate extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'settings' => 'array'];
    }

    /**
     * @return BelongsTo<Company, $this>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * The settings, complete and valid whatever is stored.
     *
     * @return array<string, mixed>
     */
    public function resolved(): array
    {
        return TemplateSettings::clean($this->settings);
    }
}
