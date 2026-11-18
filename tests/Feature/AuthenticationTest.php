<?php

namespace Tests\Feature;

use App\Livewire\Login;
use App\Livewire\Setup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_installation_leads_to_the_setup_and_the_setup_creates_the_administrator(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        Livewire::test(Login::class)->assertRedirect(route('setup'));

        Livewire::test(Setup::class)
            ->set('name', 'Admin')->set('email', 'admin@example.test')
            ->set('password', 'correct horse battery')->set('password_confirmation', 'correct horse battery')
            ->call('create')
            ->assertRedirect(route('onboarding'));

        $this->assertAuthenticated();
        $this->assertSame(1, User::count());
        $this->assertNotSame('correct horse battery', User::first()->password, 'Passwords are hashed.');
    }

    public function test_the_setup_is_closed_once_there_is_a_user_and_needs_a_strong_password(): void
    {
        Livewire::test(Setup::class)
            ->set('name', 'Admin')->set('email', 'admin@example.test')
            ->set('password', 'short')->set('password_confirmation', 'short')
            ->call('create')
            ->assertHasErrors(['password']);

        // Someone else finished the setup while this form was open: it must not create a second administrator.
        $form = Livewire::test(Setup::class);
        User::factory()->create();

        $form->set('name', 'Late')->set('email', 'late@example.test')
            ->set('password', 'correct horse battery')->set('password_confirmation', 'correct horse battery')
            ->call('create')->assertRedirect(route('login'));
        $this->assertSame(1, User::count());
        Livewire::test(Setup::class)->assertRedirect(route('login'));
    }

    public function test_signing_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'a long enough password']);

        Livewire::test(Login::class)
            ->set('email', $user->email)->set('password', 'wrong')
            ->call('authenticate')
            ->assertHasErrors(['email']);
        $this->assertGuest();

        Livewire::test(Login::class)
            ->set('email', $user->email)->set('password', 'a long enough password')
            ->call('authenticate')
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_repeated_wrong_passwords_are_throttled(): void
    {
        $user = User::factory()->create(['password' => 'a long enough password']);

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(Login::class)->set('email', $user->email)->set('password', 'wrong')->call('authenticate');
        }

        Livewire::test(Login::class)
            ->set('email', $user->email)->set('password', 'a long enough password')
            ->call('authenticate')
            ->assertHasErrors(['email']);
        $this->assertGuest();
    }

    public function test_the_application_is_closed_to_guests(): void
    {
        User::factory()->create();

        $this->get('/')->assertRedirect(route('login'));
        $this->get('/company')->assertRedirect(route('login'));
    }
}
