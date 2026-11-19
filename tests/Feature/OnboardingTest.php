<?php

namespace Tests\Feature;

use App\Enums\AuthMethod;
use App\Enums\KsefEnvironment;
use App\Ksef\KsefGateway;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
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

    /** @return array<string, string> */
    private function form(array $override = []): array
    {
        return $override + [
            'name' => 'Example sp. z o.o.', 'nip' => '5265877635', 'environment' => 'test',
            'auth_method' => 'token', 'token' => 'secret-ksef-token',
        ];
    }

    public function test_saving_stores_the_company_with_encrypted_secrets_and_shows_the_connection_check(): void
    {
        $gateway = new FakeGateway;
        $this->app->instance(KsefGateway::class, $gateway);

        $this->put('/company', $this->form())->assertSessionHasNoErrors()->assertRedirect(route('company.edit'));

        $company = Company::first();
        $this->assertSame('5265877635', $company->nip);
        $this->assertSame(KsefEnvironment::Test, $company->environment);
        $this->assertSame(AuthMethod::Token, $company->auth_method);
        $this->assertSame('secret-ksef-token', $company->credential('token'));
        $this->assertStringNotContainsString('secret-ksef-token', (string) DB::table('companies')->value('credentials'));
        $this->assertCount(1, $gateway->checked);

        $response = $this->get('/company');
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Company')
            ->where('company.name', 'Example sp. z o.o.')
            ->where('company.has_secrets', true)
            ->where('report.ok', true)
            ->where('report.checks.0.message', 'logged in'));
        $this->assertStringNotContainsString('secret-ksef-token', $response->getContent(), 'The secret is never sent back.');
    }

    public function test_a_failed_check_is_shown_with_what_to_do(): void
    {
        $this->app->instance(KsefGateway::class, new FakeGateway(healthy: false));

        $this->put('/company', $this->form());

        $this->get('/company')->assertInertia(fn (Assert $page) => $page
            ->where('report.ok', false)
            ->where('report.checks.0.message', 'refused')
            ->where('report.checks.0.details.0', 'check the token'));
    }

    public function test_invalid_input_is_refused_and_nothing_is_saved(): void
    {
        $this->app->instance(KsefGateway::class, new FakeGateway);

        $this->put('/company', $this->form(['nip' => '1234567890']))->assertSessionHasErrors('nip');
        $this->put('/company', $this->form(['nip' => '12345']))->assertSessionHasErrors('nip');
        $this->put('/company', $this->form(['token' => '']))->assertSessionHasErrors('token');
        $this->put('/company', $this->form(['auth_method' => 'certificate', 'token' => '']))->assertSessionHasErrors(['certificate', 'private_key']);
        $this->put('/company', $this->form(['environment' => 'moon']))->assertSessionHasErrors('environment');

        $this->assertSame(0, Company::count());
    }

    public function test_editing_keeps_the_stored_secret_when_the_field_is_left_blank(): void
    {
        $this->app->instance(KsefGateway::class, new FakeGateway);
        $this->put('/company', $this->form());

        $this->put('/company', $this->form(['name' => 'Renamed', 'token' => '']))->assertSessionHasNoErrors();

        $company = Company::first();
        $this->assertSame('Renamed', $company->name);
        $this->assertSame('secret-ksef-token', $company->credential('token'));
        $this->assertSame(1, Company::count());
    }

    public function test_changing_the_login_method_requires_new_secrets(): void
    {
        $this->app->instance(KsefGateway::class, new FakeGateway);
        $this->put('/company', $this->form());

        $this->put('/company', $this->form(['auth_method' => 'certificate', 'token' => '']))->assertSessionHasErrors(['certificate', 'private_key']);
    }

    public function test_signing_in_to_ksef_manually_needs_no_stored_secret(): void
    {
        $this->app->instance(KsefGateway::class, new FakeGateway);

        $this->put('/company', $this->form(['auth_method' => 'manual', 'token' => '']))->assertSessionHasNoErrors();

        $this->assertNull(Company::first()->credentials);
    }

    public function test_the_dashboard_sends_a_new_installation_to_the_onboarding_and_checks_on_demand(): void
    {
        $this->get('/')->assertRedirect(route('company.edit'));

        $gateway = new FakeGateway;
        $this->app->instance(KsefGateway::class, $gateway);
        $this->put('/company', $this->form());

        $this->get('/')->assertInertia(fn (Assert $page) => $page->component('Dashboard')->where('details.name', 'Example sp. z o.o.'));

        $this->post('/company/check')->assertRedirect();
        $this->assertCount(2, $gateway->checked);
    }
}
