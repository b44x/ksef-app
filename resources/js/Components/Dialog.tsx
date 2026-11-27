import { X } from 'lucide-react';
import { type ReactNode, useEffect, useRef } from 'react';
import { useT } from '../i18n';

/** A modal dialog on the native <dialog> element: focus trap, Escape and the backdrop come from the browser. */
export function Dialog({
    open,
    onClose,
    title,
    children,
}: {
    open: boolean;
    onClose: () => void;
    title: string;
    children: ReactNode;
}) {
    const t = useT();
    const ref = useRef<HTMLDialogElement>(null);

    useEffect(() => {
        const dialog = ref.current;
        if (!dialog) return;
        if (open && !dialog.open) dialog.showModal();
        if (!open && dialog.open) dialog.close();
    }, [open]);

    return (
        <dialog
            ref={ref}
            onClose={onClose}
            onClick={(e) => e.target === ref.current && onClose()}
            className="m-auto w-full max-w-xl rounded-2xl border border-border bg-surface p-0 text-text shadow-2xl backdrop:bg-black/40"
        >
            <div className="flex items-center justify-between border-b border-border px-6 py-4">
                <h2 className="text-lg font-semibold">{title}</h2>
                <button
                    onClick={onClose}
                    aria-label={t('Close')}
                    className="rounded-lg p-1.5 text-muted hover:bg-surface-muted"
                >
                    <X className="size-5" />
                </button>
            </div>
            <div className="px-6 py-5">{open && children}</div>
        </dialog>
    );
}
