import { useForm } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';
import { Button, Card, Field, Input } from '../Components/Ui';

export default function Setup() {
    const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

    return (
        <AppLayout>
            <div className="mx-auto max-w-md">
                <Card title="Create the administrator">
                    <p className="mb-4 text-sm text-slate-600">This is the first start. Create the account that manages the invoices.</p>
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/setup', { onFinish: () => form.reset('password', 'password_confirmation') });
                        }}
                    >
                        <Field label="Name" error={form.errors.name}>
                            <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        </Field>
                        <Field label="Email" error={form.errors.email}>
                            <Input type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                        </Field>
                        <Field label="Password" error={form.errors.password} hint="At least 12 characters.">
                            <Input type="password" value={form.data.password} onChange={(e) => form.setData('password', e.target.value)} />
                        </Field>
                        <Field label="Repeat the password">
                            <Input
                                type="password"
                                value={form.data.password_confirmation}
                                onChange={(e) => form.setData('password_confirmation', e.target.value)}
                            />
                        </Field>
                        <Button type="submit" disabled={form.processing}>
                            Create account
                        </Button>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
