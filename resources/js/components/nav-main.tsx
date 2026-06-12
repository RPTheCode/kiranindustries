import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
    useSidebar,
} from '@/components/ui/sidebar';
import { countNavLeaves, highlightNavTitle } from '@/lib/nav-menu-highlight';
import { resolveNavIcon } from '@/lib/nav-menu-icons';
import {
    groupNavItems,
    isNavBranchActive,
    isNavItemActive,
    resolveActiveNavHref,
} from '@/lib/nav-utils';
import { type NavItem } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { ChevronRight } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipTrigger } from '@/components/ui/tooltip';
import { useTranslation } from 'react-i18next';
import { NavQuickAccessPin } from '@/components/nav-quick-access-pin';

function navTooltipContent(item: NavItem) {
    if (!item.description) {
        return item.title;
    }

    return (
        <span className="flex flex-col gap-0.5">
            <span className="font-medium">{item.title}</span>
            <span className="text-xs font-normal opacity-80">{item.description}</span>
        </span>
    );
}

/** Show styled tooltip only when label text is truncated (expanded sidebar). */
function NavItemLabel({
    item,
    title,
    searchTerm,
    isSearching,
    className,
}: {
    item: NavItem;
    title: string;
    searchTerm: string;
    isSearching: boolean;
    className?: string;
}) {
    const labelRef = useRef<HTMLSpanElement>(null);
    const [isTruncated, setIsTruncated] = useState(false);

    const checkTruncation = useCallback(() => {
        const el = labelRef.current;
        if (!el) {
            return;
        }
        setIsTruncated(el.scrollWidth > el.clientWidth);
    }, []);

    useEffect(() => {
        checkTruncation();
        const el = labelRef.current;
        if (!el) {
            return;
        }

        const observer = new ResizeObserver(checkTruncation);
        observer.observe(el);

        return () => observer.disconnect();
    }, [checkTruncation, title, isSearching, searchTerm]);

    const label = (
        <span ref={labelRef} className={cn('block min-w-0 truncate', className)}>
            {isSearching ? highlightNavTitle(title, searchTerm) : title}
        </span>
    );

    if (!isTruncated) {
        return label;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>{label}</TooltipTrigger>
            <TooltipContent side="right" align="center">
                {navTooltipContent(item)}
            </TooltipContent>
        </Tooltip>
    );
}

function isNestedExpandKey(key: string): boolean {
    return /^\d+-/.test(key);
}

function computeAutoExpandedItems(
    items: NavItem[],
    activeHref: string | null,
    currentUrl: string
): Record<string, boolean> {
    const newExpandedItems: Record<string, boolean> = {};

    const checkNestedChildren = (children: NavItem[], level: number) => {
        children.forEach((child) => {
            const childKey = `${level}-${child.title}`;
            const isChildItemActive = isNavItemActive(child, activeHref, currentUrl);
            const hasActiveChild = child.children && isNavBranchActive(child.children, activeHref, currentUrl);

            if (child.children && (isChildItemActive || hasActiveChild)) {
                newExpandedItems[childKey] = true;
                checkNestedChildren(child.children, level + 1);
            }
        });
    };

    const processMenuItems = (menuItems: NavItem[], parentKey?: string) => {
        menuItems.forEach((item) => {
            const selfActive = isNavItemActive(item, activeHref, currentUrl);
            const hasActiveChild = item.children && isNavBranchActive(item.children, activeHref, currentUrl);

            if (parentKey && (selfActive || hasActiveChild)) {
                newExpandedItems[parentKey] = true;
            }

            if (item.children && (selfActive || hasActiveChild || item.defaultOpen === true)) {
                newExpandedItems[item.title] = true;
                processMenuItems(item.children, item.title);
                checkNestedChildren(item.children, 1);
            }
        });
    };

    processMenuItems(items);

    return newExpandedItems;
}

function expandedItemsChanged(prev: Record<string, boolean>, next: Record<string, boolean>): boolean {
    const keys = new Set([...Object.keys(prev), ...Object.keys(next)]);
    for (const key of keys) {
        if (!!prev[key] !== !!next[key]) {
            return true;
        }
    }
    return false;
}

function handleSidebarNavClick(href: string | undefined, e: React.MouseEvent<HTMLAnchorElement>) {
    if (!href || href === '#') {
        return;
    }

    if (e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
        return;
    }

    e.preventDefault();
    router.visit(href);
}

