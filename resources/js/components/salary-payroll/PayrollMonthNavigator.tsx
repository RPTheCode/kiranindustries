import { cn } from '@/lib/utils';

export type PayrollMonthOption = {
    value: string;
    label: string;
    short_label: string;
    year: number;
    has_payroll?: boolean;
    employee_count?: number;
};

type PayrollMonthNavigatorProps = {
    months: PayrollMonthOption[];
    selectedMonth: string;
    onSelect: (monthYear: string) => void;
    className?: string;
};

export function PayrollMonthNavigator({ months, selectedMonth, onSelect, className }: PayrollMonthNavigatorProps) {
    return (
        <div
            className={cn(
                'grid grid-cols-4 gap-1.5 sm:grid-cols-6 xl:grid-cols-12',
                className
            )}
        >
            {months.map((month) => {
                const isActive = month.value === selectedMonth;
                const count = month.employee_count ?? 0;

                return (
                    <button
                        key={month.value}
                        type="button"
                        onClick={() => onSelect(month.value)}
                        className={cn(
                            'relative flex w-full flex-col items-center justify-center rounded-lg border px-1 py-1.5 text-center transition-all sm:py-2',
                            isActive
                                ? 'border-primary bg-primary text-primary-foreground shadow-sm'
                                : 'border-slate-200 bg-white text-slate-700 hover:border-primary/40 hover:bg-primary/5 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:border-primary/50 dark:hover:bg-primary/10'
                        )}
                    >
                        <span className="block text-[10px] font-semibold uppercase leading-tight tracking-wide sm:text-xs">
                            {month.short_label}
                        </span>
                        <span
                            className={cn(
                                'block text-[9px] leading-tight sm:text-[10px]',
                                isActive ? 'text-primary-foreground/80' : 'text-slate-400'
                            )}
                        >
                            {month.year}
                        </span>
                        {count > 0 ? (
                            <span
                                className={cn(
                                    'mt-0.5 inline-block rounded-full px-1 py-px text-[8px] font-bold leading-none sm:text-[9px]',
                                    isActive
                                        ? 'bg-primary-foreground/20 text-primary-foreground'
                                        : 'bg-primary/10 text-primary dark:bg-primary/20'
                                )}
                            >
                                {count}
                            </span>
                        ) : month.has_payroll ? (
                            <span
                                className={cn(
                                    'absolute right-1 top-1 h-1.5 w-1.5 rounded-full',
                                    isActive ? 'bg-primary-foreground' : 'bg-primary'
                                )}
                            />
                        ) : null}
                    </button>
                );
            })}
        </div>
    );
}
