<?php

namespace Tests\Feature;

use App\Console\Commands\CheckAvailabilityCommand;
use App\Ksef\Availability;
use App\Ksef\AvailabilityMonitor;
use App\Ksef\AvailabilityState;
use App\Ksef\KsefGateway;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class AvailabilityTest extends TestCase
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
        $this->company = Company::create(['name' => 'Seller sp. z o.o.', 'nip' => '5265877635', 'environment' => 'production', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
    }

    private function down(AvailabilityState $state = AvailabilityState::Failure): Availability
    {
        return new Availability($state, [['title' => 'Awaria KSeF', 'text' => 'Trwa awaria', 'start' => '2026-12-01T08:00:00+00:00', 'end' => null]], new \DateTimeImmutable);
    }

    public function test_the_banner_appears_while_ksef_is_down_and_not_otherwise(): void
    {
        $monitor = app(AvailabilityMonitor::class);

        $monitor->refresh($this->company); // available
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('ksefAvailability', null));

        $this->ksef->availability = $this->down();
        $monitor->refresh($this->company);
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('ksefAvailability.state', 'failure')
            ->where('ksefAvailability.label', 'KSeF has a failure')
            ->where('ksefAvailability.notices.0.title', 'Awaria KSeF'));
    }

    public function test_each_kind_of_trouble_has_its_state(): void
    {
        foreach ([AvailabilityState::Maintenance, AvailabilityState::TotalFailure] as $state) {
            $this->ksef->availability = $this->down($state);
            app(AvailabilityMonitor::class)->refresh($this->company);

            $this->get('/')->assertInertia(fn (Assert $page) => $page->where('ksefAvailability.state', $state->value));
        }
    }

    public function test_the_test_environment_never_shows_the_banner(): void
    {
        // The TEST availability service shows made-up failures.
        $this->company->update(['environment' => 'test']);
        $this->ksef->availability = $this->down();
        app(AvailabilityMonitor::class)->refresh($this->company->fresh());

        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('ksefAvailability', null));
    }

    public function test_an_unreachable_availability_service_says_nothing_about_ksef(): void
    {
        $this->ksef->availabilityFails = new \RuntimeException('no route to host');

        $availability = app(AvailabilityMonitor::class)->refresh($this->company);

        $this->assertSame(AvailabilityState::Unknown, $availability->state);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->where('ksefAvailability', null));
    }

    public function test_pages_do_not_wait_for_the_network_and_ask_once_when_nothing_is_stored(): void
    {
        Cache::flush();

        $this->get('/')->assertOk();   // the refresh runs after the response
        $this->get('/')->assertOk();
        $this->get('/')->assertOk();

        $this->assertSame(1, $this->ksef->availabilityAsked, 'Three page loads, one question to the Ministry.');
    }

    public function test_the_scheduled_command_keeps_the_answer_fresh(): void
    {
        $this->ksef->availability = $this->down();

        $this->artisan(CheckAvailabilityCommand::class)->assertSuccessful();

        $this->assertSame(AvailabilityState::Failure, app(AvailabilityMonitor::class)->current($this->company)?->state);
    }

    public function test_an_answer_is_stored_per_environment(): void
    {
        $this->ksef->availability = $this->down();
        app(AvailabilityMonitor::class)->refresh($this->company);

        $this->company->update(['environment' => 'demo']);
        $this->assertNull(app(AvailabilityMonitor::class)->current($this->company->fresh()), 'DEMO does not borrow the production answer.');
    }
}
