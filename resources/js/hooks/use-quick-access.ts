import { useCallback, useEffect, useState } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import {
    MAX_DASHBOARD_SHORTCUTS,
    shortcutIdFromHref,
} from '@/lib/nav-shortcuts';
import { saveDashboardShortcuts } from '@/lib/save-dashboard-shortcuts';

export function useQuickAccess() {
    const { t } = useTranslation();
    const { dashboard_shortcuts: sharedShortcuts } = usePage().props as {
        dashboard_shortcuts?: string[];
    };

    const [savedIds, setSavedIds] = useState<string[]>(sharedShortcuts ?? []);

    useEffect(() => {
        setSavedIds(sharedShortcuts ?? []);
    }, [sharedShortcuts]);

    const persist = useCallback((ids: string[]) => {
        setSavedIds(ids);
        saveDashboardShortcuts(ids);
    }, []);

    const isPinned = useCallback(
        (idOrHref: string) => {
            const id = idOrHref.startsWith('/') ? idOrHref : shortcutIdFromHref(idOrHref);

            return savedIds.includes(id);
        },
        [savedIds],
    );

    const pin = useCallback(
        (idOrHref: string, title?: string) => {
            const id = idOrHref.startsWith('/') ? idOrHref : shortcutIdFromHref(idOrHref);

            if (savedIds.includes(id)) {
                return true;
            }

            if (savedIds.length >= MAX_DASHBOARD_SHORTCUTS) {
                toast.error(
                    t('You can pin up to {{count}} pages.', { count: MAX_DASHBOARD_SHORTCUTS }),
                );

                return false;
            }

            persist([...savedIds, id]);
            toast.success(
                title
                    ? t('{{title}} pinned to Quick Access.', { title })
                    : t('Pinned to Quick Access.'),
            );

            return true;
        },
        [savedIds, persist, t],
    );

    const unpin = useCallback(
        (idOrHref: string, options?: { silent?: boolean }) => {
            const id = idOrHref.startsWith('/') ? idOrHref : shortcutIdFromHref(idOrHref);
            const index = savedIds.indexOf(id);

            if (index === -1) {
                return;
            }

            const previousIds = savedIds;
            const next = savedIds.filter((item) => item !== id);
            persist(next);

            if (options?.silent) {
                return;
            }

            toast(t('Removed from Quick Access.'), {
                action: {
                    label: t('Undo'),
                    onClick: () => persist(previousIds),
                },
                duration: 5000,
            });
        },
        [savedIds, persist, t],
    );

    const unpinAtIndex = useCallback(
        (index: number) => {
            const id = savedIds[index];

            if (!id) {
                return;
            }

            unpin(id);
        },
        [savedIds, unpin],
    );

    return {
        savedIds,
        setSavedIds,
        isPinned,
        pin,
        unpin,
        unpinAtIndex,
        isFull: savedIds.length >= MAX_DASHBOARD_SHORTCUTS,
        canAddMore: savedIds.length < MAX_DASHBOARD_SHORTCUTS,
    };
}
