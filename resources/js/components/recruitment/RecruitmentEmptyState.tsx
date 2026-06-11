import { Button } from '@/components/ui/button';
import type { LucideIcon } from 'lucide-react';

export function RecruitmentEmptyState({
    icon: Icon,
    title,
    description,
    actionLabel,
    onAction,
}: {
    icon: LucideIcon;
    title: string;
    description: string;
    actionLabel?: string;
    onAction?: () => void;
}) {
    return (
        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/50 px-6 py-12 text-center dark:border-slate-800 dark:bg-slate-900/30">
            <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                <Icon className="h-6 w-6 text-primary" />
            </div>
            <h3 className="text-sm font-semibold text-slate-800 dark:text-slate-100">{title}</h3>
            <p className="mt-1 max-w-sm text-xs text-slate-500">{description}</p>
            {actionLabel && onAction ? (
                <Button size="sm" className="mt-4" onClick={onAction}>
                    {actionLabel}
                </Button>
            ) : null}
        </div>
    );
}
