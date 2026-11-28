import { Download } from 'lucide-react';
import { Button, ButtonLink } from '../Components/Button';
import { Badge, Card, PageHeader } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import { useMoney } from '../lib/format';
import type { InvoiceLineForm, TotalsPreview } from '../types';

interface Party {
    name: string;
    nip: string | null;
    address: string;
    email: string | null;
    country?: string;
}

interface Props {
    invoice: {
        id: number;
        number: string;
        status: string;
        status_label: string;
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
    };
    totals: TotalsPreview;
}

function PartyBlock({ title, party }: { title: string; party: Party }) {
    return (
        <div className="p-6">
            <div className="mb-3 text-xs font-medium uppercase tracking-wide text-muted">{title}</div>
            <div className="font-medium">{party.name}</div>
            {party.nip && <div className="text-sm text-muted">NIP {party.nip}</div>}
            <div className="text-sm text-muted">
                {party.address}
                {party.country ? ` · ${party.country}` : ''}
            </div>
            {party.email && <div className="text-sm text-muted">{party.email}</div>}
        </div>
    );
}

export default function InvoiceShow({ invoice, totals }: Props) {
    const t = useT();
    const money = useMoney();

    return (
        <AppLayout>
            <PageHeader
                title={invoice.number}
                badge={<Badge tone="primary">{invoice.status_label}</Badge>}
                description={t('Issued invoices cannot be changed. The FA(3) document below is what KSeF receives.')}
                actions={
                    <>
                        <ButtonLink href="/invoices" variant="secondary">
                            {t('Back to the list')}
                        </ButtonLink>
                        <a href={`/invoices/${invoice.id}/xml`} download>
                            <Button>
                                <Download className="size-4" />
                                {t('Download XML')}
                            </Button>
                        </a>
                    </>
                }
            />

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

            <Card title={t('FA(3) document')} description={t('The exact XML that is sent to KSeF.')} className="mt-6">
                <pre className="max-h-96 overflow-auto rounded-lg bg-surface-muted p-4 font-mono text-xs leading-relaxed">
                    {invoice.xml}
                </pre>
            </Card>
        </AppLayout>
    );
}
