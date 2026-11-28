<?php

namespace Tests\Feature;

use App\Enums\Frequency;
use App\Invoicing\GenerateRecurring;
use App\Invoicing\IssueInvoice;
use App\Ksef\KsefGateway;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class RecurringTest extends TestCase
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

    private function source(): Invoice
    {
        $buyer = $this->company->contractors()->firstOrCreate(['nip' => '7762811692'], ['name' => 'Buyer', 'country' => 'PL', 'address_line1' => 'y']);
        $series = $this->company->numberingSeries()->firstOrCreate(['name' => 'S'], ['format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
        $invoice = $this->company->invoices()->create(['contractor_id' => $buyer->id, 'numbering_series_id' => $series->id, 'issue_date' => '2026-11-01', 'due_date' => '2026-11-15', 'payment_method' => 6, 'notes' => 'Abonament']);
        $invoice->lines()->create(['position' => 1, 'name' => 'Utrzymanie', 'unit' => 'mies.', 'quantity' => '1', 'unit_net_price' => '1200.00', 'vat_rate' => '23']);

        return app(IssueInvoice::class)($invoice);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function schedule(array $overrides = []): RecurringInvoice
    {
        $this->post('/invoices/'.$this->source()->id.'/recurring', $overrides + ['name' => 'Abonament', 'frequency' => 'monthly', 'next_run_on' => '2026-12-05', 'auto_issue' => false])->assertSessionHasNoErrors();

        return $this->company->recurringInvoices()->latest('id')->firstOrFail();
    }

    public function test_the_next_run_keeps_the_anchor_day_and_ends_a_short_month_on_its_last_day(): void
    {
        $jan31 = CarbonImmutable::parse('2026-01-31');
        $feb = Frequency::Monthly->after($jan31, 31);
        $this->assertSame('2026-02-28', $feb->toDateString());
        $this->assertSame('2026-03-31', Frequency::Monthly->after($feb, 31)->toDateString(), 'No drift: back on the 31st.');
        $this->assertSame('2026-04-30', Frequency::Quarterly->after($jan31, 31)->toDateString());
        $this->assertSame('2027-02-28', Frequency::Yearly->after(CarbonImmutable::parse('2026-02-28'), 29)->toDateString());
        $this->assertSame('2026-12-12', Frequency::Weekly->after(CarbonImmutable::parse('2026-12-05'), 5)->toDateString());
    }

    public function test_a_schedule_is_made_from_an_invoice_and_copies_what_repeats(): void
    {
        $schedule = $this->schedule(['ends_on' => '2027-12-05']);

        $this->assertSame(14, $schedule->payment_days);
        $this->assertSame(5, $schedule->anchor_day);
        $this->assertSame('Utrzymanie', $schedule->lines[0]['name']);
        $this->assertSame('Abonament', $schedule->notes);
        $this->assertTrue($schedule->active);

        $this->post('/invoices/'.$this->source()->id.'/recurring', ['name' => 'x', 'frequency' => 'daily', 'next_run_on' => '2026-12-05', 'auto_issue' => false])->assertSessionHasErrors('frequency');
        $this->post('/invoices/'.$this->source()->id.'/recurring', ['name' => 'x', 'frequency' => 'monthly', 'next_run_on' => '2026-12-05', 'ends_on' => '2026-01-01', 'auto_issue' => false])->assertSessionHasErrors('ends_on');
    }

    public function test_a_due_schedule_makes_a_draft_dated_today_and_moves_on_once(): void
    {
        $schedule = $this->schedule();
        $generate = app(GenerateRecurring::class);

        $this->assertNull($generate($schedule, CarbonImmutable::parse('2026-12-04')), 'Not due yet.');

        $invoice = $generate($schedule, CarbonImmutable::parse('2026-12-05'));
        $this->assertNotNull($invoice);
        $this->assertTrue($invoice->isDraft());
        $this->assertSame('2026-12-05', $invoice->issue_date->toDateString());
        $this->assertSame('2026-12-19', $invoice->due_date->toDateString());
        $this->assertSame($schedule->id, $invoice->recurring_invoice_id);
        $this->assertSame('Utrzymanie', $invoice->lines->first()->name);
        $this->assertSame('2027-01-05', $schedule->fresh()->next_run_on->toDateString());
        $this->assertSame(1, $schedule->fresh()->generated_count);

        $this->assertNull($generate($schedule, CarbonImmutable::parse('2026-12-05')), 'The same day again makes nothing.');
    }

    public function test_missed_runs_are_not_made_up(): void
    {
        $schedule = $this->schedule();

        app(GenerateRecurring::class)($schedule, CarbonImmutable::parse('2027-03-20'));

        $this->assertSame(1, $this->company->invoices()->where('recurring_invoice_id', $schedule->id)->count());
        $this->assertSame('2027-04-05', $schedule->fresh()->next_run_on->toDateString());
    }

    public function test_the_last_run_deactivates_the_schedule(): void
    {
        $schedule = $this->schedule(['ends_on' => '2026-12-31']);

        app(GenerateRecurring::class)($schedule, CarbonImmutable::parse('2026-12-05'));

        $this->assertFalse($schedule->fresh()->active);
    }

    public function test_auto_issue_numbers_the_invoice_and_a_problem_leaves_a_draft_with_the_reason(): void
    {
        $schedule = $this->schedule(['auto_issue' => true]);
        $issued = app(GenerateRecurring::class)($schedule, CarbonImmutable::parse('2026-12-05'));
        $this->assertFalse($issued->isDraft());
        $this->assertSame('FV/002/2026', $issued->number);
        $this->assertNull($schedule->fresh()->last_error);

        $broken = $this->schedule(['auto_issue' => true]);
        $broken->forceFill(['lines' => [['name' => '', 'unit' => 'szt.', 'quantity' => '1', 'unit_net_price' => '10', 'vat_rate' => '23']]])->save();
        $draft = app(GenerateRecurring::class)($broken, CarbonImmutable::parse('2026-12-05'));
        $this->assertTrue($draft->isDraft());
        $this->assertNotNull($broken->fresh()->last_error);
    }

    public function test_the_command_runs_the_due_schedules_and_skips_paused_ones(): void
    {
        $due = $this->schedule(['next_run_on' => CarbonImmutable::today()->toDateString()]);
        $paused = $this->schedule(['next_run_on' => CarbonImmutable::today()->toDateString()]);
        $paused->forceFill(['active' => false])->save();
        $later = $this->schedule(['next_run_on' => CarbonImmutable::today()->addDays(3)->toDateString()]);

        $this->artisan('invoices:generate-recurring')->assertSuccessful();

        $this->assertSame(1, $due->fresh()->generated_count);
        $this->assertSame(0, $paused->fresh()->generated_count);
        $this->assertSame(0, $later->fresh()->generated_count);
    }

    public function test_make_one_now_does_not_move_the_schedule_and_the_schedule_can_be_edited_paused_and_deleted(): void
    {
        $schedule = $this->schedule();

        $this->post("/recurring/{$schedule->id}/run")->assertRedirect();
        $this->assertSame(1, $schedule->invoices()->count());
        $this->assertSame('2026-12-05', $schedule->fresh()->next_run_on->toDateString());

        $this->put("/recurring/{$schedule->id}", ['name' => 'Nowa nazwa', 'frequency' => 'quarterly', 'next_run_on' => '2027-01-31', 'auto_issue' => true, 'active' => false])->assertSessionHasNoErrors();
        $fresh = $schedule->fresh();
        $this->assertSame([Frequency::Quarterly, 31, false, true], [$fresh->frequency, $fresh->anchor_day, $fresh->active, $fresh->auto_issue]);

        $this->delete("/recurring/{$schedule->id}");
        $this->assertNull(RecurringInvoice::find($schedule->id));
        $this->assertSame(1, Invoice::query()->whereNull('recurring_invoice_id')->where('status', 'draft')->count(), 'The invoice made stays, no longer tied to a schedule.');
    }

    public function test_another_company_cannot_touch_a_schedule(): void
    {
        $other = Company::create(['name' => 'Other', 'nip' => '7762811692', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
        $contractor = $other->contractors()->create(['name' => 'B', 'country' => 'PL', 'address_line1' => 'z']);
        $series = $other->numberingSeries()->create(['name' => 'S', 'format' => 'X{N}', 'reset' => 'never']);
        $theirs = new RecurringInvoice(['contractor_id' => $contractor->id, 'numbering_series_id' => $series->id, 'name' => 'T', 'frequency' => 'monthly', 'anchor_day' => 1, 'next_run_on' => '2026-12-01', 'lines' => []]);
        $theirs->company_id = $other->id;
        $theirs->save();

        $this->post("/recurring/{$theirs->id}/run")->assertNotFound();
        $this->delete("/recurring/{$theirs->id}")->assertNotFound();
        $this->put("/recurring/{$theirs->id}", [])->assertNotFound();
    }
}
