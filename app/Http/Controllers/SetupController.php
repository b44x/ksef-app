<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/** First run: creates the administrator. Only available while there are no users. */
class SetupController extends Controller
{
    public function show(): Response|RedirectResponse
    {
        return User::query()->exists() ? redirect()->route('login') : Inertia::render('Setup');
    }

    public function store(Request $request): RedirectResponse
    {
        // Checked again here: two people must not both become the first administrator.
        if (User::query()->exists()) {
            return redirect()->route('login');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('company.edit');
    }
}
