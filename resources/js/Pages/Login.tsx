import { useForm } from '@inertiajs/react';
import { Button } from '../Components/Button';
import { Field, Input } from '../Components/Form';
import { useT } from '../i18n';
import GuestLayout from '../Layouts/GuestLayout';

export default function Login() {
    const t = useT();
    const form = useForm({ email: '', password: '', remember: false });

    return (
        <GuestLayout title={t('Log in')}>
            <form
                className="space-y-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    form.post('/login', { onFinish: () => form.reset('password') });
                }}
            >
                <Field label={t('Email')} error={form.errors.email}>
                    <Input
                        type="email"
                        autoComplete="email"
                        autoFocus
                        value={form.data.email}
                        onChange={(e) => form.setData('email', e.target.value)}
                    />
                </Field>
                <Field label={t('Password')} error={form.errors.password}>
                    <Input
                        type="password"
                        autoComplete="current-password"
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                    />
                </Field>
                <label className="flex items-center gap-2 text-sm text-muted">
                    <input
                        type="checkbox"
                        className="size-4 rounded border-border-strong accent-[var(--primary)]"
                        checked={form.data.remember}
                        onChange={(e) => form.setData('remember', e.target.checked)}
                    />
                    {t('Remember me')}
                </label>
                <Button type="submit" className="w-full" disabled={form.processing}>
                    {t('Log in')}
                </Button>
            </form>
        </GuestLayout>
    );
}
