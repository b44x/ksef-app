<?php

namespace Tests\Feature\Ksef;

use App\Enums\AuthMethod;
use App\Enums\KsefEnvironment;
use App\Ksef\CheckOutcome;
use App\Ksef\KsefGateway;
use App\Models\Company;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Tests\TestCase;

class SdkKsefGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unreachable_ksef_is_reported_as_failed_checks_without_leaking_the_credentials(): void
    {
        // A KSeF that answers 503 to everything.
        $this->app->instance(ClientInterface::class, new class implements ClientInterface
        {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return new Response(503, [], 'unavailable');
            }
        });
        $company = Company::create(['name' => 'Example', 'nip' => '5265877635', 'environment' => KsefEnvironment::Test, 'auth_method' => AuthMethod::Token, 'credentials' => ['token' => 'secret-ksef-token']]);

        $report = $this->app->make(KsefGateway::class)->check($company);

        $this->assertFalse($report->ok());
        $byName = [];
        foreach ($report->checks as $check) {
            $byName[$check->name] = $check;
        }
        $this->assertSame(CheckOutcome::Failed, $byName['API']->outcome);
        $this->assertSame(CheckOutcome::Failed, $byName['Login']->outcome);
        $this->assertSame(CheckOutcome::Skipped, $byName['Limits']->outcome);
        $this->assertStringNotContainsString('secret-ksef-token', json_encode($report, JSON_THROW_ON_ERROR));
    }

    public function test_broken_settings_are_a_failed_check_not_an_exception(): void
    {
        $company = Company::create(['name' => 'Example', 'nip' => '5265877635', 'environment' => KsefEnvironment::Test, 'auth_method' => AuthMethod::Token, 'credentials' => null]);

        $report = $this->app->make(KsefGateway::class)->check($company);

        $this->assertFalse($report->ok());
        $this->assertSame('Settings', $report->checks[0]->name);
    }
}
