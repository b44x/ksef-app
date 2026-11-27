import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button, Card, Field, Input, Select } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';

interface Series {
    id: number;
    name: string;
    format: string;
    reset: string;
    is_default: boolean;
    next: string;
}

interface Option {
    value: string;
    label: string;
}

const empty = { name: '', format: 'FV/{NNN}/{YYYY}', reset: 'yearly', is_default: false };

export default function NumberingSeries({ series, resets }: { series: Series[]; resets: Option[] }) {
    const t = useT();
    const [editing, setEditing] = useState<number | null>(null);
    const form = useForm(empty);

    const reset = () => {
        setEditing(null);
        form.setData(empty);
        form.clearErrors();
    };

    const edit = (s: Series) => {
        setEditing(s.id);
        form.setData({ name: s.name, format: s.format, reset: s.reset, is_default: s.is_default });
        form.clearErrors();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: reset };
        if (editing === null) {
            form.post('/series', options);
        } else {
            form.put(`/series/${editing}`, options);
        }
    };

    const remove = (s: Series) => {
        if (window.confirm(t('Delete :name?', { name: s.name }))) {
            router.delete(`/series/${s.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <Card title={t('Numbering series')}>
                {series.length === 0 ? (
                    <p className="text-sm text-slate-600">{t('No numbering series yet.')}</p>
                ) : (
                    <table className="w-full text-left text-sm">
                        <thead className="text-slate-500">
                            <tr>
                                <th className="py-1">{t('Name')}</th>
                                <th>{t('Pattern')}</th>
                                <th>{t('Next number')}</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {series.map((s) => (
                                <tr key={s.id}>
                                    <td className="py-2 font-medium">
                                        {s.name}{' '}
                                        {s.is_default && (
                                            <span className="text-xs text-slate-500">({t('default')})</span>
                                        )}
                                    </td>
                                    <td className="font-mono">{s.format}</td>
                                    <td className="font-mono">{s.next}</td>
                                    <td className="space-x-3 text-right">
                                        <button onClick={() => edit(s)} className="text-indigo-700 hover:underline">
                                            {t('Edit')}
                                        </button>
                                        <button onClick={() => remove(s)} className="text-red-700 hover:underline">
                                            {t('Delete')}
                                        </button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Card>

            <Card title={editing === null ? t('Add a series') : t('Edit the series')}>
                <form onSubmit={submit} className="grid gap-4 sm:grid-cols-2">
                    <Field label={t('Name')} error={form.errors.name}>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field
                        label={t('Pattern')}
                        error={form.errors.format}
                        hint={t('For example FV/{NNN}/{MM}/{YYYY} gives FV/007/03/2026.')}
                    >
                        <Input value={form.data.format} onChange={(e) => form.setData('format', e.target.value)} />
                    </Field>
                    <Field label={t('Start again from 1')} error={form.errors.reset}>
                        <Select value={form.data.reset} onChange={(e) => form.setData('reset', e.target.value)}>
                            {resets.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <label className="flex items-center gap-2 self-end text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_default}
                            onChange={(e) => form.setData('is_default', e.target.checked)}
                        />
                        {t('Use by default')}
                    </label>
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
