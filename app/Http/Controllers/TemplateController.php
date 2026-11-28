<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ScopedToCompany;
use App\Models\Company;
use App\Models\InvoiceTemplate;
use App\Pdf\InvoicePdf;
use App\Pdf\SampleInvoice;
use App\Pdf\TemplateSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** How printed invoices look: saved templates, and the editor with a live PDF preview. */
class TemplateController extends Controller
{
    use ScopedToCompany;

    public function index(): Response|RedirectResponse
    {
        $company = Company::current();
        if ($company === null) {
            return redirect()->route('company.edit');
        }

        return Inertia::render('Templates', [
            'templates' => $company->templates()->orderByDesc('is_default')->orderBy('name')->get()->map(fn (InvoiceTemplate $t): array => [
                'id' => $t->id,
                'name' => $t->name,
                'is_default' => $t->is_default,
                'layout' => $t->resolved()['layout'],
                'accent' => $t->resolved()['accent'],
                'used' => $t->company->invoices()->where('invoice_template_id', $t->id)->count(),
            ])->all(),
            'builtin' => TemplateSettings::defaults(),
        ]);
    }

    /** A new template, a copy of the one given (or of the built-in look). */
    public function store(Request $request): RedirectResponse
    {
        $company = Company::current();
        abort_if($company === null, 404);
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'copy' => ['nullable', 'integer']]);

        $source = isset($data['copy']) ? $company->templates()->find($data['copy']) : null;
        $template = $company->templates()->create([
            'name' => $data['name'],
            'settings' => $source?->resolved() ?? TemplateSettings::defaults(),
        ]);
        // The first one is the default: otherwise it would be saved and never used.
        if ($company->templates()->count() === 1) {
            $template->forceFill(['is_default' => true])->save();
        }

        return redirect()->route('templates.edit', $template);
    }

    public function edit(InvoiceTemplate $template): Response
    {
        $this->authorizeOwnership($template);
        $company = Company::current();

        return Inertia::render('TemplateEditor', [
            'template' => ['id' => $template->id, 'name' => $template->name, 'is_default' => $template->is_default, 'settings' => $template->resolved()],
            'hasLogo' => $company?->logo !== null,
            'options' => ['layouts' => TemplateSettings::LAYOUTS, 'fonts' => array_keys(TemplateSettings::FONTS), 'sizes' => array_keys(TemplateSettings::SIZES), 'parts' => TemplateSettings::PARTS],
        ]);
    }

    public function update(Request $request, InvoiceTemplate $template): RedirectResponse
    {
        $this->authorizeOwnership($template);
        $data = $request->validate(['name' => ['required', 'string', 'max:80'], 'settings' => ['required', 'array']]);

        $template->forceFill(['name' => $data['name'], 'settings' => TemplateSettings::clean($data['settings'])])->save();

        return back()->with('status', __('The template was saved.'));
    }

    public function makeDefault(InvoiceTemplate $template): RedirectResponse
    {
        $this->authorizeOwnership($template);

        DB::transaction(function () use ($template): void {
            InvoiceTemplate::query()->where('company_id', $template->company_id)->update(['is_default' => false]);
            $template->forceFill(['is_default' => true])->save();
        });

        return back()->with('status', __('Invoices are printed with this template unless another is chosen.'));
    }

    public function destroy(InvoiceTemplate $template): RedirectResponse
    {
        $this->authorizeOwnership($template);
        $wasDefault = $template->is_default;
        $company = $template->company;
        $template->delete();

        // Invoices that used it print with the default; if the default went, the built-in look takes over.
        if ($wasDefault) {
            $company->templates()->orderBy('id')->first()?->forceFill(['is_default' => true])->save();
        }

        return redirect()->route('templates.index')->with('status', __('Deleted.'));
    }

    /** The PDF of a made-up invoice with the settings the editor holds right now (not saved), for the preview next to the controls. */
    public function preview(Request $request, InvoicePdf $pdf): HttpResponse
    {
        $company = Company::current();
        abort_if($company === null, 404);
        $data = $request->validate(['settings' => ['required', 'array'], 'lang' => ['nullable', 'in:pl,en']]);

        return response($pdf->render(SampleInvoice::for($company), $data['lang'] ?? app()->getLocale(), $data['settings']), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview.pdf"',
        ]);
    }
}
