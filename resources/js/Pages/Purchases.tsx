import { router, usePage } from '@inertiajs/react';
import { Inbox, Loader2, RefreshCw, Search } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '../Components/Button';
import { Input } from '../Components/Form';
import { Alert, Badge, Card, EmptyState, PageHeader, Table } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import { useMoney } from '../lib/format';
import type { SharedProps } from '../types';

interface Row {
    id: number;
    number: string;
    seller_name: string;
    seller_nip: string | null;
    issue_date: string;
    gross: string;
    currency: string;
    type: string;
    is_new: boolean;
}

interface Props {
    invoices: {
        data: Row[];
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
        from: number | null;
        to: number | null;
    };
    filters: { q: string; from: string; to: string; unread: boolean };
    sync: { started: boolean; at: string | null; error: string | null; running: boolean; needs_signing: boolean };
    defaultSince: string;
}

/** The kind of document, for the few that are not an ordinary invoice. */
const kinds: Record<string, string> = {
    KOR: 'Correction',
    ZAL: 'Advance',
    ROZ: 'Settlement',
    UPR: 'Simplified',
    KOR_ZAL: 'Correction',
    KOR_ROZ: 'Correction',
};

export default function Purchases({ invoices, filters, sync, defaultSince }: Props) {
    const t = useT();
    const money = useMoney();
    const { locale, errors } = usePage<SharedProps>().props as SharedProps & { errors: Record<string, string> };
    const [q, setQ] = useState(filters.q);
    const [since, setSince] = useState(defaultSince);
    const first = useRef(true);

    // Filters narrow the list as they change; typing waits a moment so that a word is one request, not one per letter.
    const apply = (next: Partial<Props['filters']>) => {
        const merged = { ...filters, ...next };
        const query = Object.fromEntries(Object.entries(merged).filter(([, v]) => v !== '' && v !== false));
        router.get('/purchases', query, { preserveState: true, preserveScroll: true, replace: true });
    };
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => apply({ q }), 300);
        return () => clearTimeout(timer);
        // `apply` only reads the current filters; the search text is the one thing that must restart the timer.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);

    // While a fetch runs the page follows along, and stops by itself when it is done.
    useEffect(() => {
        if (!sync.running) {
            return;
        }
        const timer = setInterval(() => router.reload({ only: ['invoices', 'sync', 'unreadPurchases'] }), 3000);
        return () => clearInterval(timer);
    }, [sync.running]);

    const startFetch = () => router.post('/purchases/sync', sync.started ? {} : { since });
    const syncedAt = sync.at ? new Date(sync.at).toLocaleString(locale === 'pl' ? 'pl-PL' : 'en-GB') : null;

    return (
        <AppLayout>
            <PageHeader
                title={t('Purchases')}
                description={t('Invoices other companies issued to you, brought from KSeF.')}
                actions={
                    sync.started && (
                        <Button onClick={startFetch} disabled={sync.running || sync.needs_signing}>
                            {sync.running ? (
                                <Loader2 className="size-4 animate-spin" />
                            ) : (
                                <RefreshCw className="size-4" />
                            )}
                            {sync.running ? t('Fetching…') : t('Fetch from KSeF')}
                        </Button>
                    )
                }
            />

            <div className="mb-6 space-y-3">
                {sync.needs_signing && (
                    <Alert tone="info">
                        {t('Sign in to KSeF first: the signed login has expired or was never made.')}{' '}
                        <a href="/company/signin" className="font-medium underline">
                            {t('Sign in to KSeF')}
                        </a>
                    </Alert>
                )}
                {(sync.error || errors.sync || errors.since) && (
                    <Alert tone="warning">
                        <div className="mb-0.5 font-medium">{t('The last fetch did not succeed:')}</div>
                        {sync.error ?? errors.sync ?? errors.since}
                    </Alert>
                )}
                {sync.started && syncedAt && (
                    <p className="text-sm text-muted">
                        {t('Last fetched: :time. It runs by itself every 15 minutes while the scheduler is on.', {
                            time: syncedAt,
                        })}
                    </p>
                )}
            </div>

            {!sync.started ? (
                <Card>
                    <EmptyState
                        icon={sync.running ? <Loader2 className="size-6 animate-spin" /> : <Inbox className="size-6" />}
                        title={sync.running ? t('Fetching purchase invoices…') : t('Fetch your purchase invoices')}
                        description={t(
                            'Choose how far back to look. After that, new invoices are fetched by themselves.',
                        )}
                        action={
                            <div className="flex flex-wrap items-end justify-center gap-3">
                                <label className="text-left">
                                    <span className="mb-1.5 block text-sm font-medium">{t('Since')}</span>
                                    <Input
                                        type="date"
                                        value={since}
                                        max={new Date().toISOString().slice(0, 10)}
                                        onChange={(e) => setSince(e.target.value)}
                                    />
                                </label>
                                <Button
                                    onClick={startFetch}
                                    disabled={sync.running || sync.needs_signing || since === ''}
                                >
                                    <RefreshCw className="size-4" />
                                    {t('Fetch from KSeF')}
                                </Button>
                            </div>
                        }
                    />
                </Card>
            ) : (
                <Card className="[&>div]:p-0">
                    <div className="flex flex-wrap items-end gap-3 border-b border-border p-4">
                        <label className="relative min-w-56 flex-1">
                            <Search
                                className="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-muted"
                                aria-hidden
                            />
                            <Input
                                className="pl-9"
                                placeholder={t('Search by seller, number or NIP')}
                                aria-label={t('Search')}
                                value={q}
                                onChange={(e) => setQ(e.target.value)}
                            />
                        </label>
                        <label>
                            <span className="sr-only">{t('From')}</span>
                            <Input
                                type="date"
                                aria-label={t('From')}
                                value={filters.from}
                                onChange={(e) => apply({ from: e.target.value })}
                            />
                        </label>
                        <label>
                            <span className="sr-only">{t('To')}</span>
                            <Input
                                type="date"
                                aria-label={t('To')}
                                value={filters.to}
                                onChange={(e) => apply({ to: e.target.value })}
                            />
                        </label>
                        <label className="flex h-10 items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="size-4 rounded border-border-strong accent-[var(--primary)]"
                                checked={filters.unread}
                                onChange={(e) => apply({ unread: e.target.checked })}
                            />
                            {t('New only')}
                        </label>
                    </div>

                    {invoices.data.length === 0 ? (
                        <EmptyState
                            icon={<Inbox className="size-6" />}
                            title={t('No purchase invoices found.')}
                            description={t('Nothing matches, or nothing has arrived yet.')}
                        />
                    ) : (
                        <Table>
                            <thead>
                                <tr>
                                    <th>{t('Seller')}</th>
                                    <th>{t('Number')}</th>
                                    <th>{t('Issue date')}</th>
                                    <th className="text-right">{t('Gross')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {invoices.data.map((i) => (
                                    <tr key={i.id}>
                                        <td>
                                            <a
                                                href={`/purchases/${i.id}`}
                                                className={`text-primary hover:underline ${i.is_new ? 'font-semibold' : 'font-medium'}`}
                                            >
                                                {i.seller_name}
                                            </a>
                                            {i.is_new && (
                                                <span className="ml-2">
                                                    <Badge tone="primary">{t('New')}</Badge>
                                                </span>
                                            )}
                                            {i.seller_nip && (
                                                <div className="text-xs text-muted">NIP {i.seller_nip}</div>
                                            )}
                                        </td>
                                        <td>
                                            {i.number}
                                            {kinds[i.type] && (
                                                <span className="ml-2 text-xs text-muted">{t(kinds[i.type])}</span>
                                            )}
                                        </td>
                                        <td className="tabular-nums text-muted">{i.issue_date}</td>
                                        <td className="text-right font-medium tabular-nums">
                                            {money(i.gross, i.currency)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </Table>
                    )}

                    {(invoices.prev_page_url || invoices.next_page_url) && (
                        <div className="flex items-center justify-between border-t border-border px-4 py-3 text-sm text-muted">
                            <span>
                                {t(':from–:to of :total', {
                                    from: invoices.from ?? 0,
                                    to: invoices.to ?? 0,
                                    total: invoices.total,
                                })}
                            </span>
                            <div className="flex gap-2">
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    disabled={!invoices.prev_page_url}
                                    onClick={() => invoices.prev_page_url && router.get(invoices.prev_page_url)}
                                >
                                    {t('Previous')}
                                </Button>
                                <Button
                                    variant="secondary"
                                    size="sm"
                                    disabled={!invoices.next_page_url}
                                    onClick={() => invoices.next_page_url && router.get(invoices.next_page_url)}
                                >
                                    {t('Next')}
                                </Button>
                            </div>
                        </div>
                    )}
                </Card>
            )}
        </AppLayout>
    );
}
