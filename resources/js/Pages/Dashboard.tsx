import { router, usePage } from '@inertiajs/react';
import { AlertTriangle, Check, FilePlus2, FileText, PlugZap, Send, TrendingUp, Wallet } from 'lucide-react';
import { BarChart, type MonthPoint } from '../Components/BarChart';
import { Alert, Badge, Card, EmptyState, PageHeader, Table } from '../Components/Ui';
import { Button, ButtonLink } from '../Components/Button';
import ConnectionReport from '../Components/ConnectionReport';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import { useMoney } from '../lib/format';
import type { SharedProps } from '../types';

interface Props {
    setup: { key: string; label: string; href: string; done: boolean }[];
    kpi: {
        sales: { currency: string; count: number; net: string; vat: string; gross: string }[];
        receivables: { currency: string; count: number; outstanding: string; overdue: string; overdue_count: number }[];
        monthly: MonthPoint[];
    };
    stats: { drafts: number; contractors: number; products: number };
    offline: { waiting: number; overdue: number; next_deadline: string | null; undated: number } | null;
    recent: {
        id: number;
        number: string | null;
        status_label: string;
        issue_date: string;
        contractor: string;
        currency: string;
        gross: string;
    }[];
    details: { name: string; nip: string; environment: string; environment_key: string; auth_method: string };
}

function Stat({
    icon,
    label,
    value,
    href,
    tone = 'primary',
}: {
    icon: React.ReactNode;
    label: string;
    value: string | number;
    href: string;
    tone?: 'primary' | 'danger';
}) {
    return (
        <a
            href={href}
            className="flex items-center gap-4 rounded-xl border border-border bg-surface p-5 shadow-card transition-colors hover:border-border-strong"
        >
            <div
                className={`flex size-11 items-center justify-center rounded-lg ${tone === 'danger' ? 'bg-danger-soft text-danger' : 'bg-primary-soft text-primary'}`}
            >
                {icon}
            </div>
            <div>
                <div className="whitespace-nowrap text-xl font-semibold tabular-nums">{value}</div>
                <div className="text-sm text-muted">{label}</div>
            </div>
        </a>
    );
}

