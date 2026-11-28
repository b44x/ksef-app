<?php

namespace App\Pdf;

/**
 * How a printed invoice looks: layout, colour, type, logo and which parts are shown. Stored as JSON on the template;
 * everything that comes from outside goes through {@see self::clean()}, so a template can never hold a value the page
 * does not know how to draw (and a stored one that is old or damaged falls back to the default for that key).
 */
final class TemplateSettings
{
    public const LAYOUTS = ['classic', 'modern', 'minimal'];

    /** The fonts dompdf ships with that have the Polish letters. */
    public const FONTS = ['sans' => 'DejaVu Sans', 'serif' => 'DejaVu Serif', 'mono' => 'DejaVu Sans Mono'];

    public const SIZES = ['small' => 8.0, 'normal' => 9.0, 'large' => 10.0];

    public const PARTS = ['unit', 'vat_column', 'seller_email', 'buyer_email', 'bank_account', 'payment', 'notes', 'words', 'qr', 'signatures', 'footer'];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            'layout' => 'classic',
            'accent' => '#0a9a7b',
            'font' => 'sans',
            'size' => 'normal',
            'stripes' => false,
            'logo' => ['show' => true, 'position' => 'left', 'height' => 48],
            'show' => array_fill_keys(self::PARTS, true) + ['signatures' => false],
            'footer_text' => '',
        ];
    }

    /**
     * Whatever came in, as valid settings: unknown keys are dropped, bad values replaced by the default.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public static function clean(array $input): array
    {
        $d = self::defaults();
        $logo = is_array($input['logo'] ?? null) ? $input['logo'] : [];
        $show = is_array($input['show'] ?? null) ? $input['show'] : [];
        $accent = $input['accent'] ?? null;

        $clean = [
            'layout' => in_array($input['layout'] ?? null, self::LAYOUTS, true) ? $input['layout'] : $d['layout'],
            'accent' => is_string($accent) && preg_match('/^#[0-9a-f]{6}$/i', $accent) === 1 ? strtolower($accent) : $d['accent'],
            'font' => array_key_exists($input['font'] ?? '', self::FONTS) ? $input['font'] : $d['font'],
            'size' => array_key_exists($input['size'] ?? '', self::SIZES) ? $input['size'] : $d['size'],
            'stripes' => (bool) ($input['stripes'] ?? $d['stripes']),
            'logo' => [
                'show' => (bool) ($logo['show'] ?? $d['logo']['show']),
                'position' => in_array($logo['position'] ?? null, ['left', 'right'], true) ? $logo['position'] : $d['logo']['position'],
                'height' => max(20, min(120, (int) ($logo['height'] ?? $d['logo']['height']))),
            ],
            'show' => [],
            'footer_text' => mb_substr(trim((string) ($input['footer_text'] ?? '')), 0, 300),
        ];
        foreach (self::PARTS as $part) {
            $clean['show'][$part] = (bool) ($show[$part] ?? $d['show'][$part]);
        }

        return $clean;
    }

    /** A colour for text on the accent: white on dark colours, black on light ones. */
    public static function contrast(string $hex): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');

        return (0.299 * $r + 0.587 * $g + 0.114 * $b) > 160 ? '#111827' : '#ffffff';
    }

    /** The accent mixed with white, for light backgrounds ($amount of white, 0..1). */
    public static function tint(string $hex, float $amount): string
    {
        [$r, $g, $b] = sscanf($hex, '#%02x%02x%02x');
        $mix = static fn (int $c): int => (int) round($c + (255 - $c) * $amount);

        return sprintf('#%02x%02x%02x', $mix($r), $mix($g), $mix($b));
    }
}
