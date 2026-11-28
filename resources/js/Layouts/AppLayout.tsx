import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Building2,
    FilePlus2,
    FileText,
    Hash,
    Inbox,
    KeyRound,
    LayoutDashboard,
    LogOut,
    Menu,
    Package,
    Palette,
    Repeat,
    Users,
    X,
} from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { AvailabilityBanner } from '../Components/AvailabilityBanner';
import { Footer } from '../Components/Footer';
import { Toast } from '../Components/Toast';
import { useT } from '../i18n';
import type { SharedProps } from '../types';

const nav = [
    { href: '/invoices/create', label: 'New invoice', icon: FilePlus2, accent: true },
    { href: '/', label: 'Dashboard', icon: LayoutDashboard, exact: true },
    { href: '/invoices', label: 'Invoices', icon: FileText },
    { href: '/recurring', label: 'Recurring', icon: Repeat },
    { href: '/purchases', label: 'Purchases', icon: Inbox, badge: true },
    { href: '/reports', label: 'Reports', icon: BarChart3 },
    { href: '/contractors', label: 'Contractors', icon: Users },
    { href: '/products', label: 'Products', icon: Package },
    { href: '/series', label: 'Numbering', icon: Hash },
    { href: '/templates', label: 'Templates', icon: Palette },
    { href: '/company', label: 'Company', icon: Building2 },
];

function Brand() {
    const { app } = usePage<SharedProps>().props;

    return (
        <Link href="/" className="flex h-16 items-center gap-2.5 px-5">
            <span className="flex size-8 items-center justify-center rounded-lg bg-primary text-sm font-bold text-primary-text">
                K
            </span>
            <span className="text-base font-semibold tracking-tight">{app.name}</span>
        </Link>
    );
}

function Nav({ onNavigate }: { onNavigate?: () => void }) {
    const t = useT();
    const { unreadPurchases } = usePage<SharedProps>().props;
    const current = usePage().url;

    return (
        <nav className="flex-1 space-y-0.5 px-3 pt-2" aria-label="Main">
            {nav.map(({ href, label, icon: Icon, exact, accent, badge }) => {
                // "New invoice" lives under /invoices, so it must not light up the list entry and the other way round.
                const active =
                    exact || accent ? current === href : current.startsWith(href) && current !== '/invoices/create';
                return (
                    <Link
                        key={href}
                        href={href}
                        onClick={onNavigate}
                        aria-current={active ? 'page' : undefined}
                        className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-colors ${
                            active
                                ? 'bg-primary-soft text-primary'
                                : 'text-muted hover:bg-surface-muted hover:text-text'
                        }`}
                    >
                        <Icon className={`size-[18px] ${accent && !active ? 'text-primary' : ''}`} aria-hidden />
                        {t(label)}
                        {badge && unreadPurchases > 0 && (
                            <span className="ml-auto rounded-full bg-primary px-2 py-0.5 text-xs font-semibold text-primary-text">
                                {unreadPurchases}
                            </span>
                        )}
                    </Link>
                );
            })}
        </nav>
    );
}

/** The teal bar across the top of every page: where you are working (company) and who you are. */
function TopBar({ onMenu }: { onMenu: () => void }) {
    const t = useT();
    const { auth, company, locale, locales, ksefSession } = usePage<SharedProps>().props;

    return (
        <header className="sticky top-0 z-20 flex h-16 items-center gap-3 bg-gradient-to-r from-hero-from to-hero-to px-4 text-white shadow-sm sm:px-8">
            <button onClick={onMenu} aria-label={t('Menu')} className="rounded-lg p-2 hover:bg-white/15 lg:hidden">
                <Menu className="size-5" />
            </button>
            <div className="min-w-0 flex-1">
                {company && (
                    <div className="truncate text-sm font-medium">
                        {company.name} <span className="ml-1 hidden text-white/70 sm:inline">NIP {company.nip}</span>
                    </div>
                )}
            </div>
            {ksefSession && (
                <Link
                    href="/company/signin"
                    className={`inline-flex h-9 items-center gap-2 rounded-lg px-3 text-sm font-medium ${ksefSession.active_until ? 'bg-white/15 hover:bg-white/25' : 'bg-amber-300 text-amber-950 hover:bg-amber-200'}`}
                >
                    <KeyRound className="size-4" aria-hidden />
                    {ksefSession.active_until
                        ? t('Signed in to KSeF until :time', {
                              time: new Date(ksefSession.active_until).toLocaleTimeString(
                                  locale === 'pl' ? 'pl-PL' : 'en-GB',
                                  { hour: '2-digit', minute: '2-digit' },
                              ),
                          })
                        : t('Sign in to KSeF')}
                </Link>
            )}
            <select
                aria-label={t('Language')}
                value={locale}
                onChange={(e) => router.post('/locale', { locale: e.target.value })}
                className="h-9 rounded-lg border border-white/30 bg-white/10 px-2 text-sm text-white [&>option]:text-black"
            >
                {Object.entries(locales).map(([code, name]) => (
                    <option key={code} value={code}>
                        {name}
                    </option>
                ))}
            </select>
            <div className="hidden text-right text-sm leading-tight sm:block">
                <div className="font-medium">{auth.user?.name}</div>
                <div className="text-xs text-white/70">{auth.user?.email}</div>
            </div>
            <button
                onClick={() => router.post('/logout')}
                title={t('Log out')}
                aria-label={t('Log out')}
                className="rounded-lg p-2 hover:bg-white/15"
            >
                <LogOut className="size-[18px]" />
            </button>
        </header>
    );
}

export default function AppLayout({ children }: { children: ReactNode }) {
    const [open, setOpen] = useState(false);
    const t = useT();

    return (
        <div className="min-h-screen">
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-60 flex-col border-r border-border bg-surface lg:flex">
                <Brand />
                <Nav />
            </aside>

            {open && (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <div className="absolute inset-0 bg-black/40" onClick={() => setOpen(false)} />
                    <aside className="absolute inset-y-0 left-0 flex w-72 flex-col bg-surface shadow-xl">
                        <button
                            onClick={() => setOpen(false)}
                            aria-label={t('Close')}
                            className="absolute right-3 top-4 rounded-lg p-1.5 text-muted"
                        >
                            <X className="size-5" />
                        </button>
                        <Brand />
                        <Nav onNavigate={() => setOpen(false)} />
                    </aside>
                </div>
            )}

            <div className="lg:pl-60">
                <TopBar onMenu={() => setOpen(true)} />
                <AvailabilityBanner />
                <main className="mx-auto max-w-6xl px-4 py-8 sm:px-8">{children}</main>
                <Footer className="border-t border-border px-4 py-6 sm:px-8" />
            </div>
            <Toast />
        </div>
    );
}
