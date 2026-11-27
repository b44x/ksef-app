import { FilePlus2, FileText } from 'lucide-react';
import { ButtonLink } from '../Components/Button';
import { Badge, Card, EmptyState, PageHeader, Table } from '../Components/Ui';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import { useMoney } from '../lib/format';

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
    const money = useMoney();

    return (
        <AppLayout>
            <PageHeader
                title={t('Invoices')}
                description={t('Everything you issue, from drafts to what KSeF accepted.')}
                actions={
                    <ButtonLink href="/invoices/create">
                        <FilePlus2 className="size-4" />
                        {t('New invoice')}
                    </ButtonLink>
                }
            />

            <Card className="[&>div]:p-0">
                {invoices.length === 0 ? (
                    <EmptyState
                        icon={<FileText className="size-6" />}
                        title={t('No invoices yet.')}
                        description={t('Create the first one; it stays a draft until you issue it.')}
                        action={<ButtonLink href="/invoices/create">{t('New invoice')}</ButtonLink>}
                    />
                ) : (
                    <Table>
                        <thead>
                            <tr>
                                <th>{t('Number')}</th>
                                <th>{t('Issue date')}</th>
                                <th>{t('Buyer')}</th>
                                <th className="text-right">{t('Gross')}</th>
                                <th>{t('Status')}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {invoices.map((i) => (
                                <tr key={i.id}>
                                    <td>
                                        <a
                                            href={`/invoices/${i.id}/edit`}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {i.number ?? t('Draft')}
                                        </a>
                                    </td>
                                    <td className="tabular-nums text-muted">{i.issue_date}</td>
                                    <td>{i.contractor}</td>
                                    <td className="text-right font-medium tabular-nums">
                                        {money(i.gross, i.currency)}
                                    </td>
                                    <td>
                                        <Badge>{i.status_label}</Badge>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </Table>
                )}
            </Card>
        </AppLayout>
    );
}
