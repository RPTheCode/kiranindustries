import { useEffect, useMemo } from 'react';
import { router } from '@inertiajs/react';
import { useDashboardNavItems } from '@/hooks/use-dashboard-nav-items';
import { useQuickAccess } from '@/hooks/use-quick-access';
import {
    flattenNavLeaves,
    MAX_DASHBOARD_SHORTCUTS,
    resolveShortcutLeaves,
} from '@/lib/nav-shortcuts';

function isTypingTarget(target: EventTarget | null): boolean {
    if (!(target instanceof HTMLElement)) {
        return false;
    }

    const tag = target.tagName;

    return (
        tag === 'INPUT' ||
        tag === 'TEXTAREA' ||
        tag === 'SELECT' ||
        target.isContentEditable
    );
}

function isDialogOpen(): boolean {
    return Boolean(document.querySelector('[role="dialog"][data-state="open"]'));
}

/** Global 1–8 keys jump to Quick Access pages from anywhere in the app. */
export function useQuickAccessHotkeys(enabled = true) {
    const { savedIds } = useQuickAccess();
    const navItems = useDashboardNavItems();
    const availableLeaves = useMemo(() => flattenNavLeaves(navItems), [navItems]);
    const shortcuts = useMemo(
        () => resolveShortcutLeaves(savedIds, availableLeaves),
        [savedIds, availableLeaves],
    );

    useEffect(() => {
        if (!enabled || shortcuts.length === 0) {
            return;
        }

        const handleKeyDown = (event: KeyboardEvent) => {
            if (isTypingTarget(event.target)) {
                return;
            }

            if (event.metaKey || event.ctrlKey || event.altKey) {
                return;
            }

            if (isDialogOpen()) {
                return;
            }

            const index = Number.parseInt(event.key, 10) - 1;

            if (Number.isNaN(index) || index < 0 || index >= shortcuts.length) {
                return;
            }

            if (index >= MAX_DASHBOARD_SHORTCUTS) {
                return;
            }

            const leaf = shortcuts[index];

            if (!leaf) {
                return;
            }

            event.preventDefault();
            router.visit(leaf.href);
        };

        window.addEventListener('keydown', handleKeyDown);

        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [enabled, shortcuts]);
}

/** Mount once in app layout — no UI. */
export function QuickAccessHotkeys({ enabled = true }: { enabled?: boolean }) {
    useQuickAccessHotkeys(enabled);

    return null;
}
