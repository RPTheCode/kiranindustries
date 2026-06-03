import { ModalStackProvider } from '@/contexts/ModalStackContext';
import { LayoutProvider } from '@/contexts/LayoutContext';
import { SidebarProvider } from '@/contexts/SidebarContext';
import { BrandProvider } from '@/contexts/BrandContext';
import { CustomToast } from '@/components/custom-toast';
import { type ReactNode, Suspense } from 'react';

type AppProvidersProps = {
    children: ReactNode;
    globalSettings?: Record<string, unknown>;
    user?: Record<string, unknown> | null;
};

export function AppProviders({ children, globalSettings, user }: AppProvidersProps) {
    return (
        <ModalStackProvider>
            <LayoutProvider>
                <SidebarProvider>
                    <BrandProvider globalSettings={globalSettings} user={user}>
                        <Suspense
                            fallback={
                                <div className="flex h-screen w-full items-center justify-center text-sm text-slate-500">
                                    Loading...
                                </div>
                            }
                        >
                            {children}
                        </Suspense>
                        <CustomToast />
                    </BrandProvider>
                </SidebarProvider>
            </LayoutProvider>
        </ModalStackProvider>
    );
}
