import type { NavItem } from '@/types';
import { groupNavItems, normalizeNavPath, type NavGroupId } from '@/lib/nav-utils';

export const MAX_DASHBOARD_SHORTCUTS = 8;

export interface ShortcutLeaf {
    id: string;
    title: string;
    href: string;
    breadcrumb?: string;
    keywords?: string[];
}

export interface ShortcutLeafGroup {
    id: NavGroupId;
    label: string;
    leaves: ShortcutLeaf[];
}

export function shortcutIdFromHref(href: string): string {
    const [pathPart, queryPart] = href.split('?');
    const path = normalizeNavPath(pathPart);

    return queryPart ? `${path}?${queryPart}` : path;
}

/** Collect leaf menu items (with href) from the navigation tree. */
export function flattenNavLeaves(items: NavItem[], ancestors: string[] = []): ShortcutLeaf[] {
    const leaves: ShortcutLeaf[] = [];

    items.forEach((item) => {
        const trail = [...ancestors, item.title];

        if (item.href) {
            leaves.push({
                id: shortcutIdFromHref(item.href),
                title: item.title,
                href: item.href,
                breadcrumb: ancestors.length > 0 ? ancestors.join(' › ') : undefined,
                keywords: item.keywords,
            });
        }

        if (item.children?.length) {
            leaves.push(...flattenNavLeaves(item.children, trail));
        }
    });

    const seen = new Set<string>();

    return leaves.filter((leaf) => {
        if (seen.has(leaf.id)) {
            return false;
        }

        seen.add(leaf.id);
        return true;
    });
}

export function resolveShortcutLeaves(
    savedIds: string[],
    availableLeaves: ShortcutLeaf[],
): ShortcutLeaf[] {
    const byId = new Map(availableLeaves.map((leaf) => [leaf.id, leaf]));

    return savedIds
        .map((id) => byId.get(id))
        .filter((leaf): leaf is ShortcutLeaf => Boolean(leaf));
}

/** Shorter label for compact tiles; full title stays in tooltip. */
const SHORT_TITLE_MAP: Record<string, string> = {
    'Leave Applications': 'Leave',
    'Leave Balances': 'Balances',
    'Leave Types': 'Types',
    'Leave Policies': 'Policies',
    'Leave Management': 'Leave',
    'Run Monthly Payroll': 'Payroll',
    'ESSL Device Sync': 'ESSL',
    'Employee Salary': 'Salary',
    'Salary Increments': 'Increment',
    'Salary Advance': 'Advance',
    'Salary Loan': 'Loan',
    'Payroll Settings': 'Settings',
    'Earning / Deduction': 'Earning',
    'Attendance & Bio-Sync': 'Attendance',
    'Missed Punch': 'Mispunch',
    'Production Entry': 'Production',
    'Company Setup': 'Setup',
    'Salary Component Master': 'Components',
    'Deduction Master': 'Deduction',
    'Bank Masters': 'Banks',
    'Material Items': 'Material',
    'Resign Reasons': 'Resign',
    'Document Types': 'Documents',
    'Offers & Selection': 'Offers',
    'Staff & Security': 'Security',
    'Activity Logs': 'Logs',
    'Media Library': 'Media',
    'Attendance Reports': 'Reports',
    'Monthly Reports': 'Monthly',
    'Master Reports': 'Master',
};

export function getShortcutShortLabel(title: string): string {
    if (SHORT_TITLE_MAP[title]) {
        return SHORT_TITLE_MAP[title];
    }

    const firstWord = title.split(/\s+/)[0] ?? title;

    if (firstWord.length <= 10) {
        return firstWord;
    }

    return `${title.slice(0, 9)}…`;
}

export function isShortcutLeafActive(leaf: ShortcutLeaf, currentUrl: string): boolean {
    const currentPath = normalizeNavPath(currentUrl.split('?')[0]);
    const leafPath = normalizeNavPath(leaf.href.split('?')[0]);

    if (currentPath !== leafPath) {
        if (leafPath === '/dashboard') {
            return false;
        }

        return currentPath.startsWith(`${leafPath}/`);
    }

    const leafQuery = leaf.href.includes('?') ? leaf.href.split('?')[1] : null;
    const currentQuery = currentUrl.includes('?') ? currentUrl.split('?')[1] : null;

    if (leafQuery) {
        return leafQuery === currentQuery;
    }

    return true;
}

/** Stable id for drag-and-drop libraries (no special characters). */
export function shortcutDragId(id: string, prefix = ''): string {
    return `${prefix}${id.replace(/[^a-zA-Z0-9_-]/g, '_')}`;
}

export function reorderShortcutIds(ids: string[], fromIndex: number, toIndex: number): string[] {
    const next = [...ids];
    const [moved] = next.splice(fromIndex, 1);
    next.splice(toIndex, 0, moved);

    return next;
}

/** Group shortcut leaves by sidebar section (Overview, Workforce, etc.). */
export function groupShortcutLeavesByNavSection(navItems: NavItem[]): ShortcutLeafGroup[] {
    return groupNavItems(navItems)
        .map((group) => ({
            id: group.id,
            label: group.label,
            leaves: flattenNavLeaves(group.items),
        }))
        .filter((group) => group.leaves.length > 0);
}

export function filterShortcutLeafGroups(
    groups: ShortcutLeafGroup[],
    query: string,
): ShortcutLeafGroup[] {
    const term = query.trim();

    if (!term) {
        return groups;
    }

    return groups
        .map((group) => ({
            ...group,
            leaves: filterShortcutLeaves(group.leaves, term),
        }))
        .filter((group) => group.leaves.length > 0);
}

export function filterShortcutLeaves(leaves: ShortcutLeaf[], query: string): ShortcutLeaf[] {
    const term = query.trim().toLowerCase();

    if (!term) {
        return leaves;
    }

    return leaves.filter((leaf) => {
        if (leaf.title.toLowerCase().includes(term)) {
            return true;
        }

        if (leaf.breadcrumb?.toLowerCase().includes(term)) {
            return true;
        }

        return leaf.keywords?.some((keyword) => keyword.toLowerCase().includes(term));
    });
}
