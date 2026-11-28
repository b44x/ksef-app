import { Download } from 'lucide-react';
import { Button, ButtonLink } from '../Components/Button';
import { type Party, PartyBlock } from '../Components/PartyBlock';
import { Alert, Badge, Card, PageHeader } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import { useMoney } from '../lib/format';

interface Props {
    invoice: { id: number; ksef_number: string; number: string; seller_name: string; received_at: string; xml: string };
    details: {
        number: string;
        type: string;
        issue_date: string;
        sale_date: string | null;
        currency: string;
        total: string;
        seller: Party;
        buyer: Party;
        lines: {
            number: number;
            name: string;
            unit: string | null;
            quantity: string | null;
            unit_price: string | null;
            price_kind: 'net' | 'gross' | null;
            net: string | null;
            gross: string | null;
            vat_rate: string;
        }[];
        tax_summary: { group: string; net: string; tax: string | null }[];
        payment: { paid_on: string | null; due_dates: string[]; bank_accounts: string[]; is_paid: boolean } | null;
        notes: string | null;
        is_correction: boolean;
    } | null;
    problem: string | null;
}

const labels: Record<string, string> = {
    VAT: 'VAT invoice',
    KOR: 'Correction',
    ZAL: 'Advance invoice',
    ROZ: 'Settlement invoice',
    UPR: 'Simplified invoice',
    KOR_ZAL: 'Correction',
    KOR_ROZ: 'Correction',
};

/** The tax groups of FA(3) the summary is split into, as people know them. */
const groups: Record<string, string> = {
    '1': '23% / 22%',
    '2': '8% / 7%',
    '3': '5%',
    '4': 'Taxi flat rate',
    '5': 'OSS',
    '6_1': '0% domestic',
    '6_2': '0% intra-Community',
    '6_3': '0% export',
    '7': 'Exempt',
    '8': 'Not subject (outside Poland)',
    '9': 'Not subject (art. 100)',
    '10': 'Reverse charge',
    '11': 'Margin scheme',
};

