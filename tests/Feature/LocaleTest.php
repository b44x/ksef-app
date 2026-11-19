<?php

namespace Tests\Feature;

use App\Enums\AuthMethod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_language_follows_the_browser_and_can_be_chosen(): void
    {
        $this->withHeader('Accept-Language', 'pl-PL,pl;q=0.9,en;q=0.8')->get('/setup')
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'pl')->where('translations.Log in', 'Zaloguj się'));

        $this->withHeader('Accept-Language', 'de')->get('/setup')
            ->assertInertia(fn (Assert $page) => $page->where('locale', 'en'));

        $this->post('/locale', ['locale' => 'pl'])->assertRedirect();
        $this->get('/setup')->assertInertia(fn (Assert $page) => $page->where('locale', 'pl'));

        $this->post('/locale', ['locale' => 'xx'])->assertSessionHasErrors('locale');
    }

    public function test_server_side_texts_are_translated(): void
    {
        $this->post('/locale', ['locale' => 'pl']);
        app()->setLocale('pl');
        $this->assertSame('Token KSeF', AuthMethod::Token->label());

        User::factory()->create();
        $this->post('/login', ['email' => 'nobody@example.test', 'password' => 'x'])
            ->assertSessionHasErrors(['email' => 'Nieprawidłowy e-mail lub hasło.']);
    }

    public function test_every_language_has_the_same_keys(): void
    {
        $pl = array_keys(json_decode(file_get_contents(lang_path('pl.json')), true));

        foreach (['resources/js'] as $dir) {
            $used = [];
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path($dir))) as $file) {
                if (preg_match_all("/\\bt\\('((?:[^'\\\\]|\\\\.)*)'/", (string) @file_get_contents((string) $file), $m)) {
                    array_push($used, ...array_map(stripslashes(...), $m[1]));
                }
            }
            $this->assertSame([], array_values(array_diff($used, $pl)), 'These texts have no Polish translation.');
        }
    }
}
