<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contractor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ContractorTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->company = Company::create(['name' => 'Seller', 'nip' => '5265877635', 'environment' => 'test', 'auth_method' => 'manual']);
    }

    /**
     * @param  array<string, string>  $override
     * @return array<string, string>
     */
    private function form(array $override = []): array
    {
        return $override + ['name' => 'Buyer sp. z o.o.', 'nip' => '7762811692', 'country' => 'pl', 'address_line1' => 'ul. Prosta 1, 00-001 Warszawa'];
    }

    public function test_contractors_are_listed_created_updated_and_deleted(): void
    {
        $this->post('/contractors', $this->form())->assertSessionHasNoErrors();

        $contractor = Contractor::first();
        $this->assertSame($this->company->id, $contractor->company_id);
        $this->assertSame('PL', $contractor->country, 'The country code is normalised.');

        $this->get('/contractors')->assertInertia(fn (Assert $page) => $page->component('Contractors')->has('contractors', 1)->where('contractors.0.name', 'Buyer sp. z o.o.'));

        $this->put("/contractors/{$contractor->id}", $this->form(['name' => 'Renamed']))->assertSessionHasNoErrors();
        $this->assertSame('Renamed', $contractor->fresh()->name);

        $this->delete("/contractors/{$contractor->id}");
        $this->assertSame(0, Contractor::count());
    }

    public function test_input_is_validated(): void
    {
        $this->post('/contractors', $this->form(['nip' => '1234567890']))->assertSessionHasErrors('nip');
        $this->post('/contractors', $this->form(['name' => '']))->assertSessionHasErrors('name');
        $this->post('/contractors', $this->form(['country' => 'POL']))->assertSessionHasErrors('country');
        $this->post('/contractors', $this->form(['email' => 'nope']))->assertSessionHasErrors('email');
        $this->assertSame(0, Contractor::count());
    }

    public function test_a_foreign_buyer_needs_no_nip_but_a_nip_is_unique_per_company(): void
    {
        $this->post('/contractors', $this->form(['nip' => '', 'country' => 'DE']))->assertSessionHasNoErrors();
        $this->post('/contractors', $this->form(['nip' => '', 'name' => 'Another', 'country' => 'FR']))->assertSessionHasNoErrors();

        $this->post('/contractors', $this->form());
        $this->post('/contractors', $this->form(['name' => 'Duplicate']))->assertSessionHasErrors('nip');
        $this->assertSame(3, Contractor::count());
    }

    public function test_a_contractor_of_another_company_cannot_be_touched(): void
    {
        $other = Company::create(['name' => 'Other', 'nip' => '7762811692', 'environment' => 'test', 'auth_method' => 'manual']);
        $foreign = $other->contractors()->create(['name' => 'Theirs', 'country' => 'PL', 'address_line1' => 'x']);

        $this->put("/contractors/{$foreign->id}", $this->form())->assertNotFound();
        $this->delete("/contractors/{$foreign->id}")->assertNotFound();
        $this->assertSame(1, Contractor::count());
    }

    public function test_without_a_company_the_list_leads_to_the_onboarding(): void
    {
        Company::query()->delete();

        $this->get('/contractors')->assertRedirect(route('company.edit'));
    }
}
