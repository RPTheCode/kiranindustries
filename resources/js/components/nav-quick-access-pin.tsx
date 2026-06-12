import type { MouseEvent } from 'react';
import { Pin } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { useSidebar } from '@/components/ui/sidebar';
import { useQuickAccess } from '@/hooks/use-quick-access';
import { shortcutIdFromHref } from '@/lib/nav-shortcuts';
import { cn } from '@/lib/utils';

interface NavQuickAccessPinProps {
    href?: string;
    title: string;
    className?: string;
}

export function NavQuickAccessPin({ href, title, className }: NavQuickAccessPinProps) {
    const { t } = useTranslation();
    const { state } = useSidebar();
    const { isPinned, pin, unpin } = useQuickAccess();

    if (!href || href === '#' || state === 'collapsed') {
        return null;
    }

    const id = shortcutIdFromHref(href);
    const pinned = isPinned(id);

    const handleClick = (e: MouseEvent<HTMLButtonElement>) => {
        e.preventDefault();
        e.stopPropagation();

        if (pinned) {
            unpin(id);
        } else {
            pin(id, title);
        }
    };

    return (
        <button
            type="button"
            onClick={handleClick}
            className={cn(
                'ml-auto flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-slate-400 opacity-0 transition-opacity hover:bg-slate-100 hover:text-primary group-hover/nav-item:opacity-100 dark:hover:bg-slate-800',
                pinned && 'opacity-100 text-primary',
                className,
            )}
            aria-label={pinned ? t('Unpin from Quick Access') : t('Pin to Quick Access')}
            aria-pressed={pinned}
        >
            <Pin className={cn('h-3.5 w-3.5', pinned && 'fill-current')} aria-hidden />
        </button>
    );
}
