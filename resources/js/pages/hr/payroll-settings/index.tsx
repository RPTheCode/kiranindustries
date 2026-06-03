import React, { useEffect, useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from '@/components/custom-toast';
import { Save, Plus, Trash2, IndianRupee, Percent, Calendar, ShieldCheck, Briefcase } from 'lucide-react';

export default function PayrollSettings() {
    const { t } = useTranslation();
    const {
        parameters,
        ptSlabs: initialPtSlabs,
        itSlabs: initialItSlabs,
        selectedFinancialYear,
        defaultFinancialYear,
        nextFinancialYear,
        financialYearOptions = [],
    } = usePage().props as any;

    const yearOptionLabel = (year: string) => {
        if (year === defaultFinancialYear) {
            return `${year} (${t('Current')})`;
        }
        if (year === nextFinancialYear) {
            return `${year} (${t('Next')})`;
        }
        return year;
    };

    const [financialYear, setFinancialYear] = useState(selectedFinancialYear);
    const [formParams, setFormParams] = useState({ ...parameters });
    const [ptSlabs, setPtSlabs] = useState([...initialPtSlabs]);
    const [itSlabs, setItSlabs] = useState([...initialItSlabs]);

    useEffect(() => {
        setFinancialYear(selectedFinancialYear);
        setFormParams({ ...parameters, financial_year: selectedFinancialYear });
        setPtSlabs([...initialPtSlabs]);
        setItSlabs([...initialItSlabs]);
    }, [selectedFinancialYear, parameters, initialPtSlabs, initialItSlabs]);

    const changeFinancialYear = (year: string) => {
        if (year === financialYear) {
            return;
        }
        router.get(
            route('hr.payroll-settings.index'),
            { financial_year: year },
            { preserveState: false, replace: true }
        );
    };

    const handleParamChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const { name, value } = e.target;
        setFormParams(prev => {
            const newState = { ...prev, [name]: value };
            
            // Auto-calculate Total PF % if PF or FPF changes
            if (name === 'pf_pct' || name === 'fpf_pct') {
                const pfStr = name === 'pf_pct' ? value : String(prev.pf_pct ?? '');
                const fpfStr = name === 'fpf_pct' ? value : String(prev.fpf_pct ?? '');
                if (pfStr === '' && fpfStr === '') {
                    newState.total_pf_pct = '';
                } else {
                    const pf = parseFloat(pfStr) || 0;
                    const fpf = parseFloat(fpfStr) || 0;
                    newState.total_pf_pct = (pf + fpf).toFixed(2);
                }
            }
            
            return newState;
        });
    };

    const saveParameters = () => {
        router.post(
            route('hr.payroll-settings.parameters.update'),
            { ...formParams, financial_year: financialYear },
            {
                onSuccess: () => toast.success(t('Payroll parameters saved successfully')),
            }
        );
    };

    const addSlab = (type: 'pt' | 'it') => {
        if (type === 'pt') {
            setPtSlabs([...ptSlabs, { min_amt: 0, max_amt: 0, pt_amt: 0 }]);
        } else {
            setItSlabs([...itSlabs, { min_amt: 0, max_amt: 0, it_pct: 0 }]);
        }
    };

    const removeSlab = (type: 'pt' | 'it', index: number) => {
        if (type === 'pt') {
            setPtSlabs(ptSlabs.filter((_, i) => i !== index));
        } else {
            setItSlabs(itSlabs.filter((_, i) => i !== index));
        }
    };

    const handleSlabChange = (type: 'pt' | 'it', index: number, field: string, value: any) => {
        if (type === 'pt') {
            const newSlabs = [...ptSlabs];
            newSlabs[index] = { ...newSlabs[index], [field]: value };
            setPtSlabs(newSlabs);
        } else {
            const newSlabs = [...itSlabs];
            newSlabs[index] = { ...newSlabs[index], [field]: value };
            setItSlabs(newSlabs);
        }
    };

    const saveSlabs = (type: 'pt' | 'it') => {
        router.post(
            route('hr.payroll-settings.slabs.update'),
            {
                type,
                financial_year: financialYear,
                slabs: type === 'pt' ? ptSlabs : itSlabs,
            },
            {
                onSuccess: () => toast.success(t(`${type.toUpperCase()} slabs saved successfully`)),
            }
        );
    };

    const paramVal = (key: string) => {
        const v = formParams[key];
        return v === null || v === undefined ? '' : v;
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Payroll Management'), href: '#' },
        { title: t('Payroll Settings') }
    ];

    return (
        <PageTemplate 
            title={t('Payroll Settings')} 
            breadcrumbs={breadcrumbs}
        >
            <div className="mb-4 flex flex-wrap items-end justify-between gap-3 rounded-xl border border-slate-200 bg-white/80 p-3 shadow-sm">
                <div className="space-y-1">
                    <Label className="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                        {t('Financial Year')}
                    </Label>
                    <p className="text-[10px] text-slate-500">
                        {t('Select a year to load and save settings separately for each financial year.')}
                    </p>
                </div>
                <Select value={financialYear} onValueChange={changeFinancialYear}>
                    <SelectTrigger className="h-9 w-[180px] text-xs">
                        <SelectValue placeholder={t('Financial Year')} />
                    </SelectTrigger>
                    <SelectContent>
                        {financialYearOptions.map((year: string) => (
                            <SelectItem key={year} value={year} className="text-xs">
                                {yearOptionLabel(year)}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            <Tabs defaultValue="parameters" className="w-full">
                <TabsList className="grid w-full grid-cols-3 mb-8 bg-slate-100/50 p-1 rounded-xl border border-slate-200">
                    <TabsTrigger value="parameters" className="rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm">
                        <ShieldCheck className="w-4 h-4 mr-2" />
                        {t('Global Parameters')}
                    </TabsTrigger>
                    <TabsTrigger value="pt" className="rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm">
                        <IndianRupee className="w-4 h-4 mr-2" />
                        {t('Professional Tax')}
                    </TabsTrigger>
                    <TabsTrigger value="it" className="rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm">
                        <Percent className="w-4 h-4 mr-2" />
                        {t('Income Tax')}
                    </TabsTrigger>
                </TabsList>

                {/* Parameters Tab */}
                <TabsContent value="parameters">
                    <Card className="border-none shadow-md overflow-hidden bg-white/50 backdrop-blur-sm">
                        <CardHeader className="bg-slate-50/50 border-b border-slate-100 p-3">
                            <div className="flex justify-between items-center">
                                <div>
                                    <CardTitle className="text-sm font-bold">{t('Payroll Parameters')}</CardTitle>
                                    <CardDescription className="text-[10px]">{t('Configure PF, ESIC, Bonus and Leave limits')}</CardDescription>
                                </div>
                                <Button onClick={saveParameters} size="sm" className="h-8 bg-primary hover:bg-primary/90 text-xs">
                                    <Save className="w-3.5 h-3.5 mr-1.5" />
                                    {t('Save Changes')}
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="p-3">
                            {!parameters?.id && (
                                <p className="mb-3 rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-3 py-2 text-[10px] text-slate-500">
                                    {t('No saved settings for')} {financialYear}. {t('Fields are blank — enter values and click Save Changes.')}
                                </p>
                            )}
                            <div className="space-y-3">
                                {/* PF & Statutory Section */}
                                <div className="space-y-2">
                                    <h3 className="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center">
                                        <ShieldCheck className="w-3 h-3 mr-1 text-primary" />
                                        {t('PF & Statutory')}
                                    </h3>
                                    
                                    {/* PF Row - One Line */}
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3 p-2 bg-slate-50/50 rounded-lg border border-slate-100">
                                        <div className="space-y-1">
                                            <Label className="text-[10px] flex items-center gap-1 font-medium">
                                                {t('P.F (%)')}
                                            </Label>
                                            <Input type="number" step="0.01" name="pf_pct" value={paramVal('pf_pct')} onChange={handleParamChange} className="h-8 text-xs bg-white" />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[10px] flex items-center gap-1 font-medium text-slate-500">
                                                <span className="text-primary">+</span> {t('F.P.F (%)')}
                                            </Label>
                                            <Input type="number" step="0.01" name="fpf_pct" value={paramVal('fpf_pct')} onChange={handleParamChange} className="h-8 text-xs bg-white" />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[10px] text-primary font-bold flex items-center gap-1">
                                                <span className="text-primary">=</span> {t('Total PF (%)')}
                                            </Label>
                                            <Input 
                                                type="number" 
                                                step="0.01" 
                                                name="total_pf_pct" 
                                                value={paramVal('total_pf_pct')} 
                                                disabled 
                                                className="h-8 text-xs bg-slate-100 font-bold border-primary/20 text-primary"
                                            />
                                        </div>
                                    </div>

                                    {/* ESIC & Others Row */}
                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                                        <div className="space-y-1">
                                            <Label className="text-[10px] font-medium">{t('Max PF Amount')}</Label>
                                            <Input type="number" name="max_pf_amount" value={paramVal('max_pf_amount')} onChange={handleParamChange} className="h-8 text-xs" />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[10px] font-medium">{t('ESIC (%)')}</Label>
                                            <Input type="number" step="0.01" name="esic_pct" value={paramVal('esic_pct')} onChange={handleParamChange} className="h-8 text-xs" />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[10px] font-medium">{t('Karchi (%)')}</Label>
                                            <Input type="number" step="0.01" name="karchi_pct" value={paramVal('karchi_pct')} onChange={handleParamChange} className="h-8 text-xs" />
                                        </div>
                                    </div>

                                    {/* Bonus Row */}
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div className="space-y-1">
                                            <Label className="text-[10px] font-medium">{t('Bonus (%)')}</Label>
                                            <Input type="number" step="0.01" name="bonus_pct" value={paramVal('bonus_pct')} onChange={handleParamChange} className="h-8 text-xs" />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[10px] font-medium">{t('Max Bonus Amt')}</Label>
                                            <Input type="number" name="bonus_max_limit" value={paramVal('bonus_max_limit')} onChange={handleParamChange} className="h-8 text-xs" />
                                        </div>
                                    </div>
                                </div>

                                {/* General & Leave limits combined at bottom */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-3 border-t border-slate-100">
                                    <div className="space-y-2">
                                        <h3 className="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center">
                                            <Calendar className="w-3 h-3 mr-1 text-primary" />
                                            {t('General')}
                                        </h3>
                                        <div className="space-y-1">
                                            <Label className="text-[10px] font-medium">{t('Financial Year')}</Label>
                                            <Input
                                                name="financial_year"
                                                value={financialYear}
                                                readOnly
                                                className="h-8 text-xs bg-slate-50 font-medium"
                                            />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <h3 className="text-[10px] font-bold text-slate-500 uppercase tracking-wider flex items-center">
                                            <Briefcase className="w-3 h-3 mr-1 text-primary" />
                                            {t('Leave Limits')}
                                        </h3>
                                        <div className="grid grid-cols-3 gap-2">
                                            <div className="space-y-1">
                                                <Label className="text-[10px] font-medium">{t('Max EL')}</Label>
                                                <Input type="number" name="max_el" value={paramVal('max_el')} onChange={handleParamChange} className="h-8 text-xs" />
                                            </div>
                                            <div className="space-y-1">
                                                <Label className="text-[10px] font-medium">{t('Max SL')}</Label>
                                                <Input type="number" name="max_sl" value={paramVal('max_sl')} onChange={handleParamChange} className="h-8 text-xs" />
                                            </div>
                                            <div className="space-y-1">
                                                <Label className="text-[10px] font-medium">{t('Max CL')}</Label>
                                                <Input type="number" name="max_cl" value={paramVal('max_cl')} onChange={handleParamChange} className="h-8 text-xs" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Professional Tax Tab */}
                <TabsContent value="pt">
                    <Card className="border-none shadow-md overflow-hidden bg-white/50 backdrop-blur-sm">
                        <CardHeader className="bg-slate-50/50 border-b border-slate-100 flex flex-row items-center justify-between p-3">
                            <div>
                                <CardTitle className="text-sm font-bold">{t('Professional Tax Slabs')}</CardTitle>
                                <CardDescription className="text-[10px]">{t('Define PT amounts based on salary ranges')}</CardDescription>
                            </div>
                            <div className="flex gap-1.5">
                                <Button variant="outline" size="sm" onClick={() => addSlab('pt')} className="h-8 border-primary text-primary hover:bg-primary/5 text-xs">
                                    <Plus className="w-3.5 h-3.5 mr-1.5" />
                                    {t('Add Slab')}
                                </Button>
                                <Button size="sm" className="h-8 text-xs" onClick={() => saveSlabs('pt')}>
                                    <Save className="w-3.5 h-3.5 mr-1.5" />
                                    {t('Save Slabs')}
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader className="bg-slate-50/50">
                                    <TableRow className="h-8 hover:bg-transparent">
                                        <TableHead className="w-[30%] h-8 text-[10px] uppercase font-bold py-1 px-3">{t('Min Amount')}</TableHead>
                                        <TableHead className="w-[30%] h-8 text-[10px] uppercase font-bold py-1 px-3">{t('Max Amount')}</TableHead>
                                        <TableHead className="w-[30%] h-8 text-[10px] uppercase font-bold py-1 px-3">{t('PT Amount')}</TableHead>
                                        <TableHead className="w-[10%] h-8 text-right py-1 px-3">{t('Action')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {ptSlabs.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={4} className="py-10 text-center text-xs text-slate-400">
                                                {t('No slabs for')} {financialYear}. {t('Click Add Slab to configure.')}
                                            </TableCell>
                                        </TableRow>
                                    ) : ptSlabs.map((slab, index) => (
                                        <TableRow key={index} className="h-10 hover:bg-slate-50/30 transition-colors border-b-0">
                                            <TableCell className="py-1 px-3">
                                                <Input 
                                                    type="number" 
                                                    value={slab.min_amt ?? ''} 
                                                    onChange={(e) => handleSlabChange('pt', index, 'min_amt', e.target.value)}
                                                    className="h-8 text-xs bg-transparent border-slate-200 focus:bg-white"
                                                />
                                            </TableCell>
                                            <TableCell className="py-1 px-3">
                                                <Input 
                                                    type="number" 
                                                    value={slab.max_amt || ''} 
                                                    onChange={(e) => handleSlabChange('pt', index, 'max_amt', e.target.value)}
                                                    placeholder={t('Above')}
                                                    className="h-8 text-xs bg-transparent border-slate-200 focus:bg-white"
                                                />
                                            </TableCell>
                                            <TableCell className="py-1 px-3">
                                                <Input 
                                                    type="number" 
                                                    value={slab.pt_amt} 
                                                    onChange={(e) => handleSlabChange('pt', index, 'pt_amt', e.target.value)}
                                                    className="h-8 text-xs bg-transparent border-slate-200 focus:bg-white font-bold text-primary"
                                                />
                                            </TableCell>
                                            <TableCell className="text-right py-1 px-3">
                                                <Button variant="ghost" size="icon" onClick={() => removeSlab('pt', index)} className="h-7 w-7 text-red-400 hover:text-red-600 hover:bg-red-50">
                                                    <Trash2 className="w-3.5 h-3.5" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </TabsContent>

                {/* Income Tax Tab */}
                <TabsContent value="it">
                    <Card className="border-none shadow-md overflow-hidden bg-white/50 backdrop-blur-sm">
                        <CardHeader className="bg-slate-50/50 border-b border-slate-100 flex flex-row items-center justify-between p-3">
                            <div>
                                <CardTitle className="text-sm font-bold">{t('Income Tax Slabs')}</CardTitle>
                                <CardDescription className="text-[10px]">{t('Define IT percentages based on annual salary ranges')}</CardDescription>
                            </div>
                            <div className="flex gap-1.5">
                                <Button variant="outline" size="sm" onClick={() => addSlab('it')} className="h-8 border-primary text-primary hover:bg-primary/5 text-xs">
                                    <Plus className="w-3.5 h-3.5 mr-1.5" />
                                    {t('Add Slab')}
                                </Button>
                                <Button size="sm" className="h-8 text-xs" onClick={() => saveSlabs('it')}>
                                    <Save className="w-3.5 h-3.5 mr-1.5" />
                                    {t('Save Slabs')}
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader className="bg-slate-50/50">
                                    <TableRow className="h-8 hover:bg-transparent">
                                        <TableHead className="w-[30%] h-8 text-[10px] uppercase font-bold py-1 px-3">{t('Min Amount')}</TableHead>
                                        <TableHead className="w-[30%] h-8 text-[10px] uppercase font-bold py-1 px-3">{t('Max Amount')}</TableHead>
                                        <TableHead className="w-[30%] h-8 text-[10px] uppercase font-bold py-1 px-3">{t('Tax %')}</TableHead>
                                        <TableHead className="w-[10%] h-8 text-right py-1 px-3">{t('Action')}</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {itSlabs.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={4} className="py-10 text-center text-xs text-slate-400">
                                                {t('No slabs for')} {financialYear}. {t('Click Add Slab to configure.')}
                                            </TableCell>
                                        </TableRow>
                                    ) : itSlabs.map((slab, index) => (
                                        <TableRow key={index} className="h-10 hover:bg-slate-50/30 transition-colors border-b-0">
                                            <TableCell className="py-1 px-3">
                                                <Input 
                                                    type="number" 
                                                    value={slab.min_amt ?? ''} 
                                                    onChange={(e) => handleSlabChange('it', index, 'min_amt', e.target.value)}
                                                    className="h-8 text-xs bg-transparent border-slate-200 focus:bg-white"
                                                />
                                            </TableCell>
                                            <TableCell className="py-1 px-3">
                                                <Input 
                                                    type="number" 
                                                    value={slab.max_amt || ''} 
                                                    onChange={(e) => handleSlabChange('it', index, 'max_amt', e.target.value)}
                                                    placeholder={t('Above')}
                                                    className="h-8 text-xs bg-transparent border-slate-200 focus:bg-white"
                                                />
                                            </TableCell>
                                            <TableCell className="py-1 px-3">
                                                <Input 
                                                    type="number" 
                                                    step="0.01"
                                                    value={slab.it_pct} 
                                                    onChange={(e) => handleSlabChange('it', index, 'it_pct', e.target.value)}
                                                    className="h-8 text-xs bg-transparent border-slate-200 focus:bg-white font-bold text-primary"
                                                />
                                            </TableCell>
                                            <TableCell className="text-right py-1 px-3">
                                                <Button variant="ghost" size="icon" onClick={() => removeSlab('it', index)} className="h-7 w-7 text-red-400 hover:text-red-600 hover:bg-red-50">
                                                    <Trash2 className="w-3.5 h-3.5" />
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </PageTemplate>
    );
}
