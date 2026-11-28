<?php

namespace App\Import;

use InvalidArgumentException;

/**
 * Reads a CSV file as a list of rows keyed by our own field names. The delimiter (; , or tab), a byte order mark and
 * Windows-1250 text (what Polish spreadsheets save) are recognised; column names are matched by alias, in Polish or English.
 */
final class CsvTable
{
    /**
     * @param  array<string, list<string>>  $aliases  field => the column names that mean it (any case, with or without diacritics)
     * @return list<array{line: int, values: array<string, string>}> the data rows with the line they were on; blank lines are left out
     *
     * @throws InvalidArgumentException when the file cannot be read as a table
     */
    public static function read(string $path, array $aliases, int $maxRows = 2000): array
    {
        $text = (string) file_get_contents($path);
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        if (! mb_check_encoding($text, 'UTF-8')) {
            // Polish spreadsheets save Windows-1250 unless told to use UTF-8 (mbstring does not know that encoding).
            $converted = function_exists('iconv') ? iconv('CP1250', 'UTF-8//IGNORE', $text) : false;
            if ($converted === false) {
                throw new InvalidArgumentException(__('The file is not UTF-8. Save it as "CSV UTF-8".'));
            }
            $text = $converted;
        }

        $lines = preg_split('/\r\n|\n|\r/', $text) ?: [];
        $delimiter = self::delimiter((string) ($lines[0] ?? ''));
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $text);
        rewind($stream);

        $header = fgetcsv($stream, null, $delimiter, '"', '');
        if ($header === false || $header === [null]) {
            throw new InvalidArgumentException(__('The file is empty.'));
        }

        $columns = [];
        foreach ($header as $index => $name) {
            foreach ($aliases as $field => $names) {
                if (! isset($columns[$field]) && in_array(self::normalise((string) $name), $names, true)) {
                    $columns[$field] = $index;
                }
            }
        }
        if ($columns === []) {
            throw new InvalidArgumentException(__('No known column names were found in the first line.'));
        }

        $rows = [];
        $line = 1;
        while (($record = fgetcsv($stream, null, $delimiter, '"', '')) !== false) {
            $line++;
            if ($record === [null] || trim(implode('', array_map(strval(...), $record))) === '') {
                continue;
            }
            if (count($rows) >= $maxRows) {
                throw new InvalidArgumentException(__('The file has more than :max rows. Split it.', ['max' => $maxRows]));
            }
            $values = [];
            foreach (array_keys($aliases) as $field) {
                $values[$field] = isset($columns[$field]) ? trim((string) ($record[$columns[$field]] ?? '')) : '';
            }
            $rows[] = ['line' => $line, 'values' => $values];
        }
        fclose($stream);

        return $rows;
    }

    /** "Cena netto" → "cena netto", "Kod i miasto" → "kod i miasto", "Nazwa firmy" with Polish letters → ASCII. */
    public static function normalise(string $name): string
    {
        $name = mb_strtolower(trim($name));

        return strtr($name, ['ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n', 'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z']);
    }

    private static function delimiter(string $firstLine): string
    {
        $best = ',';
        $most = 0;
        foreach ([';', ',', "\t"] as $candidate) {
            $count = substr_count($firstLine, $candidate);
            if ($count > $most) {
                [$best, $most] = [$candidate, $count];
            }
        }

        return $best;
    }
}
