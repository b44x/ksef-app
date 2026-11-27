import { router, useForm } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Plus, Save, Trash2 } from 'lucide-react';
import { Button } from '../Components/Button';
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
}

const blankLine: InvoiceLineForm = { name: '', unit: 'szt.', quantity: '1', unit_net_price: '', vat_rate: '23' };
const columns = 'lg:grid-cols-[minmax(0,1fr)_5rem_5rem_8rem_5.5rem_7rem_2.25rem]';

export default function InvoiceForm({
    invoice,
    today,
    defaultSeriesId,
    contractors,
    series,
    products,
    vatRates,
    paymentMethods,
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

    return (
        <AppLayout>
            <PageHeader
                title={invoice ? invoice.preview_number : t('New invoice')}
                badge={<Badge tone="warning">{t('Draft')}</Badge>}
                description={
                    invoice
                        ? t('The number is final only when you issue the invoice.')
                        : t('Fill in the buyer and the lines; the totals appear as you type.')
                }
                actions={
                    <>
                        {invoice && (
                            <Button variant="danger" onClick={remove}>
                                <Trash2 className="size-4" />
                                {t('Delete')}
                            </Button>
                        )}
                        <Button type="submit" form="invoice-form" disabled={form.processing || missingCatalog}>
                            <Save className="size-4" />
                            {t('Save draft')}
                        </Button>
                    </>
                }
            />

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

            <form id="invoice-form" onSubmit={submit} className="space-y-6">
                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <Card title={t('Buyer and dates')}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('Buyer')} error={errors.contractor_id}>
                                <Select
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
                                {buyer && (
                                    <span className="mt-1.5 block text-xs text-muted">
                                        {buyer.nip ? `NIP ${buyer.nip} · ` : ''}
                                        {buyer.address_line1}
                                        {buyer.address_line2 ? `, ${buyer.address_line2}` : ''} · {buyer.country}
                                    </span>
                                )}
                            </Field>
                            <Field label={t('Numbering series')} error={errors.numbering_series_id}>
                                <Select
                                    value={form.data.numbering_series_id}
                                    onChange={(e) => form.setData('numbering_series_id', Number(e.target.value) || '')}
                                >
                                    <option value="">{t('Choose…')}</option>
                                    {series.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
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
                    </Card>
                    <Card title={t('Payment and notes')}>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label={t('Due date')} error={errors.due_date}>
                                <Input type="date" value={form.data.due_date} onChange={field('due_date')} />
                            </Field>
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
                            <Field label={t('Bank account')} error={errors.bank_account} className="sm:col-span-2">
                                <Input
                                    className="font-mono"
                                    value={form.data.bank_account}
                                    onChange={field('bank_account')}
                                />
                            </Field>
                            <Field
                                label={t('Printed at the bottom of the invoice')}
                                error={errors.notes}
                                className="sm:col-span-2"
                            >
                                <Textarea
                                    rows={3}
                                    value={form.data.notes}
                                    onChange={(e) => form.setData('notes', e.target.value)}
                                />
                            </Field>
                        </div>
                    </Card>
                </div>

                <Card
                    title={t('Lines')}
                    action={
                        products.length > 0 && (
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
                        )
                    }
                >
                    <div
                        className={`hidden gap-2 px-1 pb-2 text-xs font-medium uppercase tracking-wide text-muted lg:grid ${columns}`}
                    >
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
                                    <div className="text-right text-sm font-medium tabular-nums" title={t('Net value')}>
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
                    <div className="mt-4">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => form.setData('lines', [...form.data.lines, { ...blankLine }])}
                        >
                            <Plus className="size-4" />
                            {t('Add a line')}
                        </Button>
                    </div>
                </Card>

                <div className="grid items-start gap-6 lg:grid-cols-2">
                    <Card title={t('Before it can be sent')}>
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
                    <Card title={t('Totals')}>
                        {totals === null ? (
                            <p className="text-sm text-muted">{t('Fill in the lines to see the totals.')}</p>
                        ) : (
                            <dl className="space-y-2 text-sm" data-testid="totals">
                                {totals.buckets.map((b) => (
                                    <div key={b.rate} className="flex items-baseline justify-between gap-3 text-muted">
                                        <dt>
                                            {t('VAT')} {b.rate}
                                            {b.vat !== null && <span className="text-xs">%</span>}
                                        </dt>
                                        <dd className="tabular-nums">
                                            {money(b.net)}
                                            {b.vat !== null && <span className="text-xs"> + {money(b.vat)}</span>}
                                        </dd>
                                    </div>
                                ))}
                                <div className="flex justify-between border-t border-border pt-3">
                                    <dt className="text-muted">{t('Net')}</dt>
                                    <dd className="tabular-nums">{money(totals.net)}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-muted">{t('VAT')}</dt>
                                    <dd className="tabular-nums">{money(totals.vat)}</dd>
                                </div>
                                <div className="mt-1 flex items-baseline justify-between rounded-lg bg-primary-soft px-3 py-3">
                                    <dt className="font-medium text-primary">{t('Gross')}</dt>
                                    <dd className="text-xl font-semibold tabular-nums text-primary">
                                        {money(totals.gross, 'PLN')}
                                    </dd>
                                </div>
                            </dl>
                        )}
                    </Card>
                </div>
            </form>
        </AppLayout>
    );
}
