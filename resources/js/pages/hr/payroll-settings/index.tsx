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
import { Save, Plus, Trash2, IndianRupee, Percent, ShieldCheck, Users } from 'lucide-react';
import { canEditPayrollSettings } from '@/utils/authorization';

function sanitizeNonNegativeNumber(value: string): string {
    if (value === '') return '';
    let cleaned = value.replace(/-/g, '').replace(/[^\d.]/g, '');
    const parts = cleaned.split('.');
    if (parts.length > 2) {
        cleaned = `${parts[0]}.${parts.slice(1).join('')}`;
    }
    return cleaned;
}

function blockMinusKey(e: React.KeyboardEvent<HTMLInputElement>) {
    if (e.key === '-' || e.key === '+' || e.key === 'e' || e.key === 'E') {
        e.preventDefault();
    }
}

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
        auth,
    } = usePage().props as any;
    const permissions = auth?.permissions || [];
    const canEdit = canEditPayrollSettings(permissions);

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
        setFormParams(prev => ({ ...prev, [name]: sanitizeNonNegativeNumber(value) }));
    };

    const numericFields = [
        'pf_pct', 'fpf_pct', 'pf_admin_charge_pct', 'max_pf_amount',
        'esic_employee_pct', 'esic_employer_pct', 'esic_wage_limit',
    ];

    const hasInvalidNumbers = () => numericFields.some((key) => {
        const v = formParams[key];
        if (v === '' || v === null || v === undefined) return false;
        return Number(v) < 0 || Number.isNaN(Number(v));
    });

    const hasInvalidSlabNumbers = (slabs: Array<Record<string, unknown>>) => slabs.some((slab) =>
        Object.values(slab).some((v) => {
            if (v === '' || v === null || v === undefined) return false;
            return Number(v) < 0 || Number.isNaN(Number(v));
        })
    );

    const pfEmployerCoreTotal = () => {
        const pf = parseFloat(String(formParams.pf_pct ?? '')) || 0;
        const fpf = parseFloat(String(formParams.fpf_pct ?? '')) || 0;
        if (!formParams.pf_pct && !formParams.fpf_pct) return '';
        return (pf + fpf).toFixed(2);
    };

    const pfEmployerGrandTotal = () => {
        const core = parseFloat(pfEmployerCoreTotal()) || 0;
        const admin = parseFloat(String(formParams.pf_admin_charge_pct ?? '')) || 0;
        if (!pfEmployerCoreTotal() && !formParams.pf_admin_charge_pct) return '';
        return (core + admin).toFixed(2);
    };

    const pfEmployeePctCalculated = () => pfEmployerCoreTotal();

    const saveParameters = () => {
        if (hasInvalidNumbers()) {
            toast.error(t('Only zero or positive numbers are allowed'));
            return;
        }
        router.post(
            route('hr.payroll-settings.parameters.update'),
            {
                financial_year: financialYear,
                pf_pct: formParams.pf_pct,
                fpf_pct: formParams.fpf_pct,
                pf_admin_charge_pct: formParams.pf_admin_charge_pct,
                max_pf_amount: formParams.max_pf_amount,
                esic_employee_pct: formParams.esic_employee_pct,
                esic_employer_pct: formParams.esic_employer_pct,
                esic_wage_limit: formParams.esic_wage_limit,
            },
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
        const nextValue = typeof value === 'string' ? sanitizeNonNegativeNumber(value) : value;
        if (type === 'pt') {
            const newSlabs = [...ptSlabs];
            newSlabs[index] = { ...newSlabs[index], [field]: nextValue };
            setPtSlabs(newSlabs);
        } else {
            const newSlabs = [...itSlabs];
            newSlabs[index] = { ...newSlabs[index], [field]: nextValue };
            setItSlabs(newSlabs);
        }
    };

    const saveSlabs = (type: 'pt' | 'it') => {
        const slabs = type === 'pt' ? ptSlabs : itSlabs;
        if (hasInvalidSlabNumbers(slabs)) {
            toast.error(t('Only zero or positive numbers are allowed'));
            return;
        }
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
        { title: t('Salary Payroll'), href: '#' },
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

            <Tabs defaultValue="pf-esi" className="w-full">
                <TabsList className="grid w-full grid-cols-3 mb-8 bg-slate-100/50 p-1 rounded-xl border border-slate-200">
                    <TabsTrigger value="pf-esi" className="rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-sm">
                        <ShieldCheck className="w-4 h-4 mr-2" />
                        {t('PF / ESI')}
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

                {/* PF / ESI Tab */}
                <TabsContent value="pf-esi">
                    <Card className="border-none shadow-md overflow-hidden bg-white/50 backdrop-blur-sm">
                        <CardHeader className="bg-slate-50/50 border-b border-slate-100 p-3">
                            <div className="flex justify-between items-center">
                                <div>
                                    <CardTitle className="text-sm font-bold">{t('PF / ESI Settings')}</CardTitle>
                                    <CardDescription className="text-[10px]">
                                        {t('Configure employee & employer contribution rates for')} {financialYear}
                                    </CardDescription>
                                </div>
                                {canEdit && (
                                <Button onClick={saveParameters} size="sm" className="h-8 bg-primary hover:bg-primary/90 text-xs">
                                    <Save className="w-3.5 h-3.5 mr-1.5" />
                                    {t('Save Changes')}
                                </Button>
                                )}
                            </div>
                        </CardHeader>
                        <CardContent className="p-4">
                            {!parameters?.id && (
                                <p className="mb-4 rounded-lg border border-dashed border-slate-200 bg-slate-50/80 px-3 py-2 text-[10px] text-slate-500">
                                    {t('No saved settings for')} {financialYear}. {t('Enter values and click Save Changes.')}
                                </p>
                            )}

                            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                <div className="space-y-4 rounded-xl border border-blue-100 bg-blue-50/30 p-4">
                                    <div>
                                        <h3 className="text-xs font-bold uppercase tracking-wider text-blue-800 flex items-center gap-1.5">
                                            <ShieldCheck className="w-3.5 h-3.5" />
                                            {t('Provident Fund (PF)')}
                                        </h3>
                                        <p className="mt-1 text-[11px] text-slate-500">
                                            {t('Enter P.F & F.P.F — Employee % auto-calculates. Employer adds admin charge only.')}
                                        </p>
                                    </div>

                                    <div className="rounded-lg border border-blue-100 bg-white/80 p-3 space-y-3">
                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                            <div className="space-y-1">
                                                <Label className="text-[11px] font-medium">{t('P.F (%)')}</Label>
                                                <Input type="number" min="0" step="0.01" name="pf_pct" value={paramVal('pf_pct')} onChange={handleParamChange} onKeyDown={blockMinusKey} className="h-9 text-sm bg-white" placeholder="8.33" />
                                                <p className="text-[10px] text-muted-foreground">{t('EPF share')}</p>
                                            </div>
                                            <div className="space-y-1">
                                                <Label className="text-[11px] font-medium">{t('F.P.F (%)')}</Label>
                                                <Input type="number" min="0" step="0.01" name="fpf_pct" value={paramVal('fpf_pct')} onChange={handleParamChange} onKeyDown={blockMinusKey} className="h-9 text-sm bg-white" placeholder="3.67" />
                                                <p className="text-[10px] text-muted-foreground">{t('EPS pension fund')}</p>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 rounded-lg border border-emerald-100 bg-emerald-50/40 p-3">
                                            <div className="space-y-1">
                                                <Label className="text-[11px] font-semibold text-emerald-800">{t('Employee (%)')}</Label>
                                                <Input type="text" value={pfEmployeePctCalculated()} readOnly disabled className="h-9 text-sm bg-white font-bold text-emerald-800 border-emerald-200" placeholder="12.00" />
                                                <p className="text-[10px] text-muted-foreground">{t('Auto: P.F + F.P.F — salary deduction')}</p>
                                            </div>
                                            <div className="space-y-1">
                                                <Label className="text-[11px] font-medium">{t('Admin Charge (%)')}</Label>
                                                <Input type="number" min="0" step="0.01" name="pf_admin_charge_pct" value={paramVal('pf_admin_charge_pct')} onChange={handleParamChange} onKeyDown={blockMinusKey} className="h-9 text-sm bg-white" placeholder="1.00" />
                                                <p className="text-[10px] text-muted-foreground">{t('Employer only — PF admin / EDLI')}</p>
                                            </div>
                                        </div>

                                        <div className="space-y-1 rounded-lg border border-blue-200 bg-blue-50/50 p-3">
                                            <Label className="text-[11px] font-bold text-blue-800">{t('Total Employer (%)')}</Label>
                                            <Input type="text" value={pfEmployerGrandTotal()} readOnly disabled className="h-9 text-sm bg-white font-bold text-blue-800 border-blue-200" placeholder="13.00" />
                                            <p className="text-[10px] text-muted-foreground">{t('Auto: P.F + F.P.F + Admin charge')}</p>
                                        </div>
                                    </div>

                                    <div className="space-y-1 rounded-lg border border-amber-100 bg-amber-50/30 p-3">
                                        <Label className="text-[11px] font-semibold text-amber-900">{t('Max PF on Basic Salary (₹)')}</Label>
                                        <Input type="number" min="0" step="1" name="max_pf_amount" value={paramVal('max_pf_amount')} onChange={handleParamChange} onKeyDown={blockMinusKey} className="h-9 text-sm bg-white" placeholder="15000" />
                                        <p className="text-[10px] text-muted-foreground">
                                            {t('Limit applies on Basic / PF basic wage — not gross. PF % calculated on basic up to this cap.')}
                                        </p>
                                    </div>
                                </div>

                                <div className="space-y-3 rounded-xl border border-emerald-100 bg-emerald-50/30 p-4">
                                    <h3 className="text-xs font-bold uppercase tracking-wider text-emerald-800 flex items-center gap-1.5">
                                        <Users className="w-3.5 h-3.5" />
                                        {t('ESIC (ESI)')}
                                    </h3>
                                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                        <div className="space-y-1">
                                            <Label className="text-[10px] font-medium">{t('Employee (%)')}</Label>
                                            <Input type="number" min="0" step="0.01" name="esic_employee_pct" value={paramVal('esic_employee_pct')} onChange={handleParamChange} onKeyDown={blockMinusKey} className="h-8 text-xs bg-white" placeholder="0.75" />
                                        </div>
                                        <div className="space-y-1">
                                            <Label className="text-[10px] font-medium">{t('Employer (%)')}</Label>
                                            <Input type="number" min="0" step="0.01" name="esic_employer_pct" value={paramVal('esic_employer_pct')} onChange={handleParamChange} onKeyDown={blockMinusKey} className="h-8 text-xs bg-white" placeholder="3.25" />
                                        </div>
                                    </div>
                                    <div className="space-y-1 rounded-lg border border-amber-100 bg-amber-50/30 p-3">
                                        <Label className="text-[11px] font-semibold text-amber-900">{t('Max ESI on Gross Salary (₹)')}</Label>
                                        <Input type="number" min="0" step="1" name="esic_wage_limit" value={paramVal('esic_wage_limit')} onChange={handleParamChange} onKeyDown={blockMinusKey} className="h-9 text-sm bg-white" placeholder="21000" />
                                        <p className="text-[10px] text-muted-foreground">
                                            {t('Limit applies on monthly gross earnings — not basic only. ESI % calculated on gross up to this cap.')}
                                        </p>
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
                                {canEdit && (
                                <>
                                <Button variant="outline" size="sm" onClick={() => addSlab('pt')} className="h-8 border-primary text-primary hover:bg-primary/5 text-xs">
                                    <Plus className="w-3.5 h-3.5 mr-1.5" />
                                    {t('Add Slab')}
                                </Button>
                                <Button size="sm" className="h-8 text-xs" onClick={() => saveSlabs('pt')}>
                                    <Save className="w-3.5 h-3.5 mr-1.5" />
                                    {t('Save Slabs')}
                                </Button>
                                </>
                                )}
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
                                                    min="0"
                                                    value={slab.min_amt ?? ''} 
                                                    onChange={(e) => handleSlabChange('pt', index, 'min_amt', e.target.value)}
                                                    onKeyDown={blockMinusKey}
                                                    className="h-8 text-xs bg-transparent border-slate-200 focus:bg-white"
                                                />
                                            </TableCell>
                                            <TableCell className="py-1 px-3">
                                                <Input 
                                                    type="number"
                                                    min="0"
                                                    value={slab.max_amt || ''} 
                                                    onChange={(e) => handleSlabChange('pt', index, 'max_amt', e.target.value)}
                                                    onKeyDown={blockMinusKey}
                                                    placeholder={t('Above')}
                                                    className="h-8 text-xs bg-transparent border-slate-200 focus:bg-white"
                                                />
                                            </TableCell>
                                            <TableCell className="py-1 px-3">
                                                <Input 
                                                    type="number"
                                                    min="0"
                                                    value={slab.pt_amt} 
                                                    onChange={(e) => handleSlabChange('pt', index, 'pt_amt', e.target.value)}
                                                    onKeyDown={blockMinusKey}
                                                    className="h-8 text-xs bg-transparent border-slate-200 focus:bg-white font-bold text-primary"
                                                />
                                            </TableCell>
                                            <TableCell className="text-right py-1 px-3">
                                                {canEdit && (
                                                <Button variant="ghost" size="icon" onClick={() => removeSlab('pt', index)} className="h-7 w-7 text-red-400 hover:text-red-600 hover:bg-red-50">
                                                    <Trash2 className="w-3.5 h-3.5" />
                                                </Button>
                                                )}
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
                                {canEdit && (
                                <>
                                <Button variant="outline" size="sm" onClick={() => addSlab('it')} className="h-8 border-primary text-primary hover:bg-primary/5 text-xs">
                                    <Plus className="w-3.5 h-3.5 mr-1.5" />
                                    {t('Add Slab')}
                                </Button>
                                <Button size="sm" className="h-8 text-xs" onClick={() => saveSlabs('it')}>
                                    <Save className="w-3.5 h-3.5 mr-1.5" />
                                    {t('Save Slabs')}
                                </Button>
                                </>
                                )}
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
                                                    min="0"
                                                    value={slab.min_amt ?? ''} 
                                                    onChange={(e) => handleSlabChange('it', index, 'min_amt', e.target.value)}
                                                    onKeyDown={blockMinusKey}
                                                    className="h-8 text-xs bg-transparent border-slate-200 focus:bg-white"
                                                />
                                            </TableCell>
                                            <TableCell className="py-1 px-3">
                                                <Input 
                                                    type="number"
                                                    min="0"
                                                    value={slab.max_amt || ''} 
                                                    onChange={(e) => handleSlabChange('it', index, 'max_amt', e.target.value)}
                                                    onKeyDown={blockMinusKey}
                                                    placeholder={t('Above')}
                                                    className="h-8 text-xs bg-transparent border-slate-200 focus:bg-white"
                                                />
                                            </TableCell>
                                            <TableCell className="py-1 px-3">
                                                <Input 
                                                    type="number"
                                                    min="0"
                                                    step="0.01"
                                                    value={slab.it_pct} 
                                                    onChange={(e) => handleSlabChange('it', index, 'it_pct', e.target.value)}
                                                    onKeyDown={blockMinusKey}
                                                    className="h-8 text-xs bg-transparent border-slate-200 focus:bg-white font-bold text-primary"
                                                />
                                            </TableCell>
                                            <TableCell className="text-right py-1 px-3">
                                                {canEdit && (
                                                <Button variant="ghost" size="icon" onClick={() => removeSlab('it', index)} className="h-7 w-7 text-red-400 hover:text-red-600 hover:bg-red-50">
                                                    <Trash2 className="w-3.5 h-3.5" />
                                                </Button>
                                                )}
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
