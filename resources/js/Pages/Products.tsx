import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button, Card, Field, Input, Select } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';

interface Product {
    id: number;
    name: string;
    unit: string;
    net_price: string;
    vat_rate: string;
}

const empty = { name: '', unit: 'szt.', net_price: '', vat_rate: '23' };

export default function Products({ products, vatRates }: { products: Product[]; vatRates: string[] }) {
    const t = useT();
    const [editing, setEditing] = useState<number | null>(null);
    const form = useForm(empty);

    const reset = () => {
        setEditing(null);
        form.setData(empty);
        form.clearErrors();
    };

    const edit = (product: Product) => {
        setEditing(product.id);
        form.setData({
            name: product.name,
            unit: product.unit,
            net_price: product.net_price,
            vat_rate: product.vat_rate,
        });
        form.clearErrors();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: reset };
        if (editing === null) {
            form.post('/products', options);
        } else {
            form.put(`/products/${editing}`, options);
        }
    };

    const remove = (product: Product) => {
        if (window.confirm(t('Delete :name?', { name: product.name }))) {
            router.delete(`/products/${product.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Card title={t('Products and services')}>
                {products.length === 0 ? (
                    <p className="text-sm text-slate-600">{t('No products yet.')}</p>
                ) : (
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500">
                            <tr>
                                <th className="py-1">{t('Name')}</th>
                                <th>{t('Unit')}</th>
                                <th className="text-right">{t('Net price')}</th>
                                <th>{t('VAT')}</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {products.map((p) => (
                                <tr key={p.id}>
                                    <td className="py-2 font-medium">{p.name}</td>
                                    <td>{p.unit}</td>
                                    <td className="text-right tabular-nums">{p.net_price}</td>
                                    <td>{p.vat_rate}</td>
                                    <td className="space-x-3 text-right">
                                        <button onClick={() => edit(p)} className="text-indigo-700 hover:underline">
                                            {t('Edit')}
                                        </button>
                                        <button onClick={() => remove(p)} className="text-red-700 hover:underline">
                                            {t('Delete')}
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Card>

            <Card title={editing === null ? t('Add a product or service') : t('Edit the product')}>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label={t('Name')} error={form.errors.name}>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field label={t('Unit')} error={form.errors.unit}>
                        <Input value={form.data.unit} onChange={(e) => form.setData('unit', e.target.value)} />
                    </Field>
                    <Field label={t('Net price')} error={form.errors.net_price}>
                        <Input
                            inputMode="decimal"
                            value={form.data.net_price}
                            onChange={(e) => form.setData('net_price', e.target.value)}
                        />
                    </Field>
                    <Field label={t('VAT rate')} error={form.errors.vat_rate}>
                        <Select value={form.data.vat_rate} onChange={(e) => form.setData('vat_rate', e.target.value)}>
                            {vatRates.map((rate) => (
                                <option key={rate} value={rate}>
                                    {rate}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <div className="flex gap-3 sm:col-span-2">
                        <Button type="submit" disabled={form.processing}>
                            {t('Save')}
                        </Button>
                        {editing !== null && (
                            <button type="button" onClick={reset} className="text-sm text-slate-600 hover:underline">
                                {t('Cancel')}
                            </button>
                        )}
                    </div>
                </form>
            </Card>
        </AppLayout>
    );
}
