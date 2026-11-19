import { usePage } from '@inertiajs/react';
import type { SharedProps } from './types';

/**
 * Returns the translate function for the current language. Keys are the English texts; a text with no translation is
 * shown as it is. Placeholders look like `:name`.
 */
export function useT(): (text: string, replace?: Record<string, string | number>) => string {
    const { translations } = usePage<SharedProps>().props;

    return (text, replace = {}) =>
        Object.entries(replace).reduce(
            (result, [name, value]) => result.replaceAll(`:${name}`, String(value)),
            translations[text] ?? text,
        );
}
