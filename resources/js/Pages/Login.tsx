import { useForm } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';
import { Button, Card, Field, Input } from '../Components/Ui';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });

    return (
        <AppLayout>
            <div className="mx-auto max-w-md">
                <Card title="Log in">
                    <form
                        className="space-y-4"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post('/login', { onFinish: () => form.reset('password') });
                        }}
                    >
                        <Field label="Email" error={form.errors.email}>
                            <Input
                                type="email"
                                value={form.data.email}
                                onChange={(e) => form.setData('email', e.target.value)}
                            />
                        </Field>
                        <Field label="Password" error={form.errors.password}>
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
                            Remember me
                        </label>
                        <Button type="submit" disabled={form.processing}>
                            Log in
                        </Button>
                    </form>
                </Card>
            </div>
        </AppLayout>
    );
}
