import React, { useState } from 'react';
import { Modal } from '@/components/ui/modal';
import { useTranslation } from 'react-i18next';
import { Calendar, FileSpreadsheet, FileText, X } from 'lucide-react';

interface ExportSummaryModalProps {
    isOpen: boolean;
    onClose: () => void;
}

export function ExportSummaryModal({ isOpen, onClose }: ExportSummaryModalProps) {
    const { t } = useTranslation();
    const now = new Date();
    
    const [dateFrom, setDateFrom] = useState(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`);
    const [dateTo, setDateTo] = useState(`${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${new Date(now.getFullYear(), now.getMonth() + 1, 0).getDate()}`);

    const handleExport = (format: 'excel' | 'pdf') => {
        // Construct the URL and redirect
        const url = new URL(window.location.origin + '/payroll-runs/export-summary');
        url.searchParams.append('date_from', dateFrom);
        url.searchParams.append('date_to', dateTo);
        url.searchParams.append('format', format);
        
        window.location.href = url.toString();
        onClose();
    };

    return (
        <Modal isOpen={isOpen} onClose={onClose} size="md" title={t('Export Monthly Summary')}>
            <div className="p-4">
                <div className="mb-6 space-y-4">
                    <p className="text-sm text-slate-500">
                        {t('Select the date range for the payroll summary report. You can export this report as an Excel spreadsheet or a PDF document.')}
                    </p>
                    
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">{t('Date From')}</label>
                            <input 
                                type="date" 
                                value={dateFrom}
                                onChange={(e) => setDateFrom(e.target.value)}
                                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                        </div>
                        <div>
                            <label className="block text-xs font-medium text-slate-700 mb-1">{t('Date To')}</label>
                            <input 
                                type="date" 
                                value={dateTo}
                                onChange={(e) => setDateTo(e.target.value)}
                                className="w-full rounded-md border border-slate-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                        </div>
                    </div>
                </div>

                <div className="flex gap-3 pt-4 border-t border-slate-100">
                    <button 
                        onClick={() => handleExport('excel')}
                        className="flex-1 flex items-center justify-center gap-2 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors"
                    >
                        <FileSpreadsheet className="w-4 h-4" />
                        {t('Download Excel')}
                    </button>
                    <button 
                        onClick={() => handleExport('pdf')}
                        className="flex-1 flex items-center justify-center gap-2 bg-red-50 text-red-700 hover:bg-red-100 px-4 py-2.5 rounded-lg text-sm font-medium transition-colors"
                    >
                        <FileText className="w-4 h-4" />
                        {t('Download PDF')}
                    </button>
                </div>
            </div>
        </Modal>
    );
}
