import type { NavItem } from '@/types';

export function countNavLeaves(items: NavItem[]): number {
    let count = 0;

    const walk = (list: NavItem[]) => {
        list.forEach((item) => {
            if (item.href) {
                count += 1;
            }
            if (item.children?.length) {
                walk(item.children);
            }
        });
    };

    walk(items);

    return count;
}

export function highlightNavTitle(title: string, query: string) {
    const trimmed = query.trim();

    if (!trimmed) {
        return title;
    }

    const lowerTitle = title.toLowerCase();
    const lowerQuery = trimmed.toLowerCase();
    const index = lowerTitle.indexOf(lowerQuery);

    if (index === -1) {
        return title;
    }

    const before = title.slice(0, index);
    const match = title.slice(index, index + trimmed.length);
    const after = title.slice(index + trimmed.length);

    return (
        <>
            {before}
            <mark className="rounded-sm bg-primary/15 px-0.5 font-semibold text-primary">{match}</mark>
            {after}
        </>
    );
}
