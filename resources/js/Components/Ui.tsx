import type { InputHTMLAttributes, ReactNode, SelectHTMLAttributes, TextareaHTMLAttributes } from 'react';

export function Card({ title, children }: { title?: string; children: ReactNode }) {
    return (
        <section className="rounded-lg border border-slate-200 bg-white p-6 shadow-sm">
            {title && <h2 className="mb-4 text-lg font-semibold text-slate-900">{title}</h2>}
            {children}
        </section>
    );
}

export function Field({ label, error, hint, children }: { label: string; error?: string; hint?: string; children: ReactNode }) {
    return (
        <label className="block">
            <span className="mb-1 block text-sm font-medium text-slate-700">{label}</span>
            {children}
            {hint && !error && <span className="mt-1 block text-xs text-slate-500">{hint}</span>}
            {error && <span className="mt-1 block text-xs text-red-600">{error}</span>}
        </label>
    );
}

const control = 'w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500';

export const Input = (props: InputHTMLAttributes<HTMLInputElement>) => <input {...props} className={control} />;
export const Select = (props: SelectHTMLAttributes<HTMLSelectElement>) => <select {...props} className={control} />;
export const Textarea = (props: TextareaHTMLAttributes<HTMLTextAreaElement>) => (
    <textarea {...props} className={`${control} font-mono`} rows={6} />
);

export function Button({ children, ...rest }: { children: ReactNode } & React.ButtonHTMLAttributes<HTMLButtonElement>) {
    return (
        <button
            {...rest}
            className="rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50"
        >
            {children}
        </button>
    );
}
