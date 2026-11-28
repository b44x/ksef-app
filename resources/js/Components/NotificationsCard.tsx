import { router, useForm, usePage } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useT } from '../i18n';
import type { SharedProps } from '../types';
import { Button } from './Button';
import { Field, Input } from './Form';
import { Alert, Card } from './Ui';

export interface Notifications {
    email: string;
    offline: boolean;
    purchases: boolean;
    rejected: boolean;
}

/** The daily e-mail summary: where it goes and what it may contain. The mail server is set up in the environment. */
export function NotificationsCard({ notifications }: { notifications: Notifications }) {
    const t = useT();
    const { errors } = usePage<SharedProps>().props as SharedProps & { errors: Record<string, string> };
    const form = useForm({
        notify_email: notifications.email,
        notify_offline: notifications.offline,
        notify_purchases: notifications.purchases,
        notify_rejected: notifications.rejected,
    });

    const topics = [
        ['notify_offline', t('Offline invoices waiting for delivery, with their deadlines')],
        ['notify_purchases', t('New purchase invoices')],
        ['notify_rejected', t('Invoices rejected by KSeF')],
    ] as const;

    return (
        <Card
            title={t('E-mail summary')}
            description={t(
                'Once a day, only when there is something to tell. Needs the mail server set in the environment (MAIL_*).',
            )}
            className="mt-6"
        >
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.put('/company/notifications', { preserveScroll: true });
                }}
            >
                <Field
                    label={t('Send it to')}
                    hint={t('Leave empty to send nothing.')}
                    error={form.errors.notify_email}
                >
                    <Input
                        type="email"
                        value={form.data.notify_email}
                        onChange={(e) => form.setData('notify_email', e.target.value)}
                    />
                </Field>
                <fieldset className="space-y-2">
                    <legend className="mb-1 text-sm font-medium">{t('What it may contain')}</legend>
                    {topics.map(([key, label]) => (
                        <label key={key} className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                className="size-4 rounded border-border-strong accent-[var(--primary)]"
                                checked={form.data[key]}
                                onChange={(e) => form.setData(key, e.target.checked)}
                            />
                            {label}
                        </label>
                    ))}
                </fieldset>
                {errors.notify && <Alert tone="warning">{errors.notify}</Alert>}
                <div className="flex justify-end gap-2">
                    <Button
                        variant="secondary"
                        disabled={notifications.email === ''}
                        onClick={() => router.post('/company/notifications/test', {}, { preserveScroll: true })}
                    >
                        <Mail className="size-4" />
                        {t('Send a test message')}
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        {t('Save')}
                    </Button>
                </div>
            </form>
        </Card>
    );
}
