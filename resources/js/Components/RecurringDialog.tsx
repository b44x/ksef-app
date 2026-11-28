import { useForm } from '@inertiajs/react';
import { Repeat } from 'lucide-react';
import { useT } from '../i18n';
import { Button } from './Button';
import { Dialog } from './Dialog';
import { Field, Input, Select } from './Form';
import { Alert } from './Ui';

/** Repeats an invoice on a schedule: the buyer, lines and payment terms are copied from it. */
export function RecurringDialog({
    invoiceId,
    suggestedName,
    firstRun,
    open,
    onClose,
}: {
    invoiceId: number;
    suggestedName: string;
    firstRun: string;
    open: boolean;
    onClose: () => void;
}) {
    const t = useT();
    const form = useForm({
        name: suggestedName,
        frequency: 'monthly',
        next_run_on: firstRun,
        ends_on: '',
        auto_issue: false,
    });

    return (
        <Dialog open={open} onClose={onClose} title={t('Repeat this invoice')}>
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/invoices/${invoiceId}/recurring`, { onSuccess: onClose });
                }}
            >
                <Alert tone="info">
                    {t(
                        'The buyer, lines and payment terms are copied. The invoices are made early in the morning of each date.',
                    )}
                </Alert>
                <Field label={t('Name')} error={form.errors.name}>
                    <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                </Field>
                <div className="grid grid-cols-2 gap-3">
                    <Field label={t('How often')} error={form.errors.frequency}>
                        <Select value={form.data.frequency} onChange={(e) => form.setData('frequency', e.target.value)}>
                            <option value="weekly">{t('Every week')}</option>
                            <option value="monthly">{t('Every month')}</option>
                            <option value="quarterly">{t('Every quarter')}</option>
                            <option value="yearly">{t('Every year')}</option>
                        </Select>
                    </Field>
                    <Field label={t('First invoice on')} error={form.errors.next_run_on}>
                        <Input
                            type="date"
                            value={form.data.next_run_on}
                            onChange={(e) => form.setData('next_run_on', e.target.value)}
                        />
                    </Field>
                </div>
                <Field
                    label={t('Last invoice on')}
                    hint={t('Leave empty to repeat until you stop it.')}
                    error={form.errors.ends_on}
                >
                    <Input
                        type="date"
                        value={form.data.ends_on}
                        onChange={(e) => form.setData('ends_on', e.target.value)}
                    />
                </Field>
                <label className="flex items-start gap-2 text-sm">
                    <input
                        type="checkbox"
                        className="mt-0.5 size-4 rounded border-border-strong accent-[var(--primary)]"
                        checked={form.data.auto_issue}
                        onChange={(e) => form.setData('auto_issue', e.target.checked)}
                    />
                    <span>
                        {t('Issue it straight away')}
                        <span className="block text-xs text-muted">
                            {t(
                                'Otherwise a draft waits for you to look it over. Sending to KSeF is always your decision.',
                            )}
                        </span>
                    </span>
                </label>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={form.processing || form.data.name.trim() === ''}>
                        <Repeat className="size-4" />
                        {t('Repeat')}
                    </Button>
                </div>
            </form>
        </Dialog>
    );
}
