import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';
import { type PropsWithChildren, type ReactNode } from 'react';
import { usePage } from '@inertiajs/react';
import { ForceCreateBranchModal } from '@/components/hr/branches/force-create-branch-modal';

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
            <AppSidebar />
            <AppContent variant="sidebar" className="min-w-0">
                <div className="flex min-h-screen w-full min-w-0 flex-col">
                    <AppSidebarHeader breadcrumbs={breadcrumbs} actions={headerActions} />
                    <div className="flex-1 p-3 sm:p-4">
                        {children}
                    </div>
                    <footer className="z-10 mt-auto flex flex-col gap-2 border-t bg-white/80 px-3 py-2.5 text-[10px] text-gray-400 backdrop-blur-md dark:bg-gray-950/80 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                        <div className="shrink-0 font-medium">{footerText}</div>
                        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 sm:gap-6">
                            <div className="flex items-center gap-1.5">
                                <span className="text-gray-400">Develop by</span>
                                <img src="/sridix.png" alt="Sridix" className="h-4 w-4 object-contain" />
                                <a 
                                    href="https://www.sridix.com/" 
                                    target="_blank" 
                                    rel="noopener noreferrer" 
                                    className="font-black text-[#1a365d] dark:text-blue-400 uppercase tracking-tight hover:text-blue-600 transition-colors cursor-pointer"
                                >
                                    Sridix Technology LLP
                                </a>
                            </div>
                            <div className="h-3 w-px bg-gray-200 dark:bg-gray-800" />
                            <div className="flex items-center gap-2">
                                <span className="font-bold text-gray-400 uppercase text-[9px] tracking-wider">For Support:</span> 
                                <a 
                                    href="tel:8511474902" 
                                    className="text-[#1a365d] dark:text-blue-400 font-black text-[11px] hover:text-blue-600 transition-colors cursor-pointer"
                                >
                                    85114 74902
                                </a>
                            </div>
                            <div className="h-3 w-px bg-gray-200 dark:bg-gray-800" />
                            <div className="flex items-center gap-2">
                                <span className="font-bold text-gray-400 uppercase text-[9px] tracking-wider">For Sales:</span> 
                                <a 
                                    href="tel:9054906119" 
                                    className="text-[#1a365d] dark:text-blue-400 font-black text-[11px] hover:text-blue-600 transition-colors cursor-pointer"
                                >
                                    90549 06119
                                </a>
                            </div>
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
