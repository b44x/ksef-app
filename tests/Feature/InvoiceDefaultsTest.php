<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        Company::create(['name' => 'Seller', 'nip' => '5265877635', 'address_line1' => 'x', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
    }

    public function test_a_new_company_pays_in_fourteen_days_by_transfer(): void
    {
        $this->get('/invoices/create')->assertInertia(fn (Assert $page) => $page
            ->where('defaults.payment_days', 14)
            ->where('defaults.payment_method', '6')
            ->where('defaults.bank_account', ''));
    }

    public function test_the_defaults_are_saved_and_offered_to_the_new_invoice_form(): void
    {
        $this->put('/company/defaults', ['default_payment_days' => '30', 'default_payment_method' => '1', 'default_bank_account' => '61109010140000071219812874', 'default_notes' => 'Dziękujemy.'])->assertSessionHasNoErrors();

        $this->get('/invoices/create')->assertInertia(fn (Assert $page) => $page
            ->where('defaults.payment_days', 30)
            ->where('defaults.payment_method', '1')
            ->where('defaults.notes', 'Dziękujemy.'));
        $this->get('/company')->assertInertia(fn (Assert $page) => $page->where('defaults.payment_days', '30')->where('defaults.bank_account', '61109010140000071219812874'));
    }

    public function test_empty_fields_clear_a_default_and_nonsense_is_refused(): void
    {
        $this->put('/company/defaults', ['default_payment_days' => '', 'default_payment_method' => '', 'default_bank_account' => '', 'default_notes' => ''])->assertSessionHasNoErrors();
        $company = Company::query()->firstOrFail();
        $this->assertNull($company->default_payment_days, 'No term: no due date.');

        $this->put('/company/defaults', ['default_payment_days' => '9999'])->assertSessionHasErrors('default_payment_days');
        $this->put('/company/defaults', ['default_payment_method' => '99'])->assertSessionHasErrors('default_payment_method');
        $this->put('/company/defaults', ['default_bank_account' => 'short'])->assertSessionHasErrors('default_bank_account');
    }
}
