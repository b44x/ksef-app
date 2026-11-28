import { router } from '@inertiajs/react';
import { BarChart3, Download } from 'lucide-react';
import { useState } from 'react';
import { BarChart, type MonthPoint } from '../Components/BarChart';
import { Button } from '../Components/Button';
import { Input, Select } from '../Components/Form';
import { Card, EmptyState, PageHeader, Table } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import { useMoney } from '../lib/format';

interface Props {
    period: { key: string; from: string; to: string };
    totals: { currency: string; count: number; net: string; vat: string; gross: string }[];
    byRate: { currency: string; rate: string; net: string; vat: string | null }[];
    customers: { name: string; nip: string | null; currency: string; count: number; gross: string }[];
    monthly: MonthPoint[];
    receivables: {
        currency: string;
        count: number;
        outstanding: string;
        overdue: string;
        overdue_count: number;
        ageing: Record<string, string>;
    }[];
}

export default function Reports({ period, totals, byRate, customers, monthly, receivables }: Props) {
    const t = useT();
    const money = useMoney();
    const [from, setFrom] = useState(period.from);
    const [to, setTo] = useState(period.to);

    const periods: [string, string][] = [
        ['this_month', t('This month')],
        ['last_month', t('Last month')],
        ['this_quarter', t('This quarter')],
        ['last_quarter', t('Last quarter')],
        ['this_year', t('This year')],
        ['last_year', t('Last year')],
        ['custom', t('Custom range')],
    ];
    const go = (query: Record<string, string>) => router.get('/reports', query, { preserveScroll: true });
    const query = new URLSearchParams({ period: period.key, from: period.from, to: period.to }).toString();
    const ageingLabels: Record<string, string> = {
        current: t('Not due yet'),
        '1-30': t('1–30 days late'),
        '31-60': t('31–60 days late'),
        '61-90': t('61–90 days late'),
        '90+': t('Over 90 days late'),
    };

    return (
        <AppLayout>
            <PageHeader
                title={t('Reports')}
                description={t('Sales, VAT, customers and what you are still owed.')}
                actions={
                    <a href={`/reports/sales.csv?${query}`}>
                        <Button variant="secondary">
                            <Download className="size-4" />
                            {t('Sales register (CSV)')}
                        </Button>
                    </a>
                }
            />

            <div className="mb-6 flex flex-wrap items-end gap-3">
                <div className="w-48">
                    <Select
                        aria-label={t('Period')}
                        value={period.key}
                        onChange={(e) =>
                            e.target.value === 'custom'
                                ? go({ period: 'custom', from, to })
                                : go({ period: e.target.value })
                        }
                    >
                        {periods.map(([key, label]) => (
                            <option key={key} value={key}>
                                {label}
                            </option>
                        ))}
                    </Select>
                </div>
                {period.key === 'custom' && (
                    <>
                        <Input type="date" className="w-40" value={from} onChange={(e) => setFrom(e.target.value)} />
                        <Input type="date" className="w-40" value={to} onChange={(e) => setTo(e.target.value)} />
                        <Button variant="secondary" onClick={() => go({ period: 'custom', from, to })}>
                            {t('Show')}
                        </Button>
                    </>
                )}
                <span className="pb-2 text-sm tabular-nums text-muted">
                    {period.from} – {period.to}
                </span>
            </div>

            {totals.length === 0 ? (
                <Card>
                    <EmptyState
                        icon={<BarChart3 className="size-6" />}
                        title={t('No sales in this period.')}
                        description={t('Issued invoices appear here.')}
                    />
                </Card>
            ) : (
                <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {totals
                        .flatMap((s) => [
                            [t('Invoices'), String(s.count), s.currency],
                            [t('Net sales'), money(s.net, s.currency), s.currency],
                            [t('VAT'), money(s.vat, s.currency), s.currency],
                            [t('Gross sales'), money(s.gross, s.currency), s.currency],
                        ])
                        .map(([label, value, currency], i) => (
                            <div key={i} className="rounded-xl border border-border bg-surface p-5 shadow-card">
                                <div className="text-sm text-muted">
                                    {label}
                                    {totals.length > 1 && <span className="ml-1 text-xs">({currency})</span>}
                                </div>
                                <div className="mt-1 text-2xl font-semibold tabular-nums">{value}</div>
                            </div>
                        ))}
                </div>
            )}

            <div className="mb-6 grid gap-6 lg:grid-cols-5">
                <Card
                    title={t('Net sales by month')}
                    description={t('The last twelve months, in PLN.')}
                    className="lg:col-span-3"
                >
                    <BarChart data={monthly} currency="PLN" label={t('Net sales by month')} />
                </Card>
                <Card title={t('VAT by rate')} className="lg:col-span-2">
                    {byRate.length === 0 ? (
                        <p className="text-sm text-muted">{t('No sales in this period.')}</p>
                    ) : (
                        <Table>
                            <thead>
                                <tr>
                                    <th>{t('Rate')}</th>
                                    <th className="text-right">{t('Net total')}</th>
                                    <th className="text-right">{t('VAT total')}</th>
                                </tr>
                            </thead>
                            <tbody className="tabular-nums">
                                {byRate.map((r) => (
                                    <tr key={r.currency + r.rate}>
                                        <td>
                                            {r.rate}
                                            {/^\d/.test(r.rate) && !r.rate.includes(' ') ? '%' : ''}
                                            {totals.length > 1 && (
                                                <span className="ml-1 text-xs text-muted">{r.currency}</span>
                                            )}
                                        </td>
                                        <td className="text-right">{money(r.net)}</td>
                                        <td className="text-right">{r.vat === null ? '—' : money(r.vat)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}
                </Card>
            </div>

            <div className="grid gap-6 lg:grid-cols-2">
                <Card title={t('Top customers')}>
                    {customers.length === 0 ? (
                        <p className="text-sm text-muted">{t('No sales in this period.')}</p>
                    ) : (
                        <Table>
                            <thead>
                                <tr>
                                    <th>{t('Buyer')}</th>
                                    <th className="text-right">{t('Invoices')}</th>
                                    <th className="text-right">{t('Gross')}</th>
                                </tr>
                            </thead>
                            <tbody className="tabular-nums">
                                {customers.map((c) => (
                                    <tr key={c.name + c.currency}>
                                        <td className="font-sans">{c.name}</td>
                                        <td className="text-right">{c.count}</td>
                                        <td className="text-right">{money(c.gross, c.currency)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}
                </Card>

                <Card
                    title={t('Receivables')}
                    description={t('What you are still owed, by how late it is. All time, not only this period.')}
                >
                    {receivables.length === 0 ? (
                        <p className="text-sm text-muted">{t('Nobody owes you anything.')}</p>
                    ) : (
                        receivables.map((r) => (
                            <div key={r.currency} className="mb-4 last:mb-0">
                                <div className="mb-2 flex items-baseline justify-between">
                                    <span className="text-sm text-muted">
                                        {t(':count invoices', { count: r.count })} · {r.currency}
                                    </span>
                                    <span className="text-xl font-semibold tabular-nums">
                                        {money(r.outstanding, r.currency)}
                                    </span>
                                </div>
                                <dl className="divide-y divide-border text-sm">
                                    {Object.entries(r.ageing).map(([key, amount]) => (
                                        <div key={key} className="flex justify-between py-1.5">
                                            <dt className={key === 'current' ? 'text-muted' : 'text-danger'}>
                                                {ageingLabels[key]}
                                            </dt>
                                            <dd className="tabular-nums">{money(amount)}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>
                        ))
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
