import { router, useForm } from '@inertiajs/react';
import { BadgeCheck, Trash2 } from 'lucide-react';
import { useT } from '../i18n';
import { useMoney } from '../lib/format';
import { Button } from './Button';
import { Field, Input } from './Form';
import { Badge, Card } from './Ui';

export interface PaymentInfo {
    status: string;
    label: string;
    tone: 'neutral' | 'warning' | 'success';
    overdue: boolean;
    gross: string | null;
    paid: string;
    outstanding: string;
    items: { id: number; amount: string; paid_on: string; note: string | null }[];
}

/** The payment register of an issued invoice: what came in and when, and how much is left. */
export function PaymentsCard({
    invoiceId,
    currency,
    payment,
    today,
}: {
    invoiceId: number;
    currency: string;
    payment: PaymentInfo;
    today: string;
}) {
    const t = useT();
    const money = useMoney();
    const form = useForm({ amount: '', paid_on: today, note: '' });
    const settled = payment.status === 'paid';

    return (
        <Card
            title={t('Payments')}
            className="mt-6"
            action={
                <Badge tone={payment.overdue ? 'danger' : payment.tone}>
                    {payment.overdue ? t('Overdue') : payment.label}
                </Badge>
            }
        >
            <div className="grid gap-6 lg:grid-cols-2">
                <div>
                    <dl className="mb-4 grid grid-cols-3 gap-3 text-sm">
                        <div>
                            <dt className="text-muted">{t('Total')}</dt>
                            <dd className="font-semibold tabular-nums">{money(payment.gross ?? '0', currency)}</dd>
                        </div>
                        <div>
                            <dt className="text-muted">{t('Paid')}</dt>
                            <dd className="font-semibold tabular-nums text-success">{money(payment.paid, currency)}</dd>
                        </div>
                        <div>
                            <dt className="text-muted">{t('Left to pay')}</dt>
                            <dd className="font-semibold tabular-nums">{money(payment.outstanding, currency)}</dd>
                        </div>
                    </dl>
                    {payment.items.length === 0 ? (
                        <p className="text-sm text-muted">{t('No payments recorded.')}</p>
                    ) : (
                        <ul className="divide-y divide-border text-sm">
                            {payment.items.map((p) => (
                                <li key={p.id} className="flex items-center justify-between gap-3 py-2">
                                    <div>
                                        <span className="tabular-nums">{p.paid_on}</span>
                                        {p.note && <span className="ml-2 text-muted">{p.note}</span>}
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <span className="font-medium tabular-nums">{money(p.amount, currency)}</span>
                                        <button
                                            type="button"
                                            className="rounded p-1 text-muted hover:bg-danger-soft hover:text-danger"
                                            aria-label={t('Delete')}
                                            onClick={() =>
                                                window.confirm(t('Delete this payment?')) &&
                                                router.delete(`/payments/${p.id}`, { preserveScroll: true })
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                        </button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>

                {!settled && (
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(`/invoices/${invoiceId}/payments`, {
                                preserveScroll: true,
                                onSuccess: () => form.reset('amount', 'note'),
                            });
                        }}
                    >
                        <div className="grid grid-cols-2 gap-3">
                            <Field label={t('Amount')} error={form.errors.amount}>
                                <Input
                                    inputMode="decimal"
                                    placeholder={payment.outstanding}
                                    value={form.data.amount}
                                    onChange={(e) => form.setData('amount', e.target.value.replace(',', '.'))}
                                />
                            </Field>
                            <Field label={t('Paid on')} error={form.errors.paid_on}>
                                <Input
                                    type="date"
                                    value={form.data.paid_on}
                                    onChange={(e) => form.setData('paid_on', e.target.value)}
                                />
                            </Field>
                        </div>
                        <Field label={t('Note')} error={form.errors.note}>
                            <Input value={form.data.note} onChange={(e) => form.setData('note', e.target.value)} />
                        </Field>
                        <div className="flex flex-wrap justify-end gap-2">
                            <Button
                                variant="secondary"
                                onClick={() =>
                                    router.post(`/invoices/${invoiceId}/payments/settle`, {}, { preserveScroll: true })
                                }
                            >
                                <BadgeCheck className="size-4" />
                                {t('Mark as paid in full')}
                            </Button>
                            <Button type="submit" disabled={form.processing || form.data.amount === ''}>
                                {t('Record the payment')}
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </Card>
    );
}
