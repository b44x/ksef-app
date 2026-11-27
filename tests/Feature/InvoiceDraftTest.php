<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Contractor;
use App\Models\Invoice;
use App\Models\NumberingSeries;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceDraftTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Contractor $buyer;

    private NumberingSeries $series;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->company = Company::create([
            'name' => 'Seller sp. z o.o.', 'nip' => '5265877635', 'address_line1' => 'ul. Prosta 1', 'address_line2' => '00-001 Warszawa',
            'environment' => 'test', 'auth_method' => 'manual',
        ]);
        $this->buyer = $this->company->contractors()->create(['name' => 'Buyer', 'nip' => '7762811692', 'country' => 'PL', 'address_line1' => 'ul. Długa 2']);
        $this->series = $this->company->numberingSeries()->create(['name' => 'Sales', 'format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function form(array $override = []): array
    {
        return $override + [
            'contractor_id' => $this->buyer->id,
            'numbering_series_id' => $this->series->id,
            'issue_date' => '2026-12-01',
            'sale_date' => '',
            'due_date' => '2026-12-15',
            'payment_method' => '6',
            'bank_account' => '61109010140000071219812874',
            'notes' => '',
            'lines' => [
                ['name' => 'Consulting', 'unit' => 'godz.', 'quantity' => '10', 'unit_net_price' => '150.00', 'vat_rate' => '23'],
                ['name' => 'Book', 'unit' => 'szt.', 'quantity' => '2', 'unit_net_price' => '19.99', 'vat_rate' => '5'],
            ],
        ];
    }

    public function test_a_draft_is_saved_with_its_lines_and_taken_back_for_editing(): void
    {
        $response = $this->post('/invoices', $this->form());

        $invoice = Invoice::firstOrFail();
        $response->assertRedirect(route('invoices.edit', $invoice));
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertNull($invoice->number, 'A draft has no number: it is taken from the series on issue.');
        $this->assertSame(0, $this->series->fresh()->last_number, 'Saving a draft does not use up a number.');
        $this->assertSame(['Consulting', 'Book'], $invoice->lines->pluck('name')->all());
        $this->assertSame([1, 2], $invoice->lines->pluck('position')->all());

        $this->get(route('invoices.edit', $invoice))->assertInertia(fn (Assert $page) => $page
            ->component('InvoiceForm')
            ->where('invoice.preview_number', 'FV/001/2026')
            ->where('invoice.lines.0.quantity', '10')
            ->where('invoice.lines.1.unit_net_price', '19.99')
            ->where('invoice.problems', []));
    }

    public function test_editing_replaces_the_lines(): void
    {
        $this->post('/invoices', $this->form());
        $invoice = Invoice::firstOrFail();

        $this->put("/invoices/{$invoice->id}", $this->form(['lines' => [['name' => 'Only one', 'unit' => '', 'quantity' => '1', 'unit_net_price' => '5', 'vat_rate' => 'zw']]]))
            ->assertSessionHasNoErrors();

        $this->assertSame(['Only one'], $invoice->fresh()->lines->pluck('name')->all());
        $this->assertSame(1, Invoice::count());
    }

    public function test_the_list_shows_the_gross_total_the_sdk_computes(): void
    {
        $this->post('/invoices', $this->form());

        // 10 x 150.00 at 23% = 1500.00 + 345.00; 2 x 19.99 at 5% = 39.98 + 2.00 (1.999 rounded to the grosz)
        $this->get('/invoices')->assertInertia(fn (Assert $page) => $page->component('Invoices')->where('invoices.0.gross', '1886.98')->where('invoices.0.contractor', 'Buyer'));
    }

    public function test_the_live_totals_come_from_the_sdk(): void
    {
        $this->postJson('/invoices/preview', ['lines' => $this->form()['lines']])
            ->assertOk()
            ->assertJsonPath('net', '1539.98')
            ->assertJsonPath('vat', '347.00')
            ->assertJsonPath('gross', '1886.98')
            ->assertJsonPath('buckets.0.rate', '23')
            ->assertJsonPath('lines', ['1500.00', '39.98']);

        $this->postJson('/invoices/preview', ['lines' => [['name' => 'x', 'quantity' => '0', 'unit_net_price' => '1', 'vat_rate' => '23']]])->assertUnprocessable();
    }

    public function test_input_is_validated(): void
    {
        $line = ['name' => 'X', 'unit' => '', 'quantity' => '1', 'unit_net_price' => '1', 'vat_rate' => '23'];

        $this->post('/invoices', $this->form(['lines' => []]))->assertSessionHasErrors('lines');
        $this->post('/invoices', $this->form(['lines' => [['quantity' => '-1'] + $line]]))->assertSessionHasErrors('lines.0.quantity');
        $this->post('/invoices', $this->form(['lines' => [['unit_net_price' => '1.123456789'] + $line]]))->assertSessionHasErrors('lines.0.unit_net_price');
        $this->post('/invoices', $this->form(['lines' => [['vat_rate' => '19'] + $line]]))->assertSessionHasErrors('lines.0.vat_rate');
        $this->post('/invoices', $this->form(['due_date' => '2026-11-01']))->assertSessionHasErrors('due_date');
        $this->post('/invoices', $this->form(['contractor_id' => 999]))->assertSessionHasErrors('contractor_id');
        $this->assertSame(0, Invoice::count());
    }

    public function test_a_contractor_or_series_of_another_company_is_refused(): void
    {
        $other = Company::create(['name' => 'Other', 'nip' => '7762811692', 'environment' => 'test', 'auth_method' => 'manual']);
        $foreign = $other->contractors()->create(['name' => 'Theirs', 'country' => 'PL', 'address_line1' => 'x']);
        $foreignSeries = $other->numberingSeries()->create(['name' => 'Theirs', 'format' => '{N}', 'reset' => 'never']);

        $this->post('/invoices', $this->form(['contractor_id' => $foreign->id]))->assertSessionHasErrors('contractor_id');
        $this->post('/invoices', $this->form(['numbering_series_id' => $foreignSeries->id]))->assertSessionHasErrors('numbering_series_id');
    }

    public function test_problems_are_reported_in_words(): void
    {
        $this->company->update(['address_line1' => null]);
        $this->post('/invoices', $this->form());

        $this->get(route('invoices.edit', Invoice::firstOrFail()))
            ->assertInertia(fn (Assert $page) => $page->where('invoice.problems.0', 'Fill in the company address first.'));
    }

    public function test_the_sdk_rules_are_applied_to_a_draft(): void
    {
        // The form cannot produce this, but data can arrive another way (an import, an older version): the SDK still checks it.
        $this->company->update(['email' => 'not-an-email']);
        $this->post('/invoices', $this->form());

        $this->get(route('invoices.edit', Invoice::firstOrFail()))
            ->assertInertia(fn (Assert $page) => $page->has('invoice.problems', 1)->where('invoice.problems.0', fn ($problem) => str_contains(strtolower((string) $problem), 'email')));
    }

    public function test_a_draft_can_be_deleted_and_records_of_another_company_cannot_be_touched(): void
    {
        $this->post('/invoices', $this->form());
        $invoice = Invoice::firstOrFail();

        $other = Company::create(['name' => 'Other', 'nip' => '7762811692', 'environment' => 'test', 'auth_method' => 'manual']);
        $theirContractor = $other->contractors()->create(['name' => 'T', 'country' => 'PL', 'address_line1' => 'x']);
        $theirSeries = $other->numberingSeries()->create(['name' => 'T', 'format' => '{N}', 'reset' => 'never']);
        $theirs = $other->invoices()->create(['contractor_id' => $theirContractor->id, 'numbering_series_id' => $theirSeries->id, 'issue_date' => '2026-12-01']);

        $this->delete("/invoices/{$theirs->id}")->assertNotFound();
        $this->get("/invoices/{$theirs->id}/edit")->assertNotFound();

        $this->delete("/invoices/{$invoice->id}")->assertRedirect(route('invoices.index'));
        $this->assertSame(1, Invoice::count());
    }

    public function test_a_contractor_with_invoices_cannot_be_deleted_from_under_them(): void
    {
        $this->post('/invoices', $this->form());

        $this->expectException(QueryException::class);
        $this->buyer->delete();
    }
}
