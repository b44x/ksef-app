<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Invoicing\IssueInvoice;
use App\Ksef\KsefGateway;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\User;
use App\Reports\SalesReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->app->instance(KsefGateway::class, new FakeGateway);
        $this->company = Company::create(['name' => 'Seller', 'nip' => '5265877635', 'address_line1' => 'x', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
    }

    private function sale(string $date, string $buyer, string $price, string $rate = '23', ?string $due = null): Invoice
    {
        $nips = ['7762811692', '5260250995', '2472557490', '1234563218'];
        $contractor = $this->company->contractors()->firstOrCreate(['name' => $buyer], ['nip' => $nips[$this->company->contractors()->count()], 'country' => 'PL', 'address_line1' => 'y']);
        $series = $this->company->numberingSeries()->firstOrCreate(['name' => 'S'], ['format' => 'FV/{NNN}', 'reset' => 'never', 'is_default' => true]);
        $invoice = $this->company->invoices()->create(['contractor_id' => $contractor->id, 'numbering_series_id' => $series->id, 'issue_date' => $date, 'due_date' => $due]);
        $invoice->lines()->create(['position' => 1, 'name' => 'Usługa', 'unit' => 'szt.', 'quantity' => '1', 'unit_net_price' => $price, 'vat_rate' => $rate]);

        return app(IssueInvoice::class)($invoice);
    }

    private function pay(Invoice $invoice, string $amount, string $date): void
    {
        $payment = new Payment(['amount' => $amount, 'paid_on' => $date]);
        $payment->company_id = $this->company->id;
        $invoice->payments()->save($payment);
    }

    public function test_totals_by_rate_and_customers_count_only_real_sales(): void
    {
        $this->sale('2026-11-03', 'Acme', '1000.00');
        $this->sale('2026-11-10', 'Acme', '500.00', '8');
        $this->sale('2026-11-12', 'Beta', '2000.00');
        $rejected = $this->sale('2026-11-15', 'Beta', '9999.00');
        $rejected->forceFill(['status' => InvoiceStatus::Rejected])->save();
        $this->sale('2026-10-30', 'Acme', '777.00');   // outside the period
        $this->company->invoices()->create(['contractor_id' => $this->company->contractors()->first()->id, 'numbering_series_id' => $this->company->numberingSeries()->first()->id, 'issue_date' => '2026-11-20']); // a draft

        $report = new SalesReport($this->company);
        $from = CarbonImmutable::parse('2026-11-01');
        $to = CarbonImmutable::parse('2026-11-30');

        $this->assertSame([['currency' => 'PLN', 'count' => 3, 'net' => '3500.00', 'vat' => '730.00', 'gross' => '4230.00']], $report->totals($from, $to));
        $this->assertSame([['currency' => 'PLN', 'rate' => '23', 'net' => '3000.00', 'vat' => '690.00'], ['currency' => 'PLN', 'rate' => '8', 'net' => '500.00', 'vat' => '40.00']], $report->byRate($from, $to));
        $customers = $report->topCustomers($from, $to);
        $this->assertSame(['Beta', 'Acme'], array_column($customers, 'name'));
        $this->assertSame(['2460.00', '1770.00'], array_column($customers, 'gross'));
    }

    public function test_monthly_sales_fill_empty_months_with_zero(): void
    {
        $this->sale('2026-11-03', 'Acme', '1000.00');
        $this->sale('2026-09-03', 'Acme', '200.00');

        $monthly = (new SalesReport($this->company))->monthly(CarbonImmutable::parse('2026-11-20'), 4);

        $this->assertSame([['2026-08', '0.00'], ['2026-09', '200.00'], ['2026-10', '0.00'], ['2026-11', '1000.00']], array_map(fn ($m) => [$m['month'], $m['net']], $monthly));
    }

    public function test_receivables_are_aged_by_days_past_the_due_date_and_payments_reduce_them(): void
    {
        $today = CarbonImmutable::parse('2026-12-01');
        $this->sale('2026-11-01', 'A', '100.00', due: '2026-12-10');            // not due: 123.00
        $this->sale('2026-10-01', 'B', '100.00', due: '2026-11-20');            // 11 days late: 123.00
        $partly = $this->sale('2026-08-01', 'C', '200.00', due: '2026-09-01');  // 91 days late: 246.00 less 46.00 paid
        $this->pay($partly, '46.00', '2026-09-05');
        $paid = $this->sale('2026-10-01', 'D', '100.00', due: '2026-10-15');
        $this->pay($paid, '123.00', '2026-10-16');

        [$row] = (new SalesReport($this->company))->receivables($today);

        $this->assertSame('446.00', $row['outstanding']);
        $this->assertSame(3, $row['count']);
        $this->assertSame('323.00', $row['overdue']);
        $this->assertSame(2, $row['overdue_count']);
        $this->assertSame(['current' => '123.00', '1-30' => '123.00', '31-60' => '0.00', '61-90' => '0.00', '90+' => '200.00'], $row['ageing']);
    }

    public function test_the_page_and_the_register_download_follow_the_period(): void
    {
        $this->sale('2026-11-03', 'Kowalski; Jan "Jaś"', '1000.00');

        $this->get('/reports?period=custom&from=2026-11-01&to=2026-11-30')->assertInertia(fn (Assert $page) => $page
            ->component('Reports')
            ->where('period.key', 'custom')
            ->where('totals.0.net', '1000.00')
            ->has('monthly', 12));

        $csv = $this->get('/reports/sales.csv?period=custom&from=2026-11-01&to=2026-11-30');
        $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $body = (string) $csv->streamedContent();
        $this->assertStringContainsString('FV/001;2026-11-03;', $body);
        $this->assertStringContainsString('"Kowalski; Jan ""Jaś"""', $body);
        $this->assertStringContainsString(';1000.00;230.00;1230.00;0.00;issued;', $body);

        $empty = $this->get('/reports/sales.csv?period=custom&from=2020-01-01&to=2020-01-31');
        $this->assertSame(1, substr_count((string) $empty->streamedContent(), "\r\n"), 'Only the header.');
    }

    public function test_the_dashboard_shows_the_figures(): void
    {
        $this->sale(now()->toDateString(), 'Acme', '1000.00', due: now()->subDays(3)->toDateString());

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('kpi.sales.0.net', '1000.00')
            ->where('kpi.receivables.0.outstanding', '1230.00')
            ->where('kpi.receivables.0.overdue', '1230.00'));
    }
}
