import { useForm, usePage } from '@inertiajs/react';
import AppLayout from '../Layouts/AppLayout';
import { Button, Card, Field, Input, Select, Textarea } from '../Components/Ui';
import ConnectionReport from '../Components/ConnectionReport';
import type { SharedProps } from '../types';

interface Option {
    value: string;
    label: string;
}

interface Props {
    company: { name: string; nip: string; environment: string; auth_method: string; has_secrets: boolean } | null;
    environments: Option[];
    methods: Option[];
}

export default function Company({ company, environments, methods }: Props) {
    const { report } = usePage<SharedProps>().props;
    const form = useForm({
        name: company?.name ?? '',
        nip: company?.nip ?? '',
        environment: company?.environment ?? 'test',
        auth_method: company?.auth_method ?? 'token',
        token: '',
        certificate: '',
        private_key: '',
        passphrase: '',
    });
    const saved = company?.has_secrets && company.auth_method === form.data.auth_method;
    const hint = saved ? 'Saved. Leave blank to keep it.' : undefined;
    const secrets = ['token', 'certificate', 'private_key', 'passphrase'] as const;

    return (
        <AppLayout>
            <Card title="Your company">
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        // Secrets are cleared after every save; the server never sends them back.
                        form.put('/company', { onFinish: () => form.reset(...secrets) });
                    }}
                >
                    <Field label="Company name" error={form.errors.name}>
                        <Input value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                    </Field>
                    <Field label="NIP" error={form.errors.nip}>
                        <Input value={form.data.nip} onChange={(e) => form.setData('nip', e.target.value)} />
                    </Field>
                    <Field label="KSeF environment" error={form.errors.environment}>
                        <Select value={form.data.environment} onChange={(e) => form.setData('environment', e.target.value)}>
                            {environments.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </Select>
                    </Field>
                    <Field label="How to log in" error={form.errors.auth_method}>
                        <Select value={form.data.auth_method} onChange={(e) => form.setData('auth_method', e.target.value)}>
                            {methods.map((o) => (
                                <option key={o.value} value={o.value}>
                                    {o.label}
                                </option>
                            ))}
                        </Select>
                    </Field>

                    {form.data.auth_method === 'token' && (
                        <Field label="KSeF token" error={form.errors.token} hint={hint}>
                            <Input type="password" autoComplete="off" value={form.data.token} onChange={(e) => form.setData('token', e.target.value)} />
                        </Field>
                    )}
                    {form.data.auth_method === 'certificate' && (
                        <>
                            <Field label="Certificate (PEM)" error={form.errors.certificate} hint={hint}>
                                <Textarea value={form.data.certificate} onChange={(e) => form.setData('certificate', e.target.value)} />
                            </Field>
                            <Field label="Private key (PEM)" error={form.errors.private_key} hint={hint}>
                                <Textarea value={form.data.private_key} onChange={(e) => form.setData('private_key', e.target.value)} />
                            </Field>
                            <Field label="Key passphrase (if any)" error={form.errors.passphrase}>
                                <Input type="password" autoComplete="off" value={form.data.passphrase} onChange={(e) => form.setData('passphrase', e.target.value)} />
                            </Field>
                        </>
                    )}
                    {form.data.auth_method === 'manual' && (
                        <p className="text-sm text-slate-600">Nothing is stored. You will sign each login request yourself.</p>
                    )}

                    <Button type="submit" disabled={form.processing}>
                        Save and check the connection
                    </Button>
                </form>
            </Card>
            {report && (
                <Card title="Connection check">
                    <ConnectionReport report={report} />
                </Card>
            )}
        </AppLayout>
    );
}
