<?php

namespace App\Http\Middleware;

use App\Enums\AuthMethod;
use App\Enums\KsefEnvironment;
use App\Ksef\AvailabilityMonitor;
use App\Models\Company;
use App\Models\User;
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
     * Whether the company signs in to KSeF by hand, and until when the last signed login lasts.
     *
     * @return array{manual: bool, active_until: string|null}|null
     */
    private static function ksefSession(?User $user): ?array
    {
        $company = $user === null ? null : Company::query()->first();
        if ($company === null || $company->auth_method !== AuthMethod::Manual) {
            return null;
        }

        return ['manual' => true, 'active_until' => $company->signedInUntil()?->toIso8601String()];
    }

    /**
     * The banner about KSeF being down. Only for production: the TEST environment's availability service shows made-up
     * failures that would alarm for nothing. It reads the stored answer; with none stored, it is fetched after the
     * response, so the page itself never waits for the Ministry.
     *
     * @return array{state: string, label: string, notices: list<array{title: string, text: string, start: string, end: string|null}>}|null
     */
    private function availability(): ?array
    {
        $company = Company::query()->first();
        if ($company === null || $company->environment !== KsefEnvironment::Production) {
            return null;
        }

        $monitor = app(AvailabilityMonitor::class);
        $availability = $monitor->current($company);
        if ($availability === null) {
            if ($monitor->claimRefresh($company)) {
                // After the response has been sent, in this process: nothing is queued or serialised.
                app()->terminating(static fn () => $monitor->current($company) ?? $monitor->refresh($company));
            }

            return null;
        }

        return $availability->state->isDown()
            ? ['state' => $availability->state->value, 'label' => $availability->state->label(), 'notices' => $availability->notices]
            : null;
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
            'credits' => [
                'author' => config('credits.author'),
                'years' => config('credits.since') < (int) date('Y') ? config('credits.since').'–'.date('Y') : (string) config('credits.since'),
                'url' => config('credits.url'),
                'license' => config('credits.license'),
            ],
            'locale' => app()->getLocale(),
            'locales' => config('locales'),
            'translations' => fn () => self::translations(app()->getLocale()),
            'auth' => ['user' => $user === null ? null : ['name' => $user->name, 'email' => $user->email]],
            'company' => fn () => $user === null ? null : Company::query()->first()?->only(['name', 'nip']),
            // The result of the last connection check, shown once.
            'report' => fn () => $request->session()->get('report'),
            'status' => fn () => $request->session()->get('status'),
            'ksefSession' => fn () => self::ksefSession($user),
            'ksefAvailability' => fn () => $user === null ? null : $this->availability(),
            'unreadPurchases' => fn () => $user === null ? 0 : (Company::query()->first()?->receivedInvoices()->whereNull('read_at')->count() ?? 0),
        ];
    }
}
