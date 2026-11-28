<?php

namespace App\Http\Controllers;

use App\Ksef\KsefGateway;
use App\Ksef\OfflineCertificateRefused;
use App\Ksef\SigningRequired;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;

/** The Offline certificate: requested from KSeF once, kept encrypted, used to confirm the issuer in QR code II. */
class OfflineCertificateController extends Controller
{
    public function store(KsefGateway $gateway): RedirectResponse
    {
        $company = Company::current();
        abort_if($company === null, 404);

        try {
            $issued = $gateway->requestOfflineCertificate($company);
        } catch (SigningRequired $e) {
            return back()->withErrors(['offline' => $e->getMessage()]);
        } catch (OfflineCertificateRefused $e) {
            return back()->withErrors(['offline' => __('KSeF did not issue the certificate: :reason', ['reason' => $e->getMessage()])]);
        }

        // The key exists nowhere else: it is stored at once, encrypted with the application key.
        $company->forceFill([
            'offline_certificate' => ['certificate' => $issued->certificatePem, 'private_key' => $issued->privateKeyPem],
            'offline_certificate_expires_at' => $issued->expiresAt,
        ])->save();

        return back()->with('status', __('The Offline certificate is ready.'));
    }

    /** Forgets the stored certificate (the key cannot be recovered; a new one is requested from KSeF). */
    public function destroy(): RedirectResponse
    {
        $company = Company::current();
        abort_if($company === null, 404);

        $company->forceFill(['offline_certificate' => null, 'offline_certificate_expires_at' => null])->save();

        return back()->with('status', __('The Offline certificate was removed.'));
    }
}
