import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Download, Loader2 } from 'lucide-react';
import { useTranslation } from 'react-i18next';

type PayslipPreviewDialogProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title: string;
    subtitle?: string;
    previewUrl: string | null;
    loading: boolean;
    onLoad: () => void;
    onDownload?: () => void;
    canDownload?: boolean;
};

export function PayslipPreviewDialog({
    open,
    onOpenChange,
    title,
    subtitle,
    previewUrl,
    loading,
    onLoad,
    onDownload,
    canDownload = true,
}: PayslipPreviewDialogProps) {
    const { t } = useTranslation();

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-4xl gap-0 overflow-hidden p-0 sm:max-w-5xl">
                <DialogHeader className="border-b px-6 py-4">
                    <DialogTitle className="text-left">{title}</DialogTitle>
                    {subtitle ? (
                        <p className="text-left text-xs text-slate-500">{subtitle}</p>
                    ) : null}
                </DialogHeader>

                <div className="relative min-h-[min(72vh,680px)] bg-slate-100 dark:bg-slate-900">
                    {loading ? (
                        <div className="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 bg-white/80 dark:bg-slate-950/80">
                            <Loader2 className="h-8 w-8 animate-spin text-primary" />
                            <p className="text-sm text-slate-600">{t('Loading payslip...')}</p>
                        </div>
                    ) : null}
                    {previewUrl ? (
                        <iframe
                            title={title}
                            src={previewUrl}
                            onLoad={onLoad}
                            className="block h-[min(72vh,680px)] w-full border-0 bg-white"
                        />
                    ) : null}
                </div>

                <DialogFooter className="border-t px-6 py-3 sm:justify-between">
                    <p className="text-xs text-slate-500">{t('Preview only — status updates when you download.')}</p>
                    <div className="flex gap-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
                            {t('Close')}
                        </Button>
                        {canDownload && onDownload ? (
                            <Button type="button" onClick={onDownload}>
                                <Download className="mr-2 h-4 w-4" />
                                {t('Download PDF')}
                            </Button>
                        ) : null}
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
