import { HeaderTitleBlock } from '@/components/header-title-block';
import { ProfileMenu } from '@/components/profile-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useLayout } from '@/contexts/LayoutContext';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { BranchSwitcher } from './branch-switcher';
import type { ReactNode } from 'react';

export function AppSidebarHeader({
    breadcrumbs = [],
    actions,
}: {
    breadcrumbs?: BreadcrumbItemType[];
    actions?: ReactNode;
}) {
    const { t } = useTranslation();
    const { position } = useLayout();
    const pageProps = usePage().props as { isImpersonating?: boolean };
    const isImpersonating = pageProps.isImpersonating;

    return (
        <header
            className={cn(
                'sticky top-0 z-30 flex min-h-14 shrink-0 items-center gap-2',
                'border-b border-slate-200/80 bg-white/85 px-3 py-2 shadow-sm backdrop-blur-md',
                'dark:border-slate-800/80 dark:bg-slate-950/90',
                'sm:gap-3 sm:px-4'
            )}
        >
            <div className="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
                {position === 'left' && (
                    <SidebarTrigger className="h-8 w-8 shrink-0 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800" />
                )}
                <HeaderTitleBlock breadcrumbs={breadcrumbs} />
            </div>

            <div
                className={cn(
                    'flex shrink-0 items-center gap-0.5 rounded-lg border border-slate-200/70 bg-slate-50/70 p-0.5',
                    'dark:border-slate-700/70 dark:bg-slate-900/60'
                )}
            >
                <BranchSwitcher variant="header" embedded />

                {actions && (
                    <>
                        <div className="mx-0.5 hidden h-5 w-px bg-slate-200 sm:block dark:bg-slate-700" aria-hidden />
                        <div className="flex max-w-[40vw] items-center gap-0.5 overflow-x-auto sm:max-w-none">
                            {actions}
                        </div>
                    </>
                )}

                {isImpersonating && (
                    <button
                        type="button"
                        onClick={() => router.post(route('impersonate.leave'))}
                        className="shrink-0 rounded-md bg-red-500 px-2 py-1 text-[11px] font-medium text-white hover:bg-red-600"
                    >
                        {t('Exit')}
                    </button>
                )}

                <div className="mx-0.5 hidden h-5 w-px bg-slate-200 sm:block dark:bg-slate-700" aria-hidden />

                <ProfileMenu variant="header" embedded />

                {position === 'right' && (
                    <SidebarTrigger className="h-8 w-8 shrink-0 text-slate-500 dark:text-slate-300" />
                )}
            </div>
        </header>
    );
}
