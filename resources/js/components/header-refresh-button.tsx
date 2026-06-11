import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import { RefreshCw } from 'lucide-react';
import { useTranslation } from 'react-i18next';

type HeaderRefreshButtonProps = {
    onClick: () => void;
    isLoading?: boolean;
    label?: string;
    className?: string;
};

export function HeaderRefreshButton({
    onClick,
    isLoading = false,
    label,
    className,
}: HeaderRefreshButtonProps) {
    const { t } = useTranslation();
    const ariaLabel = label ?? t('Refresh');

    return (
        <TooltipProvider delayDuration={300}>
            <Tooltip>
                <TooltipTrigger asChild>
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className={cn(
                            'h-8 w-8 shrink-0 text-slate-600 hover:bg-white hover:text-slate-900',
                            'dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-slate-50',
                            className
                        )}
                        onClick={onClick}
                        aria-label={ariaLabel}
                        disabled={isLoading}
                    >
                        <RefreshCw className={cn('h-4 w-4', isLoading && 'animate-spin')} />
                    </Button>
                </TooltipTrigger>
                <TooltipContent side="bottom">{ariaLabel}</TooltipContent>
            </Tooltip>
        </TooltipProvider>
    );
}
