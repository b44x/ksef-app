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
     * The interface strings of a language (lang/<code>.json), keyed by the English text.
     *
     * @return array<string, string>
     */
    private static function translations(string $locale): array
    {
        $file = lang_path($locale.'.json');

        return is_file($file) ? (array) json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR) : [];
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
            'locale' => app()->getLocale(),
            'locales' => config('locales'),
            'translations' => fn () => self::translations(app()->getLocale()),
            'auth' => ['user' => $user === null ? null : ['name' => $user->name, 'email' => $user->email]],
            'company' => fn () => $user === null ? null : Company::query()->first()?->only(['name', 'nip']),
            // The result of the last connection check, shown once.
            'report' => fn () => $request->session()->get('report'),
            'saved' => fn () => (bool) $request->session()->get('saved'),
        ];
    }
}
