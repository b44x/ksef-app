import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, FileCheck2, Plus, Save, Trash2, WifiOff } from 'lucide-react';
import { useState } from 'react';
import { Button } from '../Components/Button';
import { type OfflineOptions, OfflineIssueDialog } from '../Components/OfflineIssueDialog';
import { Field, Input, Select, Textarea } from '../Components/Form';
import { Alert, Badge, Card, PageHeader } from '../Components/Ui';
import { useTotals } from '../hooks/useTotals';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import { useMoney } from '../lib/format';
import type { InvoiceLineForm } from '../types';

interface Props {
    invoice: {
        id: number;
        number: string | null;
        contractor_id: number;
        numbering_series_id: number;
        issue_date: string;
        sale_date: string;
        due_date: string;
        payment_method: string;
        bank_account: string;
        notes: string;
        lines: InvoiceLineForm[];
        problems: string[];
        preview_number: string;
    } | null;
    seller: { name: string; nip: string; address: string; email: string | null };
    today: string;
    defaultSeriesId: number | null;
    contractors: {
        id: number;
        name: string;
        nip: string | null;
        address_line1: string;
        address_line2: string | null;
        country: string;
    }[];
    series: { id: number; name: string }[];
    products: { id: number; name: string; unit: string; net_price: string; vat_rate: string }[];
    vatRates: string[];
    paymentMethods: { value: string; label: string }[];
    offline: OfflineOptions;
}

const blankLine: InvoiceLineForm = { name: '', unit: 'szt.', quantity: '1', unit_net_price: '', vat_rate: '23' };
const columns = 'lg:grid-cols-[2rem_minmax(0,1fr)_5rem_5rem_8rem_5.5rem_7rem_2.25rem]';

/** The kinds of document. Only the VAT invoice exists so far; the others are shown so the direction is clear. */
const kinds = [
    { key: 'vat', label: 'VAT invoice', enabled: true },
    { key: 'proforma', label: 'Proforma', enabled: false },
    { key: 'advance', label: 'Advance invoice', enabled: false },
    { key: 'margin', label: 'Margin', enabled: false },
];

