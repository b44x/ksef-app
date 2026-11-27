import { Link } from '@inertiajs/react';
import { Card } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';

interface Row {
    id: number;
    number: string | null;
    status: string;
    status_label: string;
    issue_date: string;
    contractor: string;
    currency: string;
    gross: string;
}

export default function Invoices({ invoices }: { invoices: Row[] }) {
    const t = useT();

    return (
        <AppLayout>
            <Card title={t('Invoices')}>
                <div className="mb-4">
                    <Link
                        href="/invoices/create"
                        className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                    >
                        {t('New invoice')}
                    </Link>
                </div>
                {invoices.length === 0 ? (
                    <p className="text-sm text-slate-600">{t('No invoices yet.')}</p>
                ) : (
                    <table className="w-full text-left text-sm [&_td]:px-3 [&_th]:px-3 [&_td:first-child]:pl-0 [&_th:first-child]:pl-0 [&_td:last-child]:pr-0">
                        <thead className="text-slate-500">
                            <tr>
                                <th className="py-1">{t('Number')}</th>
                                <th>{t('Issue date')}</th>
                                <th>{t('Buyer')}</th>
                                <th className="text-right">{t('Gross')}</th>
                                <th>{t('Status')}</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-100">
                            {invoices.map((i) => (
                                <tr key={i.id}>
                                    <td className="py-2 font-medium">
                                        <Link
                                            href={`/invoices/${i.id}/edit`}
                                            className="text-indigo-700 hover:underline"
                                        >
                                            {i.number ?? t('Draft')}
                                        </Link>
                                    </td>
                                    <td>{i.issue_date}</td>
                                    <td>{i.contractor}</td>
                                    <td className="text-right tabular-nums">
                                        {i.gross} {i.currency}
                                    </td>
                                    <td>
                                        <span className="rounded bg-slate-100 px-2 py-0.5 text-xs text-slate-700">
                                            {i.status_label}
                                        </span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Card>
        </AppLayout>
    );
}
