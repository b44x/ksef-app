<?php

namespace Tests\Feature;

use App\Invoicing\IssueInvoice;
use App\Ksef\KsefGateway;
use App\Mail\InvoiceMail;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Pdf\InvoicePdf;
use App\Pdf\InvoiceView;
use App\Reports\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class ProformaTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->app->instance(KsefGateway::class, new FakeGateway);
        $this->company = Company::create(['name' => 'Seller', 'nip' => '5265877635', 'address_line1' => 'x', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
        $buyer = $this->company->contractors()->create(['nip' => '7762811692', 'name' => 'Buyer', 'email' => 'b@buyer.example', 'country' => 'PL', 'address_line1' => 'y']);
        $this->company->numberingSeries()->create(['name' => 'Proformas', 'format' => 'PRO/{NNN}/{YYYY}', 'reset' => 'yearly']);
        $this->company->numberingSeries()->create(['name' => 'Sales', 'format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
        $this->buyerId = $buyer->id;
    }

    private int $buyerId;

    private function proforma(): Invoice
    {
        $this->post('/invoices', [
            'kind' => 'proforma', 'contractor_id' => $this->buyerId, 'numbering_series_id' => $this->company->numberingSeries()->where('name', 'Proformas')->value('id'),
            'issue_date' => '2026-12-01', 'lines' => [['name' => 'Usługa', 'unit' => 'szt.', 'quantity' => '2', 'unit_net_price' => '500.00', 'vat_rate' => '23']],
        ])->assertSessionHasNoErrors();

        return $this->company->invoices()->latest('id')->firstOrFail();
    }

    public function test_a_proforma_is_issued_with_a_number_and_sums_but_no_ksef_document(): void
    {
        $draft = $this->proforma();
        $this->assertTrue($draft->isProforma());

        $issued = app(IssueInvoice::class)($draft);

        $this->assertSame('PRO/001/2026', $issued->number);
        $this->assertNull($issued->xml, 'A proforma is not an FA(3) document.');
        $this->assertSame('1230.00', $issued->gross_total);
        $this->assertFalse($issued->isSale());
        $this->assertTrue($issued->isPrintable());
    }

    public function test_a_proforma_cannot_be_sent_to_ksef_or_paid_and_the_kind_cannot_change(): void
    {
        $issued = app(IssueInvoice::class)($this->proforma());

        $this->post("/invoices/{$issued->id}/send")->assertNotFound();
        $this->post("/invoices/{$issued->id}/payments", ['amount' => '1', 'paid_on' => '2026-12-02'])->assertNotFound();

        $draft = $this->proforma();
        $this->put("/invoices/{$draft->id}", [
            'kind' => 'vat', 'contractor_id' => $this->buyerId, 'numbering_series_id' => $draft->numbering_series_id, 'issue_date' => '2026-12-01',
            'lines' => [['name' => 'Usługa', 'unit' => 'szt.', 'quantity' => '1', 'unit_net_price' => '10', 'vat_rate' => '23']],
        ])->assertSessionHasNoErrors();
        $this->assertSame('proforma', $draft->fresh()->kind);
    }

    public function test_a_proforma_is_not_a_sale_in_reports_or_receivables(): void
    {
        app(IssueInvoice::class)($this->proforma());
        $report = new SalesReport($this->company);

        $this->assertSame([], $report->totals(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31')));
        $this->assertSame([], $report->receivables());
        $this->get('/invoices')->assertInertia(fn (Assert $page) => $page->where('invoices.0.is_proforma', true)->where('invoices.0.payment_status', null));
    }

    public function test_a_proforma_can_be_printed_and_mailed_and_says_it_is_not_an_invoice(): void
    {
        Mail::fake();
        $issued = app(IssueInvoice::class)($this->proforma());

        $html = app(InvoicePdf::class)->html(app(InvoiceView::class)->sales($issued), 'pl');
        $this->assertStringContainsString('Faktura proforma PRO/001/2026', $html);
        $this->assertStringContainsString('To jest faktura proforma. Nie jest fakturą VAT.', $html);
        $this->get("/invoices/{$issued->id}/pdf")->assertOk();

        $this->post("/invoices/{$issued->id}/email", ['to' => 'b@buyer.example', 'subject' => 'Proforma', 'message' => 'Dzień dobry', 'lang' => 'pl'])->assertSessionHasNoErrors();
        Mail::assertSent(InvoiceMail::class);
    }

    public function test_a_vat_invoice_draft_is_made_from_a_proforma_once(): void
    {
        $issued = app(IssueInvoice::class)($this->proforma());

        $this->post("/invoices/{$issued->id}/convert")->assertRedirect();
        $draft = Invoice::query()->where('converted_from_id', $issued->id)->firstOrFail();

        $this->assertFalse($draft->isProforma());
        $this->assertTrue($draft->isDraft());
        $this->assertSame('Sales', $draft->series->name, 'The VAT invoice takes the default series.');
        $this->assertSame(['Usługa'], $draft->lines->pluck('name')->all());

        $this->post("/invoices/{$issued->id}/convert")->assertRedirect(route('invoices.edit', $draft));
        $this->assertSame(1, Invoice::query()->where('converted_from_id', $issued->id)->count());

        $vat = app(IssueInvoice::class)($draft);
        $this->assertSame('FV/001/2026', $vat->number);
        $this->assertNotNull($vat->xml);
    }

    public function test_a_vat_invoice_cannot_be_converted_and_the_form_opens_in_proforma_mode(): void
    {
        $vat = $this->company->invoices()->create(['contractor_id' => $this->buyerId, 'numbering_series_id' => $this->company->numberingSeries()->where('name', 'Sales')->value('id'), 'issue_date' => '2026-12-01']);
        $this->post("/invoices/{$vat->id}/convert")->assertForbidden();

        $this->get('/invoices/create?kind=proforma')->assertInertia(fn (Assert $page) => $page->where('kind', 'proforma'));
        $this->get('/invoices/create')->assertInertia(fn (Assert $page) => $page->where('kind', 'vat'));
    }
}
