import { usePage } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { SharedProps } from '../types';

/** Shows the server's one-time status message ("Saved.") for a few seconds. */
export function Toast() {
    const page = usePage<SharedProps>();
    const message = page.props.status;
    const [visible, setVisible] = useState(false);

    useEffect(() => {
        if (!message) {
            return;
        }
        const show = setTimeout(() => setVisible(true), 0);
        const hide = setTimeout(() => setVisible(false), 3500);

        return () => {
            clearTimeout(show);
            clearTimeout(hide);
        };
        // The page object changes on every visit, so the same message twice in a row shows twice.
    }, [page, message]);

    if (!message || !visible) {
        return null;
    }

    return (
        <div
            role="status"
            className="fixed bottom-6 right-6 z-50 flex items-center gap-2.5 rounded-lg bg-text px-4 py-3 text-sm font-medium text-bg shadow-lg"
        >
            <CheckCircle2 className="size-4 text-success" aria-hidden />
            {message}
        </div>
    );
}
