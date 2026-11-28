<?php

namespace App\Http\Controllers;

use App\Mail\DigestMail;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

/** Where the daily summary goes and what it may contain. The mail server itself is set in the environment (MAIL_*). */
class NotificationSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $company = Company::current();
        abort_if($company === null, 404);

        $data = $request->validate([
            'notify_email' => ['nullable', 'email', 'max:255'],
            'notify_offline' => ['required', 'boolean'],
            'notify_purchases' => ['required', 'boolean'],
            'notify_rejected' => ['required', 'boolean'],
        ]);
        $company->forceFill($data)->save();

        return back()->with('status', __('Notification settings saved.'));
    }

    public function test(): RedirectResponse
    {
        $company = Company::current();
        abort_if($company === null, 404);
        if ($company->notify_email === null || $company->notify_email === '') {
            return back()->withErrors(['notify' => __('Save an e-mail address first.')]);
        }

        try {
            Mail::to($company->notify_email)->send(new DigestMail($company, null));
        } catch (\Throwable $e) {
            return back()->withErrors(['notify' => __('The message could not be sent: :reason', ['reason' => $e->getMessage()])]);
        }

        return back()->with('status', __('A test message was sent to :address.', ['address' => $company->notify_email]));
    }
}
