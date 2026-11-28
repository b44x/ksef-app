<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhiteListTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        Cache::flush();
    }

    public function test_a_taxpayer_is_looked_up_by_nip_and_its_address_is_split_in_two(): void
    {
        Http::fake(['wl-api.mf.gov.pl/*' => Http::response(['result' => ['subject' => [
            'name' => 'ACME POLSKA SPÓŁKA Z OGRANICZONĄ ODPOWIEDZIALNOŚCIĄ', 'nip' => '7762811692', 'statusVat' => 'Czynny',
            'workingAddress' => 'UL. DŁUGA 5/2, 80-001 GDAŃSK', 'accountNumbers' => ['61109010140000071219812874'],
        ]]])]);

        $this->getJson('/contractors/lookup?nip=776-281-16-92')->assertOk()->assertExactJson([
            'name' => 'ACME POLSKA SPÓŁKA Z OGRANICZONĄ ODPOWIEDZIALNOŚCIĄ', 'nip' => '7762811692', 'address_line1' => 'UL. DŁUGA 5/2', 'address_line2' => '80-001 GDAŃSK',
            'vat_status' => 'Czynny', 'accounts' => ['61109010140000071219812874'],
        ]);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/search/nip/7762811692') && $request->hasHeader('Accept', 'application/json'));
    }

    public function test_the_register_is_asked_once_an_hour_per_nip(): void
    {
        Http::fake(['wl-api.mf.gov.pl/*' => Http::response(['result' => ['subject' => ['name' => 'X', 'nip' => '7762811692', 'workingAddress' => 'ADRES']]])]);

        $this->getJson('/contractors/lookup?nip=7762811692')->assertOk();
        $this->getJson('/contractors/lookup?nip=7762811692')->assertOk();

        Http::assertSentCount(1);
    }

    public function test_an_unknown_taxpayer_a_bad_nip_and_a_broken_register_each_say_so(): void
    {
        Http::fake(['wl-api.mf.gov.pl/*' => Http::sequence()
            ->push(['result' => ['subject' => null]])
            ->push(['code' => 'WL-100'], 400)
            ->push('boom', 500)]);

        $this->getJson('/contractors/lookup?nip=7762811692')->assertNotFound()->assertJsonPath('message', 'No taxpayer with this NIP was found in the register.');
        $this->getJson('/contractors/lookup?nip=5260250995')->assertNotFound();
        $this->getJson('/contractors/lookup?nip=1234567890')->assertUnprocessable();
        $this->getJson('/contractors/lookup?nip=2472557490')->assertStatus(502);
    }

    public function test_an_unreachable_register_is_not_cached_as_an_answer(): void
    {
        Http::fake(['wl-api.mf.gov.pl/*' => Http::sequence()->pushFailedConnection()->push(['result' => ['subject' => ['name' => 'X', 'nip' => '7762811692', 'workingAddress' => 'A, B']]])]);

        $this->getJson('/contractors/lookup?nip=7762811692')->assertStatus(502);
        $this->getJson('/contractors/lookup?nip=7762811692')->assertOk()->assertJsonPath('name', 'X');
    }
}
