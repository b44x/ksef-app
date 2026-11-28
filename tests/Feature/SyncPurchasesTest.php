<?php

namespace Tests\Feature;

use App\Console\Commands\SyncPurchasesCommand;
use App\Jobs\SyncPurchasesJob;
use App\Ksef\KsefGateway;
use App\Ksef\PurchaseBatch;
use App\Ksef\PurchaseDocument;
use App\Models\Company;
use App\Receiving\SyncPurchases;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Support\FakeGateway;
use Tests\Support\PurchaseXml;
use Tests\TestCase;

class SyncPurchasesTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private FakeGateway $ksef;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ksef = new FakeGateway;
        $this->app->instance(KsefGateway::class, $this->ksef);
        $this->company = Company::create(['name' => 'Seller sp. z o.o.', 'nip' => '5265877635', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
    }

    private function document(int $seed = 1, string $number = 'FV/12/2026'): PurchaseDocument
    {
        return new PurchaseDocument(PurchaseXml::ksefNumber($seed), PurchaseXml::make($number));
    }

    private function sync(?DateTimeImmutable $since = null): int
    {
        return app(SyncPurchases::class)($this->company->fresh(), $since);
    }

    public function test_the_first_synchronisation_stores_the_invoices_and_remembers_where_it_stopped(): void
    {
        $until = new DateTimeImmutable('2026-11-29 10:00:00');
        $this->ksef->purchaseBatches = [new PurchaseBatch([$this->document(1)], $until)];

        $new = $this->sync(new DateTimeImmutable('2026-10-01'));

        $this->assertSame(1, $new);
        $invoice = $this->company->receivedInvoices()->firstOrFail();
        $this->assertSame(PurchaseXml::ksefNumber(1), $invoice->ksef_number);
        $this->assertSame(['FV/12/2026', 'VAT', 'Dostawca sp. z o.o.', '7762811692', 'PLN'], [$invoice->number, $invoice->type, $invoice->seller_name, $invoice->seller_nip, $invoice->currency]);
        $this->assertSame('2026-11-20', $invoice->issue_date->toDateString());
        $this->assertSame('349.95', $invoice->gross, 'The total is the issuer\'s: 200.00 + 46.00 and 99.00 + 4.95.');
        $this->assertNull($invoice->read_at);
        $this->assertSame('2026-10-01', $this->ksef->purchaseFrom[0]->format('Y-m-d'), 'The first synchronisation starts where the user said.');
        $company = $this->company->fresh();
        $this->assertSame('2026-11-29 10:00:00', $company->purchases_synced_until->format('Y-m-d H:i:s'));
        $this->assertNotNull($company->purchases_synced_at);
        $this->assertNull($company->purchases_sync_error);
    }

    public function test_the_next_run_continues_from_the_stored_place_and_stores_nothing_twice(): void
    {
        $until = new DateTimeImmutable('2026-11-29 10:00:00');
        $this->ksef->purchaseBatches = [new PurchaseBatch([$this->document(1)], $until), new PurchaseBatch([$this->document(1), $this->document(2, 'FV/13/2026')], $until->modify('+1 hour'))];

        $this->assertSame(1, $this->sync(new DateTimeImmutable('2026-10-01')));
        $this->assertSame(1, $this->sync(new DateTimeImmutable('2020-01-01')), 'Only the second invoice is new; the "since" is ignored once there is a place to continue from.');

        $this->assertSame(2, $this->company->receivedInvoices()->count());
        $this->assertSame('2026-11-29 10:00:00', $this->ksef->purchaseFrom[1]->format('Y-m-d H:i:s'));
    }

    public function test_a_large_backlog_is_fetched_in_several_exports(): void
    {
        $cut = new DateTimeImmutable('2026-11-10');
        $end = new DateTimeImmutable('2026-11-29');
        $this->ksef->purchaseBatches = [
            new PurchaseBatch([$this->document(1)], $cut, $cut),
            new PurchaseBatch([$this->document(2, 'FV/2/2026')], $end),
        ];

        $this->assertSame(2, $this->sync(new DateTimeImmutable('2026-10-01')));

        $this->assertSame('2026-11-10', $this->ksef->purchaseFrom[1]->format('Y-m-d'), 'The second export starts where KSeF cut the first.');
        $this->assertSame('2026-11-29', $this->company->fresh()->purchases_synced_until->format('Y-m-d'));
    }

    public function test_a_document_that_cannot_be_read_does_not_stop_the_others(): void
    {
        $this->ksef->purchaseBatches = [new PurchaseBatch([new PurchaseDocument(PurchaseXml::ksefNumber(9), '<not-an-invoice/>'), $this->document(1)], new DateTimeImmutable('2026-11-29'))];

        $this->assertSame(1, $this->sync(new DateTimeImmutable('2026-10-01')));
        $this->assertSame([PurchaseXml::ksefNumber(1)], $this->company->receivedInvoices()->pluck('ksef_number')->all());
    }

    public function test_a_failure_is_noted_and_the_place_does_not_move(): void
    {
        $this->company->forceFill(['purchases_synced_until' => '2026-11-01 00:00:00'])->save();
        $this->ksef->purchasesFail = new RuntimeException('KSeF said no');

        try {
            $this->sync();
            $this->fail('The failure should be rethrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('KSeF said no', $e->getMessage());
        }

        $company = $this->company->fresh();
        $this->assertSame('KSeF said no', $company->purchases_sync_error);
        $this->assertSame('2026-11-01 00:00:00', $company->purchases_synced_until->format('Y-m-d H:i:s'));
    }

    public function test_the_job_swallows_the_failure_and_clears_the_running_flag(): void
    {
        $this->ksef->purchasesFail = new RuntimeException('KSeF said no');
        Cache::put(SyncPurchasesJob::runningKey($this->company->id), true);

        (new SyncPurchasesJob($this->company->id, '2026-10-01'))->handle(app(SyncPurchases::class));

        $this->assertFalse(Cache::has(SyncPurchasesJob::runningKey($this->company->id)));
        $this->assertSame('KSeF said no', $this->company->fresh()->purchases_sync_error);
    }

    public function test_the_scheduled_command_queues_only_companies_that_started_and_can_log_in(): void
    {
        Queue::fake();
        $started = $this->company;
        $started->forceFill(['purchases_synced_until' => now()])->save();
        Company::create(['name' => 'Never synced', 'nip' => '7762811692', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
        Company::create(['name' => 'By hand', 'nip' => '1234563218', 'environment' => 'test', 'auth_method' => 'manual', 'purchases_synced_until' => now()]);

        $this->artisan(SyncPurchasesCommand::class)->assertSuccessful();

        Queue::assertPushed(SyncPurchasesJob::class, 1);
        Queue::assertPushed(SyncPurchasesJob::class, fn (SyncPurchasesJob $job) => $job->companyId === $started->id);
    }
}
