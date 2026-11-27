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
    saved: boolean;
    locale: string;
    locales: Record<string, string>;
    translations: Record<string, string>;
    [key: string]: unknown;
}

export interface TotalsPreview {
    buckets: { rate: string; net: string; vat: string | null }[];
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
