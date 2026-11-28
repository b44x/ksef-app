<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SetupChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_dashboard_lists_what_is_left_until_everything_is_done(): void
    {
        $this->actingAs(User::factory()->create());
        $company = Company::create(['name' => 'Seller', 'nip' => '5265877635', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->has('setup', 5)
            ->where('setup.0', ['key' => 'company', 'label' => 'Fill in your company address', 'href' => '/company', 'done' => false])
            ->where('setup.1.done', true));

        $company->forceFill(['address_line1' => 'ul. Prosta 1'])->save();
        $contractor = $company->contractors()->create(['name' => 'B', 'nip' => '7762811692', 'country' => 'PL', 'address_line1' => 'x']);
        $series = $company->numberingSeries()->create(['name' => 'S', 'format' => 'FV/{NNN}', 'reset' => 'never', 'is_default' => true]);
        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('setup', 5)->where('setup.4.done', false));

        $invoice = $company->invoices()->create(['contractor_id' => $contractor->id, 'numbering_series_id' => $series->id, 'issue_date' => '2026-12-01']);
        $invoice->forceFill(['status' => 'issued'])->save();

        $this->get('/')->assertInertia(fn (Assert $page) => $page->has('setup', 0));
    }
}
