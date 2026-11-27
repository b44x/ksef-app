<?php

namespace App\Invoicing;

use B4x\Ksef\Invoice\InvoiceLine as SdkLine;
use B4x\Ksef\Invoice\InvoiceTotals;
use B4x\Ksef\Invoice\VatRate;
use B4x\Ksef\Support\Decimal;

/** The sums of an invoice as the SDK computes them (tax per rate on the summed net, rounded to the grosz). */
final class Totals
{
    /**
     * @param  list<array{name: string, quantity: string, unit_net_price: string, vat_rate: string}>  $lines
     * @return array{buckets: list<array{rate: string, net: string, vat: string|null, gross: string}>, net: string, vat: string, gross: string, lines: list<string>}
     */
    public static function of(array $lines, string $currency = 'PLN'): array
    {
        $sdkLines = array_map(
            static fn (array $l): SdkLine => SdkLine::of($l['name'], SdkInvoiceFactory::trim($l['quantity']), null, SdkInvoiceFactory::trim($l['unit_net_price']), VatRate::from($l['vat_rate']), $currency),
            $lines,
        );

        $totals = InvoiceTotals::calculate($sdkLines, $currency, null);

        return [
            'buckets' => array_map(static fn ($b): array => [
                'rate' => $b->rate->value,
                'net' => $b->net->toString(2),
                'vat' => $b->vat?->toString(2),
                'gross' => $b->net->add($b->vat ?? Decimal::of('0.00'))->toString(2),
            ], $totals->buckets),
            'net' => $totals->net()->toString(2),
            'vat' => $totals->vat()->toString(2),
            'gross' => $totals->gross()->toString(2),
            // The net value of each line, so the form can show it next to the line.
            'lines' => array_map(static fn (SdkLine $l): string => $l->unitNetPrice->amount->multiply($l->quantity)->roundTo(2)->toString(2), $sdkLines),
        ];
    }
}
