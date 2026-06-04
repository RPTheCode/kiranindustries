import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbLink,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';
import { cn } from '@/lib/utils';
import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { Fragment } from 'react';

type BreadcrumbEntry = { label: string; href?: string };

export function Breadcrumbs({
    items,
    variant = 'default',
}: {
    items: BreadcrumbEntry[];
    variant?: 'default' | 'header';
}) {
    if (!items || items.length === 0) {
        return null;
    }

    const isHeader = variant === 'header';
    const lastItem = items[items.length - 1];

    if (isHeader && items.length === 1) {
        return (
            <h1 className="truncate text-[15px] font-semibold text-slate-900 dark:text-slate-50 sm:text-base">
                {lastItem.label}
            </h1>
        );
    }

    return (
        <Breadcrumb className={cn(isHeader && 'min-w-0 max-w-full overflow-hidden')}>
            <BreadcrumbList
                className={cn(
                    'flex-nowrap items-center gap-1 overflow-x-auto',
                    isHeader ? 'max-w-full text-xs text-slate-500 sm:text-sm' : 'text-muted-foreground text-sm'
                )}
            >
                {items.map((item, index) => {
                    const isLast = index === items.length - 1;
                    return (
                        <Fragment key={`${item.label}-${index}`}>
                            <BreadcrumbItem className="shrink-0">
                                {isLast ? (
                                    <BreadcrumbPage
                                        className={cn(
                                            'max-w-[8rem] truncate font-medium text-slate-700 sm:max-w-[12rem] dark:text-slate-200',
                                            isHeader && 'font-semibold text-slate-900 dark:text-slate-50'
                                        )}
                                    >
                                        {item.label}
                                    </BreadcrumbPage>
                                ) : (
                                    <BreadcrumbLink
                                        asChild
                                        className={cn(
                                            'max-w-[6rem] truncate sm:max-w-[9rem]',
                                            isHeader && 'text-slate-500 hover:text-primary'
                                        )}
                                    >
                                        <Link href={item.href || '#'}>{item.label}</Link>
                                    </BreadcrumbLink>
                                )}
                            </BreadcrumbItem>
                            {!isLast && (
                                <BreadcrumbSeparator className="shrink-0 [&>svg]:size-3.5 text-slate-300">
                                    <ChevronRight />
                                </BreadcrumbSeparator>
                            )}
                        </Fragment>
                    );
                })}
            </BreadcrumbList>
        </Breadcrumb>
    );
}
