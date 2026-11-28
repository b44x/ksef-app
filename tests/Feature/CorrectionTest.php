<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Invoicing\InvoiceNotReady;
use App\Invoicing\IssueInvoice;
use App\Ksef\KsefGateway;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use App\Pdf\InvoicePdf;
use App\Pdf\InvoiceView;
use App\Reports\SalesReport;
use B4x\Ksef\Support\KsefNumber;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class CorrectionTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->app->instance(KsefGateway::class, new FakeGateway);
        $this->company = Company::create(['name' => 'Seller', 'nip' => '5265877635', 'address_line1' => 'ul. Prosta 1', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
    }

    /** An invoice for 10 x 100.00 + 23% that KSeF has accepted. */
    private function accepted(): Invoice
    {
        $buyer = $this->company->contractors()->firstOrCreate(['nip' => '7762811692'], ['name' => 'Buyer', 'country' => 'PL', 'address_line1' => 'y']);
        $series = $this->company->numberingSeries()->firstOrCreate(['name' => 'S'], ['format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
        $invoice = $this->company->invoices()->create(['contractor_id' => $buyer->id, 'numbering_series_id' => $series->id, 'issue_date' => '2026-11-01', 'due_date' => '2026-11-15']);
        $invoice->lines()->create(['position' => 1, 'name' => 'Consulting', 'unit' => 'godz.', 'quantity' => '10', 'unit_net_price' => '100.00', 'vat_rate' => '23']);
        $issued = app(IssueInvoice::class)($invoice);
        $issued->forceFill(['status' => InvoiceStatus::Accepted, 'ksef_number' => '5265877635-20261101-4F2A91C07B31-'.KsefNumber::crc8('5265877635-20261101-4F2A91C07B31')])->save();

        return $issued->fresh();
    }

    private function draftCorrection(Invoice $original, string $quantity, string $reason = 'Błędna ilość'): Invoice
    {
        $this->post("/invoices/{$original->id}/correct")->assertRedirect();
        $draft = $original->corrections()->where('status', 'draft')->firstOrFail();
        $line = $draft->lines->first();
        $this->put("/invoices/{$draft->id}", [
            'contractor_id' => $draft->contractor_id, 'numbering_series_id' => $draft->numbering_series_id, 'issue_date' => '2026-12-01',
            'correction_reason' => $reason, 'correction_type' => 1,
            'lines' => [['name' => $line->name, 'unit' => 'godz.', 'quantity' => $quantity, 'unit_net_price' => '100.00', 'vat_rate' => '23']],
        ])->assertSessionHasNoErrors();

        return $draft->fresh();
    }

    public function test_only_an_accepted_invoice_can_be_corrected_and_the_draft_starts_from_its_lines(): void
    {
        $original = $this->accepted();
        $this->assertTrue($original->canBeCorrected());
        $original->forceFill(['status' => InvoiceStatus::Issued])->save();
        $this->post("/invoices/{$original->id}/correct")->assertForbidden();
        $original->forceFill(['status' => InvoiceStatus::Accepted])->save();

        $this->post("/invoices/{$original->id}/correct")->assertRedirect();
        $draft = $original->corrections()->firstOrFail();

        $this->assertTrue($draft->isDraft());
        $this->assertSame($original->contractor_id, $draft->contractor_id);
        $this->assertSame(['Consulting'], $draft->lines->pluck('name')->all());
        $this->post("/invoices/{$original->id}/correct");
        $this->assertSame(1, $original->corrections()->count(), 'An unfinished correction is continued, not doubled.');
        $this->assertFalse($draft->canBeCorrected(), 'A correction is not corrected itself.');
    }

    public function test_issuing_builds_a_kor_with_the_before_and_after_lines_and_the_difference_as_totals(): void
    {
        $original = $this->accepted();
        $draft = $this->draftCorrection($original, '8');   // 10 -> 8 pieces: -200.00 net, -46.00 VAT

        $correction = app(IssueInvoice::class)($draft);

        $this->assertSame('FV/002/2026', $correction->number);
        $this->assertStringContainsString('<RodzajFaktury>KOR</RodzajFaktury>', (string) $correction->xml);
        $this->assertStringContainsString('<StanPrzed>1</StanPrzed>', (string) $correction->xml);
        $this->assertStringContainsString('<PrzyczynaKorekty>Błędna ilość</PrzyczynaKorekty>', (string) $correction->xml);
        $this->assertStringContainsString('<NrKSeFFaKorygowanej>', (string) $correction->xml);
        $this->assertSame('-200.00', $correction->net_total);
        $this->assertSame('-46.00', $correction->vat_total);
        $this->assertSame('-246.00', $correction->gross_total);
    }

    public function test_a_second_correction_starts_from_what_the_first_one_left(): void
    {
        $original = $this->accepted();
        app(IssueInvoice::class)($this->draftCorrection($original, '8'));

        $second = $this->draftCorrection($original, '9');   // 8 -> 9: +100.00 net

        $this->assertEquals([8], $second->stateBefore()->pluck('quantity')->all());
        $issued = app(IssueInvoice::class)($second);
        $this->assertSame('100.00', $issued->net_total);
        $this->assertSame('123.00', $issued->gross_total);
    }

    public function test_a_correction_needs_a_reason_and_an_accepted_original(): void
    {
        $original = $this->accepted();
        $this->post("/invoices/{$original->id}/correct");
        $draft = $original->corrections()->firstOrFail();

        try {
            app(IssueInvoice::class)($draft);
            $this->fail('A correction without a reason must be refused.');
        } catch (InvoiceNotReady $e) {
            $this->assertContains('Give the reason for the correction.', $e->problems);
        }

        $draft->forceFill(['correction_reason' => 'x'])->save();
        $original->forceFill(['status' => InvoiceStatus::Rejected])->save();
        $this->expectException(InvoiceNotReady::class);
        app(IssueInvoice::class)($draft->fresh());
    }

    public function test_the_live_totals_of_a_correction_are_the_difference(): void
    {
        $original = $this->accepted();
        $this->post("/invoices/{$original->id}/correct");
        $draft = $original->corrections()->firstOrFail();

        $response = $this->postJson('/invoices/preview', ['invoice_id' => $draft->id, 'lines' => [['name' => 'Consulting', 'quantity' => '12', 'unit_net_price' => '100.00', 'vat_rate' => '23']]]);

        $response->assertOk()->assertJsonPath('net', '200.00')->assertJsonPath('gross', '246.00');
    }

    public function test_the_pdf_of_a_correction_shows_both_states_and_the_reason(): void
    {
        $original = $this->accepted();
        $issued = app(IssueInvoice::class)($this->draftCorrection($original, '8'));

        $html = app(InvoicePdf::class)->html(app(InvoiceView::class)->sales($issued), 'pl');

        $this->assertStringContainsString('Faktura korygująca', $html);
        $this->assertStringContainsString('Przed korektą', $html);
        $this->assertStringContainsString('Po korekcie', $html);
        $this->assertStringContainsString('Dotyczy faktury FV/001/2026 z dnia 2026-11-01', $html);
        $this->assertStringContainsString('Błędna ilość', $html);
        $this->assertStringContainsString('-246.00', $html);
    }

    public function test_the_buyer_of_a_correction_cannot_be_changed_and_reports_count_the_difference(): void
    {
        $original = $this->accepted();
        $other = $this->company->contractors()->create(['name' => 'Else', 'nip' => '5260250995', 'country' => 'PL', 'address_line1' => 'z']);
        $this->post("/invoices/{$original->id}/correct");
        $draft = $original->corrections()->firstOrFail();

        $this->put("/invoices/{$draft->id}", [
            'contractor_id' => $other->id, 'numbering_series_id' => $draft->numbering_series_id, 'issue_date' => '2026-12-01', 'correction_reason' => 'r',
            'lines' => [['name' => 'Consulting', 'unit' => 'godz.', 'quantity' => '8', 'unit_net_price' => '100.00', 'vat_rate' => '23']],
        ])->assertSessionHasNoErrors();
        $this->assertSame($original->contractor_id, $draft->fresh()->contractor_id);

        app(IssueInvoice::class)($draft->fresh())->forceFill(['status' => InvoiceStatus::Accepted])->save();
        $totals = (new SalesReport($this->company))->totals(CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-12-31'));
        $this->assertSame('800.00', $totals[0]['net'], '1000.00 sold, 200.00 taken back.');
    }
}
