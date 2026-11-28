<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Invoicing\IssueInvoice;
use App\Jobs\PollInvoiceStatusJob;
use App\Jobs\SendInvoiceJob;
use App\Ksef\KsefGateway;
use App\Ksef\SigningRefused;
use App\Ksef\SigningRequired;
use App\Ksef\SubmissionState;
use App\Models\Company;
use App\Models\KsefToken;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class KsefSignInTest extends TestCase
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
        $this->company = Company::create([
            'name' => 'Seller sp. z o.o.', 'nip' => '5265877635', 'address_line1' => 'ul. Prosta 1', 'address_line2' => '00-001 Warszawa',
            'environment' => 'test', 'auth_method' => 'manual',
        ]);
    }

    /** A login of the kind the SDK keeps in the token store after a signed request was accepted. */
    private function login(string $until = '+7 days'): KsefToken
    {
        return $this->company->tokens()->create(['token_key' => uniqid('k'), 'payload' => 'tokens', 'expires_at' => now()->modify($until)]);
    }

    public function test_the_page_tells_whether_the_company_is_signed_in(): void
    {
        $this->get('/company/signin')->assertInertia(fn (Assert $page) => $page->component('KsefSignIn')->where('manual', true)->where('signedInUntil', null)->where('pendingUntil', null));

        $this->login();
        $this->get('/company/signin')->assertInertia(fn (Assert $page) => $page->where('signedInUntil', fn ($v) => is_string($v)));
    }

    public function test_a_company_with_a_token_has_nothing_to_sign(): void
    {
        $this->company->update(['auth_method' => 'token', 'credentials' => ['token' => 't']]);

        $this->get('/company/signin')->assertInertia(fn (Assert $page) => $page->where('manual', false));
        $this->post('/company/signin/request')->assertNotFound();
        $this->get('/company/signin/request.xml')->assertNotFound();
        $this->post('/company/signin/complete', ['signed_text' => '<x/>'])->assertNotFound();
        $this->delete('/company/signin')->assertNotFound();
    }

    public function test_the_login_document_is_prepared_kept_and_downloaded(): void
    {
        $this->get('/company/signin/request.xml')->assertNotFound();

        $this->post('/company/signin/request')->assertSessionHasNoErrors();

        $response = $this->get('/company/signin/request.xml');
        $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $this->assertSame('<AuthTokenRequest>to sign</AuthTokenRequest>', $response->streamedContent());
        $this->assertStringContainsString('ksef-login-5265877635.xml', (string) $response->headers->get('Content-Disposition'));
        $this->get('/company/signin')->assertInertia(fn (Assert $page) => $page->where('pendingUntil', fn ($v) => is_string($v)));
    }

    public function test_the_signed_file_completes_the_login(): void
    {
        $this->post('/company/signin/request');

        $this->post('/company/signin/complete', ['signed' => UploadedFile::fake()->createWithContent('signed.xml', '<AuthTokenRequest>signed</AuthTokenRequest>')])
            ->assertRedirect(route('signin.show'))->assertSessionHasNoErrors();

        $this->assertSame(['<AuthTokenRequest>signed</AuthTokenRequest>'], $this->ksef->signed);
        $this->assertFalse(Cache::has('ksef-signin-request:'.$this->company->id), 'A challenge is used once.');
    }

    public function test_the_signed_text_can_be_pasted_instead(): void
    {
        $this->post('/company/signin/request');

        $this->post('/company/signin/complete', ['signed_text' => '<AuthTokenRequest>pasted</AuthTokenRequest>'])->assertSessionHasNoErrors();

        $this->assertSame(['<AuthTokenRequest>pasted</AuthTokenRequest>'], $this->ksef->signed);
    }

    public function test_a_refused_signature_is_explained_and_the_document_stays_usable(): void
    {
        $this->post('/company/signin/request');
        $this->ksef->refuseSigning = new SigningRefused('The request carries no enveloped signature: it has not been signed.');

        $this->post('/company/signin/complete', ['signed_text' => '<AuthTokenRequest/>'])
            ->assertSessionHasErrors(['signed' => 'The request carries no enveloped signature: it has not been signed.']);

        $this->assertTrue(Cache::has('ksef-signin-request:'.$this->company->id), 'The person can try again with a correctly signed file.');
    }

    public function test_nothing_to_hand_in_an_expired_document_and_a_huge_file_are_refused(): void
    {
        $this->post('/company/signin/request');
        $this->post('/company/signin/complete', [])->assertSessionHasErrors('signed');
        $this->post('/company/signin/complete', ['signed' => UploadedFile::fake()->create('big.xml', 2048)])->assertSessionHasErrors('signed');

        Cache::forget('ksef-signin-request:'.$this->company->id);
        $this->post('/company/signin/complete', ['signed_text' => '<AuthTokenRequest/>'])->assertSessionHasErrors('signed');

        $this->assertSame([], $this->ksef->signed);
    }

    public function test_signing_out_forgets_the_login(): void
    {
        $this->login();
        $this->post('/company/signin/request');

        $this->delete('/company/signin')->assertRedirect();

        $this->assertSame(0, $this->company->tokens()->count());
        $this->assertTrue($this->company->fresh()->needsSigningIn());
        $this->assertFalse(Cache::has('ksef-signin-request:'.$this->company->id));
    }

    public function test_whether_a_login_is_valid_follows_the_stored_tokens(): void
    {
        $this->assertTrue($this->company->needsSigningIn());
        $this->assertNull($this->company->signedInUntil());

        $this->login('-1 minute');
        $this->assertTrue($this->company->fresh()->needsSigningIn(), 'An expired login does not count.');

        $this->login('+2 days');
        $this->assertFalse($this->company->fresh()->needsSigningIn());
        $this->assertNotNull($this->company->fresh()->signedInUntil());

        $this->company->update(['auth_method' => 'token', 'credentials' => ['token' => 't']]);
        $this->assertFalse($this->company->fresh()->needsSigningIn(), 'A company with a token never waits for a signature.');
        $this->assertNull($this->company->fresh()->signedInUntil());
    }

    public function test_the_top_bar_gets_the_state_of_the_login(): void
    {
        $this->get('/company/signin')->assertInertia(fn (Assert $page) => $page->where('ksefSession.manual', true)->where('ksefSession.active_until', null));

        $this->login();
        $this->get('/company/signin')->assertInertia(fn (Assert $page) => $page->where('ksefSession.active_until', fn ($v) => is_string($v)));

        $this->company->update(['auth_method' => 'token', 'credentials' => ['token' => 't']]);
        $this->get('/company/signin')->assertInertia(fn (Assert $page) => $page->where('ksefSession', null));
    }

    public function test_changing_the_environment_or_the_way_to_sign_in_drops_the_old_login(): void
    {
        $this->login();

        $this->put('/company', [
            'name' => 'Seller sp. z o.o.', 'nip' => '5265877635', 'address_line1' => 'ul. Prosta 1', 'address_line2' => '00-001 Warszawa',
            'environment' => 'demo', 'auth_method' => 'manual',
        ]);

        $this->assertSame(0, $this->company->tokens()->count(), 'A login for TEST is no use on DEMO.');
    }

    public function test_a_company_that_is_signed_in_can_send_and_fetch_and_one_that_is_not_is_sent_to_sign_in(): void
    {
        Queue::fake();
        $buyer = $this->company->contractors()->create(['name' => 'Buyer', 'nip' => '7762811692', 'country' => 'PL', 'address_line1' => 'ul. Długa 2']);
        $series = $this->company->numberingSeries()->create(['name' => 'Sales', 'format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
        $draft = $this->company->invoices()->create(['contractor_id' => $buyer->id, 'numbering_series_id' => $series->id, 'issue_date' => '2026-12-01']);
        $draft->lines()->create(['position' => 1, 'name' => 'x', 'unit' => 'szt.', 'quantity' => '1', 'unit_net_price' => '10.00', 'vat_rate' => '23']);
        $invoice = app(IssueInvoice::class)($draft);

        $this->get("/invoices/{$invoice->id}")->assertInertia(fn (Assert $page) => $page->where('canSend', false)->where('needsSigning', true));
        $this->post("/invoices/{$invoice->id}/send")->assertSessionHasErrors('send');
        $this->post('/purchases/sync')->assertSessionHasErrors('sync');
        Queue::assertNothingPushed();

        $this->login();
        $this->get("/invoices/{$invoice->id}")->assertInertia(fn (Assert $page) => $page->where('canSend', true)->where('needsSigning', false));
        $this->post("/invoices/{$invoice->id}/send")->assertSessionHasNoErrors();
        Queue::assertPushed(SendInvoiceJob::class);
    }

    public function test_an_expired_login_puts_the_invoice_back_and_stops_the_waiting(): void
    {
        $buyer = $this->company->contractors()->create(['name' => 'Buyer', 'nip' => '7762811692', 'country' => 'PL', 'address_line1' => 'ul. Długa 2']);
        $series = $this->company->numberingSeries()->create(['name' => 'Sales', 'format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
        $draft = $this->company->invoices()->create(['contractor_id' => $buyer->id, 'numbering_series_id' => $series->id, 'issue_date' => '2026-12-01']);
        $draft->lines()->create(['position' => 1, 'name' => 'x', 'unit' => 'szt.', 'quantity' => '1', 'unit_net_price' => '10.00', 'vat_rate' => '23']);
        $invoice = app(IssueInvoice::class)($draft);
        $this->ksef->needsSigning = new SigningRequired;

        $invoice->forceFill(['status' => InvoiceStatus::Sending])->save();
        (new SendInvoiceJob($invoice->id))->handle($this->ksef);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertStringContainsString('Sign in to KSeF first', (string) $invoice->ksef_error);

        Queue::fake();
        $invoice->forceFill(['status' => InvoiceStatus::Sending, 'ksef_session_reference' => 's', 'ksef_invoice_reference' => 'i', 'ksef_invoice_hash' => 'h', 'ksef_error' => null])->save();
        (new PollInvoiceStatusJob($invoice->id))->handle($this->ksef);
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Sending, $invoice->status);
        $this->assertStringContainsString('Sign in to KSeF first', (string) $invoice->ksef_error);
        Queue::assertNothingPushed();
        $this->assertNotSame(SubmissionState::Accepted->value, $invoice->status->value);
    }
}
