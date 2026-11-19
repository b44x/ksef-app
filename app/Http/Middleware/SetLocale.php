<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/** Chooses the language: the one the user picked (kept in the session), else the browser's, else the configured one. */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('locales'));
        $chosen = $request->session()->get('locale');

        App::setLocale(
            is_string($chosen) && in_array($chosen, $supported, true)
                ? $chosen
                : ($request->getPreferredLanguage($supported) ?? config('app.locale')),
        );

        return $next($request);
    }
}