export default function Dashboard({ setup, kpi, stats, offline, recent, details }: Props) {
    const t = useT();
    const money = useMoney();
    const { report, locale } = usePage<SharedProps>().props;
    const live = details.environment_key === 'production';

    return (
        <AppLayout>
            <PageHeader
                title={details.name}
                description={`NIP ${details.nip}`}
                badge={<Badge tone={live ? 'success' : 'warning'}>{details.environment}</Badge>}
                actions={
                    <ButtonLink href="/invoices/create">
                        <FilePlus2 className="size-4" />
                        {t('New invoice')}
                    </ButtonLink>
                }
            />

            {setup.length > 0 && (
                <Card
                    title={t('Get started')}
                    description={t(':done of :total steps done', {
                        done: setup.filter((s) => s.done).length,
                        total: setup.length,
                    })}
                    className="mb-6"
                >
                    <ol className="grid gap-2 sm:grid-cols-2 lg:grid-cols-5">
                        {setup.map((s, i) => (
                            <li key={s.key}>
                                <a
                                    href={s.href}
                                    className={`flex h-full items-start gap-3 rounded-lg border p-3 text-sm transition-colors hover:bg-surface-muted ${s.done ? 'border-border text-muted' : 'border-primary/40 bg-primary-soft text-text'}`}
                                >
                                    <span
                                        className={`mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full text-xs font-semibold ${s.done ? 'bg-success text-white' : 'bg-primary text-primary-text'}`}
                                    >
                                        {s.done ? <Check className="size-3" /> : i + 1}
                                    </span>
                                    <span className={s.done ? 'line-through' : 'font-medium'}>{s.label}</span>
                                </a>
                            </li>
                        ))}
                    </ol>
                </Card>
            )}

            {offline && (
                <div className="mb-6">
                    <Alert tone={offline.overdue > 0 ? 'danger' : 'warning'}>
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div className="font-medium">
                                    {t(':count offline invoice(s) waiting for delivery to KSeF', {
                                        count: offline.waiting,
                                    })}
                                </div>
                                <div className="text-sm">
                                    {offline.overdue > 0 && (
                                        <span className="font-medium">
                                            {t(':count past the deadline.', { count: offline.overdue })}{' '}
                                        </span>
                                    )}
                                    {offline.next_deadline &&
                                        t('Nearest deadline: :date.', {
                                            date: new Date(offline.next_deadline).toLocaleDateString(
                                                locale === 'pl' ? 'pl-PL' : 'en-GB',
                                                { dateStyle: 'long' },
                                            ),
                                        })}
                                    {offline.undated > 0 &&
                                        ` ${t(':count without a deadline yet (a failure has not ended).', { count: offline.undated })}`}
                                </div>
                            </div>
                            <Button variant="secondary" size="sm" onClick={() => router.post('/invoices/send-offline')}>
                                <Send className="size-4" />
                                {t('Deliver all to KSeF')}
                            </Button>
                        </div>
                    </Alert>
                </div>
            )}

            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <Stat
                    icon={<TrendingUp className="size-5" />}
                    label={t('Net sales this month')}
                    value={money(kpi.sales.find((s) => s.currency === 'PLN')?.net ?? '0', 'PLN')}
                    href="/reports"
                />
                <Stat
                    icon={<Wallet className="size-5" />}
                    label={t('You are owed')}
                    value={money(kpi.receivables.find((r) => r.currency === 'PLN')?.outstanding ?? '0', 'PLN')}
                    href="/invoices"
                />
                <Stat
                    icon={<AlertTriangle className="size-5" />}
                    label={t('Overdue')}
                    value={money(kpi.receivables.find((r) => r.currency === 'PLN')?.overdue ?? '0', 'PLN')}
                    href="/invoices"
                    tone={
                        (kpi.receivables.find((r) => r.currency === 'PLN')?.overdue_count ?? 0) > 0
                            ? 'danger'
                            : 'primary'
                    }
                />
                <Stat
                    icon={<FileText className="size-5" />}
                    label={t('Drafts')}
                    value={stats.drafts}
                    href="/invoices"
                />
            </div>

            <div className="mb-6 grid gap-6 lg:grid-cols-5">
                <Card
                    title={t('Net sales by month')}
                    description={t('The last twelve months, in PLN.')}
                    className="lg:col-span-3"
                >
                    <BarChart data={kpi.monthly} currency="PLN" label={t('Net sales by month')} />
                </Card>
                <Card title={t('Recent invoices')} className="lg:col-span-5 lg:order-last">
                    {recent.length === 0 ? (
                        <EmptyState
                            icon={<FileText className="size-6" />}
                            title={t('No invoices yet.')}
                            description={t('Create the first one; it stays a draft until you issue it.')}
                            action={<ButtonLink href="/invoices/create">{t('New invoice')}</ButtonLink>}
                        />
                    ) : (
                        <Table>
                            <thead>
                                <tr>
                                    <th>{t('Number')}</th>
                                    <th>{t('Buyer')}</th>
                                    <th className="text-right">{t('Gross')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {recent.map((i) => (
                                    <tr key={i.id}>
                                        <td>
                                            <a
                                                href={`/invoices/${i.id}`}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {i.number ?? t('Draft')}
                                            </a>
                                            <div className="text-xs text-muted">{i.issue_date}</div>
                                        </td>
                                        <td>{i.contractor}</td>
                                        <td className="text-right tabular-nums">{money(i.gross, i.currency)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}
                </Card>

                <Card
                    title={t('KSeF connection')}
                    description={details.auth_method}
                    className="lg:col-span-2"
                    action={
                        <Button variant="secondary" size="sm" onClick={() => router.post('/company/check')}>
                            <PlugZap className="size-4" />
                            {t('Check the connection')}
                        </Button>
                    }
                >
                    {report ? (
                        <ConnectionReport report={report} />
                    ) : (
                        <p className="text-sm text-muted">
                            {t('Check that KSeF accepts your login and that nothing stands in the way of sending.')}
                        </p>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}
