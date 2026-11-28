import { usePage } from '@inertiajs/react';
import { AlertTriangle, OctagonAlert } from 'lucide-react';
import { useT } from '../i18n';
import type { SharedProps } from '../types';

/** Shown on every page while the Ministry says KSeF is down: what is happening, and what it means for sending. */
export function AvailabilityBanner() {
    const t = useT();
    const { ksefAvailability, locale } = usePage<SharedProps>().props;

    if (!ksefAvailability) {
        return null;
    }

    const failure = ksefAvailability.state !== 'maintenance';
    const when = (iso: string) =>
        new Date(iso).toLocaleString(locale === 'pl' ? 'pl-PL' : 'en-GB', { dateStyle: 'short', timeStyle: 'short' });
    const advice =
        ksefAvailability.state === 'total_failure'
            ? t('Delivery to KSeF and the QR codes are suspended. Follow the Ministry of Finance announcement.')
            : failure
              ? t(
                    'Invoices cannot be delivered to KSeF now. Sending is paused; invoices issued meanwhile can be delivered later.',
                )
              : t('KSeF is down for planned maintenance. Sending is paused until it ends.');
    const Icon = failure ? OctagonAlert : AlertTriangle;

    return (
        <div
            role="alert"
            className={`border-b px-4 py-3 text-sm sm:px-8 ${failure ? 'border-danger/30 bg-danger-soft text-danger' : 'border-warning/30 bg-warning-soft text-warning'}`}
        >
            <div className="mx-auto flex max-w-6xl gap-3">
                <Icon className="mt-0.5 size-5 shrink-0" aria-hidden />
                <div className="min-w-0">
                    <div className="font-semibold">{ksefAvailability.label}</div>
                    <div>{advice}</div>
                    {ksefAvailability.notices.slice(0, 2).map((notice, i) => (
                        <div key={i} className="mt-1 text-xs opacity-90">
                            {notice.title} · {when(notice.start)}
                            {notice.end ? ` – ${when(notice.end)}` : ` – ${t('end not known yet')}`}
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
