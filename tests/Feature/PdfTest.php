<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OfflineMode;
use App\Invoicing\IssueInvoice;
use App\Ksef\KsefGateway;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\ReceivedInvoice;
use App\Models\User;
use App\Pdf\InvoicePdf;
use App\Pdf\InvoiceView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeGateway;
use Tests\Support\OfflineCertificates;
use Tests\Support\PurchaseXml;
use Tests\TestCase;

class PdfTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->app->instance(KsefGateway::class, new FakeGateway);

        [$certificate, $key] = OfflineCertificates::make();
        $this->company = Company::create([
            'name' => 'Seller sp. z o.o.', 'nip' => '5265877635', 'address_line1' => 'ul. Prosta 1', 'address_line2' => '00-001 Warszawa',
            'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't'],
        ]);
        $this->company->forceFill(['offline_certificate' => ['certificate' => $certificate, 'private_key' => $key], 'offline_certificate_expires_at' => now()->addYear()])->save();
    }

    private function draft(): Invoice
    {
        $buyer = $this->company->contractors()->create(['nip' => '7762811692', 'name' => 'Buyer sp. z o.o.', 'country' => 'PL', 'address_line1' => 'ul. Długa 2']);
        $series = $this->company->numberingSeries()->create(['name' => 'Sales', 'format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
        $invoice = $this->company->invoices()->create([
            'contractor_id' => $buyer->id, 'numbering_series_id' => $series->id, 'issue_date' => '2026-12-01', 'due_date' => '2026-12-15',
            'payment_method' => 6, 'bank_account' => '61109010140000071219812874', 'notes' => 'Thank you.',
        ]);
        $invoice->lines()->create(['position' => 1, 'name' => 'Konsultacje', 'unit' => 'godz.', 'quantity' => '10', 'unit_net_price' => '150.00', 'vat_rate' => '23']);

        return $invoice;
    }

    private function issued(?OfflineMode $mode = null): Invoice
    {
        return app(IssueInvoice::class)($this->draft(), $mode);
    }

    public function test_the_sales_invoice_shows_the_parties_lines_sums_and_the_total_in_words(): void
    {
        $html = app(InvoicePdf::class)->html(app(InvoiceView::class)->sales($this->issued()), 'pl');

        $this->assertStringContainsString('Faktura VAT FV/001/2026', $html);
        foreach (['Seller sp. z o.o.', 'Buyer sp. z o.o.', '5265877635', '7762811692', 'Konsultacje', '1500.00', '345.00', '1845.00', '61109010140000071219812874', 'Thank you.'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
        $this->assertStringContainsString('tysiąc osiemset czterdzieści pięć złotych', $html);
        $this->assertStringContainsString('Przelew', $html);
    }

    public function test_the_language_is_chosen_per_pdf_and_the_amount_in_words_is_only_for_polish(): void
    {
        $view = app(InvoiceView::class)->sales($this->issued());
        $english = app(InvoicePdf::class)->html($view, 'en');

        $this->assertStringContainsString('VAT invoice FV/001/2026', $english);
        $this->assertStringNotContainsString('tysiąc', $english);
        $this->assertSame('en', app()->getLocale(), 'Rendering restores the language of the request.');
    }

    public function test_an_offline_invoice_carries_both_qr_codes_and_an_accepted_one_the_ksef_number(): void
    {
        $pdf = app(InvoicePdf::class);

        $offline = $pdf->html(app(InvoiceView::class)->sales($this->issued(OfflineMode::Offline24)), 'pl');
        $this->assertStringContainsString('OFFLINE', $offline);
        $this->assertStringContainsString('CERTYFIKAT', $offline);
        $this->assertSame(2, substr_count($offline, 'data:image/svg+xml;base64,'));

        $draft = $this->company->invoices()->first()->replicate(['number', 'xml', 'snapshot', 'offline_mode', 'offline_deadline', 'verification_url', 'issuer_url', 'issued_at'])->forceFill(['status' => InvoiceStatus::Draft]);
        $draft->save();
        $draft->lines()->create(['position' => 1, 'name' => 'x', 'unit' => 'szt.', 'quantity' => '1', 'unit_net_price' => '10', 'vat_rate' => '23']);
        $online = app(IssueInvoice::class)($draft);
        $this->assertStringNotContainsString('data:image/svg+xml', $pdf->html(app(InvoiceView::class)->sales($online), 'pl'), 'Nothing to verify until KSeF accepts it.');

        $number = '5265877635-20261201-4F2A91C07B31-21';
        $online->forceFill(['status' => InvoiceStatus::Accepted, 'ksef_number' => $number])->save();
        $accepted = $pdf->html(app(InvoiceView::class)->sales($online->fresh()), 'pl');
        $this->assertStringContainsString($number, $accepted);
        $this->assertSame(1, substr_count($accepted, 'data:image/svg+xml;base64,'));
    }

    public function test_the_download_is_a_pdf_in_the_asked_language(): void
    {
        $invoice = $this->issued();

        $response = $this->get("/invoices/{$invoice->id}/pdf?lang=en");

        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->get("/invoices/{$invoice->id}/pdf")->assertOk();
        $this->get("/invoices/{$invoice->id}/pdf?lang=de")->assertSessionHasErrors('lang');
    }

    public function test_a_draft_has_no_pdf_and_another_company_cannot_have_it(): void
    {
        $draft = $this->draft();
        $this->get("/invoices/{$draft->id}/pdf")->assertNotFound();

        $issued = app(IssueInvoice::class)($draft);
        $other = Company::create(['name' => 'Other', 'nip' => '7762811692', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
        $foreign = $other->receivedInvoices()->create([
            'ksef_number' => PurchaseXml::ksefNumber(2), 'xml' => PurchaseXml::make('FV/9', 'Dostawca', '5260250995'), 'number' => 'FV/9', 'type' => 'VAT',
            'issue_date' => '2026-11-20', 'seller_name' => 'Dostawca', 'seller_nip' => '5260250995', 'currency' => 'PLN', 'gross' => '1.00', 'received_at' => now(),
        ]);

        $this->get("/invoices/{$issued->id}/pdf")->assertOk();
        $this->get("/purchases/{$foreign->id}/pdf")->assertNotFound();
    }

    public function test_a_purchase_invoice_can_be_printed_with_its_verification_code(): void
    {
        $xml = PurchaseXml::make('FV/7/2026', 'Dostawca sp. z o.o.', '5260250995', date: '2026-11-20');
        $purchase = $this->company->receivedInvoices()->create([
            'ksef_number' => PurchaseXml::ksefNumber(3), 'xml' => $xml, 'number' => 'FV/7/2026', 'type' => 'VAT', 'issue_date' => '2026-11-20',
            'seller_name' => 'Dostawca sp. z o.o.', 'seller_nip' => '5260250995', 'currency' => 'PLN', 'gross' => '349.95', 'received_at' => now(),
        ]);

        $html = app(InvoicePdf::class)->html(app(InvoiceView::class)->purchase($purchase), 'pl');
        $this->assertStringContainsString('FV/7/2026', $html);
        $this->assertStringContainsString('Dostawca sp. z o.o.', $html);
        $this->assertStringContainsString(PurchaseXml::ksefNumber(3), $html);
        $this->assertSame(1, substr_count($html, 'data:image/svg+xml;base64,'));

        $response = $this->get("/purchases/{$purchase->id}/pdf");
        $response->assertOk();
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
        $this->assertInstanceOf(ReceivedInvoice::class, $purchase);
    }
}
