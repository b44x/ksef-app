<?php

namespace App\Reports;

/**
 * CSV the way Polish Excel opens it: semicolons, a UTF-8 byte order mark, a comma for the decimal point is left to the
 * reader (we write plain numbers). A cell that starts with = + - or @ would be run as a formula by a spreadsheet, so it
 * is written as text.
 */
final class CsvWriter
{
    /**
     * @param  list<string>  $header
     * @param  list<list<string|int|float|null>>  $rows
     */
    public static function render(array $header, array $rows): string
    {
        $out = "\xEF\xBB\xBF";
        foreach ([$header, ...$rows] as $row) {
            $out .= implode(';', array_map(self::cell(...), $row))."\r\n";
        }

        return $out;
    }

    private static function cell(string|int|float|null $value): string
    {
        $text = (string) $value;
        if ($text !== '' && preg_match('/^[=+\-@\t\r]/', $text) === 1 && preg_match('/^-?\d+([.,]\d+)?$/', $text) !== 1) {
            $text = "'".$text;
        }

        return preg_match('/[;"\r\n]/', $text) === 1 ? '"'.str_replace('"', '""', $text).'"' : $text;
    }
}
