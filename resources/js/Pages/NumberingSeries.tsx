import { router, useForm } from '@inertiajs/react';
import { Hash, Plus } from 'lucide-react';
import { useState } from 'react';
import { Button } from '../Components/Button';
import { Dialog } from '../Components/Dialog';
import { Field, Input, Select } from '../Components/Form';
import { RowActions } from '../Components/RowActions';
import { Badge, Card, EmptyState, PageHeader, Table } from '../Components/Ui';
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
    const [open, setOpen] = useState(false);
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
    const edit = (s: Series) => {
        form.setData({ name: s.name, format: s.format, reset: s.reset, is_default: s.is_default });
        setEditing(s.id);
        setOpen(true);
    };
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = { preserveScroll: true, onSuccess: close };
        if (editing === null) form.post('/series', options);
        else form.put(`/series/${editing}`, options);
    };
    const remove = (s: Series) => {
        if (window.confirm(t('Delete :name?', { name: s.name }))) {
            router.delete(`/series/${s.id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout>
            <PageHeader
                title={t('Numbering series')}
                description={t('How invoice numbers look and when the counter starts again.')}
                actions={
                    <Button onClick={add}>
                        <Plus className="size-4" />
                        {t('Add a series')}
                    </Button>
                }
            />

            <Card className="[&>div]:p-0">
                {series.length === 0 ? (
                    <EmptyState
                        icon={<Hash className="size-6" />}
                        title={t('No numbering series yet.')}
                        description={t('A series gives every invoice its number, without gaps and without repeats.')}
                        action={<Button onClick={add}>{t('Add a series')}</Button>}
                    />
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <th>{t('Name')}</th>
                                <th>{t('Pattern')}</th>
                                <th>{t('Next number')}</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {series.map((s) => (
                                <tr key={s.id}>
                                    <td>
                                        <span className="font-medium">{s.name}</span>{' '}
                                        {s.is_default && <Badge tone="primary">{t('default')}</Badge>}
                                    </td>
                                    <td className="font-mono text-xs text-muted">{s.format}</td>
                                    <td className="font-mono text-xs">{s.next}</td>
                                    <td>
                                        <RowActions onEdit={() => edit(s)} onDelete={() => remove(s)} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            <Dialog open={open} onClose={close} title={editing === null ? t('Add a series') : t('Edit the series')}>
                <form onSubmit={submit} className="space-y-4">
                    <Field label={t('Name')} error={form.errors.name}>
                        <Input
                            autoFocus
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                    </Field>
                    <Field
                        label={t('Pattern')}
                        error={form.errors.format}
                        hint={t('For example FV/{NNN}/{MM}/{YYYY} gives FV/007/03/2026.')}
                    >
                        <Input
                            className="font-mono"
                            value={form.data.format}
                            onChange={(e) => form.setData('format', e.target.value)}
                        />
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
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="size-4 rounded border-border-strong accent-[var(--primary)]"
                            checked={form.data.is_default}
                            onChange={(e) => form.setData('is_default', e.target.checked)}
                        />
                        {t('Use by default')}
                    </label>
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
        </AppLayout>
    );
}
