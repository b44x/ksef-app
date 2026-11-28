import { FileText } from 'lucide-react';
import { usePage } from '@inertiajs/react';
import { useT } from '../i18n';
import type { SharedProps } from '../types';
import { Button } from './Button';

/** The printed invoice: in the language of the app, and in the other one for a buyer abroad (or at home). */
export function PdfLinks({ href }: { href: string }) {
    const t = useT();
    const { locale } = usePage<SharedProps>().props;
    const other = locale === 'pl' ? 'en' : 'pl';

    return (
        <>
            <a href={`${href}?lang=${locale}`} target="_blank" rel="noreferrer">
                <Button variant="secondary">
                    <FileText className="size-4" />
                    {t('Download PDF')}
                </Button>
            </a>
            <a href={`${href}?lang=${other}`} target="_blank" rel="noreferrer">
                <Button variant="ghost">
                    {other === 'en' ? t('Download PDF in English') : t('Download PDF in Polish')}
                </Button>
            </a>
        </>
    );
}
