import { useEffect, useState } from 'react';

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * The PDF of a sample invoice drawn with the settings as they are right now, as an object URL for an iframe. It asks the
 * server (the same code that prints the real invoices, so what you see is what you get) after a short pause in editing,
 * and keeps showing the last good preview while the next one is being drawn.
 */
export function usePdfPreview(
    settings: unknown,
    lang: string,
): { url: string | null; loading: boolean; failed: boolean } {
    const [state, setState] = useState<{ url: string | null; loading: boolean; failed: boolean }>({
        url: null,
        loading: true,
        failed: false,
    });
    const body = JSON.stringify({ settings, lang });

    useEffect(() => {
        const controller = new AbortController();
        const timer = setTimeout(() => {
            setState((s) => ({ ...s, loading: true }));
            fetch('/templates/preview', {
                method: 'POST',
                signal: controller.signal,
                headers: { 'Content-Type': 'application/json', Accept: 'application/pdf', 'X-XSRF-TOKEN': xsrfToken() },
                body,
            })
                .then((response) => (response.ok ? response.blob() : Promise.reject(new Error('preview'))))
                .then((blob) => {
                    const url = URL.createObjectURL(blob);
                    setState((previous) => {
                        if (previous.url) URL.revokeObjectURL(previous.url);
                        return { url, loading: false, failed: false };
                    });
                })
                .catch((error: unknown) => {
                    if (!(error instanceof DOMException && error.name === 'AbortError')) {
                        setState((s) => ({ ...s, loading: false, failed: true }));
                    }
                });
        }, 450);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [body]);

    return state;
}
