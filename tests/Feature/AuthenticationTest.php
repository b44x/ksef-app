<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'correct horse battery';

    /**
     * @param  array<string, string>  $override
     * @return array<string, string>
     */
    private function setupData(array $override = []): array
    {
        return $override + ['name' => 'Admin', 'email' => 'admin@example.test', 'password' => self::PASSWORD, 'password_confirmation' => self::PASSWORD];
    }

    public function test_a_fresh_installation_leads_to_the_setup_and_the_setup_creates_the_administrator(): void
    {
        $this->get('/')->assertRedirect(route('login'));
        $this->get('/login')->assertRedirect(route('setup'));
        $this->get('/setup')->assertInertia(fn (Assert $page) => $page->component('Setup')->where('credits.author', 'Michell Hoduń')->where('credits.url', 'https://github.com/b44x/ksef-app'));

        $this->post('/setup', $this->setupData())->assertRedirect(route('company.edit'));

        $this->assertAuthenticated();
        $this->assertSame(1, User::count());
        $this->assertNotSame(self::PASSWORD, User::first()->password, 'Passwords are hashed.');
    }

    public function test_the_setup_is_closed_once_there_is_a_user_and_needs_a_strong_password(): void
    {
        $this->post('/setup', $this->setupData(['password' => 'short', 'password_confirmation' => 'short']))->assertSessionHasErrors('password');
        $this->assertSame(0, User::count());

        // Someone else finished the setup first: a second administrator must not be created.
        User::factory()->create();

        $this->post('/setup', $this->setupData(['email' => 'late@example.test']))->assertRedirect(route('login'));
        $this->assertSame(1, User::count());
        $this->get('/setup')->assertRedirect(route('login'));
    }

    public function test_signing_in_and_out(): void
    {
        $user = User::factory()->create(['password' => 'a long enough password']);

        $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('Login'));

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/login', ['email' => $user->email, 'password' => 'a long enough password'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_repeated_wrong_passwords_are_throttled(): void
    {
        $user = User::factory()->create(['password' => 'a long enough password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/login', ['email' => $user->email, 'password' => 'a long enough password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_the_application_is_closed_to_guests(): void
    {
        User::factory()->create();

        $this->get('/')->assertRedirect(route('login'));
        $this->get('/company')->assertRedirect(route('login'));
        $this->post('/company/check')->assertRedirect(route('login'));
    }
}