export function NavMain({
    items = [],
    position,
    searchQuery = '',
}: {
    items: NavItem[];
    position: 'left' | 'right';
    searchQuery?: string;
}) {
    const { t } = useTranslation();
    const page = usePage();
    const { state, isMobile, setOpenMobile } = useSidebar();
    const isRtl = document.documentElement.dir === 'rtl';

    const handleNavClick = useCallback(
        (href: string | undefined, e: React.MouseEvent<HTMLAnchorElement>) => {
            handleSidebarNavClick(href, e);
            if (isMobile) {
                setOpenMobile(false);
            }
        },
        [isMobile, setOpenMobile]
    );
    const effectivePosition = isRtl ? (position === 'left' ? 'right' : 'left') : position;

    const itemsRef = useRef(items);
    itemsRef.current = items;

    const activeHref = useMemo(() => resolveActiveNavHref(items, page.url), [items, page.url]);

    const navGroups = useMemo(() => groupNavItems(items), [items]);
    const searchTerm = searchQuery.trim();
    const isSearching = searchTerm.length > 0;
    const resultCount = useMemo(() => (isSearching ? countNavLeaves(items) : 0), [items, isSearching]);

    const [expandedItems, setExpandedItems] = useState<Record<string, boolean>>({});

    const lastSyncedUrlRef = useRef<string | null>(null);

    const syncExpandedToActiveRoute = useCallback((currentUrl: string) => {
        const href = resolveActiveNavHref(itemsRef.current, currentUrl);
        const autoExpanded = computeAutoExpandedItems(itemsRef.current, href, currentUrl);

        setExpandedItems((prev) => {
            if (!expandedItemsChanged(prev, autoExpanded)) {
                return prev;
            }
            return autoExpanded;
        });
    }, []);

    useEffect(() => {
        if (isSearching) {
            const href = resolveActiveNavHref(itemsRef.current, page.url);
            const auto = computeAutoExpandedItems(itemsRef.current, href, page.url);
            setExpandedItems(auto);
            return;
        }

        if (lastSyncedUrlRef.current === page.url) {
            return;
        }
        lastSyncedUrlRef.current = page.url;
        syncExpandedToActiveRoute(page.url);
    }, [page.url, syncExpandedToActiveRoute, isSearching, items]);

    const renderTitle = (title: string, item?: NavItem) => {
        if (item) {
            return (
                <NavItemLabel
                    item={item}
                    title={title}
                    searchTerm={searchTerm}
                    isSearching={isSearching}
                />
            );
        }

        return (
            <span className="truncate">
                {isSearching ? highlightNavTitle(title, searchTerm) : title}
            </span>
        );
    };

    // Scroll active menu item into view after navigation.
    useEffect(() => {
        const id = window.setTimeout(() => {
            const activeEl = document.querySelector(
                '[data-nav-active="true"]'
            ) as HTMLElement | null;

            activeEl?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }, 120);

        return () => window.clearTimeout(id);
    }, [page.url, activeHref]);

    const isLeafActive = useCallback(
        (item: NavItem) => isNavItemActive(item, activeHref, page.url),
        [activeHref, page.url]
    );

    const isBranchActive = useCallback(
        (children?: NavItem[]) => isNavBranchActive(children, activeHref, page.url),
        [activeHref, page.url]
    );

    /** One top-level section open at a time — less clutter (skipped while searching) */
    const toggleTopLevelExpand = (title: string) => {
        setExpandedItems((prev) => {
            const willOpen = !prev[title];

            if (isSearching) {
                return { ...prev, [title]: willOpen };
            }

            if (!willOpen) {
                return { ...prev, [title]: false };
            }

            const next: Record<string, boolean> = {};

            Object.entries(prev).forEach(([key, value]) => {
                if (isNestedExpandKey(key)) {
                    next[key] = value;
                } else {
                    next[key] = false;
                }
            });

            next[title] = true;
            return next;
        });
    };

    const toggleNestedExpand = (key: string) => {
        setExpandedItems((prev) => ({ ...prev, [key]: !prev[key] }));
    };

    const menuIconClass = (active: boolean, sectionOnly = false, size: 'sm' | 'md' = 'md') =>
        cn(
            size === 'sm' ? 'h-3.5 w-3.5' : 'h-4 w-4',
            'shrink-0 transition-colors duration-200',
            active ? (sectionOnly ? 'text-primary/90' : 'text-primary') : 'text-slate-500 dark:text-slate-400'
        );

    const chevronClass = (active: boolean, isOpen: boolean) =>
        cn(
            'ml-auto h-3.5 w-3.5 shrink-0 text-slate-400 transition-transform duration-200',
            isOpen && 'rotate-90',
            active && 'text-primary/70'
        );

    const subLinkClass = (active: boolean) =>
        cn(
            'flex w-full min-w-0 cursor-pointer items-center gap-2 transition-colors duration-200',
            active
                ? 'font-semibold text-primary'
                : 'font-normal text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'
        );

    const renderSubMenuIcon = (child: NavItem, active: boolean) => {
        const Icon = resolveNavIcon(child);
        return <Icon className={menuIconClass(active, false, 'sm')} aria-hidden />;
    };

    const renderSubMenu = (children: NavItem[], level: number = 1) => (
        <SidebarMenuSub className="relative z-[2] border-l-primary/25 py-0.5">
            {children.map((child) => {
                if (child.children) {
                    const nestedKey = `${level}-${child.title}`;
                    const nestedOpen = !!expandedItems[nestedKey];
                    const branchActive = isBranchActive(child.children);

                    return (
                        <SidebarMenuSubItem key={child.title}>
                            <SidebarMenuSubButton asChild isActive={branchActive}>
                                <button
                                    type="button"
                                    aria-expanded={nestedOpen}
                                    onClick={() => toggleNestedExpand(nestedKey)}
                                    className={cn(
                                        subLinkClass(branchActive),
                                        'font-medium',
                                        effectivePosition === 'right' ? 'justify-end text-right' : 'justify-start text-left'
                                    )}
                                >
                                    {renderSubMenuIcon(child, branchActive)}
                                    {renderTitle(child.title, child)}
                                    {state !== 'collapsed' && (
                                        <ChevronRight className={chevronClass(branchActive, nestedOpen)} aria-hidden />
                                    )}
                                </button>
                            </SidebarMenuSubButton>
                            {nestedOpen && renderSubMenu(child.children, level + 1)}
                        </SidebarMenuSubItem>
                    );
                }

                const leafActive = isLeafActive(child);

                return (
                    <SidebarMenuSubItem key={child.title} className="group/nav-item">
                        <SidebarMenuSubButton
                            isActive={leafActive}
                            href={child.href || '#'}
                            target={child.target}
                            data-nav-active={leafActive ? 'true' : undefined}
                            aria-current={leafActive ? 'page' : undefined}
                            onClick={(e) => handleNavClick(child.href, e)}
                            className={cn(
                                subLinkClass(leafActive),
                                effectivePosition === 'right' ? 'justify-end text-right' : 'justify-start text-left',
                            )}
                        >
                            {renderSubMenuIcon(child, leafActive)}
                            <span className="min-w-0 flex-1">{renderTitle(child.title, child)}</span>
                            {!child.target && <NavQuickAccessPin href={child.href} title={child.title} />}
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                );
            })}
        </SidebarMenuSub>
    );

    const renderMenuItem = (item: NavItem) => {
        const isOpen = !!expandedItems[item.title];

        if (item.children) {
            const hasActiveChild = isBranchActive(item.children);
            const selfActive = isLeafActive(item);
            const sectionOpen = hasActiveChild && !selfActive;

            return (
                <SidebarMenuItem
                    key={item.title}
                    className={cn('relative', isOpen && state !== 'collapsed' && 'z-20')}
                >
                    <SidebarMenuButton
                        type="button"
                        isActive={selfActive}
                        data-section-open={sectionOpen ? true : undefined}
                        data-expanded={isOpen ? true : undefined}
                        aria-expanded={isOpen}
                        className={cn(
                            sectionOpen && 'sidebar-menu-section-open',
                            isOpen && 'sidebar-menu-parent-open'
                        )}
                        tooltip={{ children: navTooltipContent(item) }}
                        onClick={() => toggleTopLevelExpand(item.title)}
                    >
                        <span
                            className={cn(
                                'flex w-full items-center gap-2',
                                isOpen && 'font-semibold',
                                effectivePosition === 'right' ? 'justify-end text-right' : 'justify-start text-left'
                            )}
                        >
                            {effectivePosition === 'right' ? (
                                <>
                                    {state !== 'collapsed' && (
                                        <span className="min-w-0 truncate">{renderTitle(item.title, item)}</span>
                                    )}
                                    {item.icon && (
                                        <item.icon className={menuIconClass(selfActive || hasActiveChild, sectionOpen)} />
                                    )}
                                    {state !== 'collapsed' && (
                                        <ChevronRight className={chevronClass(hasActiveChild, isOpen)} aria-hidden />
                                    )}
                                </>
                            ) : (
                                <>
                                    {item.icon && (
                                        <item.icon className={menuIconClass(selfActive || hasActiveChild, sectionOpen)} />
                                    )}
                                    {state !== 'collapsed' && (
                                        <span className="min-w-0 flex-1">{renderTitle(item.title, item)}</span>
                                    )}
                                    {state !== 'collapsed' && item.badge && (
                                        <span className="shrink-0 rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-medium text-white">
                                            {item.badge.label}
                                        </span>
                                    )}
                                    {state !== 'collapsed' && (
                                        <ChevronRight className={chevronClass(hasActiveChild, isOpen)} aria-hidden />
                                    )}
                                </>
                            )}
                        </span>
                    </SidebarMenuButton>
                    {state !== 'collapsed' && isOpen && (
                        <div className="sidebar-submenu-panel mx-0.5 mb-0.5 rounded-md border border-slate-100 bg-slate-50/90 dark:border-slate-700/80 dark:bg-slate-800/50">
                            {renderSubMenu(item.children)}
                        </div>
                    )}
                </SidebarMenuItem>
            );
        }

        const leafActive = isLeafActive(item);

        return (
            <SidebarMenuItem key={item.title} className="group/nav-item">
                <SidebarMenuButton asChild isActive={leafActive} tooltip={{ children: navTooltipContent(item) }}>
                    {item.target === '_blank' ? (
                        <a
                            href={item.href || '#'}
                            target="_blank"
                            rel="noopener noreferrer"
                            className={cn(
                                'flex items-center gap-2',
                                effectivePosition === 'right' ? 'justify-end text-right' : 'justify-start text-left',
                            )}
                        >
                            {effectivePosition === 'right' ? (
                                <>
                                    {state !== 'collapsed' && renderTitle(item.title, item)}
                                    {item.icon && <item.icon className={menuIconClass(leafActive)} />}
                                </>
                            ) : (
                                <>
                                    {item.icon && <item.icon className={menuIconClass(leafActive)} />}
                                    {state !== 'collapsed' && (
                                        <span className="min-w-0 flex-1 truncate">{renderTitle(item.title, item)}</span>
                                    )}
                                </>
                            )}
                        </a>
                    ) : (
                        <a
                            href={item.href || '#'}
                            data-nav-active={leafActive ? 'true' : undefined}
                            aria-current={leafActive ? 'page' : undefined}
                            onClick={(e) => handleNavClick(item.href, e)}
                            className={cn(
                                'flex items-center gap-2',
                                effectivePosition === 'right' ? 'justify-end text-right' : 'justify-start text-left',
                            )}
                        >
                            {effectivePosition === 'right' ? (
                                <>
                                    {state !== 'collapsed' && (
                                        <span className="min-w-0 flex-1">{renderTitle(item.title, item)}</span>
                                    )}
                                    {item.icon && <item.icon className={menuIconClass(leafActive)} />}
                                    {state !== 'collapsed' && (
                                        <NavQuickAccessPin href={item.href} title={item.title} />
                                    )}
                                </>
                            ) : (
                                <>
                                    {item.icon && <item.icon className={menuIconClass(leafActive)} />}
                                    {state !== 'collapsed' && (
                                        <span className="min-w-0 flex-1 truncate">{renderTitle(item.title, item)}</span>
                                    )}
                                    {state !== 'collapsed' && (
                                        <NavQuickAccessPin href={item.href} title={item.title} />
                                    )}
                                </>
                            )}
                        </a>
                    )}
                </SidebarMenuButton>
            </SidebarMenuItem>
        );
    };

    if (isSearching && resultCount === 0) {
        return (
            <div className="px-3 py-8 text-center">
                <p className="text-sm font-medium text-slate-600 dark:text-slate-300">
                    {t('No menu items found')}
                </p>
                <p className="mt-1 text-xs text-slate-400">{t('Try a different search term')}</p>
            </div>
        );
    }

    return (
        <nav aria-label={t('Main menu')} className="w-full">
            {isSearching && (
                <p className="mb-1.5 px-2 text-[10px] font-medium text-slate-500 dark:text-slate-400">
                    {resultCount} {t('menu items found')}
                </p>
            )}
            {navGroups.map((group, groupIndex) => (
                <SidebarGroup
                    key={group.id}
                    className={cn(
                        'relative px-1 py-0',
                        groupIndex > 0 && 'z-0 mt-1 border-t border-slate-200/70 pt-1.5 dark:border-slate-700/80'
                    )}
                >
                    {state !== 'collapsed' && (
                        <SidebarGroupLabel className="sidebar-group-label-sticky mb-0.5 px-2 text-[9px] font-bold uppercase tracking-wide text-slate-600 dark:text-slate-300">
                            {t(group.label)}
                        </SidebarGroupLabel>
                    )}
                    <SidebarMenu>{group.items.map((item) => renderMenuItem(item))}</SidebarMenu>
                </SidebarGroup>
            ))}
        </nav>
    );
}
