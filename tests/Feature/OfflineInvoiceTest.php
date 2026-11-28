<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OfflineMode;
use App\Invoicing\InvoiceNotReady;
use App\Invoicing\IssueInvoice;
use App\Jobs\SendInvoiceJob;
use App\Ksef\Availability;
use App\Ksef\AvailabilityMonitor;
use App\Ksef\AvailabilityState;
use App\Ksef\KsefGateway;
use App\Ksef\OfflineCertificateRefused;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeGateway;
use Tests\Support\OfflineCertificates;
use Tests\TestCase;

class OfflineInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private FakeGateway $ksef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->ksef = new FakeGateway;
        $this->app->instance(KsefGateway::class, $this->ksef);

        [$certificate, $key] = OfflineCertificates::make();
        $this->company = Company::create([
            'name' => 'Seller sp. z o.o.', 'nip' => '5265877635', 'address_line1' => 'ul. Prosta 1', 'address_line2' => '00-001 Warszawa',
            'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't'],
        ]);
        // Not mass-assignable on purpose: the key is only ever set by the code that requests the certificate.
        $this->company->forceFill(['offline_certificate' => ['certificate' => $certificate, 'private_key' => $key], 'offline_certificate_expires_at' => now()->addYear()])->save();
    }

    private function draft(string $date = '2026-12-01'): Invoice
    {
        $buyer = $this->company->contractors()->firstOrCreate(['nip' => '7762811692'], ['name' => 'Buyer', 'country' => 'PL', 'address_line1' => 'ul. Długa 2']);
        $series = $this->company->numberingSeries()->firstOrCreate(['name' => 'Sales'], ['format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
        $invoice = $this->company->invoices()->create(['contractor_id' => $buyer->id, 'numbering_series_id' => $series->id, 'issue_date' => $date]);
        $invoice->lines()->create(['position' => 1, 'name' => 'Consulting', 'unit' => 'godz.', 'quantity' => '10', 'unit_net_price' => '150.00', 'vat_rate' => '23']);

        return $invoice;
    }

    private function issueOffline(OfflineMode $mode = OfflineMode::Offline24, ?string $anchor = null, string $date = '2026-12-01'): Invoice
    {
        return app(IssueInvoice::class)($this->draft($date), $mode, $anchor === null ? null : new \DateTimeImmutable($anchor));
    }

    public function test_an_offline_invoice_gets_its_number_document_qr_links_and_a_deadline(): void
    {
        $invoice = $this->issueOffline();

        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertSame(OfflineMode::Offline24, $invoice->offline_mode);
        $this->assertSame('FV/001/2026', $invoice->number);
        $this->assertStringContainsString('<P_2>FV/001/2026</P_2>', (string) $invoice->xml);
        // Tuesday 1 December: the next business day is Wednesday the 2nd, to the end of that day (Polish time).
        $this->assertSame('2026-12-02 23:59:59', $invoice->offline_deadline->setTimezone('Europe/Warsaw')->format('Y-m-d H:i:s'));

        $hash = rtrim(strtr(base64_encode(hash('sha256', (string) $invoice->xml, true)), '+/', '-_'), '=');
        $this->assertSame("https://qr-test.ksef.mf.gov.pl/invoice/5265877635/01-12-2026/{$hash}", $invoice->verification_url, 'KOD I carries the hash of the exact document.');
        $this->assertStringStartsWith('https://qr-test.ksef.mf.gov.pl/certificate/Nip/5265877635/5265877635/', (string) $invoice->issuer_url);
        $this->assertStringContainsString($hash, (string) $invoice->issuer_url);
    }

    public function test_without_a_valid_offline_certificate_nothing_is_issued_and_no_number_is_used(): void
    {
        foreach ([['offline_certificate' => null, 'offline_certificate_expires_at' => null], ['offline_certificate_expires_at' => now()->subDay()]] as $change) {
            $this->company->forceFill($change)->save();
            $draft = $this->draft();

            try {
                app(IssueInvoice::class)($draft->fresh(), OfflineMode::Offline24);
                $this->fail('Issuing offline should be refused.');
            } catch (InvoiceNotReady $e) {
                $this->assertStringContainsString('Offline certificate', $e->getMessage());
            }
            $this->assertSame(0, $this->company->numberingSeries()->first()->last_number);
            $this->assertSame(InvoiceStatus::Draft, $draft->fresh()->status);
        }
    }

    public function test_the_deadline_of_a_failure_counts_from_its_end_and_waits_for_it(): void
    {
        $open = $this->issueOffline(OfflineMode::Emergency);
        $this->assertNull($open->offline_deadline, 'The failure has not ended: there is no deadline yet.');
        $this->assertNull($open->offline_anchor_date);

        // Ended on Tuesday 1 December: seven business days are the 2nd, 3rd, 4th, 7th, 8th, 9th and 10th.
        $ended = $this->issueOffline(OfflineMode::Emergency, '2026-12-01');
        $this->assertSame('2026-12-10', $ended->offline_deadline->setTimezone('Europe/Warsaw')->format('Y-m-d'));

        $unavailable = $this->issueOffline(OfflineMode::Unavailability, '2026-12-04'); // Friday: next business day is Monday
        $this->assertSame('2026-12-07', $unavailable->offline_deadline->setTimezone('Europe/Warsaw')->format('Y-m-d'));
    }

    public function test_the_end_of_a_failure_can_be_entered_later(): void
    {
        $invoice = $this->issueOffline(OfflineMode::Emergency);

        $this->post("/invoices/{$invoice->id}/offline-anchor", ['anchor_date' => '2026-12-01'])->assertSessionHasNoErrors();
        $this->assertSame('2026-12-10', $invoice->fresh()->offline_deadline->setTimezone('Europe/Warsaw')->format('Y-m-d'));

        $this->post("/invoices/{$invoice->id}/offline-anchor", ['anchor_date' => 'soon'])->assertSessionHasErrors('anchor_date');

        $offline24 = $this->issueOffline();
        $this->post("/invoices/{$offline24->id}/offline-anchor", ['anchor_date' => '2026-12-01'])->assertForbidden();
        $online = app(IssueInvoice::class)($this->draft());
        $this->post("/invoices/{$online->id}/offline-anchor", ['anchor_date' => '2026-12-01'])->assertForbidden();
    }

    public function test_the_form_endpoint_issues_offline_with_the_chosen_reason(): void
    {
        $draft = $this->draft();

        $this->post("/invoices/{$draft->id}/issue", ['mode' => 'unavailability', 'anchor_date' => '2026-12-04'])->assertRedirect(route('invoices.show', $draft));

        $this->assertSame(OfflineMode::Unavailability, $draft->fresh()->offline_mode);
        $this->post('/invoices/'.$this->draft()->id.'/issue', ['mode' => 'whenever'])->assertSessionHasErrors('mode');
    }

    public function test_the_qr_codes_are_drawn_for_offline_and_for_accepted_invoices_only(): void
    {
        $offline = $this->issueOffline();
        $this->get("/invoices/{$offline->id}")->assertInertia(fn (Assert $page) => $page
            ->where('offline.mode', 'offline24')
            ->where('offline.overdue', false)
            ->where('qr.verification', fn ($svg) => str_contains((string) $svg, '<svg') && str_contains((string) $svg, '>OFFLINE</text>'))
            ->where('qr.issuer', fn ($svg) => str_contains((string) $svg, '>CERTYFIKAT</text>')));

        $online = app(IssueInvoice::class)($this->draft());
        $this->get("/invoices/{$online->id}")->assertInertia(fn (Assert $page) => $page->where('offline', null)->where('qr', null));

        // Once KSeF has accepted it, KOD I shows the KSeF number; there is no KOD II on an online invoice.
        $online->forceFill(['status' => InvoiceStatus::Accepted, 'ksef_number' => '2472557490-20261128-11583C400000-9D'])->save();
        $this->get("/invoices/{$online->id}")->assertInertia(fn (Assert $page) => $page
            ->where('qr.verification', fn ($svg) => str_contains((string) $svg, '>2472557490-20261128-11583C400000-9D</text>'))
            ->where('qr.issuer', null));
    }

    public function test_an_offline_invoice_is_declared_offline_when_it_is_sent(): void
    {
        Queue::fake();
        $offline = $this->issueOffline();
        $online = app(IssueInvoice::class)($this->draft());

        foreach ([$offline, $online] as $invoice) {
            $invoice->forceFill(['status' => InvoiceStatus::Sending])->save();
            (new SendInvoiceJob($invoice->id))->handle($this->ksef);
        }

        $this->assertSame([true, false], $this->ksef->submittedOffline);
        $this->assertSame([$offline->xml, $online->xml], $this->ksef->submitted, 'KSeF gets the document that was issued, byte for byte.');
    }

    public function test_every_waiting_offline_invoice_can_be_sent_at_once_and_only_once(): void
    {
        Queue::fake();
        $this->issueOffline();
        $this->issueOffline();
        app(IssueInvoice::class)($this->draft()); // online: not part of it

        $this->post('/invoices/send-offline')->assertSessionHasNoErrors();
        Queue::assertPushed(SendInvoiceJob::class, 2);

        $this->post('/invoices/send-offline')->assertSessionHasNoErrors();
        Queue::assertPushed(SendInvoiceJob::class, 2);
        $this->assertSame(2, Invoice::query()->where('status', InvoiceStatus::Sending)->count());
    }

    public function test_the_dashboard_reminds_of_offline_invoices_and_their_deadlines(): void
    {
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('offline', null));

        $this->issueOffline(date: '2026-01-05'); // long overdue
        $this->issueOffline(OfflineMode::Emergency);   // no deadline yet

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('offline.waiting', 2)
            ->where('offline.overdue', 1)
            ->where('offline.undated', 1)
            ->where('offline.next_deadline', fn ($d) => is_string($d)));
    }

    public function test_the_certificate_is_requested_stored_encrypted_and_can_be_removed(): void
    {
        $this->company->forceFill(['offline_certificate' => null, 'offline_certificate_expires_at' => null])->save();

        $this->post('/company/offline-certificate')->assertSessionHasNoErrors();

        $company = $this->company->fresh();
        $this->assertTrue($company->canIssueOffline());
        $this->assertSame(1, $this->ksef->certificatesRequested);
        $raw = (string) DB::table('companies')->where('id', $company->id)->value('offline_certificate');
        $this->assertStringNotContainsString('BEGIN', $raw);
        $this->assertStringNotContainsString('BEGIN', $company->toJson(), 'The key never leaves the model when it is serialised.');
        $this->get('/company')->assertInertia(fn (Assert $page) => $page->where('offline.usable', true)->missing('offline.certificate'));

        $this->delete('/company/offline-certificate');
        $this->assertFalse($this->company->fresh()->canIssueOffline());
    }

    public function test_a_refused_certificate_is_explained(): void
    {
        $this->company->forceFill(['offline_certificate' => null])->save();
        $this->ksef->refuseCertificate = new OfflineCertificateRefused('A login with a signature is needed.');

        $this->post('/company/offline-certificate')->assertSessionHasErrors(['offline']);

        $this->assertFalse($this->company->fresh()->canIssueOffline());
    }

    public function test_the_form_suggests_the_reason_that_fits_what_ksef_is_doing(): void
    {
        $draft = $this->draft();
        $suggested = fn () => $this->get("/invoices/{$draft->id}/edit")->viewData('page')['props']['offline']['suggested'];

        $this->assertSame('offline24', $suggested());

        app(AvailabilityMonitor::class)->refresh($this->company); // available
        $this->ksef->availability = new Availability(AvailabilityState::Failure, [], new \DateTimeImmutable);
        app(AvailabilityMonitor::class)->refresh($this->company);
        $this->assertSame('emergency', $suggested());

        $this->ksef->availability = new Availability(AvailabilityState::Maintenance, [], new \DateTimeImmutable);
        app(AvailabilityMonitor::class)->refresh($this->company);
        $this->assertSame('unavailability', $suggested());
    }
}
