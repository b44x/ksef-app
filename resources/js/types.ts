export type Outcome = 'ok' | 'warning' | 'failed' | 'skipped';

export interface Check {
    name: string;
    outcome: Outcome;
    message: string;
    details: string[];
}

export interface Report {
    ok: boolean;
    checks: Check[];
}

export interface SharedProps {
    app: { name: string };
    auth: { user: { name: string; email: string } | null };
    company: { name: string; nip: string } | null;
    report: Report | null;
    status: string | null;
    import: { created: number; updated: number; errors: { line: number; message: string }[] } | null;
    unreadPurchases: number;
    ksefAvailability: {
        state: string;
        label: string;
        notices: { title: string; text: string; start: string; end: string | null }[];
    } | null;
    ksefSession: { manual: boolean; active_until: string | null } | null;
    credits: { author: string; years: string; url: string; license: string };
    locale: string;
    locales: Record<string, string>;
    translations: Record<string, string>;
    [key: string]: unknown;
}

export interface TotalsPreview {
    vat_pln?: string | null;
    buckets: { rate: string; net: string; vat: string | null; gross: string }[];
    net: string;
    vat: string;
    gross: string;
    lines: string[];
}

export interface InvoiceLineForm {
    name: string;
    unit: string;
    quantity: string;
    unit_net_price: string;
    vat_rate: string;
}
