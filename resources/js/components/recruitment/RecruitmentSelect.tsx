import { Combobox } from '@/components/ui/combobox';
import { cn } from '@/lib/utils';

export type RecruitmentSelectOption = {
    value: string;
    label: string;
};

export function toSelectOptions(
    items: { id: number | string; name: string }[],
    labelFn?: (item: { id: number | string; name: string }) => string
): RecruitmentSelectOption[] {
    return items.map((item) => ({
        value: String(item.id),
        label: labelFn ? labelFn(item) : item.name,
    }));
}

export function RecruitmentSelect({
    options,
    value,
    onValueChange,
    placeholder,
    searchPlaceholder,
    emptyText,
    disabled,
    className,
}: {
    options: RecruitmentSelectOption[];
    value: string;
    onValueChange: (value: string) => void;
    placeholder?: string;
    searchPlaceholder?: string;
    emptyText?: string;
    disabled?: boolean;
    className?: string;
}) {
    return (
        <Combobox
            options={options}
            value={value}
            onChange={onValueChange}
            placeholder={placeholder}
            searchPlaceholder={searchPlaceholder ?? placeholder}
            emptyText={emptyText}
            disabled={disabled}
            className={cn('h-10 bg-white dark:bg-slate-950', className)}
        />
    );
}