export default function PurchaseShow({ invoice, details, problem }: Props) {
    const t = useT();
    const money = useMoney();

    return (
        <AppLayout>
            <PageHeader
                title={invoice.number}
                badge={
                    details && (
                        <Badge tone={details.is_correction ? 'warning' : 'neutral'}>
                            {t(labels[details.type] ?? 'VAT invoice')}
                        </Badge>
                    )
                }
                description={invoice.seller_name}
                actions={
                    <>
                        <ButtonLink href="/purchases" variant="secondary">
                            {t('Back to the list')}
                        </ButtonLink>
                        <a href={`/purchases/${invoice.id}/xml`} download>
                            <Button>
                                <Download className="size-4" />
                                {t('Download XML')}
                            </Button>
                        </a>
                    </>
                }
            />

            {problem && (
                <div className="mb-6">
                    <Alert tone="warning">
                        <div className="mb-0.5 font-medium">
                            {t('This invoice cannot be shown here, but its document is kept and can be downloaded.')}
                        </div>
                        {problem}
                    </Alert>
                </div>
            )}

            {details && (
                <div className="rounded-xl border border-border bg-surface shadow-card">
                    <div className="grid gap-4 border-b border-border p-6 sm:grid-cols-3">
                        <div>
                            <div className="text-xs font-medium uppercase tracking-wide text-muted">
                                {t('Issue date')}
                            </div>
                            <div className="mt-1 font-medium tabular-nums">{details.issue_date}</div>
                        </div>
                        <div>
                            <div className="text-xs font-medium uppercase tracking-wide text-muted">
                                {t('Sale date')}
                            </div>
                            <div className="mt-1 font-medium tabular-nums">
                                {details.sale_date ?? details.issue_date}
                            </div>
                        </div>
                        <div>
                            <div className="text-xs font-medium uppercase tracking-wide text-muted">
                                {t('Due date')}
                            </div>
                            <div className="mt-1 font-medium tabular-nums">{details.payment?.due_dates[0] ?? '—'}</div>
                        </div>
                    </div>

                    <div className="grid divide-border md:grid-cols-2 md:divide-x">
                        <PartyBlock title={t('Seller')} party={details.seller} />
                        <PartyBlock title={t('Buyer')} party={details.buyer} />
                    </div>

                    <div className="overflow-x-auto border-t border-border p-6">
                        <table className="w-full text-sm [&_td]:px-3 [&_td]:py-2 [&_td:first-child]:pl-0 [&_td:last-child]:pr-0 [&_th]:px-3 [&_th:first-child]:pl-0 [&_th:last-child]:pr-0 [&_th]:py-2 [&_th]:text-xs [&_th]:font-medium [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-muted">
                            <thead>
                                <tr className="border-b border-border text-left">
                                    <th className="w-10">{t('No.')}</th>
                                    <th>{t('Item or service')}</th>
                                    <th className="text-right">{t('Qty')}</th>
                                    <th>{t('Unit')}</th>
                                    <th className="text-right">{t('Price')}</th>
                                    <th className="text-right">{t('VAT')}</th>
                                    <th className="text-right">{t('Value')}</th>
                                </tr>
                            </thead>
                            <tbody className="tabular-nums">
                                {details.lines.map((line) => (
                                    <tr key={line.number} className="border-b border-border last:border-0">
                                        <td className="text-muted">{line.number}</td>
                                        <td className="font-medium">{line.name}</td>
                                        <td className="text-right">{line.quantity ?? '—'}</td>
                                        <td className="text-muted">{line.unit ?? ''}</td>
                                        <td className="text-right">
                                            {line.unit_price ? money(line.unit_price) : '—'}
                                            {line.price_kind === 'gross' && (
                                                <span className="ml-1 text-xs text-muted">{t('gross')}</span>
                                            )}
                                        </td>
                                        <td className="text-right">{line.vat_rate}</td>
                                        <td className="text-right">{money(line.net ?? line.gross ?? '0')}</td>
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
                            {details.payment ? (
                                <>
                                    <div>
                                        <Badge tone={details.payment.is_paid ? 'success' : 'warning'}>
                                            {details.payment.is_paid ? t('Paid') : t('Not paid')}
                                        </Badge>
                                        {details.payment.paid_on && (
                                            <span className="ml-2 text-muted">{details.payment.paid_on}</span>
                                        )}
                                    </div>
                                    {details.payment.bank_accounts.map((account) => (
                                        <div key={account}>
                                            <span className="text-muted">{t('Bank account')}: </span>
                                            <span className="font-mono">{account}</span>
                                        </div>
                                    ))}
                                </>
                            ) : (
                                <p className="text-muted">{t('No payment details.')}</p>
                            )}
                            {details.notes && <p className="whitespace-pre-line text-muted">{details.notes}</p>}
                        </div>
                        <div>
                            <div className="mb-3 text-xs font-medium uppercase tracking-wide text-muted">
                                {t('Summary')}
                            </div>
                            <table className="w-full text-sm [&_td]:py-1.5 [&_th]:py-1.5 [&_th]:text-xs [&_th]:font-medium [&_th]:text-muted">
                                <thead>
                                    <tr className="border-b border-border">
                                        <th className="text-left">{t('Rate group')}</th>
                                        <th className="text-right">{t('Net total')}</th>
                                        <th className="text-right">{t('VAT total')}</th>
                                    </tr>
                                </thead>
                                <tbody className="tabular-nums">
                                    {details.tax_summary.map((row) => (
                                        <tr key={row.group}>
                                            <td className="text-muted">
                                                {groups[row.group] ? t(groups[row.group]) : row.group}
                                            </td>
                                            <td className="text-right">{money(row.net)}</td>
                                            <td className="text-right">{row.tax !== null ? money(row.tax) : '—'}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <div className="mt-4 flex items-baseline justify-between rounded-lg bg-primary-soft px-4 py-3">
                                <span className="font-medium text-primary">{t('Amount due')}</span>
                                <span className="text-2xl font-semibold tabular-nums text-primary">
                                    {money(details.total, details.currency)}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            )}

            <Card title={t('KSeF')} className="mt-6">
                <dl className="grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-muted">{t('KSeF number')}</dt>
                        <dd className="mt-0.5 font-mono">{invoice.ksef_number}</dd>
                    </div>
                    <div>
                        <dt className="text-muted">{t('Received')}</dt>
                        <dd className="mt-0.5 tabular-nums">{new Date(invoice.received_at).toLocaleString()}</dd>
                    </div>
                </dl>
            </Card>
        </AppLayout>
    );
}
