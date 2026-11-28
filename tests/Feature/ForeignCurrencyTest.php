<?php

namespace Tests\Feature;

use App\Invoicing\InvoiceNotReady;
use App\Invoicing\IssueInvoice;
use App\Ksef\KsefGateway;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Pdf\InvoicePdf;
use App\Pdf\InvoiceView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class ForeignCurrencyTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->app->instance(KsefGateway::class, new FakeGateway);
        Cache::flush();
        $this->company = Company::create(['name' => 'Seller', 'nip' => '5265877635', 'address_line1' => 'x', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
    }

    private function draft(string $currency = 'EUR', ?string $rate = '4.4809'): Invoice
    {
        $buyer = $this->company->contractors()->firstOrCreate(['nip' => '7762811692'], ['name' => 'Buyer', 'country' => 'PL', 'address_line1' => 'y']);
        $series = $this->company->numberingSeries()->firstOrCreate(['name' => 'S'], ['format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
        $invoice = $this->company->invoices()->create(['contractor_id' => $buyer->id, 'numbering_series_id' => $series->id, 'issue_date' => '2026-11-26', 'currency' => $currency, 'exchange_rate' => $rate, 'exchange_rate_date' => $rate ? '2026-11-25' : null]);
        $invoice->lines()->create(['position' => 1, 'name' => 'Consulting', 'unit' => 'godz.', 'quantity' => '10', 'unit_net_price' => '100.00', 'vat_rate' => '23']);

        return $invoice;
    }

    public function test_a_euro_invoice_is_issued_with_the_vat_converted_to_zloty(): void
    {
        $invoice = app(IssueInvoice::class)($this->draft());

        $this->assertSame('EUR', $invoice->currency);
        $this->assertSame('1230.00', $invoice->gross_total, 'The amounts stay in euro.');
        $this->assertStringContainsString('<KodWaluty>EUR</KodWaluty>', (string) $invoice->xml);
        $this->assertStringContainsString('<P_14_1W>1030.61</P_14_1W>', (string) $invoice->xml, '230.00 EUR at 4.4809 is 1030.61 PLN.');
    }

    public function test_a_euro_invoice_without_a_rate_cannot_be_issued(): void
    {
        $this->expectException(InvoiceNotReady::class);

        app(IssueInvoice::class)($this->draft('EUR', null));
    }

    public function test_the_form_demands_a_rate_for_a_foreign_currency_and_drops_it_for_zloty(): void
    {
        $draft = $this->draft('PLN', null);
        $line = [['name' => 'Consulting', 'unit' => 'godz.', 'quantity' => '1', 'unit_net_price' => '100', 'vat_rate' => '23']];
        $base = ['contractor_id' => $draft->contractor_id, 'numbering_series_id' => $draft->numbering_series_id, 'issue_date' => '2026-11-26', 'lines' => $line];

        $this->put("/invoices/{$draft->id}", $base + ['currency' => 'EUR'])->assertSessionHasErrors('exchange_rate');
        $this->put("/invoices/{$draft->id}", $base + ['currency' => 'XXX'])->assertSessionHasErrors('currency');
        $this->put("/invoices/{$draft->id}", $base + ['currency' => 'EUR', 'exchange_rate' => '4.4809', 'exchange_rate_date' => '2026-11-25'])->assertSessionHasNoErrors();
        $this->assertSame('4.480900', $draft->fresh()->exchange_rate);

        $this->put("/invoices/{$draft->id}", $base + ['currency' => 'PLN', 'exchange_rate' => '4.4809'])->assertSessionHasNoErrors();
        $this->assertNull($draft->fresh()->exchange_rate, 'A złoty invoice has no rate.');
    }

    public function test_the_live_totals_include_the_vat_in_zloty_once_there_is_a_rate(): void
    {
        $lines = [['name' => 'Consulting', 'quantity' => '10', 'unit_net_price' => '100.00', 'vat_rate' => '23']];

        $this->postJson('/invoices/preview', ['currency' => 'EUR', 'exchange_rate' => '4.4809', 'lines' => $lines])->assertOk()->assertJsonPath('vat_pln', '1030.61')->assertJsonPath('vat', '230.00');
        $this->postJson('/invoices/preview', ['currency' => 'EUR', 'lines' => $lines])->assertOk()->assertJsonPath('vat_pln', null);
        $this->postJson('/invoices/preview', ['lines' => $lines])->assertOk()->assertJsonPath('vat_pln', null);
    }

    public function test_the_pdf_shows_the_rate_and_the_vat_in_zloty(): void
    {
        $invoice = app(IssueInvoice::class)($this->draft());

        $html = app(InvoicePdf::class)->html(app(InvoiceView::class)->sales($invoice), 'pl');

        $this->assertStringContainsString('Średni kurs NBP 4.4809 PLN/EUR z dnia 2026-11-25', $html);
        $this->assertStringContainsString('VAT w PLN: 1030.61', $html);
        $this->assertStringContainsString('(EUR)', $html);
    }

    public function test_the_nbp_rate_is_the_last_one_before_the_day_and_is_remembered(): void
    {
        Http::fake(['api.nbp.pl/*' => Http::response(['rates' => [
            ['no' => '228/A/NBP/2026', 'effectiveDate' => '2026-11-24', 'mid' => 4.4633],
            ['no' => '229/A/NBP/2026', 'effectiveDate' => '2026-11-25', 'mid' => 4.4809],
        ]])]);

        $this->getJson('/rates?currency=EUR&date=2026-11-26')->assertOk()->assertExactJson(['rate' => '4.4809', 'date' => '2026-11-25', 'table' => '229/A/NBP/2026']);
        $this->getJson('/rates?currency=EUR&date=2026-11-26')->assertOk();

        Http::assertSentCount(1);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/rates/a/eur/2026-11-16/2026-11-25/'));
    }

    public function test_the_nbp_failures_and_bad_requests_are_reported(): void
    {
        Http::fake(['api.nbp.pl/*' => Http::sequence()->push('Not found', 404)->pushFailedConnection()]);

        $this->getJson('/rates?currency=EUR&date=2026-11-26')->assertStatus(502);
        $this->getJson('/rates?currency=USD&date=2026-11-26')->assertStatus(502);
        $this->getJson('/rates?currency=PLN&date=2026-11-26')->assertUnprocessable();
        $this->getJson('/rates?currency=EUR&date=yesterday')->assertUnprocessable();
    }
}
