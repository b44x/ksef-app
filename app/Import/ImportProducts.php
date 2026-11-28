<?php

namespace App\Import;

use App\Http\Requests\ProductRequest;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Products and services from a CSV file. One with the name of an existing product updates it. */
final class ImportProducts
{
    private const ALIASES = [
        'name' => ['name', 'nazwa', 'towar', 'usluga', 'towar lub usluga', 'produkt'],
        'unit' => ['unit', 'jednostka', 'jm', 'j.m.', 'j. m.'],
        'net_price' => ['net price', 'net_price', 'price', 'cena', 'cena netto'],
        'vat_rate' => ['vat', 'vat rate', 'vat_rate', 'stawka', 'stawka vat'],
    ];

    public function __invoke(Company $company, string $path): ImportResult
    {
        $rows = CsvTable::read($path, self::ALIASES);
        $created = $updated = 0;
        $errors = [];

        DB::transaction(function () use ($company, $rows, &$created, &$updated, &$errors): void {
            foreach ($rows as ['line' => $line, 'values' => $values]) {
                $data = [
                    'name' => $values['name'],
                    'unit' => $values['unit'] !== '' ? $values['unit'] : 'szt.',
                    // "1 234,50", "1234.5": spreadsheets write the Polish way.
                    'net_price' => str_replace([' ', "\u{a0}", ','], ['', '', '.'], $values['net_price']),
                    // "23%" and "ZW" are what people type; the codes are "23" and "zw".
                    'vat_rate' => self::rate($values['vat_rate']),
                ];

                $validator = Validator::make($data, ProductRequest::baseRules());
                if ($validator->fails()) {
                    $errors[] = ['line' => $line, 'message' => $validator->errors()->first()];

                    continue;
                }

                $existing = $company->products()->where('name', $data['name'])->first();
                if ($existing === null) {
                    $company->products()->create($data);
                    $created++;
                } else {
                    $existing->update($data);
                    $updated++;
                }
            }
        });

        return new ImportResult($created, $updated, $errors);
    }

    private static function rate(string $text): string
    {
        $text = strtolower(trim(str_replace('%', '', $text)));

        return $text === 'zw.' ? 'zw' : $text;
    }
}
