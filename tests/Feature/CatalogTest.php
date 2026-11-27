<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\NumberingSeries;
use App\Models\Product;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->company = Company::create(['name' => 'Seller', 'nip' => '5265877635', 'environment' => 'test', 'auth_method' => 'manual']);
    }

    public function test_products_are_managed_with_the_sdk_vat_rates(): void
    {
        $this->post('/products', ['name' => 'Consulting', 'unit' => 'godz.', 'net_price' => '150.5', 'vat_rate' => '23'])->assertSessionHasNoErrors();

        $product = Product::first();
        $this->assertSame('150.50', $product->net_price);
        $this->get('/products')->assertInertia(fn (Assert $page) => $page->component('Products')->has('products', 1)->where('vatRates.0', '23')->has('vatRates', 12));

        $this->put("/products/{$product->id}", ['name' => 'Consulting', 'unit' => 'godz.', 'net_price' => '200', 'vat_rate' => 'zw'])->assertSessionHasNoErrors();
        $this->assertSame('zw', $product->fresh()->vat_rate);

        $this->delete("/products/{$product->id}");
        $this->assertSame(0, Product::count());
    }

    public function test_product_input_is_validated(): void
    {
        $base = ['name' => 'X', 'unit' => 'szt.', 'net_price' => '1', 'vat_rate' => '23'];

        $this->post('/products', ['vat_rate' => '19'] + $base)->assertSessionHasErrors('vat_rate');
        $this->post('/products', ['net_price' => '-1'] + $base)->assertSessionHasErrors('net_price');
        $this->post('/products', ['net_price' => '1.234'] + $base)->assertSessionHasErrors('net_price');
        $this->post('/products', ['name' => ''] + $base)->assertSessionHasErrors('name');
        $this->assertSame(0, Product::count());
    }

    public function test_the_first_series_becomes_the_default_and_a_new_default_replaces_it(): void
    {
        $this->post('/series', ['name' => 'Sales', 'format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly'])->assertSessionHasNoErrors();
        $this->post('/series', ['name' => 'Corrections', 'format' => 'KOR/{NNN}/{YYYY}', 'reset' => 'yearly'])->assertSessionHasNoErrors();

        $this->assertTrue(NumberingSeries::where('name', 'Sales')->first()->is_default);
        $this->assertFalse(NumberingSeries::where('name', 'Corrections')->first()->is_default);

        $this->post('/series', ['name' => 'Export', 'format' => 'EX/{N}', 'reset' => 'never', 'is_default' => true]);
        $this->assertSame(['Export'], NumberingSeries::where('is_default', true)->pluck('name')->all());

        $this->get('/series')->assertInertia(fn (Assert $page) => $page->component('NumberingSeries')->has('series', 3)->where('series.0.next', 'KOR/001/'.date('Y')));
    }

    public function test_a_series_pattern_is_validated(): void
    {
        $this->post('/series', ['name' => 'Bad', 'format' => 'FV/{YYYY}', 'reset' => 'yearly'])->assertSessionHasErrors('format');
        $this->post('/series', ['name' => 'Bad', 'format' => 'FV/{NNN}', 'reset' => 'daily'])->assertSessionHasErrors('reset');
    }

    public function test_numbers_are_taken_in_order_and_restart_with_the_period(): void
    {
        $series = $this->company->numberingSeries()->create(['name' => 'Sales', 'format' => 'FV/{NNN}/{MM}/{YYYY}', 'reset' => 'monthly']);

        $this->assertSame('FV/001/12/2026', $series->issue(new DateTimeImmutable('2026-12-30')));
        $this->assertSame('FV/002/12/2026', $series->issue(new DateTimeImmutable('2026-12-31')));
        $this->assertSame('FV/003/12/2026', $series->fresh()->preview(new DateTimeImmutable('2026-12-31')), 'A preview does not take the number.');
        $this->assertSame('FV/001/01/2027', $series->fresh()->preview(new DateTimeImmutable('2027-01-02')));
        $this->assertSame('FV/001/01/2027', $series->issue(new DateTimeImmutable('2027-01-02')), 'A new month starts from 1.');
        $this->assertSame('FV/002/01/2027', $series->fresh()->issue(new DateTimeImmutable('2027-01-03')), 'A stale copy continues, it does not repeat.');
    }

    public function test_records_of_another_company_cannot_be_touched(): void
    {
        $other = Company::create(['name' => 'Other', 'nip' => '7762811692', 'environment' => 'test', 'auth_method' => 'manual']);
        $product = $other->products()->create(['name' => 'Theirs', 'unit' => 'szt.', 'net_price' => '1', 'vat_rate' => '23']);
        $series = $other->numberingSeries()->create(['name' => 'Theirs', 'format' => '{N}', 'reset' => 'never']);

        $this->delete("/products/{$product->id}")->assertNotFound();
        $this->delete("/series/{$series->id}")->assertNotFound();
        $this->assertSame(1, Product::count());
        $this->assertSame(1, NumberingSeries::count());
    }
}
