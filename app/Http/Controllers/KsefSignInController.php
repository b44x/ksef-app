<?php

namespace App\Http\Controllers;

use App\Enums\AuthMethod;
use App\Ksef\KsefGateway;
use App\Ksef\SigningRefused;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Signing in to KSeF by hand, for a company that has no token or certificate: the application prepares the login
 * document, the person signs it outside the application (a qualified signature, a trusted profile, ...) and hands it
 * back. KSeF then trusts the application for the lifetime of the login.
 */
class KsefSignInController extends Controller
{
    /** KSeF accepts a login document for ten minutes. */
    private const PENDING_MINUTES = 10;

    /** A signed login is a few kilobytes; a megabyte is more than generous and keeps a mistaken upload out. */
    private const MAX_KILOBYTES = 1024;

    public function show(): Response|RedirectResponse
    {
        $company = $this->company();
        if ($company === null) {
            return redirect()->route('company.edit');
        }

        $pending = Cache::get($this->pendingKey($company));

        return Inertia::render('KsefSignIn', [
            'manual' => $company->auth_method === AuthMethod::Manual,
            'environment' => $company->environment->label(),
            'signedInUntil' => $company->signedInUntil()?->toIso8601String(),
            'pendingUntil' => is_array($pending) ? $pending['expires_at'] : null,
        ]);
    }

    /** Step one: asks KSeF for a login challenge and keeps the document to sign for ten minutes. */
    public function request(KsefGateway $gateway): RedirectResponse
    {
        $company = $this->manualCompany();

        try {
            $request = $gateway->signingRequest($company);
        } catch (SigningRefused $e) {
            return back()->withErrors(['signin' => __('The login document could not be prepared: :reason', ['reason' => $e->getMessage()])]);
        }

        Cache::put($this->pendingKey($company), ['xml' => $request->xml, 'expires_at' => $request->expiresAt->format(DATE_ATOM)], now()->addMinutes(self::PENDING_MINUTES));

        return back()->with('status', __('The login document is ready. Download it and sign it.'));
    }

    public function download(): StreamedResponse
    {
        $company = $this->manualCompany();
        $pending = Cache::get($this->pendingKey($company));
        abort_unless(is_array($pending), 404);

        return response()->streamDownload(function () use ($pending): void {
            echo $pending['xml'];
        }, 'ksef-login-'.$company->nip.'.xml', ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /** Step two: the signed document comes back, as a file or pasted text. */
    public function complete(Request $request, KsefGateway $gateway): RedirectResponse
    {
        $company = $this->manualCompany();

        $data = $request->validate([
            'signed' => ['nullable', 'file', 'max:'.self::MAX_KILOBYTES],
            'signed_text' => ['nullable', 'string', 'max:'.(self::MAX_KILOBYTES * 1024)],
        ]);

        $signed = $request->file('signed')?->get() ?? (string) ($data['signed_text'] ?? '');
        if (trim($signed) === '') {
            return back()->withErrors(['signed' => __('Choose the signed file, or paste its contents.')]);
        }
        if (! Cache::has($this->pendingKey($company))) {
            return back()->withErrors(['signed' => __('The login document has expired. Prepare a new one: KSeF accepts it for ten minutes.')]);
        }

        try {
            $gateway->completeSigning($company, $signed);
        } catch (SigningRefused $e) {
            return back()->withErrors(['signed' => $e->getMessage()]);
        }

        Cache::forget($this->pendingKey($company));

        return redirect()->route('signin.show')->with('status', __('Signed in to KSeF.'));
    }

    /** Forgets the login: the application can no longer act for the company until the person signs again. */
    public function destroy(): RedirectResponse
    {
        $company = $this->manualCompany();
        $company->tokens()->delete();
        Cache::forget($this->pendingKey($company));

        return back()->with('status', __('Signed out of KSeF.'));
    }

    private function company(): ?Company
    {
        return Company::current();
    }

    private function manualCompany(): Company
    {
        $company = $this->company();
        abort_if($company === null || $company->auth_method !== AuthMethod::Manual, 404);

        return $company;
    }

    private function pendingKey(Company $company): string
    {
        return 'ksef-signin-request:'.$company->id;
    }
}
