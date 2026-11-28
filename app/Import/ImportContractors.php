<?php

namespace App\Import;

use App\Http\Requests\ContractorRequest;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Contractors from a CSV file. One that has the NIP of an existing contractor updates it; one without a NIP is matched by name. */
final class ImportContractors
{
    private const ALIASES = [
        'name' => ['name', 'nazwa', 'nazwa firmy', 'firma', 'kontrahent', 'nabywca'],
        'nip' => ['nip', 'vat id', 'tax id'],
        'country' => ['country', 'kraj'],
        'address_line1' => ['address', 'address line 1', 'address_line1', 'adres', 'ulica', 'adres 1'],
        'address_line2' => ['address line 2', 'address_line2', 'adres 2', 'kod i miasto', 'miasto', 'kod pocztowy i miasto'],
        'email' => ['email', 'e-mail', 'mail', 'adres e-mail'],
    ];

    public function __invoke(Company $company, string $path): ImportResult
    {
        $rows = CsvTable::read($path, self::ALIASES);
        $created = $updated = 0;
        $errors = [];

        DB::transaction(function () use ($company, $rows, &$created, &$updated, &$errors): void {
            foreach ($rows as ['line' => $line, 'values' => $values]) {
                $data = [
                    ...$values,
                    'country' => strtoupper($values['country'] !== '' ? $values['country'] : 'PL'),
                    'nip' => $values['nip'] === '' ? null : preg_replace('/\D/', '', $values['nip']),
                    'address_line2' => $values['address_line2'] === '' ? null : $values['address_line2'],
                    'email' => $values['email'] === '' ? null : $values['email'],
                ];

                $existing = $data['nip'] !== null
                    ? $company->contractors()->where('nip', $data['nip'])->first()
                    : $company->contractors()->whereNull('nip')->where('name', $data['name'])->first();

                // The same rules as the form; the contractor being updated does not count as a duplicate of itself.
                $validator = Validator::make($data, ContractorRequest::rulesFor($company->id, $existing));
                if ($validator->fails()) {
                    $errors[] = ['line' => $line, 'message' => $validator->errors()->first()];

                    continue;
                }

                if ($existing === null) {
                    $company->contractors()->create($data);
                    $created++;
                } else {
                    $existing->update($data);
                    $updated++;
                }
            }
        });

        return new ImportResult($created, $updated, $errors);
    }
}
