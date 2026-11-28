<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Invoicing\IssueInvoice;
use App\Ksef\KsefGateway;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class PaymentTest extends TestCase
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

    private function draft(string $due = '2049-12-31'): Invoice
    {
        $buyer = $this->company->contractors()->firstOrCreate(['nip' => '7762811692'], ['name' => 'Buyer', 'country' => 'PL', 'address_line1' => 'y']);
        $series = $this->company->numberingSeries()->firstOrCreate(['name' => 'S'], ['format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
        $invoice = $this->company->invoices()->create(['contractor_id' => $buyer->id, 'numbering_series_id' => $series->id, 'issue_date' => '2026-12-01', 'due_date' => $due]);
        $invoice->lines()->create(['position' => 1, 'name' => 'Consulting', 'unit' => 'godz.', 'quantity' => '10', 'unit_net_price' => '100.00', 'vat_rate' => '23']);

        return $invoice;
    }

    private function issued(string $due = '2049-12-31'): Invoice
    {
        return app(IssueInvoice::class)($this->draft($due));
    }

    public function test_issuing_fixes_the_sums_as_the_sdk_computed_them(): void
    {
        $invoice = $this->issued()->fresh();

        $this->assertSame('1000.00', $invoice->net_total);
        $this->assertSame('230.00', $invoice->vat_total);
        $this->assertSame('1230.00', $invoice->gross_total);
        $this->assertSame(PaymentStatus::Unpaid, $invoice->paymentStatus());
        $this->assertSame('1230.00', $invoice->outstanding()->toString(2));
    }

    public function test_payments_move_the_invoice_from_unpaid_to_partly_to_paid(): void
    {
        $invoice = $this->issued();

        $this->post("/invoices/{$invoice->id}/payments", ['amount' => '500', 'paid_on' => '2026-12-05'])->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Partial, $invoice->fresh()->paymentStatus());
        $this->assertSame('730.00', $invoice->fresh()->outstanding()->toString(2));

        $this->post("/invoices/{$invoice->id}/payments", ['amount' => '730,00', 'paid_on' => '2026-12-06'])->assertSessionHasErrors('amount'); // comma: not a number for the server; the form sends a dot
        $this->post("/invoices/{$invoice->id}/payments", ['amount' => '730.00', 'paid_on' => '2026-12-06'])->assertSessionHasNoErrors();

        $this->assertSame(PaymentStatus::Paid, $invoice->fresh()->paymentStatus());
        $this->assertTrue($invoice->fresh()->outstanding()->isZero());
    }

    public function test_more_than_is_left_is_refused_and_so_is_nonsense(): void
    {
        $invoice = $this->issued();

        $this->post("/invoices/{$invoice->id}/payments", ['amount' => '1230.01', 'paid_on' => '2026-12-05'])->assertSessionHasErrors('amount');
        $this->post("/invoices/{$invoice->id}/payments", ['amount' => '0', 'paid_on' => '2026-12-05'])->assertSessionHasErrors('amount');
        $this->post("/invoices/{$invoice->id}/payments", ['amount' => '10.555', 'paid_on' => '2026-12-05'])->assertSessionHasErrors('amount');
        $this->post("/invoices/{$invoice->id}/payments", ['amount' => '10', 'paid_on' => 'yesterday'])->assertSessionHasErrors('paid_on');

        $this->assertSame(0, $invoice->payments()->count());
    }

    public function test_one_click_settles_what_is_left_and_a_payment_can_be_deleted(): void
    {
        $invoice = $this->issued();
        $this->post("/invoices/{$invoice->id}/payments", ['amount' => '30', 'paid_on' => '2026-12-05']);

        $this->post("/invoices/{$invoice->id}/payments/settle");
        $this->assertSame(PaymentStatus::Paid, $invoice->fresh()->paymentStatus());
        $this->assertEqualsCanonicalizing(['30.00', '1200.00'], $invoice->payments->pluck('amount')->all());

        $this->post("/invoices/{$invoice->id}/payments/settle");
        $this->assertSame(2, $invoice->payments()->count(), 'Nothing is left: nothing is added.');

        $this->delete('/payments/'.$invoice->payments()->where('amount', '1200.00')->first()->id);
        $this->assertSame(PaymentStatus::Partial, $invoice->fresh()->paymentStatus());
    }

    public function test_an_invoice_is_overdue_after_its_due_date_until_it_is_paid(): void
    {
        $late = $this->issued('2020-01-01');
        $this->assertTrue($late->fresh()->isOverdue());
        $this->assertFalse($this->issued('2049-01-01')->isOverdue());

        $this->post("/invoices/{$late->id}/payments/settle");
        $this->assertFalse($late->fresh()->isOverdue());
        $this->assertFalse($this->draft('2020-01-01')->fresh()->isOverdue(), 'A draft is never late.');
    }

    public function test_a_draft_takes_no_payments_and_other_companies_cannot_touch_them(): void
    {
        $this->post('/invoices/'.$this->draft()->id.'/payments', ['amount' => '1', 'paid_on' => '2026-12-05'])->assertNotFound();

        $other = Company::create(['name' => 'Other', 'nip' => '7762811692', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
        $foreign = $other->invoices()->create(['contractor_id' => $other->contractors()->create(['name' => 'B', 'country' => 'PL', 'address_line1' => 'z'])->id, 'numbering_series_id' => $other->numberingSeries()->create(['name' => 'S', 'format' => 'X{N}', 'reset' => 'never'])->id, 'issue_date' => '2026-12-01']);
        $foreign->forceFill(['gross_total' => '10.00', 'status' => 'issued'])->save();
        $this->post("/invoices/{$foreign->id}/payments", ['amount' => '1', 'paid_on' => '2026-12-05'])->assertNotFound();
    }

    public function test_the_list_and_the_page_tell_the_payment_state(): void
    {
        $invoice = $this->issued('2020-01-01');
        $this->post("/invoices/{$invoice->id}/payments", ['amount' => '200', 'paid_on' => '2026-12-05']);

        $this->get('/invoices')->assertInertia(fn (Assert $page) => $page
            ->where('invoices.0.payment_status', 'partial')
            ->where('invoices.0.overdue', true)
            ->where('invoices.0.outstanding', '1030.00'));
        $this->get("/invoices/{$invoice->id}")->assertInertia(fn (Assert $page) => $page
            ->where('payment.paid', '200.00')
            ->where('payment.outstanding', '1030.00')
            ->has('payment.items', 1));
    }
}
