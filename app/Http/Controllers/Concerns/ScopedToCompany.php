<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

trait ScopedToCompany
{
    /** A record of another company is not reachable, even by guessing its id. */
    private function authorizeOwnership(Model $record): void
    {
        abort_unless($record->getAttribute('company_id') === Company::current()?->id, 404);
    }
}
