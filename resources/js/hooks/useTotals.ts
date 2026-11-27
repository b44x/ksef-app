import { useEffect, useState } from 'react';
import type { InvoiceLineForm, TotalsPreview } from '../types';

const NUMBER = /^\d+([.,]\d+)?$/;

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/** The lines that are complete enough to be priced; a half-typed line is left out until it is ready. */
function completeLines(lines: InvoiceLineForm[]) {
    return lines
        .filter((l) => NUMBER.test(l.quantity.trim()) && NUMBER.test(l.unit_net_price.trim()))
        .map((l) => ({
            name: l.name.trim() || '-',
            quantity: l.quantity.trim().replace(',', '.'),
            unit_net_price: l.unit_net_price.trim().replace(',', '.'),
            vat_rate: l.vat_rate,
        }));
}

/**
 * The totals of the lines as the server (the SDK) computes them. Money is never added up in the browser: the form asks
 * after a short pause in typing, and keeps showing the last answer while a line is being edited.
 */
export function useTotals(lines: InvoiceLineForm[]): TotalsPreview | null {
    const [totals, setTotals] = useState<TotalsPreview | null>(null);
    const body = JSON.stringify({ lines: completeLines(lines) });
    const hasLines = body !== '{"lines":[]}';

    useEffect(() => {
        if (!hasLines) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(() => {
            fetch('/invoices/preview', {
                method: 'POST',
                signal: controller.signal,
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body,
            })
                .then((response) => (response.ok ? response.json() : null))
                .then((data: TotalsPreview | null) => data && setTotals(data))
                .catch(() => undefined);
        }, 250);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [body, hasLines]);

    return hasLines ? totals : null;
}
