<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** First run: creates the administrator. Only available while there are no users. */
#[Layout('layouts.app')]
#[Title('Set up')]
class Setup extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): mixed
    {
        return User::query()->exists() ? $this->redirectRoute('login', navigate: false) : null;
    }

    public function create(): mixed
    {
        // Checked again here: two people must not both become the first administrator.
        if (User::query()->exists()) {
            return $this->redirectRoute('login');
        }

        $data = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => Hash::make($data['password'])]);
        Auth::login($user);
        session()->regenerate();

        return $this->redirectRoute('onboarding');
    }

    public function render()
    {
        return view('livewire.setup');
    }
}
