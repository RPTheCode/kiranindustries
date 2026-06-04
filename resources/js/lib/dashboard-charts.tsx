import { useEffect, useRef, useState } from 'react';
import type { TooltipProps } from 'recharts';
import { Cell, Pie, PieChart, ResponsiveContainer, Tooltip } from 'recharts';

export type ChartSlice = { name: string; value: number; color: string };

export function ChartTooltip({ active, payload, label }: TooltipProps<number, string>) {
    if (!active || !payload?.length) {
        return null;
    }
    const row = payload[0]?.payload as ChartSlice | undefined;
    const title = row?.name ?? (typeof label === 'string' ? label : '');
    const value = row?.value ?? payload[0]?.value;
    if (title === '' && value == null) {
        return null;
    }
    return (
        <div className="rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs shadow-md dark:border-slate-700 dark:bg-slate-900">
            {title ? (
                <p className="max-w-[200px] truncate font-medium text-slate-800 dark:text-slate-100">{title}</p>
            ) : null}
            <p className="mt-0.5 tabular-nums text-slate-500">{value}</p>
        </div>
    );
}

export function ChartLegend({ items, max = 6 }: { items: ChartSlice[]; max?: number }) {
    if (!items.length) {
        return null;
    }
    const visible = items.slice(0, max);
    const hidden = items.length - visible.length;
    return (
        <ul className="mt-2 space-y-1 border-t border-slate-100 pt-2 dark:border-slate-800">
            {visible.map((item) => (
                <li key={item.name} className="flex items-center justify-between gap-2 text-[11px]">
                    <span className="flex min-w-0 items-center gap-1.5">
                        <span
                            className="h-2.5 w-2.5 shrink-0 rounded-sm ring-1 ring-slate-200/80"
                            style={{ backgroundColor: item.color }}
                        />
                        <span className="truncate text-slate-600 dark:text-slate-400">{item.name}</span>
                    </span>
                    <span className="shrink-0 tabular-nums font-medium text-slate-700 dark:text-slate-300">{item.value}</span>
                </li>
            ))}
            {hidden > 0 ? (
                <li className="text-[10px] text-slate-400">+{hidden} more in chart</li>
            ) : null}
        </ul>
    );
}

export const HIRING_BAR_COLOR = '#6366f1';

/** Recharts needs a non-zero container width; defer mount until layout is ready. */
export function DashboardPieChart({ data, height = 140 }: { data: ChartSlice[]; height?: number }) {
    const containerRef = useRef<HTMLDivElement>(null);
    const [ready, setReady] = useState(false);

    useEffect(() => {
        const el = containerRef.current;
        if (!el) {
            return;
        }

        const markReady = () => {
            if (el.offsetWidth > 0) {
                setReady(true);
            }
        };

        markReady();
        const observer = new ResizeObserver(markReady);
        observer.observe(el);

        return () => observer.disconnect();
    }, []);

    return (
        <div ref={containerRef} className="w-full min-w-0" style={{ height }}>
            {ready ? (
                <ResponsiveContainer width="100%" height={height}>
                    <PieChart>
                        <Pie
                            data={data}
                            cx="50%"
                            cy="50%"
                            innerRadius={42}
                            outerRadius={68}
                            dataKey="value"
                            stroke="#f8fafc"
                            strokeWidth={1}
                        >
                            {data.map((entry, index) => (
                                <Cell key={`slice-${index}`} fill={entry.color} />
                            ))}
                        </Pie>
                        <Tooltip content={<ChartTooltip />} />
                    </PieChart>
                </ResponsiveContainer>
            ) : (
                <div className="h-full w-full animate-pulse rounded-lg bg-slate-100 dark:bg-slate-800" />
            )}
        </div>
    );
}
