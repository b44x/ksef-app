import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';

const control =
    'block w-full rounded-lg border border-border-strong bg-surface px-3 text-sm text-text shadow-card placeholder:text-muted/70 focus:border-primary focus:outline-none focus:ring-4 focus:ring-[var(--ring)] disabled:bg-surface-muted';

export const Input = ({ className = '', ...props }: InputHTMLAttributes<HTMLInputElement>) => (
    <input {...props} className={`${control} h-10 ${className}`} />
);

export const Select = ({ className = '', ...props }: SelectHTMLAttributes<HTMLSelectElement>) => (
    <select {...props} className={`${control} h-10 ${className}`} />
);

export const Textarea = ({ className = '', ...props }: TextareaHTMLAttributes<HTMLTextAreaElement>) => (
    <textarea rows={5} {...props} className={`${control} py-2 ${className}`} />
);

interface FieldProps {
    label: string;
    error?: string;
    hint?: string;
    children: ReactNode;
    className?: string;
}

/** A labelled control with its hint and error. The label wraps the control, so clicking it focuses the control. */
export function Field({ label, error, hint, children, className = '' }: FieldProps) {
    return (
        <label className={`block ${className}`}>
            <span className="mb-1.5 block text-sm font-medium text-text">{label}</span>
            {children}
            {error ? (
                <span className="mt-1.5 block text-xs text-danger">{error}</span>
            ) : (
                hint && <span className="mt-1.5 block text-xs text-muted">{hint}</span>
            )}
        </label>
    );
}
