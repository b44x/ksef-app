<?php

namespace App\Lookup;

use B4x\Ksef\Exception\ValidationException;
use B4x\Ksef\Support\Nip;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Company data by NIP from the Ministry of Finance's VAT taxpayer register (the "white list"): a public API, no key. Used
 * to fill in a new contractor. A lookup that fails says nothing about the company; the form just stays as it is.
 */
final class WhiteList
{
    private const URL = 'https://wl-api.mf.gov.pl/api/search/nip/';

    /**
     * @return array{name: string, nip: string, address_line1: string, address_line2: string, vat_status: string|null, accounts: list<string>}|null null: no such taxpayer
     *
     * @throws LookupFailed when the register could not be asked, or answered something unexpected
     * @throws ValidationException when the NIP is not a NIP
     */
    public function find(string $nip): ?array
    {
        $nip = Nip::of($nip)->value;

        /** @var array{found: bool, data?: array<string, mixed>}|null $cached */
        $cached = Cache::get("whitelist.{$nip}");
        if ($cached === null) {
            $cached = $this->ask($nip);
            Cache::put("whitelist.{$nip}", $cached, now()->addHour());
        }

        return $cached['found'] ? $this->present($cached['data'] ?? []) : null;
    }

    /**
     * @return array{found: bool, data?: array<string, mixed>}
     */
    private function ask(string $nip): array
    {
        try {
            $response = Http::timeout(8)->acceptJson()->get(self::URL.$nip, ['date' => now()->toDateString()]);
        } catch (\Throwable $e) {
            throw new LookupFailed(__('The register could not be reached.'), 0, $e);
        }

        // The register answers 400 with a message for a NIP it has never heard of.
        if ($response->status() === 400 || $response->status() === 404) {
            return ['found' => false];
        }
        $subject = $response->json('result.subject');
        if (! $response->successful() || ! is_array($response->json('result'))) {
            throw new LookupFailed(__('The register did not answer properly (:status).', ['status' => $response->status()]));
        }

        return is_array($subject) ? ['found' => true, 'data' => $subject] : ['found' => false];
    }

    /**
     * @param  array<string, mixed>  $subject
     * @return array{name: string, nip: string, address_line1: string, address_line2: string, vat_status: string|null, accounts: list<string>}
     */
    private function present(array $subject): array
    {
        // One string, "UL. PROSTA 1/2, 00-001 WARSZAWA": the street and number, then the postcode and town.
        $address = trim((string) ($subject['workingAddress'] ?? $subject['residenceAddress'] ?? ''));
        $split = strrpos($address, ', ');
        $accounts = array_values(array_filter(array_map('strval', is_array($subject['accountNumbers'] ?? null) ? $subject['accountNumbers'] : [])));

        return [
            'name' => (string) ($subject['name'] ?? ''),
            'nip' => (string) ($subject['nip'] ?? ''),
            'address_line1' => $split === false ? $address : substr($address, 0, $split),
            'address_line2' => $split === false ? '' : substr($address, $split + 2),
            'vat_status' => isset($subject['statusVat']) ? (string) $subject['statusVat'] : null,
            'accounts' => $accounts,
        ];
    }
}
