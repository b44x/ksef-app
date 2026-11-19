import { Link, router, usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { SharedProps } from '../types';

export default function AppLayout({ children }: { children: ReactNode }) {
    const { app, auth, company } = usePage<SharedProps>().props;

    return (
        <div className="min-h-screen bg-slate-50 font-sans text-slate-900">
            <header className="border-b border-slate-200 bg-white">
                <div className="mx-auto flex max-w-4xl items-center justify-between px-4 py-3">
                    <Link href="/" className="font-semibold">
                        {app.name}
                    </Link>
                    {auth.user && (
                        <nav className="flex items-center gap-4 text-sm">
                            {company && <span className="text-slate-500">{company.name}</span>}
                            <Link href="/company" className="text-indigo-700 hover:underline">
                                Company
                            </Link>
                            <button onClick={() => router.post('/logout')} className="text-slate-600 hover:underline">
                                Log out
                            </button>
                        </nav>
                    )}
                </div>
            </header>
            <main className="mx-auto max-w-4xl space-y-6 px-4 py-8">{children}</main>
        </div>
    );
}
