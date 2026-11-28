import { router, useForm } from '@inertiajs/react';
import { Pause, Pencil, Play, Repeat, Trash2, Zap } from 'lucide-react';
import { useState } from 'react';
import { Button } from '../Components/Button';
import { Dialog } from '../Components/Dialog';
import { Field, Input, Select } from '../Components/Form';
import { Alert, Badge, Card, EmptyState, PageHeader, Table } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';

interface Schedule {
    id: number;
    name: string;
    contractor: string;
    frequency: string;
    frequency_label: string;
    next_run_on: string;
    ends_on: string | null;
    active: boolean;
    auto_issue: boolean;
    generated_count: number;
    last_error: string | null;
    lines: number;
}

export default function Recurring({
    schedules,
    frequencies,
}: {
    schedules: Schedule[];
    frequencies: { value: string; label: string }[];
}) {
    const t = useT();
    const [editing, setEditing] = useState<Schedule | null>(null);
    const form = useForm({
        name: '',
        frequency: 'monthly',
        next_run_on: '',
        ends_on: '',
        auto_issue: false,
        active: true,
    });

    const edit = (s: Schedule) => {
        form.setData({
            name: s.name,
            frequency: s.frequency,
            next_run_on: s.next_run_on,
            ends_on: s.ends_on ?? '',
            auto_issue: s.auto_issue,
            active: s.active,
        });
        setEditing(s);
    };
    const toggle = (s: Schedule) =>
        router.put(
            `/recurring/${s.id}`,
            {
                name: s.name,
                frequency: s.frequency,
                next_run_on: s.next_run_on,
                ends_on: s.ends_on ?? '',
                auto_issue: s.auto_issue,
                active: !s.active,
            },
            { preserveScroll: true },
        );

    return (
        <AppLayout>
            <PageHeader
                title={t('Recurring invoices')}
                description={t('Invoices that repeat on their own. Start one from any invoice with "Repeat".')}
            />

            <Card className="[&>div]:p-0">
                {schedules.length === 0 ? (
                    <EmptyState
                        icon={<Repeat className="size-6" />}
                        title={t('Nothing repeats yet.')}
                        description={t(
                            'Open an invoice and choose "Repeat" to make it recur every week, month, quarter or year.',
                        )}
                    />
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <th>{t('Name')}</th>
                                <th>{t('Buyer')}</th>
                                <th>{t('How often')}</th>
                                <th>{t('Next one')}</th>
                                <th>{t('Made so far')}</th>
                                <th />
                            </tr>
                        </thead>
                        <tbody>
                            {schedules.map((s) => (
                                <tr key={s.id} className={s.active ? '' : 'opacity-60'}>
                                    <td>
                                        <div className="font-medium">{s.name}</div>
                                        <div className="text-xs text-muted">
                                            {s.auto_issue ? t('Issued automatically') : t('Left as a draft')}
                                            {!s.active && ` · ${t('Paused')}`}
                                        </div>
                                        {s.last_error && (
                                            <div className="mt-1">
                                                <Badge tone="danger">{t('Needs a look')}</Badge>
                                                <span className="ml-2 text-xs text-danger">{s.last_error}</span>
                                            </div>
                                        )}
                                    </td>
                                    <td>{s.contractor}</td>
                                    <td>{s.frequency_label}</td>
                                    <td className="tabular-nums">
                                        {s.active ? s.next_run_on : '—'}
                                        {s.ends_on && (
                                            <div className="text-xs text-muted">
                                                {t('until')} {s.ends_on}
                                            </div>
                                        )}
                                    </td>
                                    <td className="tabular-nums">{s.generated_count}</td>
                                    <td>
                                        <div className="flex justify-end gap-1">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => router.post(`/recurring/${s.id}/run`)}
                                                title={t('Make one now')}
                                            >
                                                <Zap className="size-4" />
                                                <span className="sr-only">{t('Make one now')}</span>
                                            </Button>
                                            <Button variant="ghost" size="sm" onClick={() => toggle(s)}>
                                                {s.active ? <Pause className="size-4" /> : <Play className="size-4" />}
                                                <span className="sr-only">{s.active ? t('Pause') : t('Resume')}</span>
                                            </Button>
                                            <Button variant="ghost" size="sm" onClick={() => edit(s)}>
                                                <Pencil className="size-4" />
                                                <span className="sr-only">{t('Edit')}</span>
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    window.confirm(
                                                        t(
                                                            'Stop repeating this invoice? The invoices already made stay.',
                                                        ),
                                                    ) && router.delete(`/recurring/${s.id}`, { preserveScroll: true })
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                                <span className="sr-only">{t('Delete')}</span>
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>

            <Dialog open={editing !== null} onClose={() => setEditing(null)} title={t('Edit the schedule')}>
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(`/recurring/${editing?.id}`, {
                            preserveScroll: true,
                            onSuccess: () => setEditing(null),
                        });
                    }}
                >
                    {Object.keys(form.errors).length > 0 && (
                        <Alert tone="danger">{Object.values(form.errors)[0]}</Alert>
                    )}
                    <Field label={t('Name')}>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <div className="grid grid-cols-2 gap-3">
                        <Field label={t('How often')}>
                            <Select
                                value={form.data.frequency}
                                onChange={(e) => form.setData('frequency', e.target.value)}
                            >
                                {frequencies.map((f) => (
                                    <option key={f.value} value={f.value}>
                                        {f.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('Next one')}>
                            <Input
                                type="date"
                                value={form.data.next_run_on}
                                onChange={(e) => form.setData('next_run_on', e.target.value)}
                            />
                        </Field>
                    </div>
                    <Field label={t('Last invoice on')}>
                        <Input
                            type="date"
                            value={form.data.ends_on}
                            onChange={(e) => form.setData('ends_on', e.target.value)}
                        />
                    </Field>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="size-4 rounded border-border-strong accent-[var(--primary)]"
                            checked={form.data.auto_issue}
                            onChange={(e) => form.setData('auto_issue', e.target.checked)}
                        />
                        {t('Issue it straight away')}
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            className="size-4 rounded border-border-strong accent-[var(--primary)]"
                            checked={form.data.active}
                            onChange={(e) => form.setData('active', e.target.checked)}
                        />
                        {t('Active')}
                    </label>
                    <div className="flex justify-end gap-2">
                        <Button variant="secondary" onClick={() => setEditing(null)}>
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
