<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props every page receives.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'app' => ['name' => config('app.name')],
            'auth' => ['user' => $user === null ? null : ['name' => $user->name, 'email' => $user->email]],
            'company' => fn () => $user === null ? null : Company::query()->first()?->only(['name', 'nip']),
            // The result of the last connection check, shown once.
            'report' => fn () => $request->session()->get('report'),
        ];
    }
}
