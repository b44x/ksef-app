<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** The company logo for printed invoices: a small PNG or JPEG, kept in the database as a data URI so a backup has it. */
class LogoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $company = Company::current();
        abort_if($company === null, 404);
        $request->validate(['logo' => ['required', 'file', 'mimes:png,jpg,jpeg', 'max:512', 'dimensions:max_width=2000,max_height=2000']]);

        $file = $request->file('logo');
        $mime = $file?->getMimeType() === 'image/png' ? 'image/png' : 'image/jpeg';
        $company->forceFill(['logo' => 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents((string) $file?->getRealPath()))])->save();

        return back()->with('status', __('The logo was saved.'));
    }

    public function destroy(): RedirectResponse
    {
        Company::current()?->forceFill(['logo' => null])->save();

        return back()->with('status', __('Deleted.'));
    }
}
