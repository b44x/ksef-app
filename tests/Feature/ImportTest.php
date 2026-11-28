<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->company = Company::create(['name' => 'Seller', 'nip' => '5265877635', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
    }

    private function csv(string $content, string $name = 'import.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_contractors_are_imported_from_a_polish_spreadsheet_file(): void
    {
        // Semicolons, Polish headers, a byte order mark: what Excel saves.
        $file = $this->csv("\xEF\xBB\xBFNazwa;NIP;Kraj;Adres;Kod i miasto;E-mail\nAcme Polska sp. z o.o.;776-281-16-92;;ul. Długa 5;80-001 Gdańsk;ksiegowosc@acme.example\nPapierex;5260250995;pl;ul. Krótka 1;;\n");

        $this->post('/contractors/import', ['file' => $file])->assertSessionHas('import', ['created' => 2, 'updated' => 0, 'errors' => []]);

        $acme = $this->company->contractors()->where('nip', '7762811692')->firstOrFail();
        $this->assertSame('Acme Polska sp. z o.o.', $acme->name);
        $this->assertSame('PL', $acme->country, 'No country means Poland.');
        $this->assertSame('80-001 Gdańsk', $acme->address_line2);
        $this->assertSame('PL', $this->company->contractors()->where('nip', '5260250995')->firstOrFail()->country, 'Lower case is fixed.');
    }

    public function test_a_second_import_updates_by_nip_and_reports_the_rows_that_do_not_pass(): void
    {
        $this->company->contractors()->create(['name' => 'Old name', 'nip' => '7762811692', 'country' => 'PL', 'address_line1' => 'Old street']);
        $file = $this->csv("name,nip,country,address,email\nNew name,7762811692,PL,New street 1,\n,5260250995,PL,No name here,\nBad NIP,1234567890,PL,Street,\nNo address,,PL,,\nBad mail,,PL,Street,not-an-address\n");

        $this->post('/contractors/import', ['file' => $file]);
        $result = session('import');

        $this->assertSame(1, $result['updated']);
        $this->assertSame(0, $result['created']);
        $this->assertSame([3, 4, 5, 6], array_column($result['errors'], 'line'), 'Each rejected row is named by its line in the file.');
        $this->assertSame('New name', $this->company->contractors()->where('nip', '7762811692')->firstOrFail()->name);
        $this->assertSame(1, $this->company->contractors()->count());
    }

    public function test_contractors_without_a_nip_are_matched_by_name(): void
    {
        $file = $this->csv("name,address\nJan Kowalski,ul. Prosta 1\n");

        $this->post('/contractors/import', ['file' => $file]);
        $this->post('/contractors/import', ['file' => $file])->assertSessionHas('import.updated', 1);

        $this->assertSame(1, $this->company->contractors()->count());
    }

    public function test_products_accept_polish_numbers_and_rates(): void
    {
        $file = $this->csv("Nazwa;J.m.;Cena netto;Stawka VAT\nKonsultacje IT;godz.;\"1 234,50\";23%\nKsiążka;;79;5\nUsługa zwolniona;szt.;10;ZW\nZły towar;szt.;abc;23\nZła stawka;szt.;10;99\n");

        $this->post('/products/import', ['file' => $file]);
        $result = session('import');

        $this->assertSame(3, $result['created']);
        $this->assertSame([5, 6], array_column($result['errors'], 'line'));
        $product = $this->company->products()->where('name', 'Konsultacje IT')->firstOrFail();
        $this->assertSame('1234.50', $product->net_price);
        $this->assertSame('23', $product->vat_rate);
        $this->assertSame('szt.', $this->company->products()->where('name', 'Książka')->firstOrFail()->unit, 'No unit means pieces.');
        $this->assertSame('zw', $this->company->products()->where('name', 'Usługa zwolniona')->firstOrFail()->vat_rate);
    }

    public function test_a_windows_1250_file_is_read_as_such(): void
    {
        $file = $this->csv((string) iconv('UTF-8', 'CP1250', "Nazwa;Jednostka;Cena;VAT\nŻółć gęślą;szt.;10;23\n"));

        $this->post('/products/import', ['file' => $file])->assertSessionHas('import.created', 1);

        $this->assertSame('Żółć gęślą', $this->company->products()->firstOrFail()->name);
    }

    public function test_a_file_that_is_not_a_table_is_refused_with_the_reason(): void
    {
        $this->post('/products/import', ['file' => $this->csv('')])->assertSessionHasErrors('file');
        $this->post('/products/import', ['file' => $this->csv("foo;bar\n1;2\n")])->assertSessionHasErrors('file');
        $this->post('/products/import', [])->assertSessionHasErrors('file');
        $this->post('/products/import', ['file' => UploadedFile::fake()->create('pic.png', 1)])->assertSessionHasErrors('file');

        $this->assertSame(0, $this->company->products()->count());
    }

    public function test_nothing_leaks_between_companies(): void
    {
        $other = Company::create(['name' => 'Other', 'nip' => '7762811692', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
        $other->contractors()->create(['name' => 'Theirs', 'nip' => '5260250995', 'country' => 'PL', 'address_line1' => 'x']);

        $this->post('/contractors/import', ['file' => $this->csv("name,nip,address\nMine,5260250995,Street 1\n")])->assertSessionHas('import.created', 1);

        $this->assertSame('Theirs', $other->contractors()->firstOrFail()->name);
    }
}
