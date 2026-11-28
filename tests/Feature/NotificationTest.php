<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\OfflineMode;
use App\Mail\DigestMail;
use App\Models\Company;
use App\Models\User;
use App\Notifications\BuildDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\PurchaseXml;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->company = Company::create(['name' => 'Seller sp. z o.o.', 'nip' => '5265877635', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function invoice(array $attributes): void
    {
        $buyer = $this->company->contractors()->firstOrCreate(['nip' => '7762811692'], ['name' => 'Buyer', 'country' => 'PL', 'address_line1' => 'x']);
        $series = $this->company->numberingSeries()->firstOrCreate(['name' => 'S'], ['format' => 'FV/{NNN}', 'reset' => 'never', 'is_default' => true]);
        $invoice = $this->company->invoices()->create(['contractor_id' => $buyer->id, 'numbering_series_id' => $series->id, 'issue_date' => '2026-12-01']);
        $invoice->forceFill($attributes)->save();
    }

    private function purchase(int $seed, \DateTimeInterface $receivedAt): void
    {
        $this->company->receivedInvoices()->create([
            'ksef_number' => PurchaseXml::ksefNumber($seed), 'xml' => '<x/>', 'number' => "FV/{$seed}", 'type' => 'VAT', 'issue_date' => '2026-11-20',
            'seller_name' => 'Dostawca', 'seller_nip' => '5260250995', 'currency' => 'PLN', 'gross' => '10.00', 'received_at' => $receivedAt,
        ]);
    }

    public function test_the_digest_lists_offline_deadlines_and_only_what_is_new(): void
    {
        $this->invoice(['number' => 'FV/001', 'status' => InvoiceStatus::Issued, 'offline_mode' => OfflineMode::Offline24, 'offline_deadline' => now()->subDay()]);
        $this->invoice(['number' => 'FV/002', 'status' => InvoiceStatus::Rejected, 'ksef_error' => "Błąd walidacji\nszczegóły"]);
        $this->purchase(1, now()->subDays(3));
        $this->purchase(2, now());
        $this->company->forceFill(['last_digest_at' => now()->subDay()])->save();

        $digest = app(BuildDigest::class)->for($this->company->fresh());
        $this->assertNotNull($digest);
        $this->assertSame([['number' => 'FV/001', 'deadline' => $digest['offline'][0]['deadline'], 'overdue' => true]], $digest['offline']);
        $this->assertSame(['FV/2'], array_column($digest['purchases'], 'number'), 'The purchase received before the last summary is old news.');
        $this->assertSame([['number' => 'FV/002', 'error' => 'Błąd walidacji']], $digest['rejected']);
    }

    public function test_the_digest_respects_the_topics_and_is_empty_when_there_is_nothing_to_tell(): void
    {
        $this->assertNull(app(BuildDigest::class)->for($this->company));

        $this->purchase(1, now());
        $this->company->forceFill(['notify_purchases' => false])->save();
        $this->assertNull(app(BuildDigest::class)->for($this->company->fresh()));
    }

    public function test_the_command_mails_companies_with_an_address_once_and_remembers_when(): void
    {
        Mail::fake();
        $this->purchase(1, now());
        $this->company->forceFill(['notify_email' => 'owner@example.test'])->save();

        $this->artisan('ksef:send-digest')->assertSuccessful();
        Mail::assertSent(DigestMail::class, fn (DigestMail $m) => $m->hasTo('owner@example.test') && ($m->digest['purchases'][0]['number'] ?? null) === 'FV/1');
        $this->assertNotNull($this->company->fresh()->last_digest_at);

        $this->artisan('ksef:send-digest')->assertSuccessful();
        Mail::assertSentCount(1);   // the purchase is not news any more
    }

    public function test_nothing_is_sent_without_an_address(): void
    {
        Mail::fake();
        $this->purchase(1, now());

        $this->artisan('ksef:send-digest')->assertSuccessful();

        Mail::assertNothingSent();
    }

    public function test_a_failing_mail_server_does_not_use_up_the_news(): void
    {
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('connection refused'));
        $this->purchase(1, now());
        $this->company->forceFill(['notify_email' => 'owner@example.test'])->save();

        $this->artisan('ksef:send-digest')->assertSuccessful();

        $this->assertNull($this->company->fresh()->last_digest_at);
    }

    public function test_the_settings_are_saved_and_the_test_message_is_sent(): void
    {
        Mail::fake();
        $this->actingAs(User::factory()->create());

        $this->post('/company/notifications/test')->assertSessionHasErrors('notify');

        $this->put('/company/notifications', ['notify_email' => 'owner@example.test', 'notify_offline' => true, 'notify_purchases' => false, 'notify_rejected' => true])
            ->assertSessionHasNoErrors();
        $company = $this->company->fresh();
        $this->assertSame('owner@example.test', $company->notify_email);
        $this->assertFalse($company->notify_purchases);

        $this->post('/company/notifications/test')->assertSessionHasNoErrors();
        Mail::assertSent(DigestMail::class, fn (DigestMail $m) => $m->digest === null);

        $this->put('/company/notifications', ['notify_email' => 'not an address', 'notify_offline' => true, 'notify_purchases' => true, 'notify_rejected' => true])
            ->assertSessionHasErrors('notify_email');
    }

    public function test_the_mail_is_rendered_in_polish_with_every_section(): void
    {
        app()->setLocale('pl');
        $digest = ['offline' => [['number' => 'FV/1', 'deadline' => '2026-12-02 23:59', 'overdue' => false]], 'purchases' => [['number' => 'FV/9', 'seller' => 'Dostawca', 'gross' => '10.00', 'currency' => 'PLN']], 'rejected' => [['number' => 'FV/2', 'error' => 'Błąd']]];

        $html = (new DigestMail($this->company, $digest))->render();

        foreach (['Faktury offline czekające', 'dostarcz do 2026-12-02 23:59', 'Faktury odrzucone przez KSeF', 'Nowe faktury zakupowe', 'Dostawca'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }
}
