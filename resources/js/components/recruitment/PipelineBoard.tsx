import { CandidateCard, type PipelineCandidate } from '@/components/recruitment/CandidateCard';
import { StatusBadge } from '@/components/recruitment/StatusBadge';
import { DragDropContext, Draggable, Droppable, type DropResult } from '@hello-pangea/dnd';
import { cn } from '@/lib/utils';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';

const COLUMNS = ['New', 'Screening', 'Interview', 'Offer', 'Hired', 'Rejected'] as const;

type PipelineData = Record<string, PipelineCandidate[]>;

export function PipelineBoard({
    pipeline,
    onMoveStatus,
    canEdit,
}: {
    pipeline: PipelineData;
    onMoveStatus?: (id: number, status: string) => void;
    canEdit?: boolean;
}) {
    const { t } = useTranslation();
    const [board, setBoard] = useState<PipelineData>(pipeline);

    useEffect(() => {
        setBoard(pipeline);
    }, [pipeline]);

    const totalCount = useMemo(
        () => Object.values(board).reduce((sum, col) => sum + col.length, 0),
        [board]
    );

    const onDragEnd = (result: DropResult) => {
        if (!canEdit || !onMoveStatus || !result.destination) return;

        const sourceStatus = result.source.droppableId;
        const destStatus = result.destination.droppableId;
        const candidateId = Number(result.draggableId);

        if (sourceStatus === destStatus) return;

        setBoard((prev) => {
            const sourceItems = [...(prev[sourceStatus] ?? [])];
            const destItems = [...(prev[destStatus] ?? [])];
            const movedIndex = sourceItems.findIndex((c) => c.id === candidateId);
            if (movedIndex === -1) return prev;

            const [moved] = sourceItems.splice(movedIndex, 1);
            destItems.splice(result.destination!.index, 0, { ...moved, status: destStatus });

            return {
                ...prev,
                [sourceStatus]: sourceItems,
                [destStatus]: destItems,
            };
        });

        onMoveStatus(candidateId, destStatus);
    };

    return (
        <div className="space-y-2">
            {totalCount > 30 ? (
                <p className="rounded-lg border border-amber-200/80 bg-amber-50/80 px-3 py-2 text-xs text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-200">
                    {t('{{count}} candidates on the board — each column scrolls independently. Use List view for search and filters.', {
                        count: totalCount,
                    })}
                </p>
            ) : canEdit ? (
                <p className="text-[11px] text-slate-400">
                    {t('Drag cards between columns or use Advance / Reject on each card.')}
                </p>
            ) : null}

            <DragDropContext onDragEnd={onDragEnd}>
                <div className="flex gap-3 overflow-x-auto pb-2 custom-scrollbar">
                    {COLUMNS.map((status) => {
                        const items = board[status] ?? [];
                        const isTall = items.length > 8;

                        return (
                            <div
                                key={status}
                                className="flex w-[260px] shrink-0 flex-col rounded-xl border border-slate-200/80 bg-slate-50/50 dark:border-slate-800 dark:bg-slate-900/20"
                            >
                                <div className="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200/60 bg-slate-50/95 px-3 py-2 backdrop-blur-sm dark:border-slate-800 dark:bg-slate-900/90">
                                    <StatusBadge status={status} />
                                    <span className="text-xs font-bold tabular-nums text-slate-500">{items.length}</span>
                                </div>

                                <Droppable droppableId={status} isDropDisabled={!canEdit}>
                                    {(provided, snapshot) => (
                                        <div
                                            ref={provided.innerRef}
                                            {...provided.droppableProps}
                                            className={cn(
                                                'flex min-h-[120px] max-h-[min(520px,calc(100vh-300px))] flex-col gap-2 overflow-y-auto p-2 transition-colors custom-scrollbar',
                                                snapshot.isDraggingOver && 'bg-primary/5 ring-1 ring-inset ring-primary/20',
                                                isTall && 'shadow-inner'
                                            )}
                                        >
                                            {items.length === 0 ? (
                                                <p className="py-6 text-center text-[11px] text-slate-400">
                                                    {canEdit ? t('Drop here') : t('No candidates')}
                                                </p>
                                            ) : (
                                                items.map((c, index) => (
                                                    <Draggable
                                                        key={c.id}
                                                        draggableId={String(c.id)}
                                                        index={index}
                                                        isDragDisabled={!canEdit}
                                                    >
                                                        {(dragProvided, dragSnapshot) => (
                                                            <div
                                                                ref={dragProvided.innerRef}
                                                                {...dragProvided.draggableProps}
                                                                className={cn(
                                                                    dragSnapshot.isDragging && 'rotate-1 opacity-90 shadow-lg'
                                                                )}
                                                            >
                                                                <CandidateCard
                                                                    candidate={c}
                                                                    onMoveStatus={onMoveStatus}
                                                                    canEdit={canEdit}
                                                                    dragHandleProps={dragProvided.dragHandleProps}
                                                                    isDragging={dragSnapshot.isDragging}
                                                                />
                                                            </div>
                                                        )}
                                                    </Draggable>
                                                ))
                                            )}
                                            {provided.placeholder}
                                        </div>
                                    )}
                                </Droppable>
                            </div>
                        );
                    })}
                </div>
            </DragDropContext>
        </div>
    );
}
