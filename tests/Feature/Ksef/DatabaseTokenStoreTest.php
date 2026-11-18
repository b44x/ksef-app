<?php

namespace Tests\Feature\Ksef;

use App\Enums\AuthMethod;
use App\Enums\KsefEnvironment;
use App\Ksef\DatabaseTokenStore;
use App\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseTokenStoreTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $nip): Company
    {
        return Company::create(['name' => 'C '.$nip, 'nip' => $nip, 'environment' => KsefEnvironment::Test, 'auth_method' => AuthMethod::Token, 'credentials' => ['token' => 't']]);
    }

    public function test_values_round_trip_and_are_encrypted_at_rest(): void
    {
        $store = new DatabaseTokenStore($company = $this->company('5265877635'));
        $key = hash('sha256', 'k');

        $this->assertNull($store->get($key));
        $store->set($key, '{"access":"secret-access-token"}', 600);

        $this->assertSame('{"access":"secret-access-token"}', $store->get($key));
        $this->assertStringNotContainsString('secret-access-token', (string) DB::table('ksef_tokens')->where('company_id', $company->id)->value('payload'));

        $store->set($key, 'newer', 600);
        $this->assertSame('newer', $store->get($key));
        $this->assertSame(1, DB::table('ksef_tokens')->count(), 'A key has one row.');

        $store->delete($key);
        $this->assertNull($store->get($key));
    }

    public function test_expired_values_are_not_returned(): void
    {
        $store = new DatabaseTokenStore($this->company('5265877635'));
        $store->set('k', 'v', 60);

        $this->travel(2)->minutes();

        $this->assertNull($store->get('k'));
    }

    public function test_companies_do_not_see_each_others_tokens(): void
    {
        $a = new DatabaseTokenStore($this->company('5265877635'));
        $b = new DatabaseTokenStore($this->company('1234563218'));

        $a->set('same-key', 'a-token', 600);

        $this->assertNull($b->get('same-key'));
        $this->assertSame('a-token', $a->get('same-key'));
    }
}
