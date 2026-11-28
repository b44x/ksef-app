import { FilePlus2, FileText, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { ButtonLink } from '../Components/Button';
import { Input } from '../Components/Form';
import { Badge, Card, EmptyState, PageHeader, Table } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import { useMoney } from '../lib/format';

type Tone = 'warning' | 'primary' | 'success' | 'danger' | 'neutral';

interface Row {
    id: number;
    number: string | null;
    status: string;
    status_label: string;
    status_tone: Tone;
    offline: boolean;
    offline_deadline: string | null;
    issue_date: string;
    contractor: string;
    currency: string;
    gross: string;
    payment_status: string | null;
    payment_label: string | null;
    payment_tone: Tone | null;
    outstanding: string | null;
    due_date: string | null;
    overdue: boolean;
    is_correction: boolean;
}

type Filter = 'all' | 'draft' | 'unpaid' | 'overdue' | 'paid' | 'problems';

const matches = (row: Row, filter: Filter): boolean => {
    switch (filter) {
        case 'draft':
            return row.status === 'draft';
        case 'unpaid':
            return row.status !== 'draft' && row.payment_status !== 'paid';
        case 'overdue':
            return row.overdue;
        case 'paid':
            return row.payment_status === 'paid';
        case 'problems':
            return row.status === 'rejected' || (row.offline && row.status === 'issued');
        default:
            return true;
    }
};

export default function Invoices({ invoices }: { invoices: Row[] }) {
    const t = useT();
    const money = useMoney();
    const [filter, setFilter] = useState<Filter>('all');
    const [query, setQuery] = useState('');

    const filters: { key: Filter; label: string }[] = [
        { key: 'all', label: t('All') },
        { key: 'draft', label: t('Drafts') },
        { key: 'unpaid', label: t('Unpaid') },
        { key: 'overdue', label: t('Overdue') },
        { key: 'paid', label: t('Paid') },
        { key: 'problems', label: t('Needs attention') },
    ];

    const visible = useMemo(() => {
        const q = query.trim().toLowerCase();
        return invoices.filter(
            (i) =>
                matches(i, filter) &&
                (q === '' || (i.number ?? '').toLowerCase().includes(q) || i.contractor.toLowerCase().includes(q)),
        );
    }, [invoices, filter, query]);
    const count = (key: Filter) => invoices.filter((i) => matches(i, key)).length;

    return (
        <AppLayout>
            <PageHeader
                title={t('Invoices')}
                description={t('Everything you issue, from drafts to what KSeF accepted.')}
                actions={
                    <ButtonLink href="/invoices/create">
                        <FilePlus2 className="size-4" />
                        {t('New invoice')}
                    </ButtonLink>
                }
            />

            {invoices.length > 0 && (
                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-wrap gap-1.5" role="tablist" aria-label={t('Filter')}>
                        {filters.map((f) => (
                            <button
                                key={f.key}
                                type="button"
                                role="tab"
                                aria-selected={filter === f.key}
                                onClick={() => setFilter(f.key)}
                                className={`rounded-full border px-3 py-1 text-sm transition-colors ${filter === f.key ? 'border-primary bg-primary-soft font-medium text-primary' : 'border-border text-muted hover:bg-surface-muted'}`}
                            >
                                {f.label}
                                <span className="ml-1.5 tabular-nums opacity-70">{count(f.key)}</span>
                            </button>
                        ))}
                    </div>
                    <div className="relative w-full sm:w-64">
                        <Search className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted" />
                        <Input
                            className="pl-9"
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder={t('Search by number or buyer')}
                            aria-label={t('Search by number or buyer')}
                        />
                    </div>
                </div>
            )}

            <Card className="[&>div]:p-0">
                {invoices.length === 0 ? (
                    <EmptyState
                        icon={<FileText className="size-6" />}
                        title={t('No invoices yet.')}
                        description={t('Create the first one; it stays a draft until you issue it.')}
                        action={<ButtonLink href="/invoices/create">{t('New invoice')}</ButtonLink>}
                    />
                ) : visible.length === 0 ? (
                    <EmptyState
                        icon={<Search className="size-6" />}
                        title={t('Nothing matches.')}
                        description={t('Try another filter or search.')}
                    />
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <th>{t('Number')}</th>
                                <th>{t('Issue date')}</th>
                                <th>{t('Buyer')}</th>
                                <th className="text-right">{t('Gross')}</th>
                                <th>{t('Payment')}</th>
                                <th>{t('Status')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {visible.map((i) => (
                                <tr key={i.id}>
                                    <td>
                                        <a
                                            href={`/invoices/${i.id}`}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {i.number ?? t('Draft')}
                                        </a>
                                        {i.is_correction && (
                                            <span className="ml-2 text-xs text-muted">{t('correction')}</span>
                                        )}
                                    </td>
                                    <td className="tabular-nums text-muted">{i.issue_date}</td>
                                    <td>{i.contractor}</td>
                                    <td className="text-right font-medium tabular-nums">
                                        {money(i.gross, i.currency)}
                                    </td>
                                    <td>
                                        {i.payment_label && (
                                            <>
                                                <Badge tone={i.overdue ? 'danger' : (i.payment_tone ?? 'neutral')}>
                                                    {i.overdue ? t('Overdue') : i.payment_label}
                                                </Badge>
                                                {i.due_date && i.payment_status !== 'paid' && (
                                                    <span className="ml-2 text-xs tabular-nums text-muted">
                                                        {t('due')} {i.due_date}
                                                    </span>
                                                )}
                                            </>
                                        )}
                                    </td>
                                    <td>
                                        <Badge tone={i.status_tone}>{i.status_label}</Badge>
                                        {i.offline && (
                                            <span className="ml-2 text-xs text-muted">
                                                {t('offline')}
                                                {i.offline_deadline
                                                    ? ` · ${t('deliver by')} ${i.offline_deadline}`
                                                    : ''}
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>
        </AppLayout>
    );
}
