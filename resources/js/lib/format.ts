import { usePage } from '@inertiajs/react';
import type { SharedProps } from '../types';

const tags: Record<string, string> = { pl: 'pl-PL', en: 'en-GB' };

/**
 * Formats an amount the server computed ("4381.65") for display in the user's language ("4 381,65"). Display only:
 * nothing is calculated here, so the digits are exactly the server's.
 */
export function useMoney(): (amount: string, currency?: string) => string {
    const { locale } = usePage<SharedProps>().props;
    const tag = tags[locale] ?? locale;

    return (amount, currency) => {
        const formatted = new Intl.NumberFormat(tag, { minimumFractionDigits: 2, maximumFractionDigits: 8 }).format(
            Number(amount),
        );
        return currency ? `${formatted} ${currency}` : formatted;
    };
}
