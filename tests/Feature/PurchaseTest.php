<?php

namespace Tests\Feature;

use App\Jobs\SyncPurchasesJob;
use App\Models\Company;
use App\Models\ReceivedInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\PurchaseXml;
use Tests\TestCase;

class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->company = Company::create(['name' => 'Seller sp. z o.o.', 'nip' => '5265877635', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
    }

    private function purchase(int $seed, string $number, string $seller = 'Dostawca sp. z o.o.', string $nip = '7762811692', string $date = '2026-11-20', ?Company $company = null): ReceivedInvoice
    {
        $xml = PurchaseXml::make($number, $seller, $nip, date: $date);

        return ($company ?? $this->company)->receivedInvoices()->create([
            'ksef_number' => PurchaseXml::ksefNumber($seed), 'xml' => $xml, 'number' => $number, 'type' => 'VAT', 'issue_date' => $date,
            'seller_name' => $seller, 'seller_nip' => $nip, 'currency' => 'PLN', 'gross' => '349.95', 'received_at' => now(),
        ]);
    }

    public function test_the_list_shows_the_newest_first_and_marks_what_has_not_been_opened(): void
    {
        $this->purchase(1, 'FV/1/2026', date: '2026-11-01');
        $newer = $this->purchase(2, 'FV/2/2026', date: '2026-11-15');

        $this->get('/purchases')->assertInertia(fn (Assert $page) => $page
            ->component('Purchases')
            ->has('invoices.data', 2)
            ->where('invoices.data.0.number', 'FV/2/2026')
            ->where('invoices.data.0.is_new', true)
            ->where('sync.started', false));

        $this->get("/purchases/{$newer->id}");
        $this->get('/purchases')->assertInertia(fn (Assert $page) => $page->where('invoices.data.0.is_new', false)->where('invoices.data.1.is_new', true));
    }

    public function test_the_list_can_be_searched_and_filtered(): void
    {
        $this->purchase(1, 'FV/1/2026', 'Papierex', '7762811692', '2026-10-05');
        $this->purchase(2, 'FV/2/2026', 'Książki 100%', '5260250995', '2026-11-05');

        $count = fn (string $query) => $this->get('/purchases?'.$query)->viewData('page')['props']['invoices']['data'];

        $this->assertCount(1, $count('q=papier'));
        $this->assertCount(1, $count('q=5260250995'), 'By the seller\'s NIP.');
        $this->assertCount(1, $count('q='.urlencode('FV/2')), 'By the invoice number.');
        $this->assertCount(1, $count('q='.urlencode('100%')), 'A percent sign is a character, not a wildcard.');
        $this->assertCount(0, $count('q=_'), 'An underscore is not a wildcard either.');
        $this->assertCount(0, $count('q=zzz'));
        $this->assertCount(1, $count('from=2026-11-01'));
        $this->assertCount(1, $count('to=2026-10-31'));
        $this->assertCount(2, $count('from=2026-10-01&to=2026-11-30'));
    }

    public function test_only_this_companys_invoices_are_listed_and_reachable(): void
    {
        $other = Company::create(['name' => 'Other', 'nip' => '7762811692', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 'x']]);
        $theirs = $this->purchase(5, 'FV/9/2026', company: $other);

        $this->get('/purchases')->assertInertia(fn (Assert $page) => $page->has('invoices.data', 0));
        $this->get("/purchases/{$theirs->id}")->assertNotFound();
        $this->get("/purchases/{$theirs->id}/xml")->assertNotFound();
    }

    public function test_the_page_shows_the_invoice_as_the_issuer_wrote_it(): void
    {
        $invoice = $this->purchase(1, 'FV/12/2026');

        $this->get("/purchases/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->component('PurchaseShow')
            ->where('invoice.ksef_number', PurchaseXml::ksefNumber(1))
            ->where('details.number', 'FV/12/2026')
            ->where('details.seller.name', 'Dostawca sp. z o.o.')
            ->where('details.seller.tax_id', '7762811692')
            ->where('details.buyer.name', 'Seller sp. z o.o.')
            ->where('details.total', '349.95')
            ->has('details.lines', 2)
            ->where('details.lines.0.name', 'Papier A4')
            ->where('details.lines.0.net', '200.00')
            ->where('details.tax_summary.0.tax', '46.00')
            ->where('problem', null));
    }

    public function test_a_document_this_version_cannot_read_is_still_there_to_download(): void
    {
        $invoice = $this->purchase(1, 'FV/12/2026');
        $invoice->forceFill(['xml' => '<broken/>'])->save();

        $this->get("/purchases/{$invoice->id}")->assertInertia(fn (Assert $page) => $page->where('details', null)->where('problem', fn ($p) => is_string($p) && $p !== ''));
        $this->get("/purchases/{$invoice->id}/xml")->assertOk();
    }

    public function test_the_xml_is_downloaded_as_stored(): void
    {
        $invoice = $this->purchase(1, 'FV/12/2026');

        $response = $this->get("/purchases/{$invoice->id}/xml");

        $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=utf-8');
        $this->assertSame($invoice->xml, $response->streamedContent());
        $this->assertStringContainsString(PurchaseXml::ksefNumber(1).'.xml', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_the_first_fetch_takes_the_date_and_later_ones_ignore_it(): void
    {
        Queue::fake();

        $this->post('/purchases/sync', ['since' => '2026-10-01'])->assertSessionHasNoErrors();
        Queue::assertPushed(SyncPurchasesJob::class, fn (SyncPurchasesJob $job) => $job->since === '2026-10-01');

        // The first job "finished": neither the running flag nor the one-job-per-company lock is held any more.
        Cache::forget(SyncPurchasesJob::runningKey($this->company->id));
        Cache::lock('laravel_unique_job:'.SyncPurchasesJob::class.':'.$this->company->id)->forceRelease();
        $this->company->forceFill(['purchases_synced_until' => now()])->save();
        $this->post('/purchases/sync', ['since' => '2026-01-01']);
        Queue::assertPushed(SyncPurchasesJob::class, fn (SyncPurchasesJob $job) => $job->since === null);
    }

    public function test_a_fetch_that_is_running_is_not_started_again_and_bad_dates_are_refused(): void
    {
        Queue::fake();

        $this->post('/purchases/sync')->assertSessionHasNoErrors();
        $this->post('/purchases/sync');
        Queue::assertPushed(SyncPurchasesJob::class, 1);

        Cache::forget(SyncPurchasesJob::runningKey($this->company->id));
        $this->post('/purchases/sync', ['since' => '2999-01-01'])->assertSessionHasErrors('since');
        $this->post('/purchases/sync', ['since' => 'yesterday'])->assertSessionHasErrors('since');
    }

    public function test_a_company_that_signs_in_by_hand_cannot_fetch_yet(): void
    {
        Queue::fake();
        $this->company->update(['auth_method' => 'manual', 'credentials' => null]);

        $this->post('/purchases/sync')->assertSessionHasErrors('sync');

        Queue::assertNothingPushed();
    }

    public function test_the_number_of_unread_purchases_is_on_every_page(): void
    {
        $this->purchase(1, 'FV/1/2026');
        $second = $this->purchase(2, 'FV/2/2026');
        $second->forceFill(['read_at' => now()])->save();

        $this->get('/purchases')->assertInertia(fn (Assert $page) => $page->where('unreadPurchases', 1));
    }
}
