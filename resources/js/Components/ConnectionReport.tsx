import { AlertTriangle, CheckCircle2, MinusCircle, XCircle } from 'lucide-react';
import { useT } from '../i18n';
import type { Outcome, Report } from '../types';

const style: Record<Outcome, { Icon: typeof CheckCircle2; classes: string }> = {
    ok: { Icon: CheckCircle2, classes: 'text-success' },
    warning: { Icon: AlertTriangle, classes: 'text-warning' },
    failed: { Icon: XCircle, classes: 'text-danger' },
    skipped: { Icon: MinusCircle, classes: 'text-muted' },
};

export default function ConnectionReport({ report }: { report: Report }) {
    const t = useT();

    return (
        <div data-testid="report">
            <p className={`mb-3 text-sm font-medium ${report.ok ? 'text-success' : 'text-danger'}`}>
                {report.ok ? t('The connection works.') : t('The connection has problems.')}
            </p>
            <ul className="divide-y divide-border overflow-hidden rounded-lg border border-border">
                {report.checks.map((check) => {
                    const { Icon, classes } = style[check.outcome];
                    return (
                        <li key={check.name} className="flex gap-3 px-4 py-3 text-sm">
                            <Icon
                                className={`mt-0.5 size-4 shrink-0 ${classes}`}
                                aria-label={t(
                                    check.outcome === 'ok'
                                        ? 'OK'
                                        : check.outcome === 'warning'
                                          ? 'Warning'
                                          : check.outcome === 'failed'
                                            ? 'Failed'
                                            : 'Skipped',
                                )}
                            />
                            <div className="min-w-0">
                                <div>
                                    <span className="font-medium">{t(check.name)}</span>
                                    <span className="text-muted"> · {check.message}</span>
                                </div>
                                {check.details.length > 0 && (
                                    <ul className="mt-1 list-disc space-y-0.5 pl-4 text-xs text-muted">
                                        {check.details.map((d) => (
                                            <li key={d}>{d}</li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
