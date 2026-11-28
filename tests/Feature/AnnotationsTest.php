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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class AnnotationsTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->app->instance(KsefGateway::class, new FakeGateway);
        $this->company = Company::create(['name' => 'Seller', 'nip' => '5265877635', 'address_line1' => 'x', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function draft(string $rate = '23', array $attributes = [], ?string $gtu = null): Invoice
    {
        $buyer = $this->company->contractors()->firstOrCreate(['nip' => '7762811692'], ['name' => 'Buyer', 'country' => 'PL', 'address_line1' => 'y']);
        $series = $this->company->numberingSeries()->firstOrCreate(['name' => 'S'], ['format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
        $invoice = $this->company->invoices()->create(['contractor_id' => $buyer->id, 'numbering_series_id' => $series->id, 'issue_date' => '2026-12-01'] + $attributes);
        $invoice->lines()->create(['position' => 1, 'name' => 'Usługa', 'unit' => 'szt.', 'quantity' => '1', 'unit_net_price' => '100', 'vat_rate' => $rate, 'gtu' => $gtu]);

        return $invoice;
    }

    public function test_split_payment_cash_accounting_and_gtu_reach_the_document(): void
    {
        $xml = (string) app(IssueInvoice::class)($this->draft('23', ['split_payment' => true, 'cash_accounting' => true], 'GTU_05'))->xml;

        $this->assertStringContainsString('<P_16>1</P_16>', $xml);
        $this->assertStringContainsString('<P_18A>1</P_18A>', $xml);
        $this->assertStringContainsString('<GTU>GTU_05</GTU>', $xml);
    }

    public function test_an_exempt_line_needs_its_basis_and_it_is_printed(): void
    {
        try {
            app(IssueInvoice::class)($this->draft('zw'));
            $this->fail('An exempt invoice without a basis must be refused.');
        } catch (InvoiceNotReady) {
            $this->addToAssertionCount(1);
        }

        $issued = app(IssueInvoice::class)($this->draft('zw', ['exemption_basis' => 'art. 43 ust. 1 pkt 37 ustawy o VAT']));
        $this->assertStringContainsString('art. 43 ust. 1 pkt 37 ustawy o VAT', (string) $issued->xml);

        $html = app(InvoicePdf::class)->html(app(InvoiceView::class)->sales($issued), 'pl');
        $this->assertStringContainsString('Podstawa zwolnienia z VAT: art. 43 ust. 1 pkt 37 ustawy o VAT', $html);
    }

    public function test_the_form_saves_the_annotations_and_rejects_an_unknown_gtu(): void
    {
        $draft = $this->draft();
        $base = ['contractor_id' => $draft->contractor_id, 'numbering_series_id' => $draft->numbering_series_id, 'issue_date' => '2026-12-01'];
        $line = ['name' => 'Usługa', 'unit' => 'szt.', 'quantity' => '1', 'unit_net_price' => '100', 'vat_rate' => '23'];

        $this->put("/invoices/{$draft->id}", $base + ['split_payment' => true, 'cash_accounting' => true, 'lines' => [$line + ['gtu' => 'GTU_13']]])->assertSessionHasNoErrors();
        $fresh = $draft->fresh();
        $this->assertTrue($fresh->split_payment && $fresh->cash_accounting);
        $this->assertSame('GTU_13', $fresh->lines->first()->gtu);

        $this->put("/invoices/{$draft->id}", $base + ['lines' => [$line + ['gtu' => 'GTU_99']]])->assertSessionHasErrors('lines.0.gtu');
        $this->put("/invoices/{$draft->id}", $base + ['lines' => [$line + ['gtu' => '']]])->assertSessionHasNoErrors();
        $this->assertNull($draft->fresh()->lines->first()->gtu);
    }

    public function test_the_company_can_default_to_cash_accounting_and_copies_keep_the_annotations(): void
    {
        $this->put('/company/defaults', ['default_cash_accounting' => true])->assertSessionHasNoErrors();
        $this->get('/invoices/create')->assertInertia(fn (Assert $page) => $page->where('defaults.cash_accounting', true)->has('gtuCodes', 13));

        $issued = app(IssueInvoice::class)($this->draft('23', ['split_payment' => true], 'GTU_01'));
        $this->post("/invoices/{$issued->id}/duplicate");
        $copy = $this->company->invoices()->latest('id')->firstOrFail();
        $this->assertTrue($copy->split_payment);
        $this->assertSame('GTU_01', $copy->lines->first()->gtu);
    }
}
