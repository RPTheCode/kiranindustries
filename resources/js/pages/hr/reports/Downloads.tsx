import React, { useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Download, RefreshCw, AlertCircle, FileText, CheckCircle2, Clock } from 'lucide-react';
import { format } from 'date-fns';

export default function Downloads({ downloads }: { downloads: any[] }) {
    
    // Auto refresh every 10 seconds to check job status
    useEffect(() => {
        const interval = setInterval(() => {
            router.reload({ only: ['downloads'] });
        }, 10000);
        return () => clearInterval(interval);
    }, []);

    const getStatusIcon = (status: string) => {
        switch (status) {
            case 'completed': return <CheckCircle2 className="w-5 h-5 text-emerald-500" />;
            case 'processing': return <RefreshCw className="w-5 h-5 text-blue-500 animate-spin" />;
            case 'failed': return <AlertCircle className="w-5 h-5 text-red-500" />;
            default: return <Clock className="w-5 h-5 text-orange-500" />;
        }
    };

    const getStatusText = (status: string) => {
        switch (status) {
            case 'completed': return 'Ready to Download';
            case 'processing': return 'Generating PDF...';
            case 'failed': return 'Failed';
            default: return 'In Queue';
        }
    };

    return (
        <AppLayout>
            <Head title="Report Downloads" />
            
            <div className="p-6 max-w-[1200px] mx-auto space-y-6">
                <div className="flex items-center justify-between bg-white p-4 rounded-xl border shadow-sm">
                    <div className="flex items-center gap-4">
                        <div className="p-2.5 rounded-lg shadow-lg bg-slate-900">
                            <Download className="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-slate-900">Background Downloads</h1>
                            <p className="text-slate-500 text-sm">View and download your massive background-generated reports.</p>
                        </div>
                    </div>
                    <Button onClick={() => router.reload({ only: ['downloads'] })} variant="outline" size="sm" className="gap-2">
                        <RefreshCw className="w-4 h-4" />
                        Refresh List
                    </Button>
                </div>

                <Card className="shadow-sm border-slate-200 bg-white">
                    <CardHeader className="pb-3 border-b bg-slate-50/50">
                        <CardTitle className="text-sm font-black flex items-center gap-2 text-slate-600 uppercase tracking-widest">
                            <FileText className="w-4 h-4" />
                            Recent Downloads
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm text-left">
                                <thead className="bg-slate-50 text-slate-400 text-[10px] uppercase font-black border-b tracking-widest">
                                    <tr>
                                        <th className="px-6 py-4">Report Name</th>
                                        <th className="px-6 py-4">Requested At</th>
                                        <th className="px-6 py-4">Status</th>
                                        <th className="px-6 py-4">Error (if any)</th>
                                        <th className="px-6 py-4 text-right">Action</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {downloads && downloads.length > 0 ? downloads.map((item) => (
                                        <tr key={item.id} className="hover:bg-slate-50/50 transition-colors">
                                            <td className="px-6 py-4 font-bold text-slate-700">{item.report_name}</td>
                                            <td className="px-6 py-4 text-slate-500 text-xs">
                                                {format(new Date(item.created_at), 'dd MMM yyyy, hh:mm a')}
                                            </td>
                                            <td className="px-6 py-4">
                                                <div className="flex items-center gap-2">
                                                    {getStatusIcon(item.status)}
                                                    <span className={`text-xs font-bold ${
                                                        item.status === 'completed' ? 'text-emerald-600' :
                                                        item.status === 'processing' ? 'text-blue-600' :
                                                        item.status === 'failed' ? 'text-red-600' : 'text-orange-600'
                                                    }`}>
                                                        {getStatusText(item.status)}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 text-xs text-red-500 font-mono max-w-xs truncate">
                                                {item.error_message || '-'}
                                            </td>
                                            <td className="px-6 py-4 text-right">
                                                {item.status === 'completed' && (
                                                    <a 
                                                        href={`/reports/downloads/${item.id}`} 
                                                        className="inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-bold text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition-colors shadow-sm"
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        <Download className="w-3 h-3" />
                                                        Download PDF
                                                    </a>
                                                )}
                                                {item.status === 'processing' && (
                                                    <span className="text-xs text-slate-400 font-medium italic">Please wait...</span>
                                                )}
                                            </td>
                                        </tr>
                                    )) : (
                                        <tr>
                                            <td colSpan={5} className="px-6 py-20 text-center">
                                                <div className="flex flex-col items-center justify-center space-y-3">
                                                    <div className="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center">
                                                        <FileText className="w-8 h-8 text-slate-300" />
                                                    </div>
                                                    <p className="text-slate-400 font-medium tracking-tight">No background downloads found.</p>
                                                </div>
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
