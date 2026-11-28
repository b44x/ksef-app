import { router, useForm, usePage } from '@inertiajs/react';
import { CheckCircle2, Download, FileSignature, KeyRound, LogOut } from 'lucide-react';
import { useState } from 'react';
import { Button, ButtonLink } from '../Components/Button';
import { Field, Textarea } from '../Components/Form';
import { Alert, Badge, Card, PageHeader } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import type { SharedProps } from '../types';

interface Props {
    manual: boolean;
    environment: string;
    signedInUntil: string | null;
    pendingUntil: string | null;
}

function Step({ n, title, children }: { n: number; title: string; children: React.ReactNode }) {
    return (
        <li className="flex gap-4">
            <span className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full bg-primary-soft text-sm font-semibold text-primary">
                {n}
            </span>
            <div className="min-w-0 flex-1">
                <h3 className="font-medium">{title}</h3>
                <div className="mt-2 space-y-3 text-sm text-muted">{children}</div>
            </div>
        </li>
    );
}

export default function KsefSignIn({ manual, environment, signedInUntil, pendingUntil }: Props) {
    const t = useT();
    const { locale, errors } = usePage<SharedProps>().props as SharedProps & { errors: Record<string, string> };
    const [pasting, setPasting] = useState(false);
    const form = useForm<{ signed: File | null; signed_text: string }>({ signed: null, signed_text: '' });
    const when = (iso: string) => new Date(iso).toLocaleString(locale === 'pl' ? 'pl-PL' : 'en-GB');

    if (!manual) {
        return (
            <AppLayout>
                <PageHeader title={t('Sign in to KSeF')} />
                <Alert tone="info">
                    {t('This company signs in with a token or a certificate, so there is nothing to sign by hand.')}{' '}
                    <a href="/company" className="font-medium underline">
                        {t('Company settings')}
                    </a>
                </Alert>
            </AppLayout>
        );
    }

    return (
        <AppLayout>
            <PageHeader
                title={t('Sign in to KSeF')}
                description={t(
                    'For companies without a token or a certificate: you sign the login yourself, and the application keeps it for as long as KSeF allows.',
                )}
                badge={<Badge tone="warning">{environment}</Badge>}
            />

            <Card className="mb-6">
                {signedInUntil ? (
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <div className="flex items-center gap-3">
                            <CheckCircle2 className="size-6 text-success" aria-hidden />
                            <div>
                                <div className="font-medium">{t('You are signed in to KSeF.')}</div>
                                <div className="text-sm text-muted">
                                    {t('The login works until :time. After that you sign again.', {
                                        time: when(signedInUntil),
                                    })}
                                </div>
                            </div>
                        </div>
                        <Button
                            variant="secondary"
                            onClick={() => window.confirm(t('Sign out of KSeF?')) && router.delete('/company/signin')}
                        >
                            <LogOut className="size-4" />
                            {t('Sign out')}
                        </Button>
                    </div>
                ) : (
                    <div className="flex items-center gap-3">
                        <KeyRound className="size-6 text-warning" aria-hidden />
                        <div>
                            <div className="font-medium">{t('You are not signed in to KSeF.')}</div>
                            <div className="text-sm text-muted">
                                {t('Until you sign in, invoices cannot be sent or fetched.')}
                            </div>
                        </div>
                    </div>
                )}
            </Card>

            {errors.signin && (
                <div className="mb-6">
                    <Alert tone="danger">{errors.signin}</Alert>
                </div>
            )}

            <Card title={signedInUntil ? t('Sign in again') : t('Sign in')}>
                <ol className="space-y-8">
                    <Step n={1} title={t('Prepare the login document')}>
                        <p>
                            {t(
                                'KSeF gives the application a one-time challenge. It is valid for ten minutes, so do the next steps right away.',
                            )}
                        </p>
                        <div className="flex flex-wrap items-center gap-3">
                            <Button
                                variant={pendingUntil ? 'secondary' : 'primary'}
                                onClick={() => router.post('/company/signin/request')}
                            >
                                <FileSignature className="size-4" />
                                {pendingUntil ? t('Prepare a new one') : t('Prepare the document')}
                            </Button>
                            {pendingUntil && (
                                <>
                                    <a href="/company/signin/request.xml" download>
                                        <Button>
                                            <Download className="size-4" />
                                            {t('Download the document')}
                                        </Button>
                                    </a>
                                    <span className="text-xs">
                                        {t('Ready until :time', {
                                            time: new Date(pendingUntil).toLocaleTimeString(
                                                locale === 'pl' ? 'pl-PL' : 'en-GB',
                                            ),
                                        })}
                                    </span>
                                </>
                            )}
                        </div>
                    </Step>

                    <Step n={2} title={t('Sign it outside the application')}>
                        <p>
                            {t(
                                'Sign the downloaded file with a qualified electronic signature or seal, or with a trusted profile (Profil Zaufany) in its "sign a document" service. Choose the XAdES format with the signature inside the file ("enveloped"), and save the signed file.',
                            )}
                        </p>
                        <p>
                            {t(
                                'The application never sees your signing key or your trusted-profile password: only the signed file comes back.',
                            )}
                        </p>
                    </Step>

                    <Step n={3} title={t('Hand the signed file back')}>
                        <form
                            className="space-y-3"
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.post('/company/signin/complete', {
                                    forceFormData: true,
                                    onFinish: () => form.reset(),
                                });
                            }}
                        >
                            {pasting ? (
                                <Field label={t('Signed document (XML)')} error={errors.signed}>
                                    <Textarea
                                        className="font-mono text-xs"
                                        rows={8}
                                        value={form.data.signed_text}
                                        onChange={(e) => form.setData('signed_text', e.target.value)}
                                    />
                                </Field>
                            ) : (
                                <Field label={t('Signed file')} error={errors.signed}>
                                    <input
                                        type="file"
                                        accept=".xml,.xades,text/xml,application/xml"
                                        onChange={(e) => form.setData('signed', e.target.files?.[0] ?? null)}
                                        className="block w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-primary-soft file:px-4 file:py-2 file:text-sm file:font-medium file:text-primary hover:file:opacity-90"
                                    />
                                </Field>
                            )}
                            <div className="flex flex-wrap items-center gap-4">
                                <Button
                                    type="submit"
                                    disabled={
                                        form.processing ||
                                        !pendingUntil ||
                                        (pasting ? form.data.signed_text.trim() === '' : form.data.signed === null)
                                    }
                                >
                                    {t('Sign in to KSeF')}
                                </Button>
                                <button
                                    type="button"
                                    onClick={() => setPasting(!pasting)}
                                    className="text-sm text-primary hover:underline"
                                >
                                    {pasting ? t('Upload a file instead') : t('Paste the contents instead')}
                                </button>
                            </div>
                            {!pendingUntil && <p className="text-xs">{t('Prepare the document first (step 1).')}</p>}
                        </form>
                    </Step>
                </ol>
            </Card>

            <p className="mt-6 text-sm">
                <ButtonLink href="/company" variant="ghost" size="sm">
                    {t('Company settings')}
                </ButtonLink>
            </p>
        </AppLayout>
    );
}
