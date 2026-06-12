import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { DragDropContext, Draggable, Droppable, type DropResult } from '@hello-pangea/dnd';
import { ChevronRight, GripVertical, Search, Sparkles, X } from 'lucide-react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { toast } from '@/components/custom-toast';
import {
    filterShortcutLeafGroups,
    filterShortcutLeaves,
    getShortcutShortLabel,
    groupShortcutLeavesByNavSection,
    MAX_DASHBOARD_SHORTCUTS,
    reorderShortcutIds,
    resolveShortcutLeaves,
    shortcutDragId,
    type ShortcutLeaf,
    type ShortcutLeafGroup,
} from '@/lib/nav-shortcuts';
import { saveDashboardShortcuts } from '@/lib/save-dashboard-shortcuts';
import { resolveNavIcon } from '@/lib/nav-menu-icons';
import type { NavItem } from '@/types';
import { cn } from '@/lib/utils';

interface ShortcutCustomizeDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    navItems: NavItem[];
    availableLeaves: ShortcutLeaf[];
    savedIds: string[];
    suggestionIds?: string[];
    onSaved: (ids: string[]) => void;
}

export function ShortcutCustomizeDialog({
    open,
    onOpenChange,
    navItems,
    availableLeaves,
    savedIds,
    suggestionIds = [],
    onSaved,
}: ShortcutCustomizeDialogProps) {
    const { t } = useTranslation();
    const [search, setSearch] = useState('');
    const [selectedIds, setSelectedIds] = useState<string[]>(savedIds);
    const [saving, setSaving] = useState(false);
    const [expandedGroups, setExpandedGroups] = useState<Set<string>>(new Set());

    const leafGroups = useMemo(() => groupShortcutLeavesByNavSection(navItems), [navItems]);

    useEffect(() => {
        if (open) {
            setSelectedIds(savedIds);
            setSearch('');

            const next = new Set<string>();
            leafGroups.forEach((group) => {
                if (group.leaves.some((leaf) => savedIds.includes(leaf.id))) {
                    next.add(group.id);
                }
            });

            if (next.size === 0 && leafGroups.length > 0) {
                next.add(leafGroups[0].id);
            }

            setExpandedGroups(next);
        }
    }, [open, savedIds, leafGroups]);

    const selectedLeaves = useMemo(
        () => resolveShortcutLeaves(selectedIds, availableLeaves),
        [selectedIds, availableLeaves],
    );

    const suggestionLeaves = useMemo(() => {
        const resolved = resolveShortcutLeaves(suggestionIds, availableLeaves);
        return resolved.filter((leaf) => !selectedIds.includes(leaf.id));
    }, [suggestionIds, availableLeaves, selectedIds]);

    const visibleGroups = useMemo(
        () => filterShortcutLeafGroups(leafGroups, search),
        [leafGroups, search],
    );

    const flatSearchResults = useMemo(
        () => filterShortcutLeaves(availableLeaves, search),
        [availableLeaves, search],
    );

    const isSearching = search.trim().length > 0;

    const toggleId = (id: string) => {
        setSelectedIds((prev) => {
            if (prev.includes(id)) {
                return prev.filter((item) => item !== id);
            }

            if (prev.length >= MAX_DASHBOARD_SHORTCUTS) {
                toast.error(t('You can pin up to {{count}} pages.', { count: MAX_DASHBOARD_SHORTCUTS }));
                return prev;
            }

            return [...prev, id];
        });
    };

    const addSuggestion = (id: string) => {
        setSelectedIds((prev) => {
            if (prev.includes(id) || prev.length >= MAX_DASHBOARD_SHORTCUTS) {
                return prev;
            }

            return [...prev, id];
        });
    };

    const removeSelected = (id: string) => {
        setSelectedIds((prev) => prev.filter((item) => item !== id));
    };

    const clearAll = () => {
        setSelectedIds([]);
    };

    const handleSelectedDragEnd = (result: DropResult) => {
        if (!result.destination || result.source.index === result.destination.index) {
            return;
        }

        setSelectedIds((prev) =>
            reorderShortcutIds(prev, result.source.index, result.destination.index),
        );
    };

    const toggleGroup = (groupId: string, expanded: boolean) => {
        setExpandedGroups((prev) => {
            const next = new Set(prev);

            if (expanded) {
                next.add(groupId);
            } else {
                next.delete(groupId);
            }

            return next;
        });
    };

    const handleSave = () => {
        setSaving(true);

        saveDashboardShortcuts(selectedIds, {
            onSuccess: () => {
                onSaved(selectedIds);
                onOpenChange(false);
                toast.success(
                    selectedIds.length === 0
                        ? t('Quick access cleared.')
                        : t('Quick access saved.'),
                );
            },
            onError: () => {
                toast.error(t('Could not save quick access. Please try again.'));
            },
            onFinish: () => setSaving(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[min(90vh,640px)] flex-col gap-0 overflow-hidden p-0 sm:max-w-lg">
                <DialogHeader className="border-b border-slate-100 px-5 py-4 dark:border-slate-800">
                    <DialogTitle>{t('Manage quick access')}</DialogTitle>
                    <DialogDescription>
                        {t('Optional — choose up to {{count}} pages. Uncheck or remove anytime; empty is fine.', {
                            count: MAX_DASHBOARD_SHORTCUTS,
                        })}
                    </DialogDescription>
                </DialogHeader>

                {selectedLeaves.length > 0 ? (
                    <div className="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                        <div className="mb-2 flex items-center justify-between gap-2">
                            <p className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                {t('Selected')} ({selectedIds.length}/{MAX_DASHBOARD_SHORTCUTS})
                            </p>
                            <button
                                type="button"
                                onClick={clearAll}
                                className="text-[11px] font-medium text-muted-foreground underline-offset-2 hover:text-destructive hover:underline"
                            >
                                {t('Clear all')}
                            </button>
                        </div>
                        <DragDropContext onDragEnd={handleSelectedDragEnd}>
                            <Droppable droppableId="shortcut-customize-order" direction="vertical">
                                {(provided) => (
                                    <div
                                        ref={provided.innerRef}
                                        {...provided.droppableProps}
                                        className="grid grid-cols-2 gap-1.5 sm:grid-cols-4"
                                    >
                                        {selectedLeaves.map((leaf, index) => (
                                            <Draggable
                                                key={leaf.id}
                                                draggableId={shortcutDragId(leaf.id, 'sel-')}
                                                index={index}
                                                isDragDisabled={selectedLeaves.length < 2}
                                            >
                                                {(dragProvided, snapshot) => (
                                                    <div
                                                        ref={dragProvided.innerRef}
                                                        {...dragProvided.draggableProps}
                                                        style={dragProvided.draggableProps.style}
                                                        className={cn(
                                                            'flex min-w-0 items-center gap-0.5 rounded-md border border-primary/20 bg-primary/5 pr-0.5',
                                                            snapshot.isDragging && 'z-10 shadow-md ring-2 ring-primary/20',
                                                        )}
                                                    >
                                                        {selectedLeaves.length > 1 && (
                                                            <button
                                                                type="button"
                                                                {...dragProvided.dragHandleProps}
                                                                className="cursor-grab shrink-0 touch-none p-0.5 text-slate-400"
                                                                aria-label={t('Drag to reorder')}
                                                            >
                                                                <GripVertical className="h-3 w-3" aria-hidden />
                                                            </button>
                                                        )}
                                                        <SelectedChip leaf={leaf} />
                                                        <button
                                                            type="button"
                                                            onClick={() => removeSelected(leaf.id)}
                                                            className="shrink-0 rounded p-0.5 text-slate-400 hover:bg-slate-200/80 hover:text-slate-700 dark:hover:bg-slate-800"
                                                            aria-label={t('Remove')}
                                                        >
                                                            <X className="h-3 w-3" aria-hidden />
                                                        </button>
                                                    </div>
                                                )}
                                            </Draggable>
                                        ))}
                                        {provided.placeholder}
                                    </div>
                                )}
                            </Droppable>
                        </DragDropContext>
                    </div>
                ) : (
                    <div className="border-b border-slate-100 px-5 py-3 text-center text-xs text-muted-foreground dark:border-slate-800">
                        {t('Nothing selected yet — pick from suggestions or search below.')}
                    </div>
                )}

                {suggestionLeaves.length > 0 && selectedIds.length < MAX_DASHBOARD_SHORTCUTS && (
                    <div className="border-b border-slate-100 px-4 py-3 dark:border-slate-800">
                        <p className="mb-2 flex items-center gap-1.5 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                            <Sparkles className="h-3 w-3" aria-hidden />
                            {t('Popular picks')}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {suggestionLeaves.map((leaf) => (
                                <button
                                    key={leaf.id}
                                    type="button"
                                    onClick={() => addSuggestion(leaf.id)}
                                    className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2.5 py-1 text-[11px] font-medium text-slate-700 transition-colors hover:border-primary/30 hover:bg-primary/5 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200"
                                >
                                    <SuggestionIcon leaf={leaf} />
                                    <span className="max-w-[7rem] truncate">{leaf.title}</span>
                                </button>
                            ))}
                        </div>
                    </div>
                )}

                <div className="border-b border-slate-100 px-5 py-3 dark:border-slate-800">
                    <div className="relative">
                        <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('Search pages...')}
                            className="h-9 pl-8"
                            aria-label={t('Search pages...')}
                        />
                    </div>
                </div>

                <div className="min-h-0 flex-1 overflow-y-auto px-3 py-2">
                    {isSearching ? (
                        flatSearchResults.length === 0 ? (
                            <p className="px-2 py-8 text-center text-sm text-muted-foreground">
                                {t('No pages found')}
                            </p>
                        ) : (
                            <ShortcutLeafList
                                leaves={flatSearchResults}
                                selectedIds={selectedIds}
                                onToggle={toggleId}
                                showBreadcrumb
                            />
                        )
                    ) : visibleGroups.length === 0 ? (
                        <p className="px-2 py-8 text-center text-sm text-muted-foreground">
                            {t('No pages found')}
                        </p>
                    ) : (
                        <div className="space-y-1">
                            {visibleGroups.map((group) => (
                                <ShortcutSectionAccordion
                                    key={group.id}
                                    group={group}
                                    expanded={expandedGroups.has(group.id)}
                                    onExpandedChange={(expanded) => toggleGroup(group.id, expanded)}
                                    selectedIds={selectedIds}
                                    onToggle={toggleId}
                                />
                            ))}
                        </div>
                    )}
                </div>

                <DialogFooter className="border-t border-slate-100 px-5 py-4 dark:border-slate-800">
                    <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={saving}>
                        {t('Cancel')}
                    </Button>
                    <Button type="button" onClick={handleSave} disabled={saving}>
                        {saving ? t('Saving...') : t('Save')}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

interface ShortcutSectionAccordionProps {
    group: ShortcutLeafGroup;
    expanded: boolean;
    onExpandedChange: (expanded: boolean) => void;
    selectedIds: string[];
    onToggle: (id: string) => void;
}

function ShortcutSectionAccordion({
    group,
    expanded,
    onExpandedChange,
    selectedIds,
    onToggle,
}: ShortcutSectionAccordionProps) {
    const { t } = useTranslation();
    const selectedInGroup = group.leaves.filter((leaf) => selectedIds.includes(leaf.id)).length;

    return (
        <Collapsible open={expanded} onOpenChange={onExpandedChange}>
            <CollapsibleTrigger className="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left transition-colors hover:bg-slate-50 dark:hover:bg-slate-900/50">
                <ChevronRight
                    className={cn(
                        'h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200',
                        expanded && 'rotate-90',
                    )}
                    aria-hidden
                />
                <span className="min-w-0 flex-1 text-xs font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-200">
                    {t(group.label)}
                </span>
                <span className="shrink-0 text-[11px] text-muted-foreground">
                    {selectedInGroup > 0 ? `${selectedInGroup}/` : ''}
                    {group.leaves.length}
                </span>
            </CollapsibleTrigger>
            <CollapsibleContent className="pb-1 pl-2">
                <ShortcutLeafList
                    leaves={group.leaves}
                    selectedIds={selectedIds}
                    onToggle={onToggle}
                />
            </CollapsibleContent>
        </Collapsible>
    );
}

interface ShortcutLeafListProps {
    leaves: ShortcutLeaf[];
    selectedIds: string[];
    onToggle: (id: string) => void;
    showBreadcrumb?: boolean;
}

function ShortcutLeafList({ leaves, selectedIds, onToggle, showBreadcrumb = false }: ShortcutLeafListProps) {
    return (
        <ul className="space-y-0.5">
            {leaves.map((leaf) => {
                const checked = selectedIds.includes(leaf.id);
                const disabled = !checked && selectedIds.length >= MAX_DASHBOARD_SHORTCUTS;
                const Icon = resolveNavIcon(leaf);

                return (
                    <li key={leaf.id}>
                        <label
                            className={cn(
                                'flex cursor-pointer items-center gap-3 rounded-lg px-2 py-2 transition-colors',
                                checked ? 'bg-primary/5' : 'hover:bg-slate-50 dark:hover:bg-slate-900/50',
                                disabled && 'cursor-not-allowed opacity-50',
                            )}
                        >
                            <Checkbox
                                checked={checked}
                                disabled={disabled}
                                onCheckedChange={() => onToggle(leaf.id)}
                            />
                            <Icon className="h-4 w-4 shrink-0 text-primary" aria-hidden />
                            <span className="min-w-0 flex-1">
                                <span className="block text-sm font-medium text-slate-800 dark:text-slate-100">
                                    {leaf.title}
                                </span>
                                {showBreadcrumb && leaf.breadcrumb && (
                                    <span className="block truncate text-xs text-muted-foreground">
                                        {leaf.breadcrumb}
                                    </span>
                                )}
                            </span>
                        </label>
                    </li>
                );
            })}
        </ul>
    );
}

function SelectedChip({ leaf }: { leaf: ShortcutLeaf }) {
    const Icon = resolveNavIcon(leaf);

    return (
        <span className="flex min-w-0 flex-1 items-center gap-1 py-1">
            <Icon className="h-3.5 w-3.5 shrink-0 text-primary" aria-hidden />
            <span className="truncate text-[11px] font-medium text-slate-800 dark:text-slate-100">
                {getShortcutShortLabel(leaf.title)}
            </span>
        </span>
    );
}

function SuggestionIcon({ leaf }: { leaf: ShortcutLeaf }) {
    const Icon = resolveNavIcon(leaf);
    return <Icon className="h-3 w-3 shrink-0 text-primary" aria-hidden />;
}
