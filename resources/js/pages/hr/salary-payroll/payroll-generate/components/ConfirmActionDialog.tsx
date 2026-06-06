import { Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';

type ConfirmVariant = 'primary' | 'destructive' | 'warning';

interface ConfirmActionDialogProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  title: string;
  description: string;
  confirmLabel: string;
  cancelLabel?: string;
  variant?: ConfirmVariant;
  icon?: React.ReactNode;
  loading?: boolean;
  onConfirm: () => void;
}

const variantStyles: Record<ConfirmVariant, { ring: string; iconBg: string; button: string }> = {
  primary: {
    ring: 'border-primary/20',
    iconBg: 'bg-primary/10 text-primary',
    button: '',
  },
  destructive: {
    ring: 'border-red-200',
    iconBg: 'bg-red-50 text-red-600',
    button: 'bg-red-600 hover:bg-red-700',
  },
  warning: {
    ring: 'border-amber-200',
    iconBg: 'bg-amber-50 text-amber-600',
    button: 'bg-amber-600 hover:bg-amber-700',
  },
};

export function ConfirmActionDialog({
  open,
  onOpenChange,
  title,
  description,
  confirmLabel,
  cancelLabel = 'Cancel',
  variant = 'primary',
  icon,
  loading = false,
  onConfirm,
}: ConfirmActionDialogProps) {
  const styles = variantStyles[variant];

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className={cn('sm:max-w-md', styles.ring)}>
        <DialogHeader className="space-y-3">
          {icon && (
            <div className={cn('flex h-12 w-12 items-center justify-center rounded-full', styles.iconBg)}>
              {icon}
            </div>
          )}
          <DialogTitle className="text-lg">{title}</DialogTitle>
          <DialogDescription className="text-sm leading-relaxed text-slate-600">
            {description}
          </DialogDescription>
        </DialogHeader>
        <DialogFooter className="gap-2 sm:gap-0">
          <Button variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
            {cancelLabel}
          </Button>
          <Button className={styles.button} onClick={onConfirm} disabled={loading}>
            {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            {confirmLabel}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
