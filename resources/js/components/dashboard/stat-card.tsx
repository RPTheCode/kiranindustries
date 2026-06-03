import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

export type StatTone = 'emerald' | 'rose' | 'blue' | 'purple' | 'orange' | 'slate';

const toneMap: Record<
    StatTone,
    { iconWrap: string; icon: string; value: string; border: string; borderHighlight: string }
> = {
    emerald: {
        iconWrap: 'bg-emerald-500/10 ring-emerald-500/20',
        icon: 'text-emerald-600',
        value: 'text-emerald-700',
        border: 'border-emerald-300 dark:border-emerald-600',
        borderHighlight: 'border-emerald-400 dark:border-emerald-500',
    },
    rose: {
        iconWrap: 'bg-rose-500/10 ring-rose-500/20',
        icon: 'text-rose-600',
        value: 'text-rose-700',
        border: 'border-rose-300 dark:border-rose-600',
        borderHighlight: 'border-rose-400 dark:border-rose-500',
    },
    blue: {
        iconWrap: 'bg-blue-500/10 ring-blue-500/20',
        icon: 'text-blue-600',
        value: 'text-blue-700',
        border: 'border-blue-300 dark:border-blue-600',
        borderHighlight: 'border-blue-400 dark:border-blue-500',
    },
    purple: {
        iconWrap: 'bg-purple-500/10 ring-purple-500/20',
        icon: 'text-purple-600',
        value: 'text-purple-700',
        border: 'border-purple-300 dark:border-purple-600',
        borderHighlight: 'border-purple-400 dark:border-purple-500',
    },
    orange: {
        iconWrap: 'bg-orange-500/10 ring-orange-500/20',
        icon: 'text-orange-600',
        value: 'text-orange-700',
        border: 'border-orange-300 dark:border-orange-600',
        borderHighlight: 'border-orange-400 dark:border-orange-500',
    },
    slate: {
        iconWrap: 'bg-slate-500/10 ring-slate-500/20',
        icon: 'text-slate-600',
        value: 'text-slate-900',
        border: 'border-slate-300 dark:border-slate-600',
        borderHighlight: 'border-slate-400 dark:border-slate-500',
    },
};

type StatCardProps = {
    label: string;
    value: ReactNode;
    hint?: string;
    icon: LucideIcon;
    tone?: StatTone;
    href?: string;
    highlight?: boolean;
};

export function StatCard({
    label,
    value,
    hint,
    icon: Icon,
    tone = 'slate',
    href,
    highlight = false,
}: StatCardProps) {
    const styles = toneMap[tone];

    const inner = (
        <div
            className={cn(
                'flex h-full items-center justify-between gap-2 rounded-xl border bg-white p-2 dark:bg-slate-950',
                highlight ? styles.borderHighlight : styles.border
            )}
        >
            <div className="min-w-0">
                <p className="text-xs font-medium text-slate-500 dark:text-slate-400">{label}</p>
                <p className={cn('mt-0.5 text-lg font-bold tabular-nums tracking-tight', styles.value)}>{value}</p>
                {hint ? <p className="mt-0.5 text-[11px] text-slate-500">{hint}</p> : null}
            </div>
            <div className={cn('flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1', styles.iconWrap)}>
                <Icon className={cn('h-4 w-4', styles.icon)} />
            </div>
        </div>
    );

    if (href) {
        return (
            <Link href={href} className="block h-full">
                {inner}
            </Link>
        );
    }

    return inner;
}

export function DashboardSection({
    title,
    children,
    action,
}: {
    title: string;
    children: React.ReactNode;
    action?: React.ReactNode;
}) {
    return (
        <section className="space-y-1.5">
            <div className="flex items-center justify-between gap-2 px-0.5">
                <h2 className="text-[11px] font-semibold tracking-wide text-slate-600 dark:text-slate-400">{title}</h2>
                {action}
            </div>
            {children}
        </section>
    );
}
