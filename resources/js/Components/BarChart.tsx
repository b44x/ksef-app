import { usePage } from '@inertiajs/react';
import { useMoney } from '../lib/format';
import type { SharedProps } from '../types';

export interface MonthPoint {
    month: string;
    net: string;
}

/**
 * Monthly net sales as bars. Plain SVG that scales with its box: no chart library for twelve rectangles. The heights
 * are drawn from the numbers the server computed; nothing is summed here.
 */
export function BarChart({ data, currency, label }: { data: MonthPoint[]; currency: string; label: string }) {
    const { locale } = usePage<SharedProps>().props;
    const money = useMoney();
    const values = data.map((d) => Number(d.net));
    const max = Math.max(...values, 1);
    const width = 720;
    const height = 220;
    const pad = { top: 16, bottom: 28, left: 8, right: 8 };
    const slot = (width - pad.left - pad.right) / data.length;
    const bar = Math.min(40, slot * 0.62);
    const monthName = (m: string) =>
        new Date(`${m}-01T00:00:00`).toLocaleDateString(locale === 'pl' ? 'pl-PL' : 'en-GB', { month: 'short' });

    return (
        <svg viewBox={`0 0 ${width} ${height}`} role="img" aria-label={label} className="h-auto w-full">
            <line
                x1={pad.left}
                x2={width - pad.right}
                y1={height - pad.bottom}
                y2={height - pad.bottom}
                stroke="var(--border)"
            />
            {data.map((d, i) => {
                const h = (values[i] / max) * (height - pad.top - pad.bottom);
                const x = pad.left + i * slot + (slot - bar) / 2;
                return (
                    <g key={d.month}>
                        <rect
                            x={x}
                            y={height - pad.bottom - h}
                            width={bar}
                            height={Math.max(h, values[i] > 0 ? 2 : 0)}
                            rx={4}
                            fill="var(--primary)"
                            opacity={i === data.length - 1 ? 1 : 0.55}
                        >
                            <title>{`${d.month}: ${money(d.net, currency)}`}</title>
                        </rect>
                        <text x={x + bar / 2} y={height - 9} textAnchor="middle" fontSize="11" fill="var(--muted)">
                            {monthName(d.month)}
                        </text>
                    </g>
                );
            })}
        </svg>
    );
}
