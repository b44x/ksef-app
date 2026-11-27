<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
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
            'auth_method' => 'manual',
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
}
