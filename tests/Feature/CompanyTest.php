<?php

namespace Tests\Feature;

use App\Enums\AuthMethod;
use App\Enums\KsefEnvironment;
use App\Models\Company;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyTest extends TestCase
{
    use RefreshDatabase;

    private function company(array $overrides = []): Company
    {
        return Company::create($overrides + [
            'name' => 'Example sp. z o.o.',
            'nip' => '5265877635',
            'environment' => KsefEnvironment::Test,
            'auth_method' => AuthMethod::Token,
            'credentials' => ['token' => 'secret-ksef-token'],
        ]);
    }

    public function test_credentials_are_encrypted_at_rest_and_readable_through_the_model(): void
    {
        $company = $this->company();

        $raw = (string) DB::table('companies')->where('id', $company->id)->value('credentials');

        $this->assertStringNotContainsString('secret-ksef-token', $raw);
        $this->assertSame('secret-ksef-token', $company->fresh()->credential('token'));
    }

    public function test_credentials_never_leave_the_model_when_it_is_serialised(): void
    {
        $company = $this->company();

        $this->assertArrayNotHasKey('credentials', $company->toArray());
        $this->assertStringNotContainsString('secret-ksef-token', $company->toJson());
    }

    public function test_enums_are_cast_and_missing_credentials_are_null(): void
    {
        $company = $this->company(['auth_method' => AuthMethod::Manual, 'credentials' => null])->fresh();

        $this->assertSame(KsefEnvironment::Test, $company->environment);
        $this->assertSame(AuthMethod::Manual, $company->auth_method);
        $this->assertNull($company->credential('token'));
    }

    public function test_a_nip_belongs_to_one_company(): void
    {
        $this->company();

        $this->expectException(QueryException::class);
        $this->company(['name' => 'Duplicate']);
    }
}
