import { useForm } from '@inertiajs/react';
import { useT } from '../i18n';
import { Button } from './Button';
import { Field, Input, Select, Textarea } from './Form';
import { Card } from './Ui';

export interface Defaults {
    payment_days: string;
    payment_method: string;
    bank_account: string;
    notes: string;
}

/** What a new invoice starts with, so it does not have to be typed every time. */
export function DefaultsCard({
    defaults,
    paymentMethods,
}: {
    defaults: Defaults;
    paymentMethods: { value: string; label: string }[];
}) {
    const t = useT();
    const form = useForm({
        default_payment_days: defaults.payment_days,
        default_payment_method: defaults.payment_method,
        default_bank_account: defaults.bank_account,
        default_notes: defaults.notes,
    });

    return (
        <Card
            title={t('Invoice defaults')}
            description={t('A new invoice starts with these. You can change them on each invoice.')}
            className="mt-6"
        >
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/company/defaults', { preserveScroll: true });
                }}
            >
                <div className="grid gap-4 sm:grid-cols-2">
                    <Field
                        label={t('Payment term (days)')}
                        error={form.errors.default_payment_days}
                        hint={t('Leave empty for no due date.')}
                    >
                        <Input
                            inputMode="numeric"
                            value={form.data.default_payment_days}
                            onChange={(e) => form.setData('default_payment_days', e.target.value.replace(/\D/g, ''))}
                        />
                    </Field>
                    <Field label={t('Payment method')} error={form.errors.default_payment_method}>
                        <Select
                            value={form.data.default_payment_method}
                            onChange={(e) => form.setData('default_payment_method', e.target.value)}
                        >
                            <option value="">{t('Not set')}</option>
                            {paymentMethods.map((m) => (
                                <option key={m.value} value={m.value}>
                                    {m.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                </div>
                <Field label={t('Bank account')} error={form.errors.default_bank_account}>
                    <Input
                        className="font-mono"
                        value={form.data.default_bank_account}
                        onChange={(e) => form.setData('default_bank_account', e.target.value)}
                    />
                </Field>
                <Field label={t('Printed at the bottom of the invoice')} error={form.errors.default_notes}>
                    <Textarea
                        rows={3}
                        value={form.data.default_notes}
                        onChange={(e) => form.setData('default_notes', e.target.value)}
                    />
                </Field>
                <div className="flex justify-end">
                    <Button type="submit" disabled={form.processing}>
                        {t('Save')}
                    </Button>
                </div>
            </form>
        </Card>
    );
}
