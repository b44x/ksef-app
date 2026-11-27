import { useForm, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { Button } from '../Components/Button';
import ConnectionReport from '../Components/ConnectionReport';
import { Field, Input, Select, Textarea } from '../Components/Form';
import { Alert, Card, PageHeader } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import type { SharedProps } from '../types';

interface Option {
    value: string;
    label: string;
}

interface Props {
    company: {
        name: string;
        nip: string;
        address_line1: string;
        address_line2: string;
        email: string;
        environment: string;
        auth_method: string;
        has_secrets: boolean;
    } | null;
    environments: Option[];
    methods: Option[];
}

export default function Company({ company, environments, methods }: Props) {
    const t = useT();
    const { report } = usePage<SharedProps>().props;
    const form = useForm({
        name: company?.name ?? '',
        nip: company?.nip ?? '',
        address_line1: company?.address_line1 ?? '',
        address_line2: company?.address_line2 ?? '',
        email: company?.email ?? '',
        environment: company?.environment ?? 'test',
        auth_method: company?.auth_method ?? 'token',
        token: '',
        certificate: '',
        private_key: '',
        passphrase: '',
    });
    const saved = company?.has_secrets && company.auth_method === form.data.auth_method;
    const hint = saved ? t('Saved. Leave blank to keep it.') : undefined;
    const secrets = ['token', 'certificate', 'private_key', 'passphrase'] as const;
    const set =
        (name: keyof typeof form.data) =>
        (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
            form.setData(name, e.target.value);

    return (
        <AppLayout>
            <PageHeader
                title={t('Company')}
                description={t('Who you issue invoices as, and how this application signs in to KSeF.')}
                actions={
                    <Button type="submit" form="company-form" disabled={form.processing}>
                        <Check className="size-4" />
                        {t('Save and check the connection')}
                    </Button>
                }
            />

            <form
                id="company-form"
                className="space-y-6"
                onSubmit={(e) => {
                    e.preventDefault();
                    // Secrets are cleared after every save; the server never sends them back.
                    form.put('/company', { onFinish: () => form.reset(...secrets) });
                }}
            >
                <Card title={t('Seller')} description={t('Printed on every invoice.')}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('Company name')} error={form.errors.name} className="sm:col-span-2">
                            <Input value={form.data.name} onChange={set('name')} />
                        </Field>
                        <Field label={t('NIP')} error={form.errors.nip}>
                            <Input inputMode="numeric" value={form.data.nip} onChange={set('nip')} />
                        </Field>
                        <Field label={t('Email')} error={form.errors.email}>
                            <Input type="email" value={form.data.email} onChange={set('email')} />
                        </Field>
                        <Field label={t('Address')} error={form.errors.address_line1}>
                            <Input value={form.data.address_line1} onChange={set('address_line1')} />
                        </Field>
                        <Field
                            label={t('Address, second line')}
                            error={form.errors.address_line2}
                            hint={t('Postal code and city.')}
                        >
                            <Input value={form.data.address_line2} onChange={set('address_line2')} />
                        </Field>
                    </div>
                </Card>

                <Card title={t('KSeF connection')} description={t('Start on TEST: nothing you do there is real.')}>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label={t('KSeF environment')} error={form.errors.environment}>
                            <Select value={form.data.environment} onChange={set('environment')}>
                                {environments.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>
                        <Field label={t('How to log in')} error={form.errors.auth_method}>
                            <Select value={form.data.auth_method} onChange={set('auth_method')}>
                                {methods.map((o) => (
                                    <option key={o.value} value={o.value}>
                                        {o.label}
                                    </option>
                                ))}
                            </Select>
                        </Field>

                        {form.data.auth_method === 'token' && (
                            <Field
                                label={t('KSeF token')}
                                error={form.errors.token}
                                hint={hint}
                                className="sm:col-span-2"
                            >
                                <Input
                                    type="password"
                                    autoComplete="off"
                                    value={form.data.token}
                                    onChange={set('token')}
                                />
                            </Field>
                        )}
                        {form.data.auth_method === 'certificate' && (
                            <>
                                <Field
                                    label={t('Certificate (PEM)')}
                                    error={form.errors.certificate}
                                    hint={hint}
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        className="font-mono text-xs"
                                        value={form.data.certificate}
                                        onChange={set('certificate')}
                                    />
                                </Field>
                                <Field
                                    label={t('Private key (PEM)')}
                                    error={form.errors.private_key}
                                    hint={hint}
                                    className="sm:col-span-2"
                                >
                                    <Textarea
                                        className="font-mono text-xs"
                                        value={form.data.private_key}
                                        onChange={set('private_key')}
                                    />
                                </Field>
                                <Field label={t('Key passphrase (if any)')} error={form.errors.passphrase}>
                                    <Input
                                        type="password"
                                        autoComplete="off"
                                        value={form.data.passphrase}
                                        onChange={set('passphrase')}
                                    />
                                </Field>
                            </>
                        )}
                        {form.data.auth_method === 'manual' && (
                            <div className="sm:col-span-2">
                                <Alert tone="info">
                                    {t('Nothing is stored. You will sign each login request yourself.')}
                                </Alert>
                            </div>
                        )}
                    </div>
                </Card>
            </form>

            {report && (
                <Card title={t('Connection check')} className="mt-6">
                    <ConnectionReport report={report} />
                </Card>
            )}
        </AppLayout>
    );
}
