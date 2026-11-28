import { router } from '@inertiajs/react';
import { WifiOff } from 'lucide-react';
import { useState } from 'react';
import { useT } from '../i18n';
import { Button } from './Button';
import { Dialog } from './Dialog';
import { Field, Input } from './Form';
import { Alert } from './Ui';

export interface OfflineOptions {
    available: boolean;
    modes: { value: string; label: string; hint: string; needs_anchor: boolean }[];
    suggested: string;
}

/**
 * Issuing without KSeF: the reason decides the delivery deadline, so the reason is asked for. The one that fits what KSeF
 * is doing right now is preselected.
 */
export function OfflineIssueDialog({
    invoiceId,
    offline,
    open,
    onClose,
}: {
    invoiceId: number;
    offline: OfflineOptions;
    open: boolean;
    onClose: () => void;
}) {
    const t = useT();
    const [mode, setMode] = useState(offline.suggested);
    const [anchor, setAnchor] = useState('');
    const chosen = offline.modes.find((m) => m.value === mode);

    const submit = () => {
        router.post(
            `/invoices/${invoiceId}/issue`,
            { mode, anchor_date: chosen?.needs_anchor ? anchor : '' },
            { onFinish: onClose },
        );
    };

    return (
        <Dialog open={open} onClose={onClose} title={t('Issue offline')}>
            <div className="space-y-5">
                <Alert tone="info">
                    {t(
                        'An offline invoice gets its number now and two QR codes to print. You deliver it to KSeF later, by a deadline that depends on the reason.',
                    )}
                </Alert>
                <fieldset className="space-y-2">
                    <legend className="mb-1 text-sm font-medium">{t('Why is it issued offline?')}</legend>
                    {offline.modes.map((m) => (
                        <label
                            key={m.value}
                            className={`flex cursor-pointer gap-3 rounded-lg border p-3 ${mode === m.value ? 'border-primary bg-primary-soft' : 'border-border'}`}
                        >
                            <input
                                type="radio"
                                name="offline-mode"
                                className="mt-1 accent-[var(--primary)]"
                                checked={mode === m.value}
                                onChange={() => setMode(m.value)}
                            />
                            <span>
                                <span className="block text-sm font-medium">
                                    {m.label}
                                    {m.value === offline.suggested && (
                                        <span className="ml-2 text-xs text-primary">{t('suggested now')}</span>
                                    )}
                                </span>
                                <span className="block text-xs text-muted">{m.hint}</span>
                            </span>
                        </label>
                    ))}
                </fieldset>
                {chosen?.needs_anchor && (
                    <Field
                        label={t('When did it end?')}
                        hint={t(
                            'Leave empty if it has not ended yet: the deadline is counted when you enter the date.',
                        )}
                    >
                        <Input type="date" value={anchor} onChange={(e) => setAnchor(e.target.value)} />
                    </Field>
                )}
                <div className="flex justify-end gap-2">
                    <Button variant="secondary" onClick={onClose}>
                        {t('Cancel')}
                    </Button>
                    <Button onClick={submit}>
                        <WifiOff className="size-4" />
                        {t('Issue offline')}
                    </Button>
                </div>
            </div>
        </Dialog>
    );
}
