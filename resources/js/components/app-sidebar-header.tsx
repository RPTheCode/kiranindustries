import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useLayout } from '@/contexts/LayoutContext';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { ProfileMenu } from '@/components/profile-menu';
import { usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { BranchSwitcher } from './branch-switcher';
import { LogOut } from 'lucide-react';
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
                'sticky top-0 z-30 flex min-h-11 shrink-0 items-center gap-2',
                'border-b border-slate-200 bg-white px-3 py-2',
                'dark:border-slate-800 dark:bg-slate-950',
                'sm:min-h-12 sm:gap-3 sm:px-4'
            )}
        >
            <div className="flex min-w-0 flex-1 items-center gap-2 overflow-hidden">
                {position === 'left' && (
                    <SidebarTrigger className="h-8 w-8 shrink-0 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800" />
                )}
                <Breadcrumbs
                    variant="header"
                    items={breadcrumbs.map((b) => ({ label: b.title, href: b.href }))}
                />
            </div>

            <div className="flex shrink-0 items-center gap-2">
                <BranchSwitcher variant="header" />

                {actions && (
                    <>
                        <div className="hidden h-5 w-px bg-slate-200 sm:block dark:bg-slate-700" aria-hidden />
                        <div className="flex max-w-[50vw] items-center gap-1.5 overflow-x-auto sm:max-w-none">
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

                <div className="hidden h-5 w-px bg-slate-200 sm:block dark:bg-slate-700" aria-hidden />

                <ProfileMenu variant="header" />

                {position === 'right' && (
                    <SidebarTrigger className="h-8 w-8 shrink-0 text-slate-500 dark:text-slate-300" />
                )}
            </div>
        </header>
    );
}
