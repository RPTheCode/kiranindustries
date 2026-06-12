import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren, type ReactNode } from 'react';
import { usePage } from '@inertiajs/react';
import { ForceCreateBranchModal } from '@/components/hr/branches/force-create-branch-modal';
import { QuickAccessHotkeys } from '@/hooks/use-quick-access-hotkeys';

import { TestingNotification } from '@/components/testing-notification';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
    headerActions,
}: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[]; headerActions?: ReactNode }>) {
    const { auth, globalSettings } = usePage<any>().props;
    const footerText = globalSettings?.footerText || 'Kiran Industries Pvt Ltd';

    return (
        <AppShell variant="sidebar">
            <TestingNotification />
            {auth?.user && <QuickAccessHotkeys />}
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0">
                <div className="flex min-h-screen w-full min-w-0 flex-col">
                    <AppSidebarHeader breadcrumbs={breadcrumbs} actions={headerActions} />
                    <div className="flex-1 p-3 sm:p-4">
                        {children}
                    </div>
                    <footer className="z-10 mt-auto border-t bg-white/80 px-4 py-2.5 text-[11px] text-slate-500 backdrop-blur-md dark:bg-gray-950/80 dark:text-slate-400 sm:flex sm:items-center sm:justify-between sm:px-6">
                        <p className="truncate text-center font-medium text-slate-700 dark:text-slate-300 sm:text-left">
                            {footerText}
                        </p>
                        <div className="mt-1.5 flex flex-wrap items-center justify-center gap-x-2.5 gap-y-1 sm:mt-0 sm:justify-end">
                            <a
                                href="https://www.sridix.com/"
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 font-medium text-slate-600 transition-colors hover:text-blue-600 dark:text-slate-300"
                            >
                                <img src="/sridix.png" alt="" className="h-3 w-3 object-contain" aria-hidden />
                                Sridix
                            </a>
                            <span className="text-slate-300 dark:text-slate-600" aria-hidden>·</span>
                            <a
                                href="tel:8511474902"
                                className="font-medium text-slate-600 transition-colors hover:text-blue-600 dark:text-slate-300"
                                title="Support: 85114 74902"
                            >
                                Support
                            </a>
                            <span className="text-slate-300 dark:text-slate-600" aria-hidden>·</span>
                            <a
                                href="tel:9054906119"
                                className="font-medium text-slate-600 transition-colors hover:text-blue-600 dark:text-slate-300"
                                title="Sales: 90549 06119"
                            >
                                Sales
                            </a>
                        </div>
                    </footer>
                </div>
            </AppContent>
            {auth.must_create_branch && (
                <ForceCreateBranchModal isOpen={true} />
            )}
        </AppShell>
    );
}
