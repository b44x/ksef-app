import { router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Footer } from '../Components/Footer';
import { Toast } from '../Components/Toast';
import { useT } from '../i18n';
import type { SharedProps } from '../types';

/** The frame of the pages shown before signing in: centred, with the brand and the language switch. */
export default function GuestLayout({
    title,
    description,
    children,
}: {
    title: string;
    description?: string;
    children: ReactNode;
}) {
    const t = useT();
    const { app, locale, locales } = usePage<SharedProps>().props;

    return (
        <div className="flex min-h-screen flex-col items-center justify-center px-4 py-10">
            <div className="mb-8 flex items-center gap-2.5">
                <div className="flex size-10 items-center justify-center rounded-xl bg-primary text-lg font-bold text-primary-text">
                    K
                </div>
                <span className="text-xl font-semibold tracking-tight">{app.name}</span>
            </div>
            <div className="w-full max-w-md rounded-2xl border border-border bg-surface p-8 shadow-card">
                <h1 className="text-xl font-semibold tracking-tight">{title}</h1>
                {description && <p className="mt-1.5 text-sm text-muted">{description}</p>}
                <div className="mt-6">{children}</div>
            </div>
            <form className="mt-6">
                <select
                    aria-label={t('Language')}
                    value={locale}
                    onChange={(e) => router.post('/locale', { locale: e.target.value })}
                    className="h-9 rounded-lg border border-border-strong bg-surface px-2 text-sm text-muted"
                >
                    {Object.entries(locales).map(([code, name]) => (
                        <option key={code} value={code}>
                            {name}
                        </option>
                    ))}
                </select>
            </form>
            <Footer className="mt-8" />
            <Toast />
        </div>
    );
}
