import { useForm } from '@inertiajs/react';
import { Button } from '../Components/Button';
import { Field, Input } from '../Components/Form';
import { useT } from '../i18n';
import GuestLayout from '../Layouts/GuestLayout';

export default function Setup() {
    const t = useT();
    const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

    return (
        <GuestLayout
            title={t('Create the administrator')}
            description={t('This is the first start. Create the account that manages the invoices.')}
        >
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/setup', { onFinish: () => form.reset('password', 'password_confirmation') });
                }}
            >
                <Field label={t('Your name')} error={form.errors.name}>
                    <Input
                        autoComplete="name"
                        value={form.data.name}
                        onChange={(e) => form.setData('name', e.target.value)}
                    />
                </Field>
                <Field label={t('Email')} error={form.errors.email}>
                    <Input
                        type="email"
                        autoComplete="email"
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                </Field>
                <Field label={t('Password')} error={form.errors.password} hint={t('At least 12 characters.')}>
                    <Input
                        type="password"
                        autoComplete="new-password"
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                    />
                </Field>
                <Field label={t('Repeat the password')}>
                    <Input
                        type="password"
                        autoComplete="new-password"
                        value={form.data.password_confirmation}
                        onChange={(e) => form.setData('password_confirmation', e.target.value)}
                    />
                </Field>
                <Button type="submit" className="w-full" disabled={form.processing}>
                    {t('Create account')}
                </Button>
            </form>
        </GuestLayout>
    );
}
