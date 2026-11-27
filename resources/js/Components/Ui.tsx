import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react';
import type { ReactNode } from 'react';

export function Card({
    title,
    description,
    action,
    children,
    className = '',
}: {
    title?: string;
    description?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={`rounded-xl border border-border bg-surface shadow-card ${className}`}>
            {(title || action) && (
                <header className="flex items-start justify-between gap-4 border-b border-border px-5 py-4">
                    <div>
                        {title && <h2 className="text-base font-semibold text-text">{title}</h2>}
                        {description && <p className="mt-0.5 text-sm text-muted">{description}</p>}
                    </div>
                    {action}
                </header>
            )}
            <div className="p-5">{children}</div>
        </section>
    );
}

export function PageHeader({
    title,
    description,
    actions,
    badge,
}: {
    title: string;
    description?: string;
    actions?: ReactNode;
    badge?: ReactNode;
}) {
    return (
        <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
                <div className="flex items-center gap-3">
                    <h1 className="text-2xl font-semibold tracking-tight text-text">{title}</h1>
                    {badge}
                </div>
                {description && <p className="mt-1 text-sm text-muted">{description}</p>}
            </div>
            {actions && <div className="flex items-center gap-2">{actions}</div>}
        </div>
    );
}

type Tone = 'neutral' | 'primary' | 'success' | 'warning' | 'danger';

const tones: Record<Tone, string> = {
    neutral: 'bg-surface-muted text-muted',
    primary: 'bg-primary-soft text-primary',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
};

export const Badge = ({ tone = 'neutral', children }: { tone?: Tone; children: ReactNode }) => (
    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${tones[tone]}`}>
        {children}
    </span>
);

const alertStyle = {
    success: { classes: 'bg-success-soft text-success', Icon: CheckCircle2 },
    warning: { classes: 'bg-warning-soft text-warning', Icon: AlertTriangle },
    danger: { classes: 'bg-danger-soft text-danger', Icon: XCircle },
    info: { classes: 'bg-primary-soft text-primary', Icon: Info },
};

export function Alert({ tone = 'info', children }: { tone?: keyof typeof alertStyle; children: ReactNode }) {
    const { classes, Icon } = alertStyle[tone];

    return (
        <div
            role={tone === 'danger' || tone === 'warning' ? 'alert' : 'status'}
            className={`flex gap-3 rounded-lg px-4 py-3 text-sm ${classes}`}
        >
            <Icon className="mt-0.5 size-4 shrink-0" aria-hidden />
            <div className="min-w-0">{children}</div>
        </div>
    );
}

export function EmptyState({
    icon,
    title,
    description,
    action,
}: {
    icon: ReactNode;
    title: string;
    description?: string;
    action?: ReactNode;
}) {
    return (
        <div className="flex flex-col items-center px-6 py-12 text-center">
            <div className="mb-4 flex size-12 items-center justify-center rounded-full bg-primary-soft text-primary">
                {icon}
            </div>
            <h3 className="text-base font-semibold text-text">{title}</h3>
            {description && <p className="mt-1 max-w-sm text-sm text-muted">{description}</p>}
            {action && <div className="mt-5">{action}</div>}
        </div>
    );
}

/** A data table: quiet header, hairline rows, numbers aligned right by the caller with `text-right tabular-nums`. */
export function Table({ children }: { children: ReactNode }) {
    return (
        <div className="overflow-x-auto">
            <table className="w-full text-left text-sm [&_td]:px-4 [&_td]:py-3 [&_th]:px-4 [&_th]:py-2.5 [&_th]:text-xs [&_th]:font-medium [&_th]:uppercase [&_th]:tracking-wide [&_th]:text-muted [&_tbody_tr]:border-t [&_tbody_tr]:border-border [&_tbody_tr:hover]:bg-surface-muted/60">
                {children}
            </table>
        </div>
    );
}
