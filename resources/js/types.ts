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
    [key: string]: unknown;
}
