<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use B4x\Ksef\Invoice\Address;
use B4x\Ksef\Invoice\Buyer;
use B4x\Ksef\Invoice\BuyerIdentifier;
use B4x\Ksef\Invoice\Invoice as SdkInvoice;
use B4x\Ksef\Invoice\InvoiceDocument;
use B4x\Ksef\Invoice\InvoiceLine;
use B4x\Ksef\Invoice\Seller;
use B4x\Ksef\Invoice\VatRate;
use B4x\Ksef\Support\Nip;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;

/**
 * Example data for trying the application out and for screenshots. Local use only: it creates a user with a known
 * password and a company that does not exist.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (App::isProduction()) {
            $this->command?->error('The demo data is for local use only.');

            return;
        }

        User::query()->updateOrCreate(['email' => 'demo@example.test'], ['name' => 'Demo', 'password' => Hash::make('demo-password-123')]);

        $company = Company::query()->updateOrCreate(['nip' => '5265877635'], [
            'name' => 'Studio Kodu Anna Nowak',
            'address_line1' => 'ul. Marszałkowska 10/12',
            'address_line2' => '00-590 Warszawa',
            'email' => 'faktury@studiokodu.example',
            'environment' => 'test',
            // A made-up token, never used: it only lets the demo show the buttons that need a way to sign in.
            'auth_method' => 'token',
            'credentials' => ['token' => 'demo-token-not-real'],
        ]);

        $acme = $company->contractors()->updateOrCreate(['nip' => '7762811692'], ['name' => 'Acme Polska sp. z o.o.', 'country' => 'PL', 'address_line1' => 'ul. Długa 5, 80-001 Gdańsk', 'email' => 'ksiegowosc@acme.example']);
        $company->contractors()->updateOrCreate(['name' => 'Müller GmbH'], ['country' => 'DE', 'address_line1' => 'Hauptstraße 1, 10115 Berlin']);
        $company->contractors()->updateOrCreate(['nip' => '5260250995'], ['name' => 'Kowalski i Wspólnicy', 'country' => 'PL', 'address_line1' => 'ul. Piękna 3, 00-549 Warszawa']);

        $company->products()->updateOrCreate(['name' => 'Konsultacje IT'], ['unit' => 'godz.', 'net_price' => '180.00', 'vat_rate' => '23']);
        $company->products()->updateOrCreate(['name' => 'Utrzymanie aplikacji (abonament)'], ['unit' => 'mies.', 'net_price' => '1200.00', 'vat_rate' => '23']);
        $company->products()->updateOrCreate(['name' => 'Szkolenie (zwolnione z VAT)'], ['unit' => 'szt.', 'net_price' => '900.00', 'vat_rate' => 'zw']);
        $company->products()->updateOrCreate(['name' => 'Książka techniczna'], ['unit' => 'szt.', 'net_price' => '79.00', 'vat_rate' => '5']);

        $series = $company->numberingSeries()->updateOrCreate(['name' => 'Faktury sprzedaży'], ['format' => 'FV/{NNN}/{MM}/{YYYY}', 'reset' => 'monthly', 'is_default' => true]);
        $company->numberingSeries()->updateOrCreate(['name' => 'Korekty'], ['format' => 'KOR/{NNN}/{YYYY}', 'reset' => 'yearly']);

        $this->purchases($company);

        if ($company->invoices()->doesntExist()) {
            $draft = Invoice::query()->create([
                'company_id' => $company->id, 'contractor_id' => $acme->id, 'numbering_series_id' => $series->id,
                'issue_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(), 'payment_method' => 6,
                'bank_account' => '61109010140000071219812874',
            ]);
            $draft->lines()->createMany([
                ['position' => 1, 'name' => 'Konsultacje IT', 'unit' => 'godz.', 'quantity' => '12', 'unit_net_price' => '180.00', 'vat_rate' => '23'],
                ['position' => 2, 'name' => 'Utrzymanie aplikacji (abonament)', 'unit' => 'mies.', 'quantity' => '1', 'unit_net_price' => '1200.00', 'vat_rate' => '23'],
                ['position' => 3, 'name' => 'Książka techniczna', 'unit' => 'szt.', 'quantity' => '3', 'unit_net_price' => '79.00', 'vat_rate' => '5'],
            ]);
        }
    }

    /** Purchase invoices as KSeF would hand them over: real FA(3) documents, issued by made-up suppliers. */
    private function purchases(Company $company): void
    {
        $suppliers = [
            ['Papierex Hurtownia sp. z o.o.', '7762811692', 'FV/2026/11/0418', '2026-11-24', [['Papier ksero A4 (ryza)', '20', 'ryza', '18.90', VatRate::Rate23], ['Toner czarny', '2', 'szt.', '189.00', VatRate::Rate23]]],
            ['Księgarnia Techniczna Anna Kowalska', '5260250995', '118/2026', '2026-11-21', [['Książka: Domain-Driven Design', '2', 'szt.', '129.00', VatRate::Rate5]]],
            ['Hosting24 sp. z o.o.', '5252248481', 'H24/11/2026/77', '2026-11-18', [['Serwer VPS (listopad)', '1', 'mies.', '89.00', VatRate::Rate23], ['Domena .pl (rok)', '1', 'szt.', '49.00', VatRate::Rate23]]],
            ['Biuro Rachunkowe Nowak', '5213003700', 'BR/2026/10/12', '2026-11-05', [['Prowadzenie ksiąg (październik)', '1', 'mies.', '650.00', VatRate::Rate23]]],
            ['Stacja Paliw Orlen Nr 214', '7740001454', 'FS/214/0098321', '2026-11-02', [['Paliwo ON', '48.3', 'l', '6.12', VatRate::Rate23]]],
        ];

        foreach ($suppliers as $i => [$name, $nip, $number, $date, $lines]) {
            $builder = SdkInvoice::builder()->number($number)->issueDate($date)
                ->seller(new Seller(Nip::of($nip), $name, Address::poland('ul. Przykładowa '.($i + 3), '00-950 Warszawa')))
                ->buyer(new Buyer(BuyerIdentifier::nip(Nip::of($company->nip)), $company->name, Address::poland((string) $company->address_line1, $company->address_line2)));
            foreach ($lines as [$item, $qty, $unit, $price, $rate]) {
                $builder->addLine(InvoiceLine::of($item, $qty, $unit, $price, $rate));
            }
            $invoice = $builder->build();
            $xml = InvoiceDocument::fromInvoice($invoice)->xml;

            $company->receivedInvoices()->updateOrCreate(
                ['ksef_number' => sprintf('%s-%s-%012X-%02X', $nip, str_replace('-', '', $date), 0x4A1C00 + $i * 977, 10 + $i)],
                [
                    'xml' => $xml, 'number' => $number, 'type' => 'VAT', 'issue_date' => $date, 'seller_name' => $name, 'seller_nip' => $nip,
                    'currency' => 'PLN', 'gross' => $invoice->totals()->gross()->toString(2), 'received_at' => now()->subDays(max(0, 3 - $i)),
                    'read_at' => $i >= 2 ? now()->subDays(1) : null,
                ],
            );
        }

        $company->forceFill(['purchases_synced_until' => now(), 'purchases_synced_at' => now()->subMinutes(7)])->save();
    }
}
