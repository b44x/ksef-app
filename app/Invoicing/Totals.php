<?php

namespace App\Invoicing;

use B4x\Ksef\Invoice\Invoice as SdkInvoice;
use B4x\Ksef\Invoice\InvoiceLine as SdkLine;
use B4x\Ksef\Invoice\InvoiceTotals;
use B4x\Ksef\Invoice\LineState;
use B4x\Ksef\Invoice\VatRate;
use B4x\Ksef\Support\Decimal;

/** The sums of an invoice as the SDK computes them (tax per rate on the summed net, rounded to the grosz). */
final class Totals
{
    /**
     * @param  list<array{name: string, quantity: string, unit_net_price: string, vat_rate: string}>  $lines
     * @param  list<array{name: string, quantity: string, unit_net_price: string, vat_rate: string}>  $before  for a correction: the corrected invoice's lines as they stood; the totals are then the difference
     * @return array{buckets: list<array{rate: string, net: string, vat: string|null, gross: string}>, net: string, vat: string, gross: string, lines: list<string>}
     */
    public static function of(array $lines, string $currency = 'PLN', array $before = []): array
    {
        $make = static fn (array $l): SdkLine => SdkLine::of($l['name'], SdkInvoiceFactory::trim($l['quantity']), null, SdkInvoiceFactory::trim($l['unit_net_price']), VatRate::from($l['vat_rate']), $currency);
        $sdkLines = array_map($make, $lines);
        $all = [...array_map(static fn (array $l): SdkLine => $make($l)->asBefore(), $before), ...$sdkLines];

        return self::describe(InvoiceTotals::calculate($all, $currency, null), $sdkLines);
    }

    /**
     * The totals of an invoice the SDK has built: for a correction that is the difference to the corrected invoice.
     *
     * @return array{buckets: list<array{rate: string, net: string, vat: string|null, gross: string}>, net: string, vat: string, gross: string, lines: list<string>}
     */
    public static function forInvoice(SdkInvoice $invoice): array
    {
        $current = array_values(array_filter($invoice->lines, static fn (SdkLine $l): bool => $l->state === LineState::Current));

        return self::describe($invoice->totals(), $current);
    }

    /**
     * @param  list<SdkLine>  $current
     * @return array{buckets: list<array{rate: string, net: string, vat: string|null, gross: string}>, net: string, vat: string, gross: string, lines: list<string>}
     */
    private static function describe(InvoiceTotals $totals, array $current): array
    {
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
            'lines' => array_map(static fn (SdkLine $l): string => $l->unitNetPrice->amount->multiply($l->quantity)->roundTo(2)->toString(2), $current),
        ];
    }
}
