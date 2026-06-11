import { Breadcrumbs } from '@/components/breadcrumbs';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';
import { SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';

type HeaderTitleBlockProps = {
    breadcrumbs: BreadcrumbItemType[];
};

export function HeaderTitleBlock({ breadcrumbs }: HeaderTitleBlockProps) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData>().props;

    const subtitle = useMemo(() => {
        const dateStr = format(new Date(), 'EEE, d MMM yyyy');
        const activeBranchId = auth.active_branch_id;
        const branchName =
            activeBranchId != null
                ? auth.branches?.find((b) => b.id === activeBranchId)?.name
                : null;

        if (branchName) {
            return `${dateStr} · ${branchName}`;
        }

        if (auth.branches && auth.branches.length > 1) {
            return `${dateStr} · ${t('All branches')}`;
        }

        return dateStr;
    }, [auth.active_branch_id, auth.branches, t]);

    if (!breadcrumbs.length) {
        return null;
    }

    const items = breadcrumbs.map((b) => ({ label: b.title, href: b.href }));
    const isSingle = breadcrumbs.length === 1;

    return (
        <div className="min-w-0 flex-1">
            {isSingle ? (
                <h1 className="truncate text-base font-semibold leading-tight text-slate-900 dark:text-slate-50">
                    {breadcrumbs[0].title}
                </h1>
            ) : (
                <Breadcrumbs variant="header" items={items} />
            )}
            <p className="mt-0.5 truncate text-[11px] leading-tight text-slate-500 sm:text-xs dark:text-slate-400">
                {subtitle}
            </p>
        </div>
    );
}
