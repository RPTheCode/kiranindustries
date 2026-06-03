import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';
import { useFavicon } from '@/hooks/use-favicon';
import { useBrandTheme } from '@/hooks/use-brand-theme';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
    headerActions?: ReactNode;
}

export default ({ children, breadcrumbs, headerActions, ...props }: AppLayoutProps) => {
    useFavicon();
    useBrandTheme();

    return (
        <AppLayoutTemplate breadcrumbs={breadcrumbs} headerActions={headerActions} {...props}>
            {children}
        </AppLayoutTemplate>
    );
};
