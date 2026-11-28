<?php

namespace Tests\Feature;

use App\Invoicing\IssueInvoice;
use App\Ksef\KsefGateway;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceTemplate;
use App\Models\User;
use App\Pdf\InvoicePdf;
use App\Pdf\InvoiceView;
use App\Pdf\TemplateSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FakeGateway;
use Tests\TestCase;

class TemplateTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->create());
        $this->app->instance(KsefGateway::class, new FakeGateway);
        $this->company = Company::create(['name' => 'Seller sp. z o.o.', 'nip' => '5265877635', 'address_line1' => 'ul. Prosta 1', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
    }

    private function issued(?int $templateId = null): Invoice
    {
        $buyer = $this->company->contractors()->firstOrCreate(['nip' => '7762811692'], ['name' => 'Buyer', 'country' => 'PL', 'address_line1' => 'ul. Długa 2']);
        $series = $this->company->numberingSeries()->firstOrCreate(['name' => 'Sales'], ['format' => 'FV/{NNN}/{YYYY}', 'reset' => 'yearly', 'is_default' => true]);
        $invoice = $this->company->invoices()->create(['contractor_id' => $buyer->id, 'numbering_series_id' => $series->id, 'issue_date' => '2026-12-01', 'invoice_template_id' => $templateId]);
        $invoice->lines()->create(['position' => 1, 'name' => 'Consulting', 'unit' => 'godz.', 'quantity' => '10', 'unit_net_price' => '150.00', 'vat_rate' => '23']);

        return app(IssueInvoice::class)($invoice);
    }

    public function test_settings_from_outside_are_cleaned_to_what_the_page_can_draw(): void
    {
        $clean = TemplateSettings::clean(['layout' => 'wild', 'accent' => 'red; background:url(x)', 'font' => 'comic', 'size' => 'huge', 'logo' => ['height' => 9999, 'position' => 'middle'], 'show' => ['qr' => false, 'evil' => true], 'footer_text' => str_repeat('x', 500), 'extra' => 1]);

        $this->assertSame('classic', $clean['layout']);
        $this->assertSame('#0a9a7b', $clean['accent'], 'Anything but #rrggbb is not a colour.');
        $this->assertSame('sans', $clean['font']);
        $this->assertSame('normal', $clean['size']);
        $this->assertSame(120, $clean['logo']['height']);
        $this->assertSame('left', $clean['logo']['position']);
        $this->assertFalse($clean['show']['qr']);
        $this->assertArrayNotHasKey('evil', $clean['show']);
        $this->assertArrayNotHasKey('extra', $clean);
        $this->assertSame(300, mb_strlen($clean['footer_text']));
        $this->assertSame($clean, TemplateSettings::clean($clean), 'Cleaning is idempotent.');
    }

    public function test_every_layout_and_font_draws_a_pdf_and_the_settings_show_in_it(): void
    {
        $view = app(InvoiceView::class)->sales($this->issued());
        $pdf = app(InvoicePdf::class);

        foreach (TemplateSettings::LAYOUTS as $layout) {
            foreach (array_keys(TemplateSettings::FONTS) as $font) {
                $this->assertStringStartsWith('%PDF', $pdf->render($view, 'pl', ['layout' => $layout, 'font' => $font]));
            }
        }

        $html = $pdf->html($view, 'pl', ['accent' => '#ff0000', 'show' => ['unit' => false, 'notes' => false, 'words' => false], 'footer_text' => 'Konto: PL00 1111', 'stripes' => true]);
        $this->assertStringContainsString('#ff0000', $html);
        $this->assertStringNotContainsString('Jednostka', $html);
        $this->assertStringNotContainsString('Słownie', $html);
        $this->assertStringContainsString('Konto: PL00 1111', $html);
        $this->assertStringNotContainsString('Faktura z Krajowego Systemu', $html, 'A footer text replaces the line about KSeF.');
    }

    public function test_the_logo_is_stored_printed_and_refused_when_it_is_not_an_image(): void
    {
        $this->post('/company/logo', ['logo' => UploadedFile::fake()->image('logo.png', 200, 80)])->assertSessionHasNoErrors();
        $this->assertStringStartsWith('data:image/png;base64,', (string) $this->company->fresh()->logo);

        $html = app(InvoicePdf::class)->html(app(InvoiceView::class)->sales($this->issued()), 'pl');
        $this->assertStringContainsString('data:image/png;base64,', $html);
        $without = app(InvoicePdf::class)->html(app(InvoiceView::class)->sales($this->issued()), 'pl', ['logo' => ['show' => false]]);
        $this->assertStringNotContainsString('data:image/png', $without);

        $this->post('/company/logo', ['logo' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('logo');
        $this->post('/company/logo', ['logo' => UploadedFile::fake()->image('big.png')->size(900)])->assertSessionHasErrors('logo');

        $this->delete('/company/logo');
        $this->assertNull($this->company->fresh()->logo);
    }

    public function test_templates_are_created_edited_defaulted_and_deleted_and_the_first_is_the_default(): void
    {
        $this->post('/templates', ['name' => 'Moja'])->assertRedirect();
        $first = $this->company->templates()->firstOrFail();
        $this->assertTrue($first->is_default);

        $this->put("/templates/{$first->id}", ['name' => 'Moja', 'settings' => ['layout' => 'modern', 'accent' => '#2563eb']])->assertSessionHasNoErrors();
        $this->assertSame('modern', $first->fresh()->settings['layout']);

        $this->post('/templates', ['name' => 'Kopia', 'copy' => $first->id]);
        $copy = $this->company->templates()->where('name', 'Kopia')->firstOrFail();
        $this->assertSame('#2563eb', $copy->settings['accent'], 'A copy starts as the template it was made from.');
        $this->assertFalse($copy->is_default);

        $this->post("/templates/{$copy->id}/default");
        $this->assertTrue($copy->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);

        $this->delete("/templates/{$copy->id}")->assertRedirect('/templates');
        $this->assertTrue($first->fresh()->is_default, 'When the default is deleted another takes over.');

        $this->get('/templates')->assertInertia(fn (Assert $page) => $page->component('Templates')->has('templates', 1));
        $this->get("/templates/{$first->id}/edit")->assertInertia(fn (Assert $page) => $page->component('TemplateEditor')->where('template.settings.layout', 'modern'));
    }

    public function test_an_invoice_is_printed_with_its_template_else_the_default_else_the_built_in_look(): void
    {
        $this->assertSame(TemplateSettings::defaults(), $this->issued()->printSettings());

        $default = $this->company->templates()->create(['name' => 'Default', 'settings' => ['accent' => '#111111']]);
        $default->forceFill(['is_default' => true])->save();
        $chosen = $this->company->templates()->create(['name' => 'Chosen', 'settings' => ['accent' => '#222222', 'layout' => 'minimal']]);

        $this->assertSame('#111111', $this->issued()->printSettings()['accent']);
        $with = $this->issued($chosen->id);
        $this->assertSame('#222222', $with->printSettings()['accent']);

        $chosen->delete();
        $this->assertNull($with->fresh()->invoice_template_id, 'A deleted template falls back to the default.');
        $this->assertSame('#111111', $with->fresh()->printSettings()['accent']);
    }

    public function test_the_preview_and_the_download_use_the_settings(): void
    {
        $response = $this->postJson('/templates/preview', ['settings' => ['layout' => 'modern'], 'lang' => 'en']);
        $response->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());

        $this->postJson('/templates/preview', [])->assertUnprocessable();
        $this->get('/invoices/'.$this->issued()->id.'/pdf')->assertOk();
    }

    public function test_templates_of_another_company_are_out_of_reach(): void
    {
        $other = Company::create(['name' => 'Other', 'nip' => '7762811692', 'environment' => 'test', 'auth_method' => 'token', 'credentials' => ['token' => 't']]);
        $theirs = $other->templates()->create(['name' => 'Theirs', 'settings' => []]);

        $this->get("/templates/{$theirs->id}/edit")->assertNotFound();
        $this->put("/templates/{$theirs->id}", ['name' => 'x', 'settings' => []])->assertNotFound();
        $this->delete("/templates/{$theirs->id}")->assertNotFound();
        $this->assertInstanceOf(InvoiceTemplate::class, $theirs->fresh());

    }
}