export default function InvoiceForm({
    invoice,
    seller,
    today,
    defaultSeriesId,
    contractors,
    series,
    products,
    vatRates,
    paymentMethods,
    offline,
}: Props) {
    const t = useT();
    const money = useMoney();
    const form = useForm({
        contractor_id: invoice?.contractor_id ?? ('' as number | ''),
        numbering_series_id: invoice?.numbering_series_id ?? defaultSeriesId ?? ('' as number | ''),
        issue_date: invoice?.issue_date ?? today,
        sale_date: invoice?.sale_date ?? '',
        due_date: invoice?.due_date ?? '',
        payment_method: invoice?.payment_method ?? '6',
        bank_account: invoice?.bank_account ?? '',
        notes: invoice?.notes ?? '',
        lines: invoice?.lines ?? [{ ...blankLine }],
    });
    const totals = useTotals(form.data.lines);
    const errors = form.errors as Record<string, string | undefined>;
    const buyer = contractors.find((c) => c.id === form.data.contractor_id);
    const missingCatalog = contractors.length === 0 || series.length === 0;

    const setLine = (index: number, patch: Partial<InvoiceLineForm>) =>
        form.setData(
            'lines',
            form.data.lines.map((line, i) => (i === index ? { ...line, ...patch } : line)),
        );
    const removeLine = (index: number) =>
        form.setData(
            'lines',
            form.data.lines.filter((_, i) => i !== index),
        );
    const addProduct = (id: string) => {
        const product = products.find((p) => String(p.id) === id);
        if (!product) return;
        const line = {
            name: product.name,
            unit: product.unit,
            quantity: '1',
            unit_net_price: product.net_price,
            vat_rate: product.vat_rate,
        };
        // A fresh, empty first line is replaced instead of being left behind.
        const first = form.data.lines[0];
        const untouched = form.data.lines.length === 1 && first.name === '' && first.unit_net_price === '';
        form.setData('lines', untouched ? [line] : [...form.data.lines, line]);
    };
    const lineError = (index: number) =>
        ['name', 'unit', 'quantity', 'unit_net_price', 'vat_rate']
            .map((f) => errors[`lines.${index}.${f}`])
            .find(Boolean);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (invoice) form.put(`/invoices/${invoice.id}`, { preserveScroll: true });
        else form.post('/invoices');
    };
    const remove = () => {
        if (invoice && window.confirm(t('Delete this draft?'))) router.delete(`/invoices/${invoice.id}`);
    };
    const field =
        (name: 'issue_date' | 'sale_date' | 'due_date' | 'bank_account') => (e: React.ChangeEvent<HTMLInputElement>) =>
            form.setData(name, e.target.value);
    // Issuing works on what is saved: only offered when the form matches the saved draft and nothing is in the way.
    const canIssue = invoice !== null && !form.isDirty && invoice.problems.length === 0;
    const issue = () => {
        if (invoice && window.confirm(t('Issue this invoice? It gets its number and can no longer be changed.'))) {
            router.post(`/invoices/${invoice.id}/issue`);
        }
    };
    const [offlineOpen, setOfflineOpen] = useState(false);
    const number = invoice ? invoice.preview_number : t('Number is given when you issue the invoice');

    return (
        <AppLayout>
            <PageHeader
                title={invoice ? invoice.preview_number : t('New invoice')}
                badge={<Badge tone="warning">{t('Draft')}</Badge>}
                actions={
                    <>
                        {invoice && (
                            <Button variant="danger" onClick={remove}>
                                <Trash2 className="size-4" />
                                {t('Delete')}
                            </Button>
                        )}
                        <Button
                            type="submit"
                            form="invoice-form"
                            variant={canIssue ? 'secondary' : 'primary'}
                            disabled={form.processing || missingCatalog}
                        >
                            <Save className="size-4" />
                            {t('Save draft')}
                        </Button>
                        {canIssue && offline.available && (
                            <Button variant="secondary" onClick={() => setOfflineOpen(true)}>
                                <WifiOff className="size-4" />
                                {t('Issue offline')}
                            </Button>
                        )}
                        {canIssue && (
                            <Button onClick={issue}>
                                <FileCheck2 className="size-4" />
                                {t('Issue invoice')}
                            </Button>
                        )}
                    </>
                }
            />

            {invoice && (
                <OfflineIssueDialog
                    invoiceId={invoice.id}
                    offline={offline}
                    open={offlineOpen}
                    onClose={() => setOfflineOpen(false)}
                />
            )}

            {errors.issue && (
                <div className="mb-6">
                    <Alert tone="danger">
                        <div className="mb-1 font-medium">{t('The invoice could not be issued:')}</div>
                        <div className="whitespace-pre-line">{errors.issue}</div>
                    </Alert>
                </div>
            )}

            {missingCatalog && (
                <div className="mb-6">
                    <Alert tone="warning">
                        {t('Add a contractor and a numbering series first.')}{' '}
                        <a href="/contractors" className="font-medium underline">
                            {t('Contractors')}
                        </a>
                        {' · '}
                        <a href="/series" className="font-medium underline">
                            {t('Numbering')}
                        </a>
                    </Alert>
                </div>
            )}

            <form id="invoice-form" onSubmit={submit}>
                <div role="tablist" aria-label={t('Document type')} className="flex gap-1 border-b border-border">
                    {kinds.map((kind) => (
                        <button
                            key={kind.key}
                            type="button"
                            role="tab"
                            aria-selected={kind.enabled}
                            disabled={!kind.enabled}
                            title={kind.enabled ? undefined : t('Coming soon')}
                            className={`-mb-px border-b-2 px-4 py-2.5 text-sm font-medium uppercase tracking-wide transition-colors ${
                                kind.enabled
                                    ? 'border-primary text-primary'
                                    : 'cursor-not-allowed border-transparent text-muted/60'
                            }`}
                        >
                            {t(kind.label)}
                        </button>
                    ))}
                </div>

                <div className="rounded-b-xl border border-t-0 border-border bg-surface shadow-card">
                    {/* The head of the document: number and dates, as on the printed invoice. */}
                    <div className="grid gap-6 border-b border-border p-6 md:grid-cols-[1fr_auto]">
                        <div>
                            <div className="text-xs font-medium uppercase tracking-wide text-muted">
                                {t('Invoice number')}
                            </div>
                            <div className="mt-1 text-2xl font-semibold tracking-tight">{number}</div>
                            <div className="mt-3 max-w-xs">
                                <Field label={t('Series')} error={errors.numbering_series_id}>
                                    <Select
                                        value={form.data.numbering_series_id}
                                        onChange={(e) =>
                                            form.setData('numbering_series_id', Number(e.target.value) || '')
                                        }
                                    >
                                        <option value="">{t('Choose…')}</option>
                                        {series.map((s) => (
                                            <option key={s.id} value={s.id}>
                                                {s.name}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                            </div>
                        </div>
                        <div className="grid gap-4 sm:grid-cols-2 md:w-[28rem]">
                            <Field label={t('Issue date')} error={errors.issue_date}>
                                <Input type="date" value={form.data.issue_date} onChange={field('issue_date')} />
                            </Field>
                            <Field
                                label={t('Sale date')}
                                error={errors.sale_date}
                                hint={t('Leave empty when it is the issue date.')}
                            >
                                <Input type="date" value={form.data.sale_date} onChange={field('sale_date')} />
                            </Field>
                        </div>
                    </div>

                    <div className="grid divide-border md:grid-cols-2 md:divide-x">
                        <div className="p-6">
                            <div className="mb-3 text-xs font-medium uppercase tracking-wide text-muted">
                                {t('Seller')}
                            </div>
                            <div className="font-medium">{seller.name}</div>
                            <div className="text-sm text-muted">NIP {seller.nip}</div>
                            <div className="text-sm text-muted">{seller.address || t('No address yet')}</div>
                            {seller.email && <div className="text-sm text-muted">{seller.email}</div>}
                        </div>
                        <div className="p-6">
                            <div className="mb-3 text-xs font-medium uppercase tracking-wide text-muted">
                                {t('Buyer')}
                            </div>
                            <Select
                                aria-label={t('Buyer')}
                                value={form.data.contractor_id}
                                onChange={(e) => form.setData('contractor_id', Number(e.target.value) || '')}
                            >
                                <option value="">{t('Choose…')}</option>
                                {contractors.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </Select>
                            {errors.contractor_id && (
                                <p className="mt-1.5 text-xs text-danger">{errors.contractor_id}</p>
                            )}
                            {buyer && (
                                <div className="mt-3 text-sm text-muted">
                                    {buyer.nip && <div>NIP {buyer.nip}</div>}
                                    <div>
                                        {buyer.address_line1}
                                        {buyer.address_line2 ? `, ${buyer.address_line2}` : ''} · {buyer.country}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="border-t border-border p-6">
                        <div className="mb-3 flex items-center justify-between gap-3">
                            <div className="text-xs font-medium uppercase tracking-wide text-muted">{t('Lines')}</div>
                            {products.length > 0 && (
                                <Select
                                    aria-label={t('Add from the catalog')}
                                    value=""
                                    onChange={(e) => addProduct(e.target.value)}
                                    className="!h-8 !w-60 !text-xs"
                                >
                                    <option value="">{t('Add from the catalog…')}</option>
                                    {products.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name} · {money(p.net_price)}
                                        </option>
                                    ))}
                                </Select>
                            )}
                        </div>
                        <div
                            className={`hidden gap-2 px-1 pb-2 text-xs font-medium uppercase tracking-wide text-muted lg:grid ${columns}`}
                        >
                            <span>{t('No.')}</span>
                            <span>{t('Item or service')}</span>
                            <span>{t('Unit')}</span>
                            <span>{t('Qty')}</span>
                            <span>{t('Net price')}</span>
                            <span>{t('VAT')}</span>
                            <span className="text-right">{t('Net value')}</span>
                            <span />
                        </div>
                        <div className="space-y-3 lg:space-y-1.5">
                            {form.data.lines.map((line, index) => (
                                <div key={index}>
                                    <div
                                        className={`grid grid-cols-2 items-center gap-2 rounded-lg border border-border p-3 lg:border-0 lg:p-0 ${columns}`}
                                    >
                                        <span className="hidden text-sm tabular-nums text-muted lg:block">
                                            {index + 1}
                                        </span>
                                        <Input
                                            aria-label={t('Item or service')}
                                            placeholder={t('Item or service')}
                                            className="col-span-2 lg:col-span-1"
                                            value={line.name}
                                            onChange={(e) => setLine(index, { name: e.target.value })}
                                        />
                                        <Input
                                            aria-label={t('Unit')}
                                            placeholder={t('Unit')}
                                            value={line.unit}
                                            onChange={(e) => setLine(index, { unit: e.target.value })}
                                        />
                                        <Input
                                            aria-label={t('Qty')}
                                            placeholder={t('Qty')}
                                            inputMode="decimal"
                                            className="text-right tabular-nums"
                                            value={line.quantity}
                                            onChange={(e) => setLine(index, { quantity: e.target.value })}
                                        />
                                        <Input
                                            aria-label={t('Net price')}
                                            placeholder={t('Net price')}
                                            inputMode="decimal"
                                            className="text-right tabular-nums"
                                            value={line.unit_net_price}
                                            onChange={(e) => setLine(index, { unit_net_price: e.target.value })}
                                        />
                                        <Select
                                            aria-label={t('VAT')}
                                            value={line.vat_rate}
                                            onChange={(e) => setLine(index, { vat_rate: e.target.value })}
                                        >
                                            {vatRates.map((rate) => (
                                                <option key={rate} value={rate}>
                                                    {rate}
                                                </option>
                                            ))}
                                        </Select>
                                        <div
                                            className="text-right text-sm font-medium tabular-nums"
                                            title={t('Net value')}
                                        >
                                            {totals?.lines[index] ? money(totals.lines[index]) : '—'}
                                        </div>
                                        <button
                                            type="button"
                                            onClick={() => removeLine(index)}
                                            disabled={form.data.lines.length === 1}
                                            aria-label={t('Remove')}
                                            title={t('Remove')}
                                            className="flex size-9 items-center justify-center justify-self-end rounded-lg text-muted hover:bg-danger-soft hover:text-danger disabled:pointer-events-none disabled:opacity-30"
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                    {lineError(index) && (
                                        <p className="mt-1 px-1 text-xs text-danger">{lineError(index)}</p>
                                    )}
                                </div>
                            ))}
                        </div>
                        {typeof errors.lines === 'string' && <p className="mt-2 text-xs text-danger">{errors.lines}</p>}
                        <div className="mt-3">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => form.setData('lines', [...form.data.lines, { ...blankLine }])}
                            >
                                <Plus className="size-4" />
                                {t('Add a line')}
                            </Button>
                        </div>
                    </div>

                    <div className="grid gap-6 border-t border-border p-6 lg:grid-cols-2">
                        <div className="space-y-4">
                            <div className="text-xs font-medium uppercase tracking-wide text-muted">
                                {t('Payment and notes')}
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Field label={t('Payment method')} error={errors.payment_method}>
                                    <Select
                                        value={form.data.payment_method}
                                        onChange={(e) => form.setData('payment_method', e.target.value)}
                                    >
                                        {paymentMethods.map((m) => (
                                            <option key={m.value} value={m.value}>
                                                {m.label}
                                            </option>
                                        ))}
                                    </Select>
                                </Field>
                                <Field label={t('Due date')} error={errors.due_date}>
                                    <Input type="date" value={form.data.due_date} onChange={field('due_date')} />
                                </Field>
                            </div>
                            <Field label={t('Bank account')} error={errors.bank_account}>
                                <Input
                                    className="font-mono"
                                    value={form.data.bank_account}
                                    onChange={field('bank_account')}
                                />
                            </Field>
                            <Field label={t('Printed at the bottom of the invoice')} error={errors.notes}>
                                <Textarea
                                    rows={3}
                                    value={form.data.notes}
                                    onChange={(e) => form.setData('notes', e.target.value)}
                                />
                            </Field>
                        </div>

                        <div>
                            <div className="mb-3 text-xs font-medium uppercase tracking-wide text-muted">
                                {t('Summary')}
                            </div>
                            {totals === null ? (
                                <p className="text-sm text-muted">{t('Fill in the lines to see the totals.')}</p>
                            ) : (
                                <div data-testid="totals">
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
                                                    <td className="text-muted">
                                                        {b.vat !== null ? `${b.rate}%` : b.rate}
                                                    </td>
                                                    <td className="text-right">{money(b.net)}</td>
                                                    <td className="text-right">
                                                        {b.vat !== null ? money(b.vat) : '—'}
                                                    </td>
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
                                            {money(totals.gross, 'PLN')}
                                        </span>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </form>

            <Card title={t('Before it can be sent')} className="mt-6">
                {!invoice ? (
                    <p className="text-sm text-muted">{t('Save the draft to check it.')}</p>
                ) : invoice.problems.length === 0 ? (
                    <p className="flex items-center gap-2 text-sm text-success">
                        <CheckCircle2 className="size-4" aria-hidden />
                        {t('Nothing stands in the way.')}
                    </p>
                ) : (
                    <ul className="space-y-2 text-sm text-warning">
                        {invoice.problems.map((p) => (
                            <li key={p} className="flex gap-2">
                                <AlertTriangle className="mt-0.5 size-4 shrink-0" aria-hidden />
                                {p}
                            </li>
                        ))}
                    </ul>
                )}
            </Card>
        </AppLayout>
    );
}
