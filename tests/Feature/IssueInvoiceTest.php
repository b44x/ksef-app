<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IssueInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->company = Company::create([
            'name' => 'Seller sp. z o.o.', 'nip' => '5265877635', 'address_line1' => 'ul. Prosta 1', 'address_line2' => '00-001 Warszawa',
            'environment' => 'test', 'auth_method' => 'manual',
        ]);
    }

    private function draft(string $date = '2026-12-01', ?Company $company = null): Invoice
    {
        $company ??= $this->company;
        $buyer = $company->contractors()->firstOrCreate(['nip' => '7762811692'], ['name' => 'Buyer', 'country' => 'PL', 'address_line1' => 'ul. Długa 2']);
        $series = $company->numberingSeries()->firstOrCreate(['name' => 'Sales'], ['format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
        $invoice = $company->invoices()->create(['contractor_id' => $buyer->id, 'numbering_series_id' => $series->id, 'issue_date' => $date, 'due_date' => '2026-12-15', 'payment_method' => 6]);
        $invoice->lines()->createMany([
            ['position' => 1, 'name' => 'Consulting', 'unit' => 'godz.', 'quantity' => '10', 'unit_net_price' => '150.00', 'vat_rate' => '23'],
            ['position' => 2, 'name' => 'Book', 'unit' => 'szt.', 'quantity' => '2', 'unit_net_price' => '19.99', 'vat_rate' => '5'],
        ]);

        return $invoice;
    }

    public function test_issuing_takes_the_number_and_freezes_a_valid_fa3_document(): void
    {
        $invoice = $this->draft();

        $this->post("/invoices/{$invoice->id}/issue")->assertRedirect(route('invoices.show', $invoice));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame('FV/001/2026', $invoice->number);
        $this->assertNotNull($invoice->issued_at);
        $this->assertStringContainsString('<P_2>FV/001/2026</P_2>', (string) $invoice->xml);
        $this->assertStringContainsString('5265877635', (string) $invoice->xml);
        $this->assertStringContainsString('<P_15>1886.98</P_15>', (string) $invoice->xml, 'The gross total is the SDK\'s.');
        $this->assertSame(1, $this->company->numberingSeries()->first()->last_number);
    }

    public function test_numbers_follow_each_other_and_an_invoice_is_issued_only_once(): void
    {
        $first = $this->draft();
        $second = $this->draft();

        $this->post("/invoices/{$first->id}/issue");
        $this->post("/invoices/{$second->id}/issue");
        $this->assertSame(['FV/001/2026', 'FV/002/2026'], [$first->fresh()->number, $second->fresh()->number]);

        $this->post("/invoices/{$first->id}/issue")->assertSessionHasErrors('issue');
        $this->assertSame(2, $this->company->numberingSeries()->first()->last_number, 'A second attempt takes no number.');
    }

    public function test_a_refused_draft_does_not_use_up_a_number(): void
    {
        $invoice = $this->draft();
        $this->company->update(['address_line1' => null]);

        $this->post("/invoices/{$invoice->id}/issue")->assertSessionHasErrors('issue');

        $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->number);
        $this->assertSame(0, $this->company->numberingSeries()->first()->last_number);
    }

    public function test_a_document_the_schema_rejects_gives_the_number_back(): void
    {
        $invoice = $this->draft();
        // The form cannot produce an empty buyer name, but data can come another way: the schema still decides.
        $invoice->contractor->forceFill(['name' => ''])->save();

        $this->post("/invoices/{$invoice->id}/issue")->assertSessionHasErrors('issue');

        $this->assertSame(0, $this->company->numberingSeries()->first()->last_number, 'The number went back with the transaction.');
        $this->assertSame(InvoiceStatus::Draft, $invoice->fresh()->status);
    }

    public function test_an_issued_invoice_is_frozen(): void
    {
        $invoice = $this->draft();
        $this->post("/invoices/{$invoice->id}/issue");

        $this->get("/invoices/{$invoice->id}/edit")->assertRedirect(route('invoices.show', $invoice));
        $this->delete("/invoices/{$invoice->id}")->assertForbidden();
        $this->put("/invoices/{$invoice->id}", [
            'contractor_id' => $invoice->contractor_id, 'numbering_series_id' => $invoice->numbering_series_id, 'issue_date' => '2026-12-02',
            'lines' => [['name' => 'x', 'quantity' => '1', 'unit_net_price' => '1', 'vat_rate' => '23']],
        ])->assertForbidden();
    }

    public function test_the_issued_invoice_keeps_the_parties_as_they_were(): void
    {
        $invoice = $this->draft();
        $this->post("/invoices/{$invoice->id}/issue");

        $invoice->contractor->update(['name' => 'Renamed later']);
        $this->company->update(['name' => 'Seller renamed']);

        $this->get("/invoices/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->component('InvoiceShow')
            ->where('invoice.number', 'FV/001/2026')
            ->where('invoice.parties.buyer.name', 'Buyer')
            ->where('invoice.parties.seller.name', 'Seller sp. z o.o.')
            ->where('totals.gross', '1886.98')
            ->where('invoice.xml', fn ($xml) => str_contains((string) $xml, "\n") && str_contains((string) $xml, '<P_2>FV/001/2026</P_2>'))
            ->has('invoice.lines', 2));
    }

    public function test_the_xml_can_be_downloaded_and_a_draft_has_none(): void
    {
        $invoice = $this->draft();
        $this->get("/invoices/{$invoice->id}/xml")->assertNotFound();

        $this->post("/invoices/{$invoice->id}/issue");
        $response = $this->get("/invoices/{$invoice->id}/xml");

        $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $this->assertStringContainsString('attachment; filename=FV-001-2026.xml', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame($invoice->fresh()->xml, $response->streamedContent());
    }

    public function test_another_companys_invoice_is_out_of_reach(): void
    {
        $other = Company::create(['name' => 'Other', 'nip' => '7762811692', 'address_line1' => 'x', 'environment' => 'test', 'auth_method' => 'manual']);
        $theirs = $this->draft('2026-12-01', $other);

        $this->post("/invoices/{$theirs->id}/issue")->assertNotFound();
        $this->get("/invoices/{$theirs->id}")->assertNotFound();
        $this->get("/invoices/{$theirs->id}/xml")->assertNotFound();
    }
}
