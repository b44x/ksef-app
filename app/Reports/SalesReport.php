<?php

namespace App\Reports;

use App\Enums\InvoiceStatus;
use App\Models\Company;
use App\Models\Invoice;
use B4x\Ksef\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Sales figures from the invoices that count: issued, sent or accepted (a draft is not a sale yet, a rejected invoice
 * is not one at all). Sums are per currency, never mixed, and added up with the SDK's decimals.
 */
final class SalesReport
{
    public function __construct(private readonly Company $company) {}

    /**
     * @return Collection<int, Invoice>
     */
    public function invoices(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return $this->company->invoices()
            ->with('contractor:id,name,nip')->withSum('payments', 'amount')
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Sending, InvoiceStatus::Accepted])
            ->whereNotNull('gross_total')
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('issue_date')->orderBy('id')->get();
    }

    /**
     * Count, net, VAT and gross for the period, per currency.
     *
     * @return list<array{currency: string, count: int, net: string, vat: string, gross: string}>
     */
    public function totals(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sums = [];
        foreach ($this->invoices($from, $to) as $invoice) {
            $c = $invoice->currency;
            $sums[$c] ??= ['currency' => $c, 'count' => 0, 'net' => Decimal::zero(), 'vat' => Decimal::zero(), 'gross' => Decimal::zero()];
            $sums[$c]['count']++;
            $sums[$c]['net'] = $sums[$c]['net']->add(Decimal::of((string) $invoice->net_total));
            $sums[$c]['vat'] = $sums[$c]['vat']->add(Decimal::of((string) $invoice->vat_total));
            $sums[$c]['gross'] = $sums[$c]['gross']->add(Decimal::of((string) $invoice->gross_total));
        }

        return array_values(array_map(static fn (array $s): array => [
            'currency' => $s['currency'], 'count' => $s['count'], 'net' => $s['net']->toString(2), 'vat' => $s['vat']->toString(2), 'gross' => $s['gross']->toString(2),
        ], $sums));
    }

    /**
     * Net and VAT per VAT rate, as the invoices were summed when issued.
     *
     * @return list<array{currency: string, rate: string, net: string, vat: string|null}>
     */
    public function byRate(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = [];
        foreach ($this->invoices($from, $to) as $invoice) {
            foreach ($invoice->vat_summary ?? [] as $bucket) {
                $key = $invoice->currency.'|'.$bucket['rate'];
                $rows[$key] ??= ['currency' => $invoice->currency, 'rate' => $bucket['rate'], 'net' => Decimal::zero(), 'vat' => $bucket['vat'] === null ? null : Decimal::zero()];
                $rows[$key]['net'] = $rows[$key]['net']->add(Decimal::of($bucket['net']));
                if ($bucket['vat'] !== null) {
                    $rows[$key]['vat'] = ($rows[$key]['vat'] ?? Decimal::zero())->add(Decimal::of($bucket['vat']));
                }
            }
        }
        ksort($rows);

        return array_values(array_map(static fn (array $r): array => [
            'currency' => $r['currency'], 'rate' => $r['rate'], 'net' => $r['net']->toString(2), 'vat' => $r['vat']?->toString(2),
        ], $rows));
    }

    /**
     * The buyers that bought the most, by gross value.
     *
     * @return list<array{name: string, nip: string|null, currency: string, count: int, gross: string}>
     */
    public function topCustomers(CarbonImmutable $from, CarbonImmutable $to, int $limit = 8): array
    {
        $rows = [];
        foreach ($this->invoices($from, $to) as $invoice) {
            $key = $invoice->contractor_id.'|'.$invoice->currency;
            $rows[$key] ??= ['name' => $invoice->contractor->name, 'nip' => $invoice->contractor->nip, 'currency' => $invoice->currency, 'count' => 0, 'gross' => Decimal::zero()];
            $rows[$key]['count']++;
            $rows[$key]['gross'] = $rows[$key]['gross']->add(Decimal::of((string) $invoice->gross_total));
        }
        usort($rows, static fn (array $a, array $b): int => $b['gross']->compare($a['gross']));

        return array_map(static fn (array $r): array => ['name' => $r['name'], 'nip' => $r['nip'], 'currency' => $r['currency'], 'count' => $r['count'], 'gross' => $r['gross']->toString(2)], array_slice($rows, 0, $limit));
    }

    /**
     * Net sales per month for the $months months up to and including the one containing $end, in one currency.
     *
     * @return list<array{month: string, net: string, gross: string}>
     */
    public function monthly(CarbonImmutable $end, int $months = 12, string $currency = 'PLN'): array
    {
        $start = $end->startOfMonth()->subMonths($months - 1);
        $buckets = [];
        for ($m = 0; $m < $months; $m++) {
            $buckets[$start->addMonths($m)->format('Y-m')] = ['net' => Decimal::zero(), 'gross' => Decimal::zero()];
        }
        foreach ($this->invoices($start, $end->endOfMonth())->where('currency', $currency) as $invoice) {
            $key = $invoice->issue_date->format('Y-m');
            $buckets[$key]['net'] = $buckets[$key]['net']->add(Decimal::of((string) $invoice->net_total));
            $buckets[$key]['gross'] = $buckets[$key]['gross']->add(Decimal::of((string) $invoice->gross_total));
        }

        $out = [];
        foreach ($buckets as $month => $b) {
            $out[] = ['month' => $month, 'net' => $b['net']->toString(2), 'gross' => $b['gross']->toString(2)];
        }

        return $out;
    }

    /**
     * What is still owed to the company, per currency, with how late it is: not yet due, then 1–30, 31–60, 61–90 and over 90 days.
     *
     * @return list<array{currency: string, count: int, outstanding: string, overdue: string, overdue_count: int, ageing: array<string, string>}>
     */
    public function receivables(?CarbonImmutable $today = null): array
    {
        $today ??= CarbonImmutable::today();
        $invoices = $this->company->invoices()->withSum('payments', 'amount')
            ->whereIn('status', [InvoiceStatus::Issued, InvoiceStatus::Sending, InvoiceStatus::Accepted])
            ->whereNotNull('gross_total')->get();

        $rows = [];
        foreach ($invoices as $invoice) {
            $left = $invoice->outstanding();
            if ($left->isZero()) {
                continue;
            }
            $c = $invoice->currency;
            $rows[$c] ??= ['count' => 0, 'outstanding' => Decimal::zero(), 'overdue' => Decimal::zero(), 'overdue_count' => 0, 'ageing' => array_fill_keys(['current', '1-30', '31-60', '61-90', '90+'], Decimal::zero())];
            $rows[$c]['count']++;
            $rows[$c]['outstanding'] = $rows[$c]['outstanding']->add($left);

            $late = $invoice->due_date === null ? 0 : (int) $invoice->due_date->startOfDay()->diffInDays($today, false);
            $bucket = match (true) {
                $late <= 0 => 'current',
                $late <= 30 => '1-30',
                $late <= 60 => '31-60',
                $late <= 90 => '61-90',
                default => '90+',
            };
            $rows[$c]['ageing'][$bucket] = $rows[$c]['ageing'][$bucket]->add($left);
            if ($late > 0) {
                $rows[$c]['overdue'] = $rows[$c]['overdue']->add($left);
                $rows[$c]['overdue_count']++;
            }
        }

        $out = [];
        foreach ($rows as $currency => $r) {
            $out[] = [
                'currency' => $currency, 'count' => $r['count'], 'outstanding' => $r['outstanding']->toString(2), 'overdue' => $r['overdue']->toString(2), 'overdue_count' => $r['overdue_count'],
                'ageing' => array_map(static fn (Decimal $d): string => $d->toString(2), $r['ageing']),
            ];
        }

        return $out;
    }
}
