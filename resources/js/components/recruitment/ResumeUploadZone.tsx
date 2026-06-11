import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Upload, FileText, ExternalLink } from 'lucide-react';
import { useRef } from 'react';

export function ResumeUploadZone({
    label,
    currentUrl,
    onFileSelect,
    accept = '.pdf,.doc,.docx',
}: {
    label: string;
    currentUrl?: string | null;
    onFileSelect: (file: File) => void;
    accept?: string;
}) {
    const inputRef = useRef<HTMLInputElement>(null);

    return (
        <div className="rounded-xl border border-dashed border-slate-200 bg-slate-50/50 p-4 dark:border-slate-700">
            <Label className="text-xs font-medium text-slate-600">{label}</Label>
            {currentUrl ? (
                <div className="mt-2 flex items-center gap-2">
                    <FileText className="h-4 w-4 text-emerald-600" />
                    <a
                        href={currentUrl}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-xs text-primary hover:underline"
                    >
                        View current file
                    </a>
                    <ExternalLink className="h-3 w-3 text-slate-400" />
                </div>
            ) : (
                <p className="mt-1 text-[11px] text-slate-400">No file uploaded yet</p>
            )}
            <input
                ref={inputRef}
                type="file"
                accept={accept}
                className="hidden"
                onChange={(e) => {
                    const file = e.target.files?.[0];
                    if (file) onFileSelect(file);
                }}
            />
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="mt-3"
                onClick={() => inputRef.current?.click()}
            >
                <Upload className="mr-1.5 h-3.5 w-3.5" />
                Upload
            </Button>
        </div>
    );
}
