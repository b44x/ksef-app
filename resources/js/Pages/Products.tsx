import { router, useForm } from '@inertiajs/react';
import { Package, Plus, Upload } from 'lucide-react';
import { useState } from 'react';
import { Button } from '../Components/Button';
import { Dialog } from '../Components/Dialog';
import { ImportDialog } from '../Components/ImportDialog';
import { Field, Input, Select } from '../Components/Form';
import { RowActions } from '../Components/RowActions';
import { Badge, Card, EmptyState, PageHeader, Table } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import { useMoney } from '../lib/format';

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
    const money = useMoney();
    const [open, setOpen] = useState(false);
    const [importing, setImporting] = useState(false);
    const [editing, setEditing] = useState<number | null>(null);
    const form = useForm(empty);

    const close = () => {
        setOpen(false);
        setEditing(null);
        form.setData(empty);
        form.clearErrors();
    };
    const add = () => {
        close();
        setOpen(true);
    };
    const edit = (p: Product) => {
        form.setData({ name: p.name, unit: p.unit, net_price: p.net_price, vat_rate: p.vat_rate });
        setEditing(p.id);
        setOpen(true);
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: close };
        if (editing === null) form.post('/products', options);
        else form.put(`/products/${editing}`, options);
    };
    const remove = (p: Product) => {
        if (window.confirm(t('Delete :name?', { name: p.name }))) {
            router.delete(`/products/${p.id}`, { preserveScroll: true });
        }
    };
    const set = (name: keyof typeof empty) => (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>) =>
        form.setData(name, e.target.value);

    return (
        <AppLayout>
            <PageHeader
                title={t('Products and services')}
                description={t('What you sell, with the usual price and VAT rate.')}
                actions={
                    <>
                        <Button variant="secondary" onClick={() => setImporting(true)}>
                            <Upload className="size-4" />
                            {t('Import from CSV')}
                        </Button>
                        <Button onClick={add}>
                            <Plus className="size-4" />
                            {t('Add a product or service')}
                        </Button>
                    </>
                }
            />

            <Card className="[&>div]:p-0">
                {products.length === 0 ? (
                    <EmptyState
                        icon={<Package className="size-6" />}
                        title={t('No products yet.')}
                        description={t('Save what you sell often, then add it to an invoice in one click.')}
                        action={<Button onClick={add}>{t('Add a product or service')}</Button>}
                    />
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <th>{t('Name')}</th>
                                <th>{t('Unit')}</th>
                                <th className="text-right">{t('Net price')}</th>
                                <th>{t('VAT')}</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {products.map((p) => (
                                <tr key={p.id}>
                                    <td className="font-medium">{p.name}</td>
                                    <td className="text-muted">{p.unit}</td>
                                    <td className="text-right tabular-nums">{money(p.net_price)}</td>
                                    <td>
                                        <Badge>{p.vat_rate}</Badge>
                                    </td>
                                    <td>
                                        <RowActions onEdit={() => edit(p)} onDelete={() => remove(p)} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            <Dialog
                open={open}
                onClose={close}
                title={editing === null ? t('Add a product or service') : t('Edit the product')}
            >
                <form onSubmit={submit} className="space-y-4">
                    <Field label={t('Name')} error={form.errors.name}>
                        <Input autoFocus value={form.data.name} onChange={set('name')} />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field label={t('Unit')} error={form.errors.unit}>
                            <Input value={form.data.unit} onChange={set('unit')} />
                        </Field>
                        <Field label={t('Net price')} error={form.errors.net_price}>
                            <Input inputMode="decimal" value={form.data.net_price} onChange={set('net_price')} />
                        </Field>
                        <Field label={t('VAT rate')} error={form.errors.vat_rate}>
                            <Select value={form.data.vat_rate} onChange={set('vat_rate')}>
                                {vatRates.map((rate) => (
                                    <option key={rate} value={rate}>
                                        {rate}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button variant="secondary" onClick={close}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {t('Save')}
                        </Button>
                    </div>
                </form>
            </Dialog>
            <ImportDialog
                open={importing}
                onClose={() => setImporting(false)}
                title={t('Import products and services')}
                url="/products/import"
                columns="name, unit, net price, VAT rate"
            />
        </AppLayout>
    );
}
