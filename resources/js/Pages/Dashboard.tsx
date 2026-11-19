import { router, usePage } from '@inertiajs/react';
import { useT } from '../i18n';
import AppLayout from '../Layouts/AppLayout';
import { Button, Card } from '../Components/Ui';
import ConnectionReport from '../Components/ConnectionReport';
import type { SharedProps } from '../types';

interface Props {
    details: { name: string; nip: string; environment: string; auth_method: string };
}

export default function Dashboard({ details }: Props) {
    const t = useT();
    const { report } = usePage<SharedProps>().props;

    return (
        <AppLayout>
            <Card title={details.name}>
                <dl className="grid grid-cols-2 gap-2 text-sm">
                    <dt className="text-slate-500">{t('NIP')}</dt>
                    <dd>{details.nip}</dd>
                    <dt className="text-slate-500">{t('Environment')}</dt>
                    <dd>{details.environment}</dd>
                    <dt className="text-slate-500">{t('Login method')}</dt>
                    <dd>{details.auth_method}</dd>
                </dl>
                <div className="mt-4">
                    <Button onClick={() => router.post('/company/check')}>{t('Check the connection')}</Button>
                </div>
            </Card>
            {report && (
                <Card title={t('Connection check')}>
                    <ConnectionReport report={report} />
                </Card>
            )}
        </AppLayout>
    );
}
