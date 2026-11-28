import { router, usePage } from '@inertiajs/react';
import { Upload } from 'lucide-react';
import { useState } from 'react';
import { useT } from '../i18n';
import type { SharedProps } from '../types';
import { Button } from './Button';
import { Dialog } from './Dialog';
import { Alert } from './Ui';

/**
 * A CSV file in, a report out: how many records were made and changed, and which lines were left out and why. The
 * rows are held to the same rules as the form.
 */
export function ImportDialog({
    open,
    onClose,
    title,
    url,
    columns,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    url: string;
    columns: string;
}) {
    const t = useT();
    const { import: result, errors } = usePage<SharedProps>().props as SharedProps & { errors: Record<string, string> };
    const [file, setFile] = useState<File | null>(null);
    const [sent, setSent] = useState(false);
    const [busy, setBusy] = useState(false);

    const submit = () => {
        if (!file) return;
        setBusy(true);
        router.post(
            url,
            { file },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => setSent(true),
                onFinish: () => setBusy(false),
            },
        );
    };

    const close = () => {
        setFile(null);
        setSent(false);
        onClose();
    };

    return (
        <Dialog open={open} onClose={close} title={title}>
            <div className="space-y-4">
                <Alert tone="info">
                    {t('A CSV file with a header line. Columns: :columns. The delimiter can be ; , or a tab.', {
                        columns,
                    })}
                </Alert>
                <input
                    type="file"
                    accept=".csv,.txt,text/csv"
                    onChange={(e) => {
                        setFile(e.target.files?.[0] ?? null);
                        setSent(false);
                    }}
                    className="block w-full text-sm file:mr-3 file:rounded-lg file:border file:border-border-strong file:bg-surface file:px-3 file:py-2 file:text-sm"
                />
                {errors.file && <Alert tone="danger">{errors.file}</Alert>}
                {sent && result && (
                    <div className="space-y-2">
                        <Alert tone={result.errors.length > 0 ? 'warning' : 'success'}>
                            {t('Added: :created. Updated: :updated. Left out: :left.', {
                                created: result.created,
                                updated: result.updated,
                                left: result.errors.length,
                            })}
                        </Alert>
                        {result.errors.length > 0 && (
                            <ul className="max-h-48 list-disc space-y-1 overflow-auto pl-5 text-sm">
                                {result.errors.map((e) => (
                                    <li key={e.line}>
                                        {t('Line :line', { line: e.line })}: {e.message}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={close}>
                        {sent ? t('Close') : t('Cancel')}
                    </Button>
                    <Button onClick={submit} disabled={!file || busy}>
                        <Upload className="size-4" />
                        {t('Import')}
                    </Button>
                </div>
            </div>
        </Dialog>
    );
}
