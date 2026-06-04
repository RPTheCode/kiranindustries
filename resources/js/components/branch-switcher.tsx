import { Combobox } from '@/components/ui/combobox';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Check, ChevronDown, MapPin } from 'lucide-react';
import { useTranslation } from 'react-i18next';

type BranchSwitcherProps = {
    variant?: 'default' | 'header';
};

export function BranchSwitcher({ variant = 'default' }: BranchSwitcherProps) {
    const { t } = useTranslation();
    const { auth } = usePage<SharedData & Record<string, unknown>>().props;

    if (!auth.branches || auth.branches.length === 0) {
        return null;
    }

    const onSelect = (branchId: string) => {
        if (!auth.user || !branchId) return;

        router.post(
            route('hr.branches.set-active'),
            { branch_id: branchId },
            {
                preserveScroll: true,
                onSuccess: () => {
                    const url = new URL(window.location.href);
                    let shouldVisit = false;

                    if (url.searchParams.has('branch_id')) {
                        url.searchParams.delete('branch_id');
                        shouldVisit = true;
                    }
                    if (url.searchParams.has('branch')) {
                        url.searchParams.delete('branch');
                        shouldVisit = true;
                    }

                    if (shouldVisit) {
                        const newUrl = url.pathname + (url.search ? url.search : '');
                        router.visit(newUrl, {
                            replace: true,
                            preserveState: false,
                        });
                    }
                },
            }
        );
    };

    const options = auth.branches.map((branch) => ({
        value: branch.id.toString(),
        label: branch.name,
    }));

    const activeValue = auth.active_branch_id?.toString() ?? options[0]?.value ?? '';
    const activeName = options.find((o) => o.value === activeValue)?.label ?? t('Branch');
    const isHeader = variant === 'header';

    if (!isHeader) {
        return (
            <Combobox
                options={options}
                value={activeValue}
                onChange={onSelect}
                placeholder={t('Select Branch')}
                searchPlaceholder={t('Search branch...')}
                className="w-[200px]"
            />
        );
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    className={cn(
                        'header-control h-9 gap-2 border-slate-200 bg-white px-2.5 text-sm font-normal shadow-none',
                        'hover:bg-slate-50 data-[state=open]:border-primary/40 data-[state=open]:bg-white',
                        'dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800',
                        'w-auto max-w-none shrink-0 sm:px-3'
                    )}
                    aria-label={t('Change branch')}
                >
                    <MapPin className="h-3.5 w-3.5 shrink-0 text-primary" aria-hidden />
                    <span className="whitespace-nowrap font-medium text-slate-800 dark:text-slate-100">
                        {activeName}
                    </span>
                    <ChevronDown className="ml-0.5 h-4 w-4 shrink-0 text-slate-400" aria-hidden />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="z-[20001] min-w-[10rem] p-1">
                {options.map((option) => {
                    const selected = option.value === activeValue;
                    return (
                        <DropdownMenuItem
                            key={option.value}
                            className={cn(
                                'flex cursor-pointer items-center justify-between gap-3 rounded-md px-3 py-2 text-sm',
                                selected && 'bg-primary/10 font-medium text-primary'
                            )}
                            onClick={() => onSelect(option.value)}
                        >
                            <span>{option.label}</span>
                            {selected && <Check className="h-4 w-4 shrink-0 text-primary" />}
                        </DropdownMenuItem>
                    );
                })}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
