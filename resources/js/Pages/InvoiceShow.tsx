import { router, usePage } from '@inertiajs/react';
import {
    CheckCircle2,
    CircleDashed,
    Copy,
    CopyPlus,
    Download,
    Repeat,
    Loader2,
    RefreshCw,
    Send,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { RecurringDialog } from '../Components/RecurringDialog';
import { PdfLinks } from '../Components/PdfLinks';
import { type PaymentInfo, PaymentsCard } from '../Components/PaymentsCard';
import { Button, ButtonLink } from '../Components/Button';
import { type Party, PartyBlock } from '../Components/PartyBlock';
import { Alert, Badge, Card, PageHeader } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import { useMoney } from '../lib/format';
import type { InvoiceLineForm, SharedProps, TotalsPreview } from '../types';

interface Props {
    invoice: {
        id: number;
        number: string;
        status: string;
        status_label: string;
        status_tone: 'warning' | 'primary' | 'success' | 'danger';
        issue_date: string;
        sale_date: string | null;
        due_date: string | null;
        payment_method: string | null;
        bank_account: string | null;
        notes: string | null;
        currency: string;
        parties: { seller: Party; buyer: Party };
        lines: InvoiceLineForm[];
        xml: string;
        ksef: {
            number: string | null;
            code: number | null;
            description: string | null;
            error: string | null;
            sent_at: string | null;
            accepted_at: string | null;
            has_upo: boolean;
            can_check: boolean;
        };
    };
    totals: TotalsPreview;
    payment: PaymentInfo;
    today: string;
    canSend: boolean;
    needsSigning: boolean;
    offline: {
        mode: string;
        label: string;
        hint: string;
        needs_anchor: boolean;
        anchor_date: string | null;
        deadline: string | null;
        overdue: boolean;
    } | null;
    qr: { verification: string; issuer: string | null } | null;
}

/** The same day next month (the 31st becomes the month's last day): a sensible first date for a monthly repeat. */
function nextMonth(day: string): string {
    const [y, m, d] = day.split('-').map(Number);
    const last = new Date(Date.UTC(y, m + 1, 0)).getUTCDate();
    return new Date(Date.UTC(y, m, Math.min(d, last))).toISOString().slice(0, 10);
}

/** Where the invoice stands on its way to KSeF: issued, sent, accepted (or rejected). */
function Delivery({ invoice }: { invoice: Props['invoice'] }) {
    const t = useT();
    const status = invoice.status;
    const sent = status !== 'issued';
    const done = status === 'accepted' || status === 'rejected';

    const steps = [
        { label: t('Issued'), state: 'done' as const },
        { label: t('Sent to KSeF'), state: sent ? ('done' as const) : ('todo' as const) },
        {
            label: status === 'rejected' ? t('Rejected by KSeF') : t('Accepted by KSeF'),
            state:
                status === 'accepted'
                    ? ('done' as const)
                    : status === 'rejected'
                      ? ('failed' as const)
                      : status === 'sending'
                        ? ('active' as const)
                        : ('todo' as const),
        },
    ];

    return (
        <ol className="flex flex-wrap items-center gap-x-2 gap-y-3" aria-label={t('Delivery to KSeF')}>
            {steps.map((step, i) => (
                <li key={step.label} className="flex items-center gap-2">
                    {step.state === 'done' && <CheckCircle2 className="size-5 text-success" aria-hidden />}
                    {step.state === 'active' && <Loader2 className="size-5 animate-spin text-primary" aria-hidden />}
                    {step.state === 'failed' && <XCircle className="size-5 text-danger" aria-hidden />}
                    {step.state === 'todo' && <CircleDashed className="size-5 text-muted/60" aria-hidden />}
                    <span
                        className={`text-sm font-medium ${step.state === 'todo' ? 'text-muted' : step.state === 'failed' ? 'text-danger' : 'text-text'}`}
                    >
                        {step.label}
                    </span>
                    {i < steps.length - 1 && (
                        <span
                            className={`mx-2 h-px w-8 sm:w-14 ${done || (i === 0 && sent) ? 'bg-success/50' : 'bg-border'}`}
                            aria-hidden
                        />
                    )}
                </li>
            ))}
        </ol>
    );
}

export default function InvoiceShow({ invoice, totals, payment, today, canSend, needsSigning, offline, qr }: Props) {
    const t = useT();
    const money = useMoney();
    const { errors, locale } = usePage<SharedProps>().props as SharedProps & { errors: Record<string, string> };
    const sending = invoice.status === 'sending';

    // While KSeF is checking the invoice the page follows along; it stops by itself when the verdict arrives.
    useEffect(() => {
        if (!sending) {
            return;
        }
        const timer = setInterval(() => router.reload({ only: ['invoice'] }), 3000);

        return () => clearInterval(timer);
    }, [sending]);

    const send = () => router.post(`/invoices/${invoice.id}/send`);
    const [anchor, setAnchor] = useState('');
    const [repeating, setRepeating] = useState(false);
    const when = (iso: string) =>
        new Date(iso).toLocaleDateString(locale === 'pl' ? 'pl-PL' : 'en-GB', { dateStyle: 'long' });

    return (
        <AppLayout>
            <PageHeader
                title={invoice.number}
                badge={<Badge tone={invoice.status_tone}>{invoice.status_label}</Badge>}
                description={t('Issued invoices cannot be changed. The FA(3) document below is what KSeF receives.')}
                actions={
                    <>
                        <ButtonLink href="/invoices" variant="secondary">
                            {t('Back to the list')}
                        </ButtonLink>
                        <PdfLinks href={`/invoices/${invoice.id}/pdf`} />
                        <Button variant="secondary" onClick={() => setRepeating(true)}>
                            <Repeat className="size-4" />
                            {t('Repeat')}
                        </Button>
                        <a href={`/invoices/${invoice.id}/xml`} download>
                            <Button variant="secondary">
                                <Download className="size-4" />
                                {t('Download XML')}
                            </Button>
                        </a>
                        {invoice.status === 'issued' && (
                            <Button onClick={send} disabled={!canSend}>
                                <Send className="size-4" />
                                {offline ? t('Deliver to KSeF') : t('Send to KSeF')}
                            </Button>
                        )}
                        {invoice.status === 'rejected' && (
                            <Button onClick={() => router.post(`/invoices/${invoice.id}/duplicate`)}>
                                <CopyPlus className="size-4" />
                                {t('Make a corrected draft')}
                            </Button>
                        )}
                    </>
                }
            />

            <Card title={t('Delivery to KSeF')} className="mb-6">
                <div className="space-y-4">
                    {offline && invoice.status === 'issued' && (
                        <Alert tone={offline.overdue ? 'danger' : 'warning'}>
                            <div className="font-medium">
                                {t('Issued offline')}: {offline.label}
                            </div>
                            {offline.deadline ? (
                                <div>
                                    {offline.overdue
                                        ? t('The delivery deadline passed on :date. Deliver it to KSeF now.', {
                                              date: when(offline.deadline),
                                          })
                                        : t('Deliver it to KSeF by the end of :date.', {
                                              date: when(offline.deadline),
                                          })}
                                </div>
                            ) : (
                                <form
                                    className="mt-2 flex flex-wrap items-end gap-2"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        router.post(`/invoices/${invoice.id}/offline-anchor`, { anchor_date: anchor });
                                    }}
                                >
                                    <label className="text-sm">
                                        <span className="mb-1 block">
                                            {t('The deadline counts from the day KSeF is back. When did it end?')}
                                        </span>
                                        <input
                                            type="date"
                                            required
                                            value={anchor}
                                            onChange={(e) => setAnchor(e.target.value)}
                                            className="h-9 rounded-lg border border-border-strong bg-surface px-2 text-sm text-text"
                                        />
                                    </label>
                                    <Button type="submit" size="sm" variant="secondary" disabled={anchor === ''}>
                                        {t('Set the deadline')}
                                    </Button>
                                </form>
                            )}
                            {errors.anchor_date && <div className="mt-1 text-xs">{errors.anchor_date}</div>}
                        </Alert>
                    )}
                    {offline && invoice.status !== 'issued' && (
                        <p className="text-sm text-muted">
                            {t('Issued offline')}: {offline.label}
                        </p>
                    )}

                    <Delivery invoice={invoice} />

                    {invoice.ksef.number && (
                        <div className="flex flex-wrap items-center gap-3 rounded-lg bg-success-soft px-4 py-3 text-sm">
                            <span className="text-success">{t('KSeF number')}</span>
                            <code className="font-mono font-medium text-text">{invoice.ksef.number}</code>
                            <button
                                type="button"
                                onClick={() => navigator.clipboard?.writeText(invoice.ksef.number ?? '')}
                                title={t('Copy')}
                                aria-label={t('Copy')}
                                className="rounded p-1 text-success hover:bg-success/10"
                            >
                                <Copy className="size-4" />
                            </button>
                            {invoice.status === 'accepted' && (
                                <a
                                    href={`/invoices/${invoice.id}/upo`}
                                    download
                                    className="ml-auto inline-flex items-center gap-1.5 font-medium text-primary hover:underline"
                                >
                                    <Download className="size-4" />
                                    {t('Download UPO')}
                                </a>
                            )}
                        </div>
                    )}

                    {sending && !invoice.ksef.error && (
                        <p className="text-sm text-muted">
                            {t('KSeF is checking the invoice. This page updates by itself.')}
                        </p>
                    )}

                    {invoice.status === 'issued' && needsSigning && (
                        <Alert tone="info">
                            {t('Sign in to KSeF first: the signed login has expired or was never made.')}{' '}
                            <a href="/company/signin" className="font-medium underline">
                                {t('Sign in to KSeF')}
                            </a>
                        </Alert>
                    )}

                    {(invoice.ksef.error || errors.send) && (
                        <Alert tone={invoice.status === 'rejected' ? 'danger' : 'warning'}>
                            <div className="whitespace-pre-line">{invoice.ksef.error ?? errors.send}</div>
                        </Alert>
                    )}

                    {invoice.ksef.can_check && (
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => router.post(`/invoices/${invoice.id}/check`)}
                        >
                            <RefreshCw className="size-4" />
                            {t('Check status')}
                        </Button>
                    )}
                </div>
            </Card>

            <div className="rounded-xl border border-border bg-surface shadow-card">
                <div className="grid gap-4 border-b border-border p-6 sm:grid-cols-3">
                    <div>
                        <div className="text-xs font-medium uppercase tracking-wide text-muted">{t('Issue date')}</div>
                        <div className="mt-1 font-medium tabular-nums">{invoice.issue_date}</div>
                    </div>
                    <div>
                        <div className="text-xs font-medium uppercase tracking-wide text-muted">{t('Sale date')}</div>
                        <div className="mt-1 font-medium tabular-nums">{invoice.sale_date ?? invoice.issue_date}</div>
                    </div>
                    <div>
                        <div className="text-xs font-medium uppercase tracking-wide text-muted">{t('Due date')}</div>
                        <div className="mt-1 font-medium tabular-nums">{invoice.due_date ?? '—'}</div>
                    </div>
                </div>

                <div className="grid divide-border md:grid-cols-2 md:divide-x">
                    <PartyBlock title={t('Seller')} party={invoice.parties.seller} />
                    <PartyBlock title={t('Buyer')} party={invoice.parties.buyer} />
                </div>

                <div className="overflow-x-auto border-t border-border p-6">
                    <table className="w-full text-sm [&_td]:px-3 [&_td]:py-2 [&_td:first-child]:pl-0 [&_td:last-child]:pr-0 [&_th]:px-3 [&_th:first-child]:pl-0 [&_th:last-child]:pr-0 [&_th]:py-2 [&_th]:text-xs [&_th]:font-medium [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-muted">
                        <thead>
                            <tr className="border-b border-border text-left">
                                <th className="w-10">{t('No.')}</th>
                                <th>{t('Item or service')}</th>
                                <th className="text-right">{t('Qty')}</th>
                                <th>{t('Unit')}</th>
                                <th className="text-right">{t('Net price')}</th>
                                <th className="text-right">{t('VAT')}</th>
                                <th className="text-right">{t('Net value')}</th>
                            </tr>
                        </thead>
                        <tbody className="tabular-nums">
                            {invoice.lines.map((line, i) => (
                                <tr key={i} className="border-b border-border last:border-0">
                                    <td className="text-muted">{i + 1}</td>
                                    <td className="font-medium">{line.name}</td>
                                    <td className="text-right">{line.quantity}</td>
                                    <td className="text-muted">{line.unit}</td>
                                    <td className="text-right">{money(line.unit_net_price)}</td>
                                    <td className="text-right">{line.vat_rate}</td>
                                    <td className="text-right">{money(totals.lines[i])}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="grid gap-6 border-t border-border p-6 lg:grid-cols-2">
                    <div className="space-y-3 text-sm">
                        <div className="text-xs font-medium uppercase tracking-wide text-muted">
                            {t('Payment and notes')}
                        </div>
                        <div>
                            <span className="text-muted">{t('Payment method')}: </span>
                            {invoice.payment_method ?? '—'}
                        </div>
                        {invoice.bank_account && (
                            <div>
                                <span className="text-muted">{t('Bank account')}: </span>
                                <span className="font-mono">{invoice.bank_account}</span>
                            </div>
                        )}
                        {invoice.notes && <p className="whitespace-pre-line text-muted">{invoice.notes}</p>}
                    </div>
                    <div>
                        <div className="mb-3 text-xs font-medium uppercase tracking-wide text-muted">
                            {t('Summary')}
                        </div>
                        <table className="w-full text-sm [&_td]:py-1.5 [&_th]:py-1.5 [&_th]:text-xs [&_th]:font-medium [&_th]:text-muted">
                            <thead>
                                <tr className="border-b border-border">
                                    <th className="text-left">{t('Rate')}</th>
                                    <th className="text-right">{t('Net total')}</th>
                                    <th className="text-right">{t('VAT total')}</th>
                                    <th className="text-right">{t('Gross total')}</th>
                                </tr>
                            </thead>
                            <tbody className="tabular-nums">
                                {totals.buckets.map((b) => (
                                    <tr key={b.rate}>
                                        <td className="text-muted">{b.vat !== null ? `${b.rate}%` : b.rate}</td>
                                        <td className="text-right">{money(b.net)}</td>
                                        <td className="text-right">{b.vat !== null ? money(b.vat) : '—'}</td>
                                        <td className="text-right">{money(b.gross)}</td>
                                    </tr>
                                ))}
                                <tr className="border-t border-border font-semibold">
                                    <td>{t('Total')}</td>
                                    <td className="text-right">{money(totals.net)}</td>
                                    <td className="text-right">{money(totals.vat)}</td>
                                    <td className="text-right">{money(totals.gross)}</td>
                                </tr>
                            </tbody>
                        </table>
                        <div className="mt-4 flex items-baseline justify-between rounded-lg bg-primary-soft px-4 py-3">
                            <span className="font-medium text-primary">{t('Amount due')}</span>
                            <span className="text-2xl font-semibold tabular-nums text-primary">
                                {money(totals.gross, invoice.currency)}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <RecurringDialog
                invoiceId={invoice.id}
                suggestedName={`${invoice.parties?.buyer?.name ?? ''} · ${invoice.number}`}
                firstRun={nextMonth(today)}
                open={repeating}
                onClose={() => setRepeating(false)}
            />

            <PaymentsCard invoiceId={invoice.id} currency={invoice.currency} payment={payment} today={today} />

            {qr && (
                <Card
                    title={t('QR codes')}
                    description={
                        offline
                            ? t('Print both on the invoice you hand to the buyer.')
                            : t('Scan to verify the invoice in KSeF.')
                    }
                    className="mt-6"
                >
                    <div className="flex flex-wrap items-start gap-8">
                        <figure>
                            <div
                                className="w-44 sm:w-56 [&>svg]:h-auto [&>svg]:w-full"
                                dangerouslySetInnerHTML={{ __html: qr.verification }}
                            />
                            <figcaption className="mt-2 text-xs text-muted">
                                {t('QR code I: verifies the invoice in KSeF')}
                            </figcaption>
                        </figure>
                        {qr.issuer && (
                            <figure>
                                <div
                                    className="w-44 sm:w-56 [&>svg]:h-auto [&>svg]:w-full"
                                    dangerouslySetInnerHTML={{ __html: qr.issuer }}
                                />
                                <figcaption className="mt-2 text-xs text-muted">
                                    {t('QR code II: confirms the issuer')}
                                </figcaption>
                            </figure>
                        )}
                    </div>
                </Card>
            )}

            <Card title={t('FA(3) document')} description={t('The exact XML that is sent to KSeF.')} className="mt-6">
                <pre className="max-h-96 overflow-auto rounded-lg bg-surface-muted p-4 font-mono text-xs leading-relaxed">
                    {invoice.xml}
                </pre>
            </Card>
        </AppLayout>
    );
}
