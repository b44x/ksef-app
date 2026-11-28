<?php

namespace App\Lookup;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * The average exchange rate of the National Bank of Poland (table A) that a foreign-currency invoice converts its VAT
 * with: the rate of the last business day before the sale (art. 31a of the VAT Act). Public API, no key.
 */
final class NbpRates
{
    private const URL = 'https://api.nbp.pl/api/exchangerates/rates/a/';

    /**
     * @return array{rate: string, date: string, table: string}
     *
     * @throws LookupFailed when the bank could not be asked or has no rate for those days
     */
    public function before(string $currency, CarbonImmutable $day): array
    {
        $code = strtolower($currency);
        $key = "nbp.{$code}.{$day->toDateString()}";

        /** @var array{rate: string, date: string, table: string}|null $cached */
        $cached = Cache::get($key);
        if ($cached !== null) {
            return $cached;
        }

        // Ten days back is enough to cover a long weekend with holidays around it.
        $from = $day->subDays(10)->toDateString();
        $to = $day->subDay()->toDateString();

        try {
            $response = Http::timeout(8)->acceptJson()->get(self::URL."{$code}/{$from}/{$to}/", ['format' => 'json']);
        } catch (\Throwable $e) {
            throw new LookupFailed(__('The National Bank of Poland could not be reached.'), 0, $e);
        }
        $rates = $response->successful() ? $response->json('rates') : null;
        if (! is_array($rates) || $rates === []) {
            throw new LookupFailed(__('The National Bank of Poland has no rate for :currency before :date.', ['currency' => strtoupper($currency), 'date' => $day->toDateString()]));
        }

        $last = $rates[array_key_last($rates)];
        $found = ['rate' => number_format((float) $last['mid'], 4, '.', ''), 'date' => (string) $last['effectiveDate'], 'table' => (string) $last['no']];
        Cache::put($key, $found, now()->addHours(12));

        return $found;
    }
}
