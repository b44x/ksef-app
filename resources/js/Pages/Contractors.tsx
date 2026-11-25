import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button, Card, Field, Input } from '../Components/Ui';
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
    const [editing, setEditing] = useState<number | null>(null);
    const form = useForm(empty);

    const edit = (contractor: Contractor) => {
        setEditing(contractor.id);
        form.setData({
            name: contractor.name,
            nip: contractor.nip ?? '',
            country: contractor.country,
            address_line1: contractor.address_line1,
            address_line2: contractor.address_line2 ?? '',
            email: contractor.email ?? '',
        });
        form.clearErrors();
    };

    const reset = () => {
        setEditing(null);
        form.setData(empty);
        form.clearErrors();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: reset };
        if (editing === null) {
            form.post('/contractors', options);
        } else {
            form.put(`/contractors/${editing}`, options);
        }
    };

    const remove = (contractor: Contractor) => {
        if (window.confirm(t('Delete :name?', { name: contractor.name }))) {
            router.delete(`/contractors/${contractor.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Card title={t('Contractors')}>
                {contractors.length === 0 ? (
                    <p className="text-sm text-slate-600">{t('No contractors yet.')}</p>
                ) : (
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500">
                            <tr>
                                <th className="py-1">{t('Name')}</th>
                                <th>{t('NIP')}</th>
                                <th>{t('Address')}</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {contractors.map((c) => (
                                <tr key={c.id}>
                                    <td className="py-2 font-medium">{c.name}</td>
                                    <td>{c.nip ?? '—'}</td>
                                    <td>
                                        {c.address_line1}, {c.country}
                                    </td>
                                    <td className="space-x-3 text-right">
                                        <button onClick={() => edit(c)} className="text-indigo-700 hover:underline">
                                            {t('Edit')}
                                        </button>
                                        <button onClick={() => remove(c)} className="text-red-700 hover:underline">
                                            {t('Delete')}
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Card>

            <Card title={editing === null ? t('Add a contractor') : t('Edit the contractor')}>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label={t('Name')} error={form.errors.name}>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field
                        label={t('NIP')}
                        error={form.errors.nip}
                        hint={t('Leave empty for foreign buyers and consumers.')}
                    >
                        <Input value={form.data.nip} onChange={(e) => form.setData('nip', e.target.value)} />
                    </Field>
                    <Field label={t('Country (two letters)')} error={form.errors.country}>
                        <Input
                            maxLength={2}
                            value={form.data.country}
                            onChange={(e) => form.setData('country', e.target.value)}
                        />
                    </Field>
                    <Field label={t('Email')} error={form.errors.email}>
                        <Input
                            type="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                        />
                    </Field>
                    <Field label={t('Address')} error={form.errors.address_line1}>
                        <Input
                            value={form.data.address_line1}
                            onChange={(e) => form.setData('address_line1', e.target.value)}
                        />
                    </Field>
                    <Field label={t('Address, second line')} error={form.errors.address_line2}>
                        <Input
                            value={form.data.address_line2}
                            onChange={(e) => form.setData('address_line2', e.target.value)}
                        />
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
