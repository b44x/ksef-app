import { Link, router, useForm, usePage } from '@inertiajs/react';
import { Button, Card, Field, Input, Select, Textarea } from '../Components/Ui';
import { useTotals } from '../hooks/useTotals';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import type { InvoiceLineForm, SharedProps } from '../types';

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
    contractors: { id: number; name: string; nip: string | null }[];
    series: { id: number; name: string }[];
    products: { id: number; name: string; unit: string; net_price: string; vat_rate: string }[];
    vatRates: string[];
    paymentMethods: { value: string; label: string }[];
}

const blankLine: InvoiceLineForm = { name: '', unit: 'szt.', quantity: '1', unit_net_price: '', vat_rate: '23' };

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
    const { saved } = usePage<SharedProps>().props;
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

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (invoice) {
            form.put(`/invoices/${invoice.id}`, { preserveScroll: true });
        } else {
            form.post('/invoices');
        }
    };

    const remove = () => {
        if (invoice && window.confirm(t('Delete this draft?'))) {
            router.delete(`/invoices/${invoice.id}`);
        }
    };

    const missingCatalog = contractors.length === 0 || series.length === 0;

    return (
        <AppLayout>
            <form onSubmit={submit} className="grid gap-6 lg:grid-cols-[1fr_20rem]">
                <div className="space-y-6">
                    <Card title={invoice ? `${t('Draft')} · ${invoice.preview_number}` : t('New invoice')}>
                        {missingCatalog && (
                            <p className="mb-4 rounded bg-amber-50 p-3 text-sm text-amber-800">
                                {t('Add a contractor and a numbering series first.')}{' '}
                                <Link href="/contractors" className="underline">
                                    {t('Contractors')}
                                </Link>
                                {' · '}
                                <Link href="/series" className="underline">
                                    {t('Numbering')}
                                </Link>
                            </p>
                        )}
                        {saved && (
                            <p className="mb-4 rounded bg-green-50 p-3 text-sm text-green-800">{t('Draft saved.')}</p>
                        )}
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
                                            {c.nip ? ` (${c.nip})` : ''}
                                        </option>
                                    ))}
                                </Select>
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
                                <Input
                                    type="date"
                                    value={form.data.issue_date}
                                    onChange={(e) => form.setData('issue_date', e.target.value)}
                                />
                            </Field>
                            <Field
                                label={t('Sale date')}
                                error={errors.sale_date}
                                hint={t('Leave empty when it is the issue date.')}
                            >
                                <Input
                                    type="date"
                                    value={form.data.sale_date}
                                    onChange={(e) => form.setData('sale_date', e.target.value)}
                                />
                            </Field>
                            <Field label={t('Due date')} error={errors.due_date}>
                                <Input
                                    type="date"
                                    value={form.data.due_date}
                                    onChange={(e) => form.setData('due_date', e.target.value)}
                                />
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
                            <Field label={t('Bank account')} error={errors.bank_account}>
                                <Input
                                    value={form.data.bank_account}
                                    onChange={(e) => form.setData('bank_account', e.target.value)}
                                />
                            </Field>
                        </div>
                    </Card>

                    <Card title={t('Lines')}>
                        <div className="space-y-3">
                            {form.data.lines.map((line, index) => (
                                <div key={index} className="space-y-3 rounded border border-slate-200 p-3">
                                    <div className="flex items-start gap-3">
                                        <div className="flex-1">
                                            <Field label={t('Item or service')} error={errors[`lines.${index}.name`]}>
                                                <Input
                                                    value={line.name}
                                                    onChange={(e) => setLine(index, { name: e.target.value })}
                                                />
                                            </Field>
                                        </div>
                                        {form.data.lines.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => removeLine(index)}
                                                className="mt-7 text-sm text-red-700 hover:underline"
                                            >
                                                {t('Remove')}
                                            </button>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-start gap-3">
                                        <div className="w-24">
                                            <Field label={t('Unit')} error={errors[`lines.${index}.unit`]}>
                                                <Input
                                                    value={line.unit}
                                                    onChange={(e) => setLine(index, { unit: e.target.value })}
                                                />
                                            </Field>
                                        </div>
                                        <div className="w-28">
                                            <Field label={t('Qty')} error={errors[`lines.${index}.quantity`]}>
                                                <Input
                                                    inputMode="decimal"
                                                    value={line.quantity}
                                                    onChange={(e) => setLine(index, { quantity: e.target.value })}
                                                />
                                            </Field>
                                        </div>
                                        <div className="w-36">
                                            <Field
                                                label={t('Net price')}
                                                error={errors[`lines.${index}.unit_net_price`]}
                                            >
                                                <Input
                                                    inputMode="decimal"
                                                    value={line.unit_net_price}
                                                    onChange={(e) => setLine(index, { unit_net_price: e.target.value })}
                                                />
                                            </Field>
                                        </div>
                                        <div className="w-28">
                                            <Field label={t('VAT')} error={errors[`lines.${index}.vat_rate`]}>
                                                <Select
                                                    value={line.vat_rate}
                                                    onChange={(e) => setLine(index, { vat_rate: e.target.value })}
                                                >
                                                    {vatRates.map((rate) => (
                                                        <option key={rate} value={rate}>
                                                            {rate}
                                                        </option>
                                                    ))}
                                                </Select>
                                            </Field>
                                        </div>
                                        <div className="ml-auto text-right">
                                            <span className="mb-1 block text-sm font-medium text-slate-700">
                                                {t('Net value')}
                                            </span>
                                            <span className="block py-2 text-sm tabular-nums">
                                                {totals?.lines[index] ?? '—'}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                        {typeof errors.lines === 'string' && (
                            <p className="mt-2 text-xs text-red-600">{errors.lines}</p>
                        )}
                        <div className="mt-4 flex flex-wrap items-center gap-4">
                            <button
                                type="button"
                                onClick={() => form.setData('lines', [...form.data.lines, { ...blankLine }])}
                                className="text-sm text-indigo-700 hover:underline"
                            >
                                + {t('Add a line')}
                            </button>
                            {products.length > 0 && (
                                <select
                                    aria-label={t('Add from the catalog')}
                                    value=""
                                    onChange={(e) => addProduct(e.target.value)}
                                    className="rounded border border-slate-300 bg-white px-2 py-1 text-sm"
                                >
                                    <option value="">{t('Add from the catalog…')}</option>
                                    {products.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.name} · {p.net_price}
                                        </option>
                                    ))}
                                </select>
                            )}
                        </div>
                    </Card>

                    <Card title={t('Notes')}>
                        <Field label={t('Printed at the bottom of the invoice')} error={errors.notes}>
                            <Textarea value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                        </Field>
                    </Card>
                </div>

                <aside className="space-y-6 lg:sticky lg:top-6 lg:self-start">
                    <Card title={t('Totals')}>
                        {totals === null ? (
                            <p className="text-sm text-slate-500">{t('Fill in the lines to see the totals.')}</p>
                        ) : (
                            <dl className="space-y-1 text-sm" data-testid="totals">
                                {totals.buckets.map((b) => (
                                    <div key={b.rate} className="flex justify-between text-slate-600">
                                        <dt>
                                            {t('VAT')} {b.rate}
                                        </dt>
                                        <dd className="tabular-nums">
                                            {b.net}
                                            {b.vat !== null && ` + ${b.vat}`}
                                        </dd>
                                    </div>
                                ))}
                                <div className="flex justify-between border-t border-slate-200 pt-2">
                                    <dt>{t('Net')}</dt>
                                    <dd className="tabular-nums">{totals.net}</dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt>{t('VAT')}</dt>
                                    <dd className="tabular-nums">{totals.vat}</dd>
                                </div>
                                <div className="flex justify-between text-base font-semibold">
                                    <dt>{t('Gross')}</dt>
                                    <dd className="tabular-nums">{totals.gross} PLN</dd>
                                </div>
                            </dl>
                        )}
                    </Card>

                    {invoice && (
                        <Card title={t('Before it can be sent')}>
                            {invoice.problems.length === 0 ? (
                                <p className="text-sm text-green-700">{t('Nothing stands in the way.')}</p>
                            ) : (
                                <ul className="list-disc space-y-1 pl-5 text-sm text-amber-800">
                                    {invoice.problems.map((p) => (
                                        <li key={p}>{p}</li>
                                    ))}
                                </ul>
                            )}
                        </Card>
                    )}

                    <div className="flex items-center gap-4">
                        <Button type="submit" disabled={form.processing || missingCatalog}>
                            {t('Save draft')}
                        </Button>
                        {invoice && (
                            <button type="button" onClick={remove} className="text-sm text-red-700 hover:underline">
                                {t('Delete')}
                            </button>
                        )}
                    </div>
                </aside>
            </form>
        </AppLayout>
    );
}
