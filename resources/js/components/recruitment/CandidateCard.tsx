import { StatusBadge } from '@/components/recruitment/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import type { DraggableProvidedDragHandleProps } from '@hello-pangea/dnd';
import { FileText, GripVertical, Mail, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export type PipelineCandidate = {
    id: number;
    full_name: string;
    email?: string;
    phone?: string;
    job_title?: string;
    source_name?: string;
    experience_years?: number;
    has_resume?: boolean;
    interviews_count?: number;
    status: string;
};

const NEXT_STATUS: Record<string, string> = {
    New: 'Screening',
    Screening: 'Interview',
    Interview: 'Offer',
    Offer: 'Hired',
};

export function CandidateCard({
    candidate,
    onMoveStatus,
    canEdit,
    dragHandleProps,
    isDragging,
}: {
    candidate: PipelineCandidate;
    onMoveStatus?: (id: number, status: string) => void;
    canEdit?: boolean;
    dragHandleProps?: DraggableProvidedDragHandleProps | null;
    isDragging?: boolean;
}) {
    const { t } = useTranslation();
    const isTerminal = candidate.status === 'Hired' || candidate.status === 'Rejected';
    const nextStatus = NEXT_STATUS[candidate.status];

    const handleReject = () => {
        if (!onMoveStatus) return;
        if (!confirm(t('Reject this candidate? They will move to the Rejected column.'))) return;
        onMoveStatus(candidate.id, 'Rejected');
    };

    return (
        <Card
            className={cn(
                'border-slate-200/80 shadow-sm transition-shadow hover:shadow-md dark:border-slate-800',
                isDragging && 'ring-2 ring-primary/30'
            )}
        >
            <CardContent className="p-3">
                <div className="flex items-start gap-1.5">
                    {canEdit && dragHandleProps ? (
                        <button
                            type="button"
                            className="mt-0.5 shrink-0 cursor-grab touch-none text-slate-300 hover:text-slate-500 active:cursor-grabbing"
                            aria-label={t('Drag to move')}
                            {...dragHandleProps}
                        >
                            <GripVertical className="h-4 w-4" />
                        </button>
                    ) : null}
                    <div className="min-w-0 flex-1">
                        <div className="flex items-start justify-between gap-2">
                            <Link
                                href={route('hr.recruitment.candidates.show', candidate.id)}
                                className="min-w-0 flex-1 font-medium text-sm text-slate-900 hover:text-primary dark:text-slate-100"
                                onClick={(e) => isDragging && e.preventDefault()}
                            >
                                {candidate.full_name}
                            </Link>
                            {candidate.has_resume ? (
                                <FileText className="h-3.5 w-3.5 shrink-0 text-emerald-600" aria-label="Resume uploaded" />
                            ) : null}
                        </div>
                        {candidate.job_title ? (
                            <p className="mt-0.5 truncate text-[11px] text-slate-500">{candidate.job_title}</p>
                        ) : null}
                        <div className="mt-2 flex flex-wrap gap-1.5 text-[10px] text-slate-400">
                            {candidate.source_name ? <span>{candidate.source_name}</span> : null}
                            {candidate.experience_years != null ? <span>· {candidate.experience_years}y exp</span> : null}
                            {candidate.interviews_count ? <span>· {candidate.interviews_count} interviews</span> : null}
                        </div>
                        {candidate.email ? (
                            <p className="mt-1.5 flex items-center gap-1 truncate text-[10px] text-slate-500">
                                <Mail className="h-3 w-3" /> {candidate.email}
                            </p>
                        ) : null}
                    </div>
                </div>

                {canEdit && onMoveStatus && !isTerminal ? (
                    <div className="mt-2 flex flex-wrap gap-1 pl-5">
                        {nextStatus ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-6 px-2 text-[10px]"
                                onClick={() => onMoveStatus(candidate.id, nextStatus)}
                            >
                                {t('Advance')}
                            </Button>
                        ) : null}
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-6 px-2 text-[10px] text-rose-600 hover:bg-rose-50 hover:text-rose-700"
                            onClick={handleReject}
                        >
                            <X className="mr-0.5 h-3 w-3" />
                            {t('Reject')}
                        </Button>
                    </div>
                ) : null}

                {isTerminal ? (
                    <div className="mt-2 pl-5">
                        <StatusBadge status={candidate.status} />
                    </div>
                ) : null}
            </CardContent>
        </Card>
    );
}
