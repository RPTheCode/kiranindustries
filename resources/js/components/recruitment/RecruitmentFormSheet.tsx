import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { Loader2 } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { useTranslation } from 'react-i18next';

export function FormSection({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="rounded-xl border border-slate-200/80 bg-slate-50/40 p-4 dark:border-slate-800 dark:bg-slate-900/30">
            <p className="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</p>
            <div className="space-y-4">{children}</div>
        </div>
    );
}

export function FormField({
    label,
    required,
    error,
    hint,
    children,
    className,
}: {
    label: string;
    required?: boolean;
    error?: string;
    hint?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <div className={cn('space-y-1.5', className)}>
            <Label className="text-xs font-medium text-slate-700 dark:text-slate-300">
                {label}
                {required ? <span className="ml-0.5 text-rose-500">*</span> : null}
            </Label>
            {children}
            {hint && !error ? <p className="text-[11px] text-slate-400">{hint}</p> : null}
            {error ? <p className="text-[11px] font-medium text-rose-600">{error}</p> : null}
        </div>
    );
}

type RecruitmentFormSheetProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    description?: string;
    onSubmit: (e: FormEvent) => void;
    processing?: boolean;
    submitLabel?: string;
    children: ReactNode;
    size?: 'md' | 'lg';
};

export function RecruitmentFormSheet({
    open,
    onOpenChange,
    title,
    description,
    onSubmit,
    processing,
    submitLabel,
    children,
    size = 'md',
}: RecruitmentFormSheetProps) {
    const { t } = useTranslation();

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                className={cn(
                    'flex h-full flex-col gap-0 p-0 sm:max-w-md',
                    size === 'lg' && 'sm:max-w-lg'
                )}
            >
                <SheetHeader className="border-b border-slate-200 px-6 py-5 text-left dark:border-slate-800">
                    <SheetTitle className="text-lg font-semibold">{title}</SheetTitle>
                    {description ? (
                        <SheetDescription className="text-xs text-slate-500">{description}</SheetDescription>
                    ) : null}
                </SheetHeader>

                <form onSubmit={onSubmit} className="flex min-h-0 flex-1 flex-col">
                    <div className="flex-1 space-y-5 overflow-y-auto px-6 py-5">{children}</div>

                    <SheetFooter className="flex-row gap-2 border-t border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-950">
                        <Button type="button" variant="outline" className="flex-1" onClick={() => onOpenChange(false)}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" className="flex-1" disabled={processing}>
                            {processing ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                            {submitLabel ?? t('Save')}
                        </Button>
                    </SheetFooter>
                </form>
            </SheetContent>
        </Sheet>
    );
}
