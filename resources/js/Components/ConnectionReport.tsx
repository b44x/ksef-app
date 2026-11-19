import { useT } from '../i18n';
import type { Outcome, Report } from '../types';

const style: Record<Outcome, { label: string; classes: string }> = {
    ok: { label: 'OK', classes: 'bg-green-100 text-green-800' },
    warning: { label: 'Warning', classes: 'bg-amber-100 text-amber-800' },
    failed: { label: 'Failed', classes: 'bg-red-100 text-red-800' },
    skipped: { label: 'Skipped', classes: 'bg-slate-100 text-slate-600' },
};

export default function ConnectionReport({ report }: { report: Report }) {
    const t = useT();

    return (
        <div className="space-y-2" data-testid="report">
            <p className={`text-sm font-medium ${report.ok ? 'text-green-700' : 'text-red-700'}`}>
                {report.ok ? t('The connection works.') : t('The connection has problems.')}
            </p>
            <ul className="divide-y divide-slate-100 rounded-md border border-slate-200">
                {report.checks.map((check) => (
                    <li key={check.name} className="p-3 text-sm">
                        <div className="flex items-center gap-2">
                            <span className={`rounded px-2 py-0.5 text-xs font-medium ${style[check.outcome].classes}`}>
                                {t(style[check.outcome].label)}
                            </span>
                            <span className="font-medium">{t(check.name)}</span>
                            <span className="text-slate-600">{check.message}</span>
                        </div>
                        {check.details.length > 0 && (
                            <ul className="mt-1 list-disc pl-10 text-xs text-slate-500">
                                {check.details.map((d) => (
                                    <li key={d}>{d}</li>
                                ))}
                            </ul>
                        )}
                    </li>
                ))}
            </ul>
        </div>
    );
}
