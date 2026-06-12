import { useCallback, useEffect, useMemo, useState } from 'react';
import { Link, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { ChevronDown, LayoutGrid, Pencil, Plus, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useDashboardNavItems } from '@/hooks/use-dashboard-nav-items';
import { useQuickAccess } from '@/hooks/use-quick-access';
import { resolveNavIcon } from '@/lib/nav-menu-icons';
import {
    flattenNavLeaves,
    getShortcutShortLabel,
    isShortcutLeafActive,
    MAX_DASHBOARD_SHORTCUTS,
    resolveShortcutLeaves,
    type ShortcutLeaf,
} from '@/lib/nav-shortcuts';
import { saveDashboardShortcutsHidden } from '@/lib/save-dashboard-shortcuts';
import { ShortcutCustomizeDialog } from '@/components/dashboard/ShortcutCustomizeDialog';
import { cn } from '@/lib/utils';

export function DashboardShortcuts() {
    const { t } = useTranslation();
    const { url } = usePage();
    const {
        dashboard_shortcut_suggestions: suggestions,
        dashboard_shortcuts_hidden: sharedHidden,
    } = usePage().props as {
        dashboard_shortcut_suggestions?: string[];
        dashboard_shortcuts_hidden?: boolean;
    };

    const navItems = useDashboardNavItems();
    const availableLeaves = useMemo(() => flattenNavLeaves(navItems), [navItems]);
    const { savedIds, setSavedIds, pin, unpinAtIndex, canAddMore } = useQuickAccess();

    const [isHidden, setIsHidden] = useState(sharedHidden ?? false);
    const [dialogOpen, setDialogOpen] = useState(false);

    useEffect(() => {
        setIsHidden(sharedHidden ?? false);
    }, [sharedHidden]);

    const shortcuts = useMemo(
        () => resolveShortcutLeaves(savedIds, availableLeaves),
        [savedIds, availableLeaves],
    );

    const suggestionLeaves = useMemo(() => {
        const resolved = resolveShortcutLeaves(suggestions ?? [], availableLeaves);
        return resolved.filter((leaf) => !savedIds.includes(leaf.id));
    }, [suggestions, availableLeaves, savedIds]);

    const addShortcut = useCallback(
        (id: string) => {
            pin(id);
        },
        [pin],
    );

    const setHidden = useCallback((hidden: boolean) => {
        setIsHidden(hidden);
        saveDashboardShortcutsHidden(hidden);
    }, []);

    const slotCount = shortcuts.length + (canAddMore ? 1 : 0);
    const gridColsClass =
        slotCount <= 4
            ? 'grid-cols-2 sm:grid-cols-4'
            : slotCount <= 6
              ? 'grid-cols-3 sm:grid-cols-6'
              : 'grid-cols-4 sm:grid-cols-8';

    if (availableLeaves.length === 0) {
        return null;
    }

    return (
        <>
            <Collapsible open={!isHidden} onOpenChange={(open) => setHidden(!open)}>
                <Card className="border-slate-200/80 shadow-sm dark:border-slate-800">
                    <CardHeader className="flex flex-row items-center gap-2 space-y-0 px-3 py-2 sm:px-4">
                        <CollapsibleTrigger asChild>
                            <button
                                type="button"
                                className="flex min-w-0 shrink-0 items-center gap-2 rounded-md text-left outline-none ring-offset-background transition-colors hover:bg-slate-50/80 focus-visible:ring-2 focus-visible:ring-ring sm:gap-2.5 dark:hover:bg-slate-900/40"
                                aria-expanded={!isHidden}
                            >
                                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary/10">
                                    <LayoutGrid className="h-4 w-4 text-primary" aria-hidden />
                                </span>
                                <div className="min-w-0">
                                    <CardTitle className="text-sm font-semibold leading-tight">
                                        {t('Quick Access')}
                                        {shortcuts.length > 0 && (
                                            <span className="ml-1.5 font-normal text-muted-foreground">
                                                ({shortcuts.length})
                                            </span>
                                        )}
                                    </CardTitle>
                                    <p className="truncate text-[11px] text-muted-foreground">
                                        {isHidden
                                            ? shortcuts.length > 0
                                                ? t('Tap icons or press 1–8 · header to expand')
                                                : t('Click header to expand')
                                            : shortcuts.length > 0
                                              ? t('Press 1–8 anywhere to jump · manage to reorder')
                                              : t('Optional — add pages you use often')}
                                    </p>
                                </div>
                                <ChevronDown
                                    className={cn(
                                        'h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-200',
                                        !isHidden && 'rotate-180',
                                    )}
                                    aria-hidden
                                />
                            </button>
                        </CollapsibleTrigger>

                        {isHidden && (
                            <CollapsedShortcutStrip
                                shortcuts={shortcuts}
                                currentUrl={url}
                                canAddMore={canAddMore}
                                onAdd={() => setDialogOpen(true)}
                            />
                        )}

                        {!isHidden && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-8 shrink-0 gap-1.5 px-2.5 text-xs"
                                onClick={() => setDialogOpen(true)}
                            >
                                {shortcuts.length > 0 ? (
                                    <>
                                        <Pencil className="h-3.5 w-3.5" aria-hidden />
                                        <span className="hidden sm:inline">{t('Manage')}</span>
                                    </>
                                ) : (
                                    <>
                                        <Plus className="h-3.5 w-3.5" aria-hidden />
                                        <span>{t('Add')}</span>
                                    </>
                                )}
                            </Button>
                        )}
                    </CardHeader>

                    <CollapsibleContent>
                        <CardContent className="px-3 pb-3 pt-0 sm:px-4">
                            {shortcuts.length === 0 ? (
                                <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50/60 px-4 py-5 dark:border-slate-700 dark:bg-slate-900/25">
                                    <p className="text-center text-xs leading-relaxed text-muted-foreground">
                                        {t('No pages pinned yet. Pick a few below or browse all — remove anytime.')}
                                    </p>

                                    {suggestionLeaves.length > 0 && (
                                        <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                                            {suggestionLeaves.map((leaf) => (
                                                <SuggestionChip
                                                    key={leaf.id}
                                                    leaf={leaf}
                                                    onAdd={() => addShortcut(leaf.id)}
                                                />
                                            ))}
                                        </div>
                                    )}

                                    <div className="mt-4 flex justify-center">
                                        <Button
                                            type="button"
                                            variant="secondary"
                                            size="sm"
                                            className="h-8 gap-1.5 text-xs"
                                            onClick={() => setDialogOpen(true)}
                                        >
                                            <Plus className="h-3.5 w-3.5" aria-hidden />
                                            {t('Browse all pages')}
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <div className={cn('grid w-full gap-2', gridColsClass)}>
                                    {shortcuts.map((leaf, index) => (
                                        <ShortcutTile
                                            key={leaf.id}
                                            leaf={leaf}
                                            currentUrl={url}
                                            hotkeyIndex={index < MAX_DASHBOARD_SHORTCUTS ? index : undefined}
                                            onRemove={() => unpinAtIndex(index)}
                                        />
                                    ))}

                                    {canAddMore && (
                                        <button
                                            type="button"
                                            onClick={() => setDialogOpen(true)}
                                            className="flex min-h-[4.25rem] flex-col items-center justify-center gap-1 rounded-lg border border-dashed border-slate-300 bg-slate-50/80 px-1 py-2 text-[10px] font-medium text-slate-600 transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-primary dark:border-slate-600 dark:bg-slate-900/40 dark:text-slate-300"
                                            aria-label={t('Pin page')}
                                        >
                                            <Plus className="h-4 w-4" aria-hidden />
                                            <span>{t('Add')}</span>
                                        </button>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </CollapsibleContent>
                </Card>
            </Collapsible>

            <ShortcutCustomizeDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                navItems={navItems}
                availableLeaves={availableLeaves}
                savedIds={savedIds}
                suggestionIds={suggestions ?? []}
                onSaved={setSavedIds}
            />
        </>
    );
}

function CollapsedShortcutStrip({
    shortcuts,
    currentUrl,
    canAddMore,
    onAdd,
}: {
    shortcuts: ShortcutLeaf[];
    currentUrl: string;
    canAddMore: boolean;
    onAdd: () => void;
}) {
    const { t } = useTranslation();

    return (
        <div className="flex min-w-0 flex-1 items-center justify-end gap-0.5 sm:gap-1">
            {shortcuts.map((leaf, index) => {
                const Icon = resolveNavIcon(leaf);
                const isActive = isShortcutLeafActive(leaf, currentUrl);
                const hotkey = index < MAX_DASHBOARD_SHORTCUTS ? index + 1 : null;

                return (
                    <Tooltip key={leaf.id}>
                        <TooltipTrigger asChild>
                            <Link
                                href={leaf.href}
                                className={cn(
                                    'relative flex h-7 w-7 shrink-0 items-center justify-center rounded-md border transition-colors sm:h-8 sm:w-8',
                                    isActive
                                        ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                        : 'border-slate-200/90 bg-white hover:border-primary/30 hover:bg-primary/5 dark:border-slate-700 dark:bg-slate-950',
                                )}
                                aria-current={isActive ? 'page' : undefined}
                            >
                                <Icon className="h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden />
                                {hotkey !== null && (
                                    <span className="absolute -right-0.5 -top-0.5 hidden h-3.5 min-w-3.5 rounded bg-slate-700 px-0.5 text-center text-[8px] font-bold leading-[14px] text-white sm:inline">
                                        {hotkey}
                                    </span>
                                )}
                            </Link>
                        </TooltipTrigger>
                        <TooltipContent side="bottom">
                            {hotkey !== null ? `${hotkey} · ${leaf.title}` : leaf.title}
                        </TooltipContent>
                    </Tooltip>
                );
            })}

            {canAddMore && (
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            onClick={onAdd}
                            className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md border border-dashed border-slate-300 bg-slate-50/80 text-slate-500 transition-colors hover:border-primary/40 hover:bg-primary/5 hover:text-primary sm:h-8 sm:w-8 dark:border-slate-600 dark:bg-slate-900/40"
                            aria-label={t('Pin page')}
                        >
                            <Plus className="h-3.5 w-3.5 sm:h-4 sm:w-4" aria-hidden />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent side="bottom">{t('Pin page')}</TooltipContent>
                </Tooltip>
            )}
        </div>
    );
}

function SuggestionChip({ leaf, onAdd }: { leaf: ShortcutLeaf; onAdd: () => void }) {
    const Icon = resolveNavIcon(leaf);

    return (
        <button
            type="button"
            onClick={onAdd}
            className="flex flex-col items-center gap-1 rounded-lg border border-slate-200 bg-white px-2 py-2.5 text-center shadow-sm transition-colors hover:border-primary/30 hover:bg-primary/5 dark:border-slate-700 dark:bg-slate-950"
        >
            <Icon className="h-4 w-4 text-primary" aria-hidden />
            <span className="line-clamp-2 w-full text-[10px] font-medium leading-tight text-slate-700 dark:text-slate-200">
                {leaf.title}
            </span>
        </button>
    );
}

interface ShortcutTileProps {
    leaf: ShortcutLeaf;
    currentUrl: string;
    hotkeyIndex?: number;
    onRemove: () => void;
}

function ShortcutTile({ leaf, currentUrl, hotkeyIndex, onRemove }: ShortcutTileProps) {
    const { t } = useTranslation();
    const Icon = resolveNavIcon(leaf);
    const isActive = isShortcutLeafActive(leaf, currentUrl);
    const shortLabel = getShortcutShortLabel(leaf.title);

    const tooltipBody = leaf.breadcrumb ? (
        <span className="flex flex-col gap-0.5">
            <span className="font-medium">{leaf.title}</span>
            <span className="text-xs opacity-80">{leaf.breadcrumb}</span>
        </span>
    ) : (
        leaf.title
    );

    return (
        <div
            className={cn(
                'group relative flex min-h-[4.25rem] flex-col items-center justify-center gap-1 rounded-lg border bg-white px-1 py-2 shadow-sm transition-shadow dark:bg-slate-950',
                isActive
                    ? 'border-primary bg-primary/[0.08] ring-1 ring-primary/30'
                    : 'border-slate-200/90 hover:border-primary/25 hover:shadow dark:border-slate-800',
            )}
        >
            {hotkeyIndex !== undefined && (
                <span className="absolute left-1 top-1 flex h-4 min-w-4 items-center justify-center rounded bg-slate-100 px-0.5 text-[9px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    {hotkeyIndex + 1}
                </span>
            )}

            <Tooltip>
                <TooltipTrigger asChild>
                    <Link
                        href={leaf.href}
                        className="flex w-full flex-col items-center gap-1 px-1 pt-0.5"
                        aria-current={isActive ? 'page' : undefined}
                    >
                        <span
                            className={cn(
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-lg transition-colors',
                                isActive
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-primary/10 text-primary',
                            )}
                        >
                            <Icon className="h-4 w-4" aria-hidden />
                        </span>
                        <span className="line-clamp-2 w-full text-center text-[10px] font-medium leading-tight text-slate-800 sm:text-[11px] dark:text-slate-100">
                            <span className="sm:hidden">{shortLabel}</span>
                            <span className="hidden sm:inline">{leaf.title}</span>
                        </span>
                    </Link>
                </TooltipTrigger>
                <TooltipContent side="bottom" align="center">
                    {isActive ? (
                        <span className="flex flex-col gap-0.5">
                            {tooltipBody}
                            <span className="text-[10px] font-medium uppercase tracking-wide opacity-80">
                                {t('Current page')}
                            </span>
                        </span>
                    ) : (
                        tooltipBody
                    )}
                </TooltipContent>
            </Tooltip>

            <button
                type="button"
                onClick={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    onRemove();
                }}
                className="absolute right-0.5 top-0.5 flex h-4 w-4 items-center justify-center rounded bg-white/90 text-slate-400 shadow-sm transition-colors hover:bg-slate-100 hover:text-slate-700 dark:bg-slate-950/90 dark:hover:bg-slate-800 dark:hover:text-slate-200"
                aria-label={t('Unpin page')}
            >
                <X className="h-2.5 w-2.5" aria-hidden />
            </button>
        </div>
    );
}
