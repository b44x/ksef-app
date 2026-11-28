<?php

namespace App\Http\Controllers;

use App\Import\ImportContractors;
use App\Import\ImportProducts;
use App\Import\ImportResult;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/** Brings contractors and products in from a CSV file; the rows that do not pass the form's rules are reported, not guessed. */
class ImportController extends Controller
{
    public function contractors(Request $request, ImportContractors $import): RedirectResponse
    {
        return $this->run($request, $import(...));
    }

    public function products(Request $request, ImportProducts $import): RedirectResponse
    {
        return $this->run($request, $import(...));
    }

    /**
     * @param  callable(Company, string): ImportResult  $import
     */
    private function run(Request $request, callable $import): RedirectResponse
    {
        $company = Company::current();
        abort_if($company === null, 404);
        $request->validate(['file' => ['required', 'file', 'max:2048', 'extensions:csv,txt']]);

        try {
            $result = $import($company, (string) $request->file('file')?->getRealPath());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['file' => $e->getMessage()]);
        }

        return back()->with('import', $result->toArray());
    }
}
