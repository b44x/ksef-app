<?php

namespace Tests\Feature;

use App\Invoicing\IssueInvoice;
use App\Ksef\KsefGateway;
use App\Mail\InvoiceMail;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class InvoiceEmailTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->app->instance(KsefGateway::class, new FakeGateway);
        $this->company = Company::create(['name' => 'Seller sp. z o.o.', 'nip' => '5265877635', 'email' => 'faktury@seller.example', 'address_line1' => 'x', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
    }

    private function issued(): Invoice
    {
        $buyer = $this->company->contractors()->create(['nip' => '7762811692', 'name' => 'Buyer', 'email' => 'ksiegowosc@buyer.example', 'country' => 'PL', 'address_line1' => 'y']);
        $series = $this->company->numberingSeries()->create(['name' => 'S', 'format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
        $invoice = $this->company->invoices()->create(['contractor_id' => $buyer->id, 'numbering_series_id' => $series->id, 'issue_date' => '2026-12-01']);
        $invoice->lines()->create(['position' => 1, 'name' => 'Usługa', 'unit' => 'szt.', 'quantity' => '1', 'unit_net_price' => '100', 'vat_rate' => '23']);

        return app(IssueInvoice::class)($invoice);
    }

    /**
     * @return array<string, string>
     */
    private function payload(string $to = 'ksiegowosc@buyer.example'): array
    {
        return ['to' => $to, 'subject' => 'Faktura FV/001/2026', 'message' => "Dzień dobry,\n\nfaktura w załączeniu.", 'lang' => 'pl'];
    }

    public function test_the_invoice_is_mailed_as_a_pdf_and_the_sending_is_remembered(): void
    {
        Mail::fake();
        $invoice = $this->issued();

        $this->post("/invoices/{$invoice->id}/email", $this->payload())->assertSessionHasNoErrors();

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail): bool {
            $attachment = $mail->attachments()[0];

            return $mail->hasTo('ksiegowosc@buyer.example')
                && $mail->hasReplyTo('faktury@seller.example')
                && $mail->mailSubject === 'Faktura FV/001/2026'
                && str_starts_with($mail->render(), '<!DOCTYPE html>')
                && $attachment->as === 'FV-001-2026.pdf';
        });
        $this->assertSame(['ksiegowosc@buyer.example'], $invoice->emails()->pluck('to')->all());
    }

    public function test_the_page_offers_the_buyers_address_and_lists_what_was_sent(): void
    {
        Mail::fake();
        $invoice = $this->issued();
        $this->post("/invoices/{$invoice->id}/email", $this->payload());

        $this->get("/invoices/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->where('email.to', 'ksiegowosc@buyer.example')
            ->where('email.can_send', true)
            ->where('email.sent.0.to', 'ksiegowosc@buyer.example')
            ->where('email.subject', 'Invoice FV/001/2026 from Seller sp. z o.o.'));
    }

    public function test_a_broken_mail_server_is_reported_and_nothing_is_recorded(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('connection refused'));
        $invoice = $this->issued();

        $this->post("/invoices/{$invoice->id}/email", $this->payload())->assertSessionHasErrors('email');

        $this->assertSame(0, $invoice->emails()->count());
    }

    public function test_a_bad_request_a_draft_and_a_foreign_invoice_are_refused(): void
    {
        Mail::fake();
        $invoice = $this->issued();
        $this->post("/invoices/{$invoice->id}/email", $this->payload('not an address'))->assertSessionHasErrors('to');
        $this->post("/invoices/{$invoice->id}/email", ['lang' => 'de'] + $this->payload())->assertSessionHasErrors('lang');

        $draft = $this->company->invoices()->create(['contractor_id' => $invoice->contractor_id, 'numbering_series_id' => $invoice->numbering_series_id, 'issue_date' => '2026-12-02']);
        $this->post("/invoices/{$draft->id}/email", $this->payload())->assertNotFound();

        $other = Company::create(['name' => 'Other', 'nip' => '7762811692', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
        $foreign = $other->invoices()->create(['contractor_id' => $other->contractors()->create(['name' => 'B', 'country' => 'PL', 'address_line1' => 'z'])->id, 'numbering_series_id' => $other->numberingSeries()->create(['name' => 'S', 'format' => 'X{N}', 'reset' => 'never'])->id, 'issue_date' => '2026-12-01']);
        $foreign->forceFill(['status' => 'issued'])->save();
        $this->post("/invoices/{$foreign->id}/email", $this->payload())->assertNotFound();

        Mail::assertNothingSent();
    }
}
