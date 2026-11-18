<?php

namespace Tests\Feature;

use App\Enums\AuthMethod;
use App\Enums\KsefEnvironment;
use App\Ksef\KsefGateway;
use App\Livewire\Dashboard;
use App\Livewire\Onboarding;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
    }

    private function fill(string $nip = '5265877635'): Testable
    {
        return Livewire::test(Onboarding::class)
            ->set('name', 'Example sp. z o.o.')->set('nip', $nip)
            ->set('environment', 'test')->set('authMethod', 'token')->set('token', 'secret-ksef-token');
    }

    public function test_saving_stores_the_company_with_encrypted_secrets_checks_the_connection_and_clears_the_form(): void
    {
        $gateway = new FakeGateway;
        $this->app->instance(KsefGateway::class, $gateway);

        $component = $this->fill()->call('save')->assertHasNoErrors();

        $company = Company::first();
        $this->assertSame('5265877635', $company->nip);
        $this->assertSame(KsefEnvironment::Test, $company->environment);
        $this->assertSame(AuthMethod::Token, $company->auth_method);
        $this->assertSame('secret-ksef-token', $company->credential('token'));
        $this->assertStringNotContainsString('secret-ksef-token', (string) DB::table('companies')->value('credentials'));
        $this->assertCount(1, $gateway->checked);
        $component->assertSet('token', '')->assertSet('reportOk', true)->assertSee('logged in');
        $this->assertStringNotContainsString('secret-ksef-token', $component->html(), 'The secret is not echoed back.');
    }

    public function test_a_failed_check_is_shown_with_what_to_do(): void
    {
        $this->app->instance(KsefGateway::class, new FakeGateway(healthy: false));

        $this->fill()->call('save')->assertSet('reportOk', false)->assertSee('refused')->assertSee('check the token');
    }

    public function test_invalid_input_is_refused_and_nothing_is_saved(): void
    {
        $this->app->instance(KsefGateway::class, new FakeGateway);

        $this->fill('1234567890')->call('save')->assertHasErrors(['nip']);
        $this->fill('12345')->call('save')->assertHasErrors(['nip']);
        Livewire::test(Onboarding::class)->set('name', 'X')->set('nip', '5265877635')->set('authMethod', 'token')->call('save')->assertHasErrors(['token']);
        Livewire::test(Onboarding::class)->set('name', 'X')->set('nip', '5265877635')->set('authMethod', 'certificate')->call('save')->assertHasErrors(['certificate', 'privateKey']);

        $this->assertSame(0, Company::count());
    }

    public function test_editing_keeps_the_stored_secret_when_the_field_is_left_blank(): void
    {
        $this->app->instance(KsefGateway::class, new FakeGateway);
        $this->fill()->call('save');

        Livewire::test(Onboarding::class)
            ->assertSet('name', 'Example sp. z o.o.')->assertSet('hasSecrets', true)->assertSet('token', '')
            ->set('name', 'Renamed')->call('save')->assertHasNoErrors();

        $company = Company::first();
        $this->assertSame('Renamed', $company->name);
        $this->assertSame('secret-ksef-token', $company->credential('token'));
        $this->assertSame(1, Company::count());
    }

    public function test_signing_out_of_ksef_manually_needs_no_stored_secret(): void
    {
        $this->app->instance(KsefGateway::class, new FakeGateway);

        Livewire::test(Onboarding::class)
            ->set('name', 'X')->set('nip', '5265877635')->set('authMethod', 'manual')
            ->call('save')->assertHasNoErrors();

        $this->assertNull(Company::first()->credentials);
    }

    public function test_the_dashboard_sends_a_new_installation_to_the_onboarding_and_checks_on_demand(): void
    {
        Livewire::test(Dashboard::class)->assertRedirect(route('onboarding'));

        $gateway = new FakeGateway;
        $this->app->instance(KsefGateway::class, $gateway);
        $this->fill()->call('save');

        Livewire::test(Dashboard::class)->assertSee('Example sp. z o.o.')->call('check')->assertSee('logged in');
        $this->assertCount(2, $gateway->checked);
    }
}
