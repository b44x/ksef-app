<?php

namespace App\Pdf;

use B4x\Ksef\Qr\QrCodeRenderer;
use B4x\Ksef\Support\Decimal;
use B4x\Ksef\Support\PolishAmountInWords;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;

/** Draws the printed invoice (A4) from the data of {@see InvoiceView}, in the language asked for. */
final class InvoicePdf
{
    /**
     * @param  array<string, mixed>  $invoice
     */
    public function render(array $invoice, string $locale): string
    {
        $dompdf = new Dompdf((new Options)
            ->set('defaultFont', 'DejaVu Sans')       // has the Polish letters
            ->set('isRemoteEnabled', false)           // nothing is fetched while drawing
            ->set('isPhpEnabled', false)
            ->set('chroot', resource_path('views/pdf')));
        $dompdf->setPaper('A4');
        $dompdf->loadHtml($this->html($invoice, $locale));
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * The page as HTML: what the PDF is drawn from, and what the tests read.
     *
     * @param  array<string, mixed>  $invoice
     */
    public function html(array $invoice, string $locale): string
    {
        $previous = App::getLocale();
        App::setLocale($locale);

        try {
            return View::make('pdf.invoice', [
                'i' => $invoice,
                'words' => $this->words($invoice, $locale),
                'qr' => $this->images($invoice['qr'] ?? null),
            ])->render();
        } finally {
            App::setLocale($previous);
        }
    }

    /** The total in words, as Polish invoices have it: only for złoty, and only in Polish. */
    private function words(array $invoice, string $locale): ?string
    {
        if ($locale !== 'pl' || $invoice['currency'] !== 'PLN' || ! isset($invoice['total']['gross'])) {
            return null;
        }

        return PolishAmountInWords::pln(Decimal::of($invoice['total']['gross']));
    }

    /**
     * The QR codes as images the PDF can embed. The caption is printed by the page, not drawn into the image.
     *
     * @param  array{verification: array{url: string, caption: string}, issuer: array{url: string, caption: string}|null}|null  $codes
     * @return array{verification: array{src: string, caption: string}, issuer: array{src: string, caption: string}|null}|null
     */
    private function images(?array $codes): ?array
    {
        if ($codes === null) {
            return null;
        }

        $renderer = new QrCodeRenderer(300);
        $image = static fn (array $c): array => ['src' => 'data:image/svg+xml;base64,'.base64_encode($renderer->svg($c['url'])), 'caption' => $c['caption']];

        return ['verification' => $image($codes['verification']), 'issuer' => $codes['issuer'] === null ? null : $image($codes['issuer'])];
    }
}
