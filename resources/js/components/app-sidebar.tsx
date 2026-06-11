import { NavMain } from '@/components/nav-main';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader } from '@/components/ui/sidebar';
import { useLayout } from '@/contexts/LayoutContext';
import { useSidebarSettings } from '@/contexts/SidebarContext';
import { useBrand } from '@/contexts/BrandContext';
import { buildCompanyNavItems } from '@/config/navigation/company-nav';
import { buildSuperAdminNavItems } from '@/config/navigation/super-admin-nav';
import { filterNavItems } from '@/lib/nav-utils';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { hasPermission } from '@/utils/authorization';
import { getImagePath } from '@/utils/helpers';
import { cn } from '@/lib/utils';

const ACTIVITY_LOG_USER_TYPES = ['company', 'admin', 'manager', 'staff'];

function getFirstLeafHref(items: NavItem[]): string {
    for (const item of items) {
        if (item.href) {
            return item.href;
        }

        if (item.children?.length) {
            const childHref = getFirstLeafHref(item.children);
            if (childHref) {
                return childHref;
            }
        }
    }

    return route('dashboard');
}

export function AppSidebar() {
    const { t, i18n } = useTranslation();
    const { auth, globalSettings } = usePage().props as any;
    const userRole = auth.user?.type || auth.user?.role;
    const permissions = auth?.permissions || [];

    const canViewActivityLogs =
        ACTIVITY_LOG_USER_TYPES.includes(userRole) ||
        hasPermission(permissions, 'view-activity-logs');

    const permissionsKey = useMemo(() => JSON.stringify(permissions), [permissions]);

    const mainNavItems = useMemo(() => {
        if (userRole === 'superadmin') {
            return buildSuperAdminNavItems(t);
        }

        return buildCompanyNavItems({
            permissions,
            t,
            canViewActivityLogs,
            userRole,
        });
    }, [userRole, permissionsKey, i18n.language, canViewActivityLogs, permissions, t]);

    const { effectivePosition } = useLayout();
    const { variant, collapsible, style } = useSidebarSettings();
    const { logoLight, logoDark, favicon, titleText, updateBrandSettings } = useBrand();
    const [sidebarStyle, setSidebarStyle] = useState<Record<string, string>>({});
    const [searchQuery, setSearchQuery] = useState('');

    useEffect(() => {
        if (style === 'colored') {
            setSidebarStyle({ backgroundColor: 'var(--primary)', color: 'white' });
        } else if (style === 'gradient') {
            setSidebarStyle({
                background: 'linear-gradient(to bottom, var(--primary), color-mix(in srgb, var(--primary), transparent 20%))',
                color: 'white',
            });
        } else {
            setSidebarStyle({});
        }
    }, [style]);

    const filteredNavItems = useMemo(
        () => filterNavItems(mainNavItems, searchQuery),
        [mainNavItems, searchQuery],
    );

    const firstAvailableHref = useMemo(
        () => (filteredNavItems.length === 0 ? route('dashboard') : getFirstLeafHref(filteredNavItems)),
        [filteredNavItems],
    );

    return (
        <Sidebar
            side={effectivePosition}
            collapsible={collapsible}
            variant={variant}
            className={style !== 'plain' ? 'sidebar-custom-style' : ''}
        >
            <SidebarHeader
                className={cn(style !== 'plain' ? 'sidebar-styled' : '', 'border-b border-sidebar-border/60')}
                style={sidebarStyle}
            >
                <div className="flex flex-col gap-2 px-2.5 py-2.5 group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:px-2">
                    <Link
                        href={firstAvailableHref}
                        className="group-data-[collapsible=icon]:hidden flex w-full items-center justify-center"
                    >
                        <div className="flex min-h-[56px] w-full items-center justify-center rounded-md border border-sidebar-border/50 bg-background/60 px-3 py-2.5 shadow-none">
                            {(() => {
                                const isDark = document.documentElement.classList.contains('dark');
                                const currentLogo = isDark ? logoLight : logoDark;
                                const displayUrl = getImagePath(currentLogo) ?? currentLogo;

                                return displayUrl ? (
                                    <img
                                        key={currentLogo}
                                        src={displayUrl}
                                        alt={titleText || 'Logo'}
                                        className="max-h-11 w-full max-w-full object-contain object-center"
                                        onError={() => updateBrandSettings({ [isDark ? 'logoLight' : 'logoDark']: '' })}
                                    />
                                ) : (
                                    <div className="truncate text-center text-sm font-semibold tracking-tight">
                                        {titleText || 'K'}
                                    </div>
                                );
                            })()}
                        </div>
                    </Link>

                    <div className="group-data-[collapsible=icon]:hidden relative w-full">
                        <Search className="pointer-events-none absolute left-2 top-2 h-3.5 w-3.5 text-muted-foreground" />
                        <Input
                            placeholder={t('Search menu...')}
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Escape') {
                                    setSearchQuery('');
                                }
                            }}
                            aria-label={t('Search menu...')}
                            className="h-8 w-full border-sidebar-border/50 bg-background/60 pl-7 pr-8 text-sm transition-colors focus:bg-background"
                        />
                        {searchQuery && (
                            <button
                                type="button"
                                onClick={() => setSearchQuery('')}
                                className="absolute right-1.5 top-1.5 flex h-5 w-5 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                aria-label={t('Clear search')}
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>

                    <Link
                        href={firstAvailableHref}
                        className="hidden h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-md border border-sidebar-border/50 bg-background/60 group-data-[collapsible=icon]:flex"
                    >
                        {(() => {
                            const displayFavicon = favicon ? getImagePath(favicon) : '';

                            return displayFavicon ? (
                                <img
                                    key={favicon}
                                    src={displayFavicon}
                                    alt={titleText || 'Icon'}
                                    className="h-7 w-7 object-contain"
                                    onError={() => updateBrandSettings({ favicon: '' })}
                                />
                            ) : (
                                <div className="flex h-9 w-9 items-center justify-center rounded-md bg-primary text-sm font-bold text-white shadow-sm">
                                    {(titleText || 'W').charAt(0).toUpperCase()}
                                </div>
                            );
                        })()}
                    </Link>
                </div>
            </SidebarHeader>

            <SidebarContent>
                <div style={sidebarStyle} className={`flex h-full min-h-0 flex-col ${style !== 'plain' ? 'sidebar-styled' : ''}`}>
                    <div className="min-h-0 flex-1 overflow-y-auto overflow-x-hidden sidebar-scroll px-0.5 pb-1.5">
                        <NavMain
                            items={filteredNavItems}
                            position={effectivePosition}
                            searchQuery={searchQuery}
                        />
                    </div>
                </div>
            </SidebarContent>

            <SidebarFooter />
        </Sidebar>
    );
}
