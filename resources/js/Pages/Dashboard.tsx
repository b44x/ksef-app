import { router, usePage } from '@inertiajs/react';
import { FilePlus2, FileText, Package, PlugZap, Users } from 'lucide-react';
import { Badge, Card, EmptyState, PageHeader, Table } from '../Components/Ui';
import { Button, ButtonLink } from '../Components/Button';
import ConnectionReport from '../Components/ConnectionReport';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import { useMoney } from '../lib/format';
import type { SharedProps } from '../types';

interface Props {
    stats: { drafts: number; contractors: number; products: number };
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

function Stat({ icon, label, value, href }: { icon: React.ReactNode; label: string; value: number; href: string }) {
    return (
        <a
            href={href}
            className="flex items-center gap-4 rounded-xl border border-border bg-surface p-5 shadow-card transition-colors hover:border-border-strong"
        >
            <div className="flex size-11 items-center justify-center rounded-lg bg-primary-soft text-primary">
                {icon}
            </div>
            <div>
                <div className="text-2xl font-semibold tabular-nums">{value}</div>
                <div className="text-sm text-muted">{label}</div>
            </div>
        </a>
    );
}

export default function Dashboard({ stats, recent, details }: Props) {
    const t = useT();
    const money = useMoney();
    const { report } = usePage<SharedProps>().props;
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

            <div className="mb-6 grid gap-4 sm:grid-cols-3">
                <Stat
                    icon={<FileText className="size-5" />}
                    label={t('Drafts')}
                    value={stats.drafts}
                    href="/invoices"
                />
                <Stat
                    icon={<Users className="size-5" />}
                    label={t('Contractors')}
                    value={stats.contractors}
                    href="/contractors"
                />
                <Stat
                    icon={<Package className="size-5" />}
                    label={t('Products')}
                    value={stats.products}
                    href="/products"
                />
            </div>

            <div className="grid gap-6 lg:grid-cols-5">
                <Card title={t('Recent invoices')} className="lg:col-span-3">
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
