import { usePage } from '@inertiajs/react';
import { useForm } from '@inertiajs/react';
import { Mail } from 'lucide-react';
import { useT } from '../i18n';
import type { SharedProps } from '../types';
import { Button } from './Button';
import { Dialog } from './Dialog';
import { Field, Input, Select, Textarea } from './Form';
import { Alert } from './Ui';

export interface EmailInfo {
    can_send: boolean;
    to: string;
    subject: string;
    message: string;
    sent: { to: string; at: string }[];
}

/** Sends the invoice to the buyer as a PDF attachment, in the language chosen. */
export function EmailDialog({
    invoiceId,
    email,
    open,
    onClose,
}: {
    invoiceId: number;
    email: EmailInfo;
    open: boolean;
    onClose: () => void;
}) {
    const t = useT();
    const { locale, errors } = usePage<SharedProps>().props as SharedProps & { errors: Record<string, string> };
    const form = useForm({
        to: email.to,
        subject: email.subject,
        message: email.message,
        lang: locale === 'pl' ? 'pl' : 'en',
    });

    return (
        <Dialog open={open} onClose={onClose} title={t('Send by e-mail')}>
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post(`/invoices/${invoiceId}/email`, { preserveScroll: true, onSuccess: onClose });
                }}
            >
                {errors.email && <Alert tone="danger">{errors.email}</Alert>}
                <Alert tone="info">
                    {t(
                        'The invoice goes as a PDF. Replies go to your company e-mail. Needs the mail server set in the environment (MAIL_*).',
                    )}
                </Alert>
                <Field label={t('To')} error={form.errors.to}>
                    <Input
                        type="email"
                        autoFocus
                        value={form.data.to}
                        onChange={(e) => form.setData('to', e.target.value)}
                    />
                </Field>
                <Field label={t('Subject')} error={form.errors.subject}>
                    <Input value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} />
                </Field>
                <Field label={t('Message')} error={form.errors.message}>
                    <Textarea
                        rows={6}
                        value={form.data.message}
                        onChange={(e) => form.setData('message', e.target.value)}
                    />
                </Field>
                <Field label={t('Language of the PDF')}>
                    <Select value={form.data.lang} onChange={(e) => form.setData('lang', e.target.value)}>
                        <option value="pl">Polski</option>
                        <option value="en">English</option>
                    </Select>
                </Field>
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        {t('Cancel')}
                    </Button>
                    <Button type="submit" disabled={form.processing || form.data.to === ''}>
                        <Mail className="size-4" />
                        {t('Send')}
                    </Button>
                </div>
            </form>
        </Dialog>
    );
}
