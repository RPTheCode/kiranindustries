import type { NavItem } from '@/types';

export type NavGroupId =
    | 'overview'
    | 'setup'
    | 'workforce'
    | 'attendance'
    | 'payroll'
    | 'salary-payroll'
    | 'leave'
    | 'reports'
    | 'admin'
    | 'other';

export const NAV_GROUP_ORDER: NavGroupId[] = [
    'overview',
    'setup',
    'workforce',
    'attendance',
    'payroll',
    'salary-payroll',
    'leave',
    'reports',
    'admin',
    'other',
];

export const NAV_GROUP_LABELS: Record<NavGroupId, string> = {
    overview: 'Overview',
    setup: 'Setup & Masters',
    workforce: 'Workforce',
    attendance: 'Attendance',
    payroll: 'Payroll',
    'salary-payroll': 'Salary Payroll',
    leave: 'Leave',
    reports: 'Reports',
    admin: 'Administration',
    other: 'More',
};

/** Normalize URL/path for comparison (no query, no trailing slash). */
export function normalizeNavPath(url: string): string {
    let path = url.split('?')[0].split('#')[0];

    if (path.startsWith('http')) {
        try {
            path = new URL(path).pathname;
        } catch {
            // keep path as-is
        }
    }

    if (!path.startsWith('/')) {
        path = `/${path}`;
    }

    return path.replace(/\/+$/, '') || '/';
}

function hrefMatchesPath(href: string, currentPath: string): boolean {
    const itemPath = normalizeNavPath(href);

    if (currentPath === itemPath) {
        return true;
    }

    if (!currentPath.startsWith(`${itemPath}/`)) {
        return false;
    }

    // Avoid /hr/employees matching unrelated /hr/employee-* siblings (longest-match handles most cases).
    const nextSegment = currentPath.slice(itemPath.length + 1).split('/')[0];
    const itemLastSegment = itemPath.split('/').filter(Boolean).pop() ?? '';

    if (itemLastSegment === 'employees' && nextSegment.startsWith('employee') && nextSegment !== 'employees') {
        return false;
    }

    return true;
}

function itemMatchesCurrent(item: NavItem, currentPath: string): boolean {
    if (item.routePattern && typeof route === 'function') {
        try {
            if (route().current(item.routePattern)) {
                return true;
            }
        } catch {
            // ignore invalid patterns
        }
    }

    if (item.href && hrefMatchesPath(item.href, currentPath)) {
        return true;
    }

    return false;
}

/** Collect every href in the tree (for longest-prefix active resolution). */
export function flattenNavItems(items: NavItem[]): NavItem[] {
    const flat: NavItem[] = [];

    const walk = (list: NavItem[]) => {
        list.forEach((item) => {
            flat.push(item);
            if (item.children?.length) {
                walk(item.children);
            }
        });
    };

    walk(items);

    return flat;
}

/**
 * Pick the single best-matching nav item for the current URL (longest path wins).
 */
export function resolveActiveNavHref(items: NavItem[], currentUrl: string): string | null {
    const currentPath = normalizeNavPath(currentUrl);
    const flat = flattenNavItems(items);

    let best: { href: string; length: number } | null = null;

    flat.forEach((item) => {
        if (!item.href) {
            return;
        }

        if (!itemMatchesCurrent(item, currentPath)) {
            return;
        }

        const path = normalizeNavPath(item.href);
        if (!best || path.length > best.length) {
            best = { href: item.href, length: path.length };
        }
    });

    return best?.href ?? null;
}

export function isNavItemActive(item: NavItem, activeHref: string | null, currentUrl: string): boolean {
    if (item.href && activeHref) {
        return normalizeNavPath(item.href) === normalizeNavPath(activeHref);
    }

    if (item.routePattern && typeof route === 'function') {
        try {
            return route().current(item.routePattern);
        } catch {
            return false;
        }
    }

    if (item.href && !activeHref) {
        return itemMatchesCurrent(item, normalizeNavPath(currentUrl));
    }

    return false;
}

export function isNavBranchActive(children: NavItem[] | undefined, activeHref: string | null, currentUrl: string): boolean {
    if (!children?.length) {
        return false;
    }

    return children.some(
        (child) =>
            isNavItemActive(child, activeHref, currentUrl) ||
            isNavBranchActive(child.children, activeHref, currentUrl)
    );
}

/** Filter navigation tree by title and optional keywords (sidebar search). */
export function filterNavItems(items: NavItem[], query: string): NavItem[] {
    if (!query) {
        return items;
    }

    const lowerQuery = query.toLowerCase();

    const itemMatches = (item: NavItem) =>
        item.title.toLowerCase().includes(lowerQuery)
        || item.keywords?.some((keyword) => keyword.toLowerCase().includes(lowerQuery));

    return items.reduce((acc: NavItem[], item) => {
        if (itemMatches(item)) {
            acc.push({ ...item, defaultOpen: true });
            return acc;
        }

        if (item.children) {
            const filteredChildren = filterNavItems(item.children, query);
            if (filteredChildren.length > 0) {
                acc.push({
                    ...item,
                    children: filteredChildren,
                    defaultOpen: true,
                });
            }
        }

        return acc;
    }, []);
}

const EMPLOYEE_ROLES = new Set(['employee']);

function isEmployeePriorityNavItem(item: NavItem): boolean {
    if (!item.href) {
        return false;
    }

    const path = normalizeNavPath(item.href);

    return (
        path.includes('/my-profile')
        || path.includes('/payslips')
        || path.includes('/leave-applications')
        || path.includes('/leave-balances')
        || path.includes('/attendance')
    );
}

function reorderChildrenForEmployee(children: NavItem[]): NavItem[] {
    return [...children]
        .map((child) => ({
            ...child,
            children: child.children ? reorderChildrenForEmployee(child.children) : undefined,
        }))
        .sort((a, b) => {
            const aFirst = isEmployeePriorityNavItem(a) ? 0 : 1;
            const bFirst = isEmployeePriorityNavItem(b) ? 0 : 1;
            return aFirst - bFirst;
        });
}

/** Prioritize employee-facing links within each group without hiding admin items. */
export function reorderNavForRole(items: NavItem[], userRole?: string): NavItem[] {
    if (!userRole || !EMPLOYEE_ROLES.has(userRole)) {
        return items;
    }

    return items.map((item) => ({
        ...item,
        children: item.children ? reorderChildrenForEmployee(item.children) : undefined,
    }));
}

export function groupNavItems(items: NavItem[]): { id: NavGroupId; label: string; items: NavItem[] }[] {
    const buckets = new Map<NavGroupId, NavItem[]>();

    items.forEach((item) => {
        const id = (item.group as NavGroupId) || 'other';
        if (!buckets.has(id)) {
            buckets.set(id, []);
        }
        buckets.get(id)!.push(item);
    });

    return NAV_GROUP_ORDER.filter((id) => buckets.has(id)).map((id) => ({
        id,
        label: NAV_GROUP_LABELS[id],
        items: buckets.get(id)!,
    }));
}
