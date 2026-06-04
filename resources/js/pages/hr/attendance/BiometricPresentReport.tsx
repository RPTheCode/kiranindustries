import React, { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Head, usePage, router } from '@inertiajs/react';
import { 
    Card, 
    CardContent, 
    CardHeader, 
    CardTitle,
    CardDescription
} from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { 
    Select, 
    SelectContent, 
    SelectItem, 
    SelectTrigger, 
    SelectValue 
} from "@/components/ui/select";
import { 
    Download, 
    Filter, 
    Calendar as CalendarIcon,
    FileText,
    Clock
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { format } from 'date-fns';
import { toast } from '@/components/custom-toast';

interface Props {
    branches: Array<{ id: number, name: string }>;
    filters: {
        date: string;
        time_period: string;
        branch_id: string;
    };
}

const BiometricPresentReport = ({ branches, filters }: Props) => {
    const { t } = useTranslation();
    const [loading, setLoading] = useState(false);
    
    const [filterData, setFilterData] = useState({
        date: filters.date || format(new Date(), 'yyyy-MM-dd'),
        time_period: filters.time_period || 'full_day',
        branch_id: filters.branch_id || 'all',
    });

    const handleFilterChange = (key: string, value: string) => {
        setFilterData(prev => ({ ...prev, [key]: value }));
    };

    const handleDownloadPdf = () => {
        const queryParams = new URLSearchParams();
        queryParams.append('date', filterData.date);
        queryParams.append('time_period', filterData.time_period);
        queryParams.append('branch_id', filterData.branch_id);
        
        window.open(`${route('hr.biometric-present-report.pdf')}?${queryParams.toString()}`, '_blank');
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Attendance Management'), href: route('hr.attendance-records.index') },
        { title: t('Biometric Present Report') }
    ];

    return (
        <PageTemplate
            title={t('Biometric Present Report')}
            url="/biometric-present-report"
            breadcrumbs={breadcrumbs}
        >
            <Head title={t('Biometric Present Report')} />
            
            <div className="max-w-4xl mx-auto space-y-6">
                <Card className="border-primary/10 shadow-md">
                    <CardHeader className="bg-primary/5 border-b border-primary/10">
                        <div className="flex items-center gap-2">
                            <FileText className="w-5 h-5 text-primary" />
                            <div>
                                <CardTitle className="text-lg">{t('Generate Attendance snapshot')}</CardTitle>
                                <CardDescription>{t('Download a PDF report of employees present at specific times.')}</CardDescription>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="pt-6">
                        <div className="py-10 flex flex-col items-center justify-center space-y-6">
                            <div className="w-20 h-20 bg-primary/10 rounded-full flex items-center justify-center">
                                <Clock className="w-10 h-10 text-primary" />
                            </div>
                            <div className="text-center space-y-1">
                                <h3 className="text-xl font-bold text-foreground">{t('Today\'s Attendance Snapshot')}</h3>
                                <p className="text-sm text-muted-foreground">{format(new Date(), 'PPPP')}</p>
                            </div>

                            <div className="w-full max-w-sm space-y-2">
                                <Label htmlFor="branch_id" className="text-sm font-medium flex items-center gap-2">
                                    <Filter className="w-4 h-4 text-muted-foreground" />
                                    {t('Select Branch')}
                                </Label>
                                <Select value={filterData.branch_id} onValueChange={(v) => handleFilterChange('branch_id', v)}>
                                    <SelectTrigger id="branch_id" className="h-11 shadow-sm">
                                        <SelectValue placeholder={t('Select Branch')} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">{t('All Branches')}</SelectItem>
                                        {branches.map(branch => (
                                            <SelectItem key={branch.id} value={branch.id.toString()}>{branch.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="mt-10 flex flex-col items-center gap-4 py-6 border-t border-dashed border-muted">
                            <Button 
                                size="lg" 
                                className="w-full md:w-64 h-12 text-md font-bold gap-2 shadow-lg hover:shadow-xl transition-all"
                                onClick={handleDownloadPdf}
                            >
                                <Download className="w-5 h-5" />
                                {t('Download PDF Report')}
                            </Button>
                            <p className="text-xs text-muted-foreground italic text-center">
                                {t('The report will be grouped by department with a two-column name layout.')}
                            </p>
                        </div>
                    </CardContent>
                </Card>

            </div>
        </PageTemplate>
    );
};

export default BiometricPresentReport;
