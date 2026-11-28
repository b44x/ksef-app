<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Invoicing\IssueInvoice;
use App\Jobs\PollInvoiceStatusJob;
use App\Jobs\SendInvoiceJob;
use App\Ksef\KsefGateway;
use App\Ksef\SubmissionRefused;
use App\Ksef\SubmissionState;
use App\Ksef\SubmissionStatus;
use App\Ksef\SubmissionUncertain;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class SendInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Invoice $invoice;

    private FakeGateway $ksef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->ksef = new FakeGateway;
        $this->app->instance(KsefGateway::class, $this->ksef);

        $this->company = Company::create([
            'name' => 'Seller sp. z o.o.', 'nip' => '5265877635', 'address_line1' => 'ul. Prosta 1', 'address_line2' => '00-001 Warszawa',
            'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 'secret'],
        ]);
        $buyer = $this->company->contractors()->create(['name' => 'Buyer', 'nip' => '7762811692', 'country' => 'PL', 'address_line1' => 'ul. Długa 2']);
        $series = $this->company->numberingSeries()->create(['name' => 'Sales', 'format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
        $draft = $this->company->invoices()->create(['contractor_id' => $buyer->id, 'numbering_series_id' => $series->id, 'issue_date' => '2026-12-01']);
        $draft->lines()->create(['position' => 1, 'name' => 'Consulting', 'unit' => 'godz.', 'quantity' => '10', 'unit_net_price' => '150.00', 'vat_rate' => '23']);
        $this->invoice = app(IssueInvoice::class)($draft);
    }

    /** An invoice that is handed over: the way the send button leaves it. */
    private function sending(): Invoice
    {
        $this->invoice->forceFill(['status' => InvoiceStatus::Sending, 'sent_at' => now()])->save();

        return $this->invoice->fresh();
    }

    /** An invoice KSeF has accepted for processing. */
    private function submitted(): Invoice
    {
        $this->invoice->forceFill([
            'status' => InvoiceStatus::Sending, 'ksef_session_reference' => 's-1', 'ksef_invoice_reference' => 'i-1', 'ksef_invoice_hash' => 'h-1',
        ])->save();

        return $this->invoice->fresh();
    }

    public function test_the_send_button_claims_the_invoice_once_and_queues_the_job(): void
    {
        Queue::fake();

        $this->post("/invoices/{$this->invoice->id}/send")->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(InvoiceStatus::Sending, $this->invoice->fresh()->status);
        Queue::assertPushed(SendInvoiceJob::class, 1);

        // A second click, or a second tab, does not hand it over again.
        $this->post("/invoices/{$this->invoice->id}/send")->assertSessionHasErrors('send');
        Queue::assertPushed(SendInvoiceJob::class, 1);
    }

    public function test_a_company_that_signs_in_by_hand_cannot_send_yet(): void
    {
        Queue::fake();
        $this->company->update(['auth_method' => 'manual', 'credentials' => null]);

        $this->post("/invoices/{$this->invoice->id}/send")->assertSessionHasErrors('send');

        $this->assertSame(InvoiceStatus::Issued, $this->invoice->fresh()->status);
        Queue::assertNothingPushed();
    }

    public function test_the_job_hands_the_frozen_document_over_and_keeps_the_references(): void
    {
        Queue::fake();
        $invoice = $this->sending();

        (new SendInvoiceJob($invoice->id))->handle($this->ksef);

        $this->assertSame([$invoice->xml], $this->ksef->submitted, 'KSeF gets byte for byte the stored document.');
        $invoice->refresh();
        $this->assertSame(['session-1', 'invoice-ref-1'], [$invoice->ksef_session_reference, $invoice->ksef_invoice_reference]);
        $this->assertSame(InvoiceStatus::Sending, $invoice->status);
        Queue::assertPushed(PollInvoiceStatusJob::class);
    }

    public function test_a_second_run_of_the_job_does_not_hand_the_invoice_over_twice(): void
    {
        Queue::fake();
        $invoice = $this->sending();

        (new SendInvoiceJob($invoice->id))->handle($this->ksef);
        (new SendInvoiceJob($invoice->id))->handle($this->ksef);

        $this->assertCount(1, $this->ksef->submitted);
    }

    public function test_a_refusal_puts_the_invoice_back_with_the_reason(): void
    {
        $this->ksef->refuse = new SubmissionRefused('The token was refused.');
        $invoice = $this->sending();

        (new SendInvoiceJob($invoice->id))->handle($this->ksef);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertStringContainsString('The token was refused.', (string) $invoice->ksef_error);
        $this->assertNull($invoice->submission());
    }

    public function test_an_unknown_outcome_is_explained_and_sending_again_is_allowed(): void
    {
        $this->ksef->uncertain = new SubmissionUncertain('timeout');
        $invoice = $this->sending();

        (new SendInvoiceJob($invoice->id))->handle($this->ksef);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertStringContainsString('duplicate', (string) $invoice->ksef_error);

        Queue::fake();
        $this->ksef->uncertain = null;
        $this->post("/invoices/{$invoice->id}/send")->assertSessionHasNoErrors();
        $this->assertSame(InvoiceStatus::Sending, $invoice->fresh()->status);
    }

    public function test_a_crashed_job_does_not_leave_the_invoice_sending_for_ever(): void
    {
        $invoice = $this->sending();

        (new SendInvoiceJob($invoice->id))->failed(new \RuntimeException('boom'));

        $this->assertSame(InvoiceStatus::Issued, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->ksef_error);
    }

    public function test_an_accepted_invoice_gets_its_ksef_number_and_upo(): void
    {
        $invoice = $this->submitted();

        (new PollInvoiceStatusJob($invoice->id))->handle($this->ksef);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Accepted, $invoice->status);
        $this->assertSame('5265877635-20261201-ABCDEF-12', $invoice->ksef_number);
        $this->assertSame('<UPO>signed by the Ministry</UPO>', $invoice->upo_xml);
        $this->assertNotNull($invoice->accepted_at);
    }

    public function test_a_rejection_keeps_the_reasons(): void
    {
        $this->ksef->verdicts = [new SubmissionStatus(SubmissionState::Rejected, null, 450, 'Semantic validation error', ['P_2 is not unique'])];
        $invoice = $this->submitted();

        (new PollInvoiceStatusJob($invoice->id))->handle($this->ksef);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Rejected, $invoice->status);
        $this->assertSame(450, $invoice->ksef_status_code);
        $this->assertStringContainsString('P_2 is not unique', (string) $invoice->ksef_error);
        $this->assertNull($invoice->ksef_number);
    }

    public function test_while_ksef_is_processing_the_job_asks_again_later_and_finally_gives_up_politely(): void
    {
        Queue::fake();
        $this->ksef->verdicts = [new SubmissionStatus(SubmissionState::Processing, null, 150, 'Processing')];
        $invoice = $this->submitted();

        (new PollInvoiceStatusJob($invoice->id))->handle($this->ksef);
        $this->assertSame(1, $invoice->fresh()->poll_attempts);
        Queue::assertPushed(PollInvoiceStatusJob::class, 1);

        $invoice->forceFill(['poll_attempts' => PollInvoiceStatusJob::MAX_ATTEMPTS - 1])->save();
        (new PollInvoiceStatusJob($invoice->id))->handle($this->ksef);

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Sending, $invoice->status, 'Still waiting: not accepted, not rejected.');
        $this->assertStringContainsString('Check status', (string) $invoice->ksef_error);
        Queue::assertPushed(PollInvoiceStatusJob::class, 1);
    }

    public function test_a_failing_question_is_asked_again_and_does_not_decide_anything(): void
    {
        Queue::fake();
        $this->ksef->statusFails = new \RuntimeException('network');
        $invoice = $this->submitted();

        (new PollInvoiceStatusJob($invoice->id))->handle($this->ksef);

        $this->assertSame(InvoiceStatus::Sending, $invoice->fresh()->status);
        Queue::assertPushed(PollInvoiceStatusJob::class, 1);
    }

    public function test_the_verdict_can_be_asked_for_again_by_hand(): void
    {
        Queue::fake();
        $invoice = $this->submitted();
        $invoice->forceFill(['poll_attempts' => 40, 'ksef_error' => 'slow'])->save();

        $this->post("/invoices/{$invoice->id}/check")->assertRedirect();

        $this->assertSame(0, $invoice->fresh()->poll_attempts);
        Queue::assertPushed(PollInvoiceStatusJob::class);

        $this->post("/invoices/{$this->invoice->id}/check")->assertRedirect(); // sending with references: fine
        $other = $this->invoice->fresh();
        $other->forceFill(['status' => InvoiceStatus::Issued])->save();
        $this->post("/invoices/{$other->id}/check")->assertForbidden();
    }

    public function test_the_upo_is_downloaded_and_fetched_when_missing(): void
    {
        $invoice = $this->submitted();
        $invoice->forceFill(['status' => InvoiceStatus::Accepted, 'ksef_number' => '5265877635-20261201-ABCDEF-12'])->save();

        $response = $this->get("/invoices/{$invoice->id}/upo");

        $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $this->assertSame('<UPO>signed by the Ministry</UPO>', $response->streamedContent());
        $this->assertSame('<UPO>signed by the Ministry</UPO>', $invoice->fresh()->upo_xml, 'Kept for next time.');

        $this->ksef->upo = null;
        $invoice->fresh()->forceFill(['upo_xml' => null])->save();
        $this->get("/invoices/{$invoice->id}/upo")->assertSessionHasErrors('send');
    }

    public function test_the_show_page_tells_where_the_invoice_stands(): void
    {
        $invoice = $this->submitted();
        $invoice->forceFill(['status' => InvoiceStatus::Accepted, 'ksef_number' => '5265877635-20261201-ABCDEF-12', 'upo_xml' => '<UPO/>'])->save();

        $this->get("/invoices/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->where('invoice.status', 'accepted')
            ->where('invoice.status_tone', 'success')
            ->where('invoice.ksef.number', '5265877635-20261201-ABCDEF-12')
            ->where('invoice.ksef.has_upo', true)
            ->where('canSend', false));

        $this->invoice->forceFill(['status' => InvoiceStatus::Issued])->save();
        $this->get("/invoices/{$this->invoice->id}")->assertInertia(fn (Assert $page) => $page->where('canSend', true)->where('needsSigning', false));
    }

    public function test_a_rejected_invoice_can_be_copied_to_a_new_draft(): void
    {
        $this->invoice->forceFill(['status' => InvoiceStatus::Rejected, 'ksef_error' => 'refused'])->save();

        $response = $this->post("/invoices/{$this->invoice->id}/duplicate");

        $copy = Invoice::query()->where('id', '!=', $this->invoice->id)->firstOrFail();
        $response->assertRedirect(route('invoices.edit', $copy));
        $this->assertSame(InvoiceStatus::Draft, $copy->status);
        $this->assertNull($copy->number);
        $this->assertSame(['Consulting'], $copy->lines->pluck('name')->all());
        $this->assertNull($copy->xml);
    }

    public function test_another_companys_invoice_cannot_be_sent(): void
    {
        $other = Company::create(['name' => 'Other', 'nip' => '7762811692', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 'x']]);
        $contractor = $other->contractors()->create(['name' => 'T', 'country' => 'PL', 'address_line1' => 'x']);
        $series = $other->numberingSeries()->create(['name' => 'T', 'format' => '{N}', 'reset' => 'never']);
        $theirs = $other->invoices()->create(['contractor_id' => $contractor->id, 'numbering_series_id' => $series->id, 'issue_date' => '2026-12-01', 'status' => InvoiceStatus::Issued]);

        $this->post("/invoices/{$theirs->id}/send")->assertNotFound();
        $this->post("/invoices/{$theirs->id}/duplicate")->assertNotFound();
        $this->get("/invoices/{$theirs->id}/upo")->assertNotFound();
    }
}
