import { useForm } from '@inertiajs/react';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import { Button, Card, Field, Input } from '../Components/Ui';

export default function Login() {
    const t = useT();
    const form = useForm({ email: '', password: '', remember: false });

    return (
        <AppLayout>
            <div className="mx-auto max-w-md">
                <Card title={t('Log in')}>
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
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                            />
                        </Field>
                        <Field label={t('Password')} error={form.errors.password}>
                            <Input
                                type="password"
                                value={form.data.password}
                                onChange={(e) => form.setData('password', e.target.value)}
                            />
                        </Field>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={form.data.remember}
                                onChange={(e) => form.setData('remember', e.target.checked)}
                            />
                            {t('Remember me')}
                        </label>
                        <Button type="submit" disabled={form.processing}>
                            {t('Log in')}
                        </Button>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
