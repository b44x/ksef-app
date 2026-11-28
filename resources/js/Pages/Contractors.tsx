import { router, useForm } from '@inertiajs/react';
import { Loader2, Plus, Search, Upload, Users } from 'lucide-react';
import { useState } from 'react';
import { Button } from '../Components/Button';
import { Dialog } from '../Components/Dialog';
import { ImportDialog } from '../Components/ImportDialog';
import { Field, Input } from '../Components/Form';
import { RowActions } from '../Components/RowActions';
import { Alert, Card, EmptyState, PageHeader, Table } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';

interface Contractor {
    id: number;
    name: string;
    nip: string | null;
    country: string;
    address_line1: string;
    address_line2: string | null;
    email: string | null;
}

const empty = { name: '', nip: '', country: 'PL', address_line1: '', address_line2: '', email: '' };

export default function Contractors({ contractors }: { contractors: Contractor[] }) {
    const t = useT();
    const [open, setOpen] = useState(false);
    const [importing, setImporting] = useState(false);
    const [editing, setEditing] = useState<number | null>(null);
    const form = useForm(empty);
    const [lookup, setLookup] = useState<{ busy: boolean; message: string | null; tone: 'success' | 'warning' }>({
        busy: false,
        message: null,
        tone: 'success',
    });

    /** Fills the form from the Ministry of Finance's register. Only what it returns; the rest stays as typed. */
    const fetchFromRegister = async () => {
        setLookup({ busy: true, message: null, tone: 'success' });
        try {
            const response = await fetch(`/contractors/lookup?nip=${encodeURIComponent(form.data.nip)}`, {
                headers: { Accept: 'application/json' },
            });
            const body = await response.json();
            if (!response.ok) {
                setLookup({
                    busy: false,
                    message: body.message ?? t('The register could not be reached.'),
                    tone: 'warning',
                });
                return;
            }
            form.setData({
                ...form.data,
                name: body.name,
                nip: body.nip,
                country: 'PL',
                address_line1: body.address_line1,
                address_line2: body.address_line2,
            });
            setLookup({
                busy: false,
                message: body.vat_status ? `${t('VAT status')}: ${body.vat_status}` : t('Found in the register.'),
                tone: body.vat_status === 'Czynny' ? 'success' : 'warning',
            });
        } catch {
            setLookup({ busy: false, message: t('The register could not be reached.'), tone: 'warning' });
        }
    };

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
    const edit = (c: Contractor) => {
        form.setData({
            name: c.name,
            nip: c.nip ?? '',
            country: c.country,
            address_line1: c.address_line1,
            address_line2: c.address_line2 ?? '',
            email: c.email ?? '',
        });
        setEditing(c.id);
        setOpen(true);
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: close };
        if (editing === null) form.post('/contractors', options);
        else form.put(`/contractors/${editing}`, options);
    };
    const remove = (c: Contractor) => {
        if (window.confirm(t('Delete :name?', { name: c.name }))) {
            router.delete(`/contractors/${c.id}`, { preserveScroll: true });
        }
    };
    const set = (name: keyof typeof empty) => (e: React.ChangeEvent<HTMLInputElement>) =>
        form.setData(name, e.target.value);

    return (
        <AppLayout>
            <PageHeader
                title={t('Contractors')}
                description={t('The buyers you issue invoices to.')}
                actions={
                    <>
                        <Button variant="secondary" onClick={() => setImporting(true)}>
                            <Upload className="size-4" />
                            {t('Import from CSV')}
                        </Button>
                        <Button onClick={add}>
                            <Plus className="size-4" />
                            {t('Add a contractor')}
                        </Button>
                    </>
                }
            />

            <Card className="[&>div]:p-0">
                {contractors.length === 0 ? (
                    <EmptyState
                        icon={<Users className="size-6" />}
                        title={t('No contractors yet.')}
                        description={t(
                            'Add the companies and people you sell to; you pick them when you write an invoice.',
                        )}
                        action={<Button onClick={add}>{t('Add a contractor')}</Button>}
                    />
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <th>{t('Name')}</th>
                                <th>{t('NIP')}</th>
                                <th>{t('Address')}</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {contractors.map((c) => (
                                <tr key={c.id}>
                                    <td>
                                        <div className="font-medium">{c.name}</div>
                                        {c.email && <div className="text-xs text-muted">{c.email}</div>}
                                    </td>
                                    <td className="tabular-nums">{c.nip ?? '—'}</td>
                                    <td className="text-muted">
                                        {c.address_line1}
                                        {c.address_line2 ? `, ${c.address_line2}` : ''} · {c.country}
                                    </td>
                                    <td>
                                        <RowActions onEdit={() => edit(c)} onDelete={() => remove(c)} />
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
                title={editing === null ? t('Add a contractor') : t('Edit the contractor')}
            >
                <form onSubmit={submit} className="space-y-4">
                    <Field label={t('Name')} error={form.errors.name}>
                        <Input autoFocus value={form.data.name} onChange={set('name')} />
                    </Field>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <Field
                            label={t('NIP')}
                            error={form.errors.nip}
                            className="sm:col-span-2"
                            hint={t('Leave empty for foreign buyers and consumers.')}
                        >
                            <div className="flex gap-2">
                                <Input inputMode="numeric" value={form.data.nip} onChange={set('nip')} />
                                <Button
                                    variant="secondary"
                                    onClick={fetchFromRegister}
                                    disabled={lookup.busy || form.data.nip.replace(/\D/g, '').length !== 10}
                                >
                                    {lookup.busy ? (
                                        <Loader2 className="size-4 animate-spin" />
                                    ) : (
                                        <Search className="size-4" />
                                    )}
                                    {t('Fetch')}
                                </Button>
                            </div>
                        </Field>
                        <Field label={t('Country (two letters)')} error={form.errors.country}>
                            <Input maxLength={2} value={form.data.country} onChange={set('country')} />
                        </Field>
                    </div>
                    {lookup.message && <Alert tone={lookup.tone}>{lookup.message}</Alert>}
                    <Field label={t('Address')} error={form.errors.address_line1}>
                        <Input value={form.data.address_line1} onChange={set('address_line1')} />
                    </Field>
                    <Field label={t('Address, second line')} error={form.errors.address_line2}>
                        <Input value={form.data.address_line2} onChange={set('address_line2')} />
                    </Field>
                    <Field label={t('Email')} error={form.errors.email}>
                        <Input type="email" value={form.data.email} onChange={set('email')} />
                    </Field>
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
                title={t('Import contractors')}
                url="/contractors/import"
                columns="name, NIP, country, address, address 2, e-mail"
            />
        </AppLayout>
    );
}
