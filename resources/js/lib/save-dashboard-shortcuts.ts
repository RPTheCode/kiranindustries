import { router } from '@inertiajs/react';

export function saveDashboardShortcutsHidden(
    hidden: boolean,
    options?: {
        onFinish?: () => void;
        only?: string[];
    },
) {
    router.put(
        route('dashboard.shortcuts.visibility'),
        { hidden },
        {
            preserveScroll: true,
            only: options?.only ?? ['dashboard_shortcuts_hidden'],
            onFinish: options?.onFinish,
        },
    );
}

export function saveDashboardShortcuts(
    shortcuts: string[],
    options?: {
        onSuccess?: () => void;
        onError?: () => void;
        onFinish?: () => void;
        only?: string[];
    },
) {
    router.put(
        route('dashboard.shortcuts.update'),
        { shortcuts },
        {
            preserveScroll: true,
            only: options?.only ?? ['dashboard_shortcuts'],
            onSuccess: options?.onSuccess,
            onError: options?.onError,
            onFinish: options?.onFinish,
        },
    );
}
