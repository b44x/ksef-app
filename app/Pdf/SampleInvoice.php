<?php

namespace App\Pdf;

use App\Models\Company;

/** An invoice made up for the template editor's preview: the company's own name and logo, invented buyer and lines. */
final class SampleInvoice
{
    /**
     * @return array<string, mixed>
     */
    public static function for(Company $company): array
    {
        $year = now()->format('Y');

        return [
            'title' => 'VAT invoice',
            'number' => "FV/001/{$year}",
            'issue_date' => now()->toDateString(),
            'sale_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'currency' => 'PLN',
            'seller' => ['name' => $company->name, 'tax_id' => $company->nip, 'address' => trim($company->address_line1.', '.$company->address_line2, ', '), 'email' => $company->email],
            'buyer' => ['name' => 'Przykładowy Klient sp. z o.o.', 'tax_id' => '7762811692', 'address' => 'ul. Długa 5, 80-001 Gdańsk', 'email' => 'ksiegowosc@klient.example'],
            'lines' => [
                ['n' => 1, 'name' => 'Konsultacje IT', 'unit' => 'godz.', 'quantity' => '12', 'unit_price' => '180.00', 'price_kind' => 'net', 'vat_rate' => '23', 'net' => '2160.00'],
                ['n' => 2, 'name' => 'Utrzymanie aplikacji (abonament)', 'unit' => 'mies.', 'quantity' => '1', 'unit_price' => '1200.00', 'price_kind' => 'net', 'vat_rate' => '23', 'net' => '1200.00'],
                ['n' => 3, 'name' => 'Książka techniczna', 'unit' => 'szt.', 'quantity' => '3', 'unit_price' => '79.00', 'price_kind' => 'net', 'vat_rate' => '5', 'net' => '237.00'],
            ],
            'summary' => [
                ['label' => '23%', 'net' => '3360.00', 'vat' => '772.80', 'gross' => '4132.80'],
                ['label' => '5%', 'net' => '237.00', 'vat' => '11.85', 'gross' => '248.85'],
            ],
            'total' => ['net' => '3597.00', 'vat' => '784.65', 'gross' => '4381.65'],
            'payment' => ['method' => 'Bank transfer', 'due_date' => now()->addDays(14)->toDateString(), 'accounts' => ['61109010140000071219812874'], 'paid' => false],
            'notes' => 'Dziękujemy za współpracę.',
            'ksef_number' => null,
            'offline' => false,
            'qr' => ['verification' => ['url' => 'https://qr.ksef.mf.gov.pl/invoice/sample', 'caption' => 'OFFLINE'], 'issuer' => null],
            'logo' => $company->logo,
        ];
    }
}
