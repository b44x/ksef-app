<?php

namespace App\Http\Controllers;

use App\Enums\NumberReset;
use App\Http\Controllers\Concerns\ScopedToCompany;
use App\Http\Requests\NumberingSeriesRequest;
use App\Models\Company;
use App\Models\NumberingSeries;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NumberingSeriesController extends Controller
{
    use ScopedToCompany;

    public function index(): Response|RedirectResponse
    {
        $company = Company::current();
        if ($company === null) {
            return redirect()->route('company.edit');
        }

        $today = now();

        return Inertia::render('NumberingSeries', [
            'series' => $company->numberingSeries()->orderBy('name')->get()->map(fn (NumberingSeries $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'format' => $s->format,
                'reset' => $s->reset->value,
                'is_default' => $s->is_default,
                'next' => $s->preview($today),
            ]),
            'resets' => array_map(fn (NumberReset $r): array => ['value' => $r->value, 'label' => $r->label()], NumberReset::cases()),
        ]);
    }

    public function store(NumberingSeriesRequest $request): RedirectResponse
    {
        $company = Company::current();
        abort_if($company === null, 404);

        DB::transaction(function () use ($request, $company): void {
            $series = $company->numberingSeries()->create($request->validated());
            // The first series is the default; so is one marked as such.
            $this->makeDefaultIfNeeded($company, $series);
        });

        return back()->with('status', __('Saved.'));
    }

    public function update(NumberingSeriesRequest $request, NumberingSeries $series): RedirectResponse
    {
        $this->authorizeOwnership($series);

        DB::transaction(function () use ($request, $series): void {
            $series->update($request->validated());
            $this->makeDefaultIfNeeded($series->company, $series);
        });

        return back()->with('status', __('Saved.'));
    }

    public function destroy(NumberingSeries $series): RedirectResponse
    {
        $this->authorizeOwnership($series);
        $series->delete();

        return back()->with('status', __('Deleted.'));
    }

    private function makeDefaultIfNeeded(Company $company, NumberingSeries $series): void
    {
        $hasDefault = $company->numberingSeries()->where('is_default', true)->where('id', '!=', $series->id)->exists();

        if ($series->is_default || ! $hasDefault) {
            $company->numberingSeries()->where('id', '!=', $series->id)->update(['is_default' => false]);
            $series->forceFill(['is_default' => true])->save();
        }
    }
}
