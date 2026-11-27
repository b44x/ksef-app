import { Link, router, usePage } from '@inertiajs/react';
import { Building2, FileText, Hash, LayoutDashboard, LogOut, Menu, Package, Users, X } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { Toast } from '../Components/Toast';
import { useT } from '../i18n';
import type { SharedProps } from '../types';

const nav = [
    { href: '/', label: 'Dashboard', icon: LayoutDashboard, exact: true },
    { href: '/invoices', label: 'Invoices', icon: FileText },
    { href: '/contractors', label: 'Contractors', icon: Users },
    { href: '/products', label: 'Products', icon: Package },
    { href: '/series', label: 'Numbering', icon: Hash },
    { href: '/company', label: 'Company', icon: Building2 },
];

function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
    const t = useT();
    const { app, auth, company, locale, locales } = usePage<SharedProps>().props;
    const current = usePage().url;

    return (
        <div className="flex h-full flex-col">
            <div className="flex h-16 items-center gap-2.5 px-5">
                <div className="flex size-8 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-text">
                    K
                </div>
                <span className="text-base font-semibold tracking-tight">{app.name}</span>
            </div>

            {company && (
                <div className="mx-3 mb-3 rounded-lg bg-surface-muted px-3 py-2">
                    <div className="truncate text-sm font-medium">{company.name}</div>
                    <div className="text-xs text-muted">NIP {company.nip}</div>
                </div>
            )}

            <nav className="flex-1 space-y-0.5 px-3" aria-label="Main">
                {nav.map(({ href, label, icon: Icon, exact }) => {
                    const active = exact ? current === href : current.startsWith(href);
                    return (
                        <Link
                            key={href}
                            href={href}
                            onClick={onNavigate}
                            aria-current={active ? 'page' : undefined}
                            className={`flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
                                active
                                    ? 'bg-primary-soft text-primary'
                                    : 'text-muted hover:bg-surface-muted hover:text-text'
                            }`}
                        >
                            <Icon className="size-[18px]" aria-hidden />
                            {t(label)}
                        </Link>
                    );
                })}
            </nav>

            <div className="space-y-3 border-t border-border p-4">
                <select
                    aria-label={t('Language')}
                    value={locale}
                    onChange={(e) => router.post('/locale', { locale: e.target.value })}
                    className="h-9 w-full rounded-lg border border-border-strong bg-surface px-2 text-sm"
                >
                    {Object.entries(locales).map(([code, name]) => (
                        <option key={code} value={code}>
                            {name}
                        </option>
                    ))}
                </select>
                <div className="flex items-center justify-between gap-2">
                    <div className="min-w-0">
                        <div className="truncate text-sm font-medium">{auth.user?.name}</div>
                        <div className="truncate text-xs text-muted">{auth.user?.email}</div>
                    </div>
                    <button
                        onClick={() => router.post('/logout')}
                        title={t('Log out')}
                        aria-label={t('Log out')}
                        className="rounded-lg p-2 text-muted hover:bg-surface-muted hover:text-text"
                    >
                        <LogOut className="size-4" />
                    </button>
                </div>
            </div>
        </div>
    );
}

export default function AppLayout({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const t = useT();
    const { app } = usePage<SharedProps>().props;

    return (
        <div className="min-h-screen">
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 border-r border-border bg-surface lg:block">
                <Sidebar />
            </aside>

            <div className="sticky top-0 z-20 flex h-14 items-center justify-between border-b border-border bg-surface px-4 lg:hidden">
                <span className="font-semibold">{app.name}</span>
                <button
                    onClick={() => setOpen(true)}
                    aria-label={t('Menu')}
                    className="rounded-lg p-2 text-muted hover:bg-surface-muted"
                >
                    <Menu className="size-5" />
                </button>
            </div>

            {open && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div className="absolute inset-0 bg-black/40" onClick={() => setOpen(false)} />
                    <aside className="absolute inset-y-0 left-0 w-72 bg-surface shadow-xl">
                        <button
                            onClick={() => setOpen(false)}
                            aria-label={t('Close')}
                            className="absolute right-3 top-4 rounded-lg p-1.5 text-muted"
                        >
                            <X className="size-5" />
                        </button>
                        <Sidebar onNavigate={() => setOpen(false)} />
                    </aside>
                </div>
            )}

            <main className="lg:pl-64">
                <div className="mx-auto max-w-6xl px-4 py-8 sm:px-8">{children}</div>
            </main>
            <Toast />
        </div>
    );
}
