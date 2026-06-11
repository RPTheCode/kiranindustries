import { cn } from '@/lib/utils';

const statusStyles: Record<string, string> = {
    New: 'bg-slate-100 text-slate-700 ring-slate-200',
    Screening: 'bg-blue-50 text-blue-700 ring-blue-200',
    Interview: 'bg-violet-50 text-violet-700 ring-violet-200',
    Offer: 'bg-amber-50 text-amber-800 ring-amber-200',
    Hired: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    Rejected: 'bg-rose-50 text-rose-700 ring-rose-200',
    Draft: 'bg-slate-100 text-slate-600 ring-slate-200',
    Published: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    Closed: 'bg-rose-50 text-rose-700 ring-rose-200',
    Open: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'Pending Approval': 'bg-amber-50 text-amber-800 ring-amber-200',
    Approved: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    'On Hold': 'bg-orange-50 text-orange-700 ring-orange-200',
    Scheduled: 'bg-blue-50 text-blue-700 ring-blue-200',
    Completed: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    Cancelled: 'bg-rose-50 text-rose-700 ring-rose-200',
    Sent: 'bg-blue-50 text-blue-700 ring-blue-200',
    Accepted: 'bg-emerald-50 text-emerald-700 ring-emerald-200',
    Declined: 'bg-rose-50 text-rose-700 ring-rose-200',
    Negotiating: 'bg-violet-50 text-violet-700 ring-violet-200',
    Expired: 'bg-slate-100 text-slate-600 ring-slate-200',
};

export function StatusBadge({ status, className }: { status: string; className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md px-2 py-0.5 text-[11px] font-semibold ring-1 ring-inset',
                statusStyles[status] ?? 'bg-slate-100 text-slate-600 ring-slate-200',
                className
            )}
        >
            {status}
        </span>
    );
}
