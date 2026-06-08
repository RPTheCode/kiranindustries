import React, { useState, useEffect, useMemo } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Head, useForm, usePage } from '@inertiajs/react';
import { 
    Card, 
    CardContent, 
    CardHeader, 
    CardTitle, 
    CardDescription 
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    Save, 
    Trash2, 
    ShieldCheck, 
    Calendar,
    PlusCircle,
    MinusCircle,
    History,
    ArrowUpRight,
    ArrowDownRight,
    FolderOpen,
    Loader2,
    ChevronRight,
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';
import {
    canCreateEarningDeductionEntry,
    canEditEarningDeductionEntry,
} from '@/utils/authorization';

import { Combobox, ComboboxOption } from '@/components/ui/combobox';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { toast } from '@/components/custom-toast';

interface EntryDetail {
    type_id: number | null;
    deduction_type_id: number | null;
    name: string;
    type: 'earning' | 'deduction';
    mode: 'amount' | 'day';
    value: string;
    temp_id: number;
}

interface DeductionTypeOption {
    id: number;
    name: string;
    default_amount: number;
    amount_type?: 'fixed' | 'category_wise';
    calculation_mode: 'day' | 'month';
    category_amounts_list?: { category_id: number; category_name?: string; amount: number }[];
}

function resolveDeductionRate(master: DeductionTypeOption, categoryId?: number | null): number {
    if (master.amount_type === 'category_wise' && categoryId) {
        const match = master.category_amounts_list?.find((row) => row.category_id === categoryId);
        if (match) return Number(match.amount);
    }
    return Number(master.default_amount);
}

function masterDeductionLabel(master: DeductionTypeOption, categoryId?: number | null): string {
    const mode = master.calculation_mode === 'day' ? 'Per Day' : 'Per Month';
    const rate = resolveDeductionRate(master, categoryId);
    const categoryHint =
        master.amount_type === 'category_wise' && categoryId
            ? ` · ${master.category_amounts_list?.find((r) => r.category_id === categoryId)?.category_name || 'Category rate'}`
            : '';
    return `${master.name} · ${mode} · ₹${rate.toLocaleString('en-IN')}${categoryHint}`;
}

function recalcMasterDeductionDetails(
    details: EntryDetail[],
    masters: DeductionTypeOption[],
    categoryId?: number | null,
): EntryDetail[] {
    return details.map((d) => {
        if (d.type !== 'deduction' || !d.deduction_type_id) {
            return d;
        }
        const master = masters.find((m) => m.id === d.deduction_type_id);
        if (!master) {
            return d;
        }
        const rate = resolveDeductionRate(master, categoryId);
        const isDay = master.calculation_mode === 'day';
        return {
            ...d,
            name: master.name,
            mode: isDay ? 'day' : 'amount',
            value: isDay ? d.value : String(rate),
        };
    });
}

interface HistoryEntry {
    id: number;
    date: string | null;
    month_year: string;
    remark: string | null;
    total_earnings: number;
    total_deductions: number;
    details: { type_id: number | null; name: string; type: string; mode: string; value: number }[];
}

export default function MonthlyIncentiveIndex() {
    const { employees, selected_employee_id, deductionTypes = [], activeBranchName, auth } = usePage<PageProps>().props as any;
    const { t } = useTranslation();
    const permissions = auth?.permissions || [];
    const canSave = canCreateEarningDeductionEntry(permissions) || canEditEarningDeductionEntry(permissions);

    const [loading, setLoading] = useState(false);
    const [historyLoading, setHistoryLoading] = useState(false);
    const [employeeDetails, setEmployeeDetails] = useState<any>(null);
    const [isExistingRecord, setIsExistingRecord] = useState(false);
    const [history, setHistory] = useState<HistoryEntry[]>([]);

    const { data, setData, post, processing, reset } = useForm({
        employee_id: '',
        month_year: new Date().toISOString().slice(0, 7),
        date: new Date().toISOString().slice(0, 10),
        remark: '',
        details: [] as EntryDetail[],
    });

    useEffect(() => {
        if (selected_employee_id && selected_employee_id !== 'null' && selected_employee_id !== 'undefined') {
            handleEmployeeChange(selected_employee_id.toString());
        }
    }, [selected_employee_id]);

    const fetchHistory = async (employeeId: string) => {
        if (!employeeId) return;
        setHistoryLoading(true);
        try {
            // Use direct URL to avoid Ziggy cache issues with newly registered routes
            const resp = await axios.get(route('hr.earning-deduction.employee-history', employeeId));
            setHistory(resp.data.entries || []);
        } catch (err) {
            console.error('History fetch failed:', err);
            setHistory([]);
        } finally {
            setHistoryLoading(false);
        }
    };

    const handleEmployeeChange = async (val: string, monthVal?: string, dateVal?: string) => {
        if (!val || val === 'null' || val === 'undefined') return;
        
        const targetMonth = monthVal || data.month_year;
        const targetDate = dateVal || data.date;
        
        setData('employee_id', val);
        setLoading(true);
        try {
            const response = await axios.get(route('hr.earning-deduction.employee-details', val), {
                params: { month: targetMonth, date: targetDate }
            });
            const { employee, existing_entry } = response.data;
            setEmployeeDetails(employee);
            
            if (existing_entry) {
                const entryDetails = recalcMasterDeductionDetails(
                    existing_entry.details.map((d: any) => ({
                        type_id: d.type_id,
                        deduction_type_id: d.deduction_type_id ?? null,
                        name: d.name || '',
                        type: d.type,
                        mode: d.mode,
                        value: d.value.toString(),
                        temp_id: Math.random(),
                    })),
                    deductionTypes as DeductionTypeOption[],
                    employee.category_id,
                );
                setData((prevData) => ({
                    ...prevData,
                    employee_id: val,
                    remark: existing_entry.remark || '',
                    date: existing_entry.date || prevData.date,
                    details: entryDetails,
                }));
                setIsExistingRecord(true);
                toast.info(t('Existing record loaded'));
            } else {
                setData((prevData) => ({
                    ...prevData,
                    employee_id: val,
                    remark: '',
                    details: []
                }));
                setIsExistingRecord(false);
            }
        } catch (error) {
            console.error('Fetch error:', error);
            toast.error(t('Failed to fetch employee details'));
        } finally {
            setLoading(false);
        }

        fetchHistory(val);
    };

    const loadHistoryEntry = (entry: HistoryEntry) => {
        const entryDetails = recalcMasterDeductionDetails(
            entry.details.map((d) => ({
                type_id: d.type_id,
                deduction_type_id: d.deduction_type_id ?? null,
                name: d.name || '',
                type: d.type as 'earning' | 'deduction',
                mode: d.mode as 'amount' | 'day',
                value: d.value.toString(),
                temp_id: Math.random(),
            })),
            deductionTypes as DeductionTypeOption[],
            employeeDetails?.category_id,
        );
        setData((prev) => ({
            ...prev,
            month_year: entry.month_year,
            date: entry.date || prev.date,
            remark: entry.remark || '',
            details: entryDetails,
        }));
        setIsExistingRecord(true);
        window.scrollTo({ top: 0, behavior: 'smooth' });
        toast.success(t('Entry loaded into form. Review and Save.'));
    };

    const handleValueChange = (tempId: number, value: string) => {
        const newDetails = [...data.details];
        const index = newDetails.findIndex(d => d.temp_id === tempId);
        if (index > -1) {
            newDetails[index] = { ...newDetails[index], value };
            setData('details', newDetails);
        }
    };

    const handleDetailUpdate = (tempId: number, field: string, val: any) => {
        const newDetails = [...data.details];
        const index = newDetails.findIndex(d => d.temp_id === tempId);
        if (index > -1) {
            newDetails[index] = { ...newDetails[index], [field]: val };
            setData('details', newDetails);
        }
    };

    const applyDeductionMaster = (tempId: number, typeId: string) => {
        if (!typeId || typeId === 'custom') {
            handleDetailUpdate(tempId, 'deduction_type_id', null);
            return;
        }
        const master = (deductionTypes as DeductionTypeOption[]).find((d) => d.id === Number(typeId));
        if (!master) return;

        const newDetails = [...data.details];
        const index = newDetails.findIndex(d => d.temp_id === tempId);
        if (index === -1) return;

        const rate = resolveDeductionRate(master, employeeDetails?.category_id);
        const isDay = master.calculation_mode === 'day';

        newDetails[index] = {
            ...newDetails[index],
            deduction_type_id: master.id,
            name: master.name,
            type: 'deduction',
            mode: isDay ? 'day' : 'amount',
            value: isDay ? '1' : String(rate),
        };
        setData('details', newDetails);
    };

    const addRow = (type: 'earning' | 'deduction') => {
        setData('details', [
            ...data.details,
            {
                type_id: null,
                deduction_type_id: null,
                name: '',
                type,
                mode: 'amount',
                value: '0',
                temp_id: Math.random(),
            }
        ]);
    };

    const removeRow = (tempId: number) => {
        setData('details', data.details.filter(d => d.temp_id !== tempId));
    };

    const handleMonthChange = (val: string) => {
        setData('month_year', val);
        if (data.employee_id) handleEmployeeChange(data.employee_id, val, data.date);
    };

    const handleDateChange = (val: string) => {
        setData('date', val);
        if (data.employee_id) handleEmployeeChange(data.employee_id, data.month_year, val);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        if (data.details.length === 0 && !data.remark.trim() && !isExistingRecord) {
            toast.error(t('Please add at least one earning/deduction component or a remark before saving.'));
            return;
        }

        post(route('hr.earning-deduction.store'), {
            onSuccess: (page) => {
                const flash = page.props.flash as any;
                if (flash && flash.error) {
                    toast.error(flash.error);
                } else if (flash && flash.success) {
                    toast.success(flash.success);
                    if (flash.success.includes('cleared')) {
                        reset();
                        setEmployeeDetails(null);
                        setIsExistingRecord(false);
                    } else {
                        setIsExistingRecord(true);
                    }
                } else {
                    toast.success(t('Earnings/Deductions record saved successfully'));
                    setIsExistingRecord(true);
                }
                if (data.employee_id) fetchHistory(data.employee_id);
            },
            onError: (errors) => {
                const firstError = Object.values(errors)[0];
                toast.error(firstError ? String(firstError) : t('An error occurred while saving.'));
            }
        });
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Salary Payroll') },
        { title: t('Earning / Deduction') },
    ];

    const employeeOptions: ComboboxOption[] = employees.map((emp: any) => ({
        label: emp.category_name
            ? `${emp.scroll_no} · ${emp.name} · ${emp.category_name}`
            : `${emp.scroll_no} · ${emp.name}`,
        value: emp.id.toString(),
    }));

    const formEarnings = data.details.filter(d => d.type === 'earning');
    const formDeductions = data.details.filter(d => d.type === 'deduction');

    const deductionComboboxOptions: ComboboxOption[] = useMemo(() => [
        { value: 'custom', label: t('Custom / Manual') },
        ...(deductionTypes as DeductionTypeOption[]).map((dt) => ({
            value: String(dt.id),
            label: masterDeductionLabel(dt, employeeDetails?.category_id),
        })),
    ], [deductionTypes, employeeDetails?.category_id, t]);

    // Group history by month_year
    const groupedHistory = history.reduce((acc, entry) => {
        if (!acc[entry.month_year]) acc[entry.month_year] = [];
        acc[entry.month_year].push(entry);
        return acc;
    }, {} as Record<string, HistoryEntry[]>);

    const formatMonth = (monthYear: string) => {
        const [y, m] = monthYear.split('-');
        return new Date(parseInt(y), parseInt(m) - 1, 1)
            .toLocaleDateString('en-IN', { month: 'long', year: 'numeric' });
    };

    const formatDate = (dateStr: string | null) => {
        if (!dateStr) return '—';
        return new Date(dateStr).toLocaleDateString('en-IN', { day: '2-digit', month: 'short', year: 'numeric' });
    };

    return (
        <PageTemplate 
            title={t('Earning / Deduction Entry')} 
            description={
                activeBranchName
                    ? `${activeBranchName} · ${t('Monthly earning & deduction entry for payroll')}`
                    : t('Monthly earning & deduction entry for payroll')
            }
            url={route('hr.earning-deduction.index')}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            <div className="max-w-6xl mx-auto p-4 space-y-4">
                <form onSubmit={submit} className="space-y-4">
                    {/* ─── HEADER CONTROLS ─────────────────────────────── */}
                    <Card className="border-none shadow-sm overflow-hidden bg-white">
                        <CardHeader className="bg-slate-50/80 border-b border-slate-100 p-5">
                            <div className="grid grid-cols-1 md:grid-cols-12 gap-5 items-end">
                                <div className="space-y-2 md:col-span-2">
                                    <Label className="text-[11px] font-bold uppercase text-slate-500 tracking-widest ml-1">{t('Salary Month')}</Label>
                                    <Input 
                                        type="month" value={data.month_year}
                                        onChange={(e) => handleMonthChange(e.target.value)}
                                        onClick={(e) => e.currentTarget.showPicker?.()}
                                        className="h-11 w-full text-sm font-semibold bg-white border-slate-200 shadow-sm cursor-pointer"
                                    />
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                    <Label className="text-[11px] font-bold uppercase text-slate-500 tracking-widest ml-1">{t('Entry Date')}</Label>
                                    <Input 
                                        type="date" value={data.date}
                                        onChange={(e) => handleDateChange(e.target.value)}
                                        onClick={(e) => e.currentTarget.showPicker?.()}
                                        className="h-11 w-full text-sm font-semibold bg-white border-slate-200 shadow-sm cursor-pointer"
                                    />
                                </div>
                                <div className="space-y-2 md:col-span-5">
                                    <Label className="text-[11px] font-bold uppercase text-slate-500 tracking-widest ml-1">{t('Employee Selection')}</Label>
                                    <Combobox
                                        options={employeeOptions}
                                        value={data.employee_id}
                                        onChange={handleEmployeeChange}
                                        placeholder={t('Search by code or name...')}
                                        className="h-11 text-sm bg-white border-slate-200 shadow-sm"
                                    />
                                </div>
                                <div className="flex gap-2 md:col-span-3">
                                    {canSave && (
                                    <Button 
                                        type="submit"
                                        className="flex-1 h-11 bg-primary hover:bg-primary/90 text-white font-bold text-xs shadow-lg shadow-primary/20"
                                        disabled={processing || !data.employee_id}
                                    >
                                        <Save className="w-4 h-4 mr-2" />
                                        {t('Save Record')}
                                    </Button>
                                    )}
                                    {canSave && (
                                    <Button 
                                        type="button" variant="outline"
                                        className="h-11 w-11 text-red-500 border-red-100 hover:bg-red-50 hover:border-red-200 shadow-sm"
                                        onClick={() => { 
                                            if (isExistingRecord) {
                                                if (confirm(t('Are you sure you want to delete this record entirely?'))) {
                                                    setData('details', []);
                                                    setData('remark', '');
                                                    setTimeout(() => {
                                                        const event = new Event('submit', { cancelable: true, bubbles: true });
                                                        document.querySelector('form')?.dispatchEvent(event);
                                                    }, 50);
                                                }
                                            } else {
                                                reset(); 
                                                setEmployeeDetails(null); 
                                                setHistory([]); 
                                            }
                                        }}
                                        title={isExistingRecord ? t('Delete this record') : t('Clear form')}
                                    >
                                        <Trash2 className="w-4 h-4" />
                                    </Button>
                                    )}
                                </div>
                            </div>
                        </CardHeader>

                        {employeeDetails && (
                            <CardContent className="p-0 border-b border-slate-100">
                                <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-0 divide-x divide-y md:divide-y-0 divide-slate-100 bg-slate-50/20">
                                    <div className="p-4">
                                        <div className="text-[10px] text-slate-400 uppercase font-bold mb-1">{t('Employee Name')}</div>
                                        <div className="text-sm font-bold text-slate-800">{employeeDetails.name}</div>
                                    </div>
                                    <div className="p-4">
                                        <div className="text-[10px] text-slate-400 uppercase font-bold mb-1">{t('Employee Code')}</div>
                                        <div className="text-sm font-bold tabular-nums text-slate-800">{employeeDetails.scroll_no}</div>
                                    </div>
                                    <div className="p-4">
                                        <div className="text-[10px] text-slate-400 uppercase font-bold mb-1">{t('Category')}</div>
                                        {employeeDetails.category_name ? (
                                            <Badge variant="outline" className="border-violet-200 bg-violet-50 text-violet-800 font-semibold">
                                                {employeeDetails.category_name}
                                            </Badge>
                                        ) : (
                                            <span className="text-xs font-medium text-slate-400">{t('Not set')}</span>
                                        )}
                                    </div>
                                    <div className="p-4">
                                        <div className="text-[10px] text-slate-400 uppercase font-bold mb-1">{t('Branch')}</div>
                                        <div className="text-sm font-semibold text-slate-700">
                                            {employeeDetails.branch_name || activeBranchName || t('N/A')}
                                        </div>
                                    </div>
                                    <div className="p-4">
                                        <div className="text-[10px] text-slate-400 uppercase font-bold mb-1">{t('Department / Designation')}</div>
                                        <div className="text-xs font-semibold text-slate-600">
                                            {employeeDetails.department || '—'} / {employeeDetails.designation || '—'}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        )}
                    </Card>

                    {/* ─── EARNINGS & DEDUCTIONS ────────────────────────── */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Earnings */}
                        <Card className="border-none shadow-sm bg-white">
                            <CardHeader className="flex flex-row items-center justify-between pb-4 border-b border-slate-50 bg-green-50/10">
                                <div className="flex items-center gap-2.5">
                                    <div className="p-2 bg-green-100 rounded-lg">
                                        <PlusCircle className="w-4 h-4 text-green-600" />
                                    </div>
                                    <div>
                                        <h3 className="text-sm font-bold text-slate-800 uppercase tracking-tight">{t('Earnings')}</h3>
                                        <p className="text-[10px] text-slate-500 font-medium">{t('Add custom earning components')}</p>
                                    </div>
                                </div>
                                {canSave && (
                                <Button type="button" variant="outline" size="sm" onClick={() => addRow('earning')}
                                    className="h-8 text-[11px] font-bold border-green-200 text-green-600 hover:bg-green-50">
                                    <PlusCircle className="w-3.5 h-3.5 mr-1.5" />{t('Add New')}
                                </Button>
                                )}
                            </CardHeader>
                            <CardContent className="p-4 space-y-3">
                                {formEarnings.length === 0 ? (
                                    <div className="py-12 flex flex-col items-center justify-center border-2 border-dashed border-slate-100 rounded-xl bg-slate-50/50">
                                        <PlusCircle className="w-8 h-8 text-slate-200 mb-2" />
                                        <p className="text-xs text-slate-400 font-medium italic">{t('No earnings added yet')}</p>
                                    </div>
                                ) : formEarnings.map((row) => (
                                    <div key={row.temp_id} className="p-3 border border-slate-100 rounded-xl bg-white shadow-sm hover:border-green-200 transition-all group">
                                        <div className="grid grid-cols-12 gap-3 items-center">
                                            <div className="col-span-12 md:col-span-5">
                                                <Input placeholder={t('Component Name (e.g. Allowance)')} value={row.name}
                                                    onChange={(e) => handleDetailUpdate(row.temp_id, 'name', e.target.value)}
                                                    className="h-9 text-xs font-bold uppercase placeholder:text-slate-300 border-none bg-slate-50 group-hover:bg-green-50/50 transition-colors" />
                                            </div>
                                            <div className="col-span-12 md:col-span-3">
                                                <div className="flex bg-slate-100 p-1 rounded-lg">
                                                    <button type="button" onClick={() => handleDetailUpdate(row.temp_id, 'mode', 'amount')}
                                                        className={`flex-1 text-[9px] font-bold py-1.5 rounded-md transition-all ${row.mode === 'amount' ? 'bg-white text-green-600 shadow-sm' : 'text-slate-400'}`}>AMOUNT</button>
                                                    <button type="button" onClick={() => handleDetailUpdate(row.temp_id, 'mode', 'day')}
                                                        className={`flex-1 text-[9px] font-bold py-1.5 rounded-md transition-all ${row.mode === 'day' ? 'bg-white text-green-600 shadow-sm' : 'text-slate-400'}`}>DAYS</button>
                                                </div>
                                            </div>
                                            <div className="col-span-10 md:col-span-3">
                                                <div className="relative">
                                                    <Input type="number" step={row.mode === 'day' ? "0.1" : "0.01"} value={row.value}
                                                        onChange={(e) => handleValueChange(row.temp_id, e.target.value)}
                                                        className="h-9 text-sm font-bold text-green-600 pr-8" />
                                                    <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[9px] font-black text-slate-300">{row.mode === 'day' ? 'D' : '₹'}</span>
                                                </div>
                                            </div>
                                            <div className="col-span-2 md:col-span-1 flex justify-end">
                                                {canSave && (
                                                <Button type="button" variant="ghost" size="icon" className="h-8 w-8 text-slate-300 hover:text-red-500 hover:bg-red-50" onClick={() => removeRow(row.temp_id)}>
                                                    <Trash2 className="w-4 h-4" />
                                                </Button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </CardContent>
                        </Card>

                        {/* Deductions */}
                        <Card className="border-none shadow-sm bg-white">
                            <CardHeader className="flex flex-row items-center justify-between pb-4 border-b border-slate-50 bg-red-50/10">
                                <div className="flex items-center gap-2.5">
                                    <div className="p-2 bg-red-100 rounded-lg">
                                        <MinusCircle className="w-4 h-4 text-red-600" />
                                    </div>
                                    <div>
                                        <h3 className="text-sm font-bold text-slate-800 uppercase tracking-tight">{t('Deductions')}</h3>
                                        <p className="text-[10px] text-slate-500 font-medium">{t('From Deduction Master or custom entry')}</p>
                                    </div>
                                </div>
                                {canSave && (
                                <Button type="button" variant="outline" size="sm" onClick={() => addRow('deduction')}
                                    className="h-8 text-[11px] font-bold border-red-200 text-red-600 hover:bg-red-50">
                                    <PlusCircle className="w-3.5 h-3.5 mr-1.5" />{t('Add New')}
                                </Button>
                                )}
                            </CardHeader>
                            <CardContent className="p-4 space-y-3">
                                {formDeductions.length === 0 ? (
                                    <div className="py-12 flex flex-col items-center justify-center border-2 border-dashed border-slate-100 rounded-xl bg-slate-50/50">
                                        <MinusCircle className="w-8 h-8 text-slate-200 mb-2" />
                                        <p className="text-xs text-slate-400 font-medium italic">{t('No deductions added yet')}</p>
                                    </div>
                                ) : formDeductions.map((row) => {
                                    const linkedMaster = row.deduction_type_id
                                        ? (deductionTypes as DeductionTypeOption[]).find((d) => d.id === row.deduction_type_id)
                                        : null;
                                    const isFromMaster = Boolean(linkedMaster);

                                    return (
                                    <div key={row.temp_id} className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition-all hover:border-red-200/80">
                                        {(deductionTypes as DeductionTypeOption[]).length > 0 && (
                                            <div className="border-b border-slate-100 bg-slate-50/60 px-3 py-2.5">
                                                <Label className="mb-1.5 block text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                                    {t('Deduction Type')}
                                                </Label>
                                                <Combobox
                                                    options={deductionComboboxOptions}
                                                    value={row.deduction_type_id ? String(row.deduction_type_id) : 'custom'}
                                                    onChange={(v) => applyDeductionMaster(row.temp_id, v || 'custom')}
                                                    placeholder={t('Select deduction...')}
                                                    searchPlaceholder={t('Search deduction...')}
                                                    emptyText={t('No deduction found')}
                                                    className="h-9 border-slate-200 bg-white text-sm font-medium text-slate-800 shadow-none"
                                                />
                                            </div>
                                        )}

                                        {!isFromMaster && (
                                            <div className="border-b border-slate-100 px-3 py-2.5">
                                                <Label className="mb-1.5 block text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                                    {t('Name')}
                                                </Label>
                                                <Input
                                                    placeholder={t('e.g. Special deduction')}
                                                    value={row.name}
                                                    onChange={(e) => handleDetailUpdate(row.temp_id, 'name', e.target.value)}
                                                    className="h-9 border-slate-200 bg-white text-sm font-medium"
                                                />
                                            </div>
                                        )}

                                        <div className="flex flex-wrap items-center gap-2 px-3 py-2.5">
                                            {isFromMaster && (
                                                <div className="flex min-w-0 flex-1 flex-wrap items-center gap-1.5">
                                                    <span className="text-sm font-semibold text-slate-800">{row.name}</span>
                                                    <Badge
                                                        variant="outline"
                                                        className={cn(
                                                            'text-[10px] font-semibold',
                                                            linkedMaster?.calculation_mode === 'day'
                                                                ? 'border-sky-200 bg-sky-50 text-sky-700'
                                                                : 'border-violet-200 bg-violet-50 text-violet-700',
                                                        )}
                                                    >
                                                        {linkedMaster?.calculation_mode === 'day' ? t('Per Day') : t('Per Month')}
                                                    </Badge>
                                                    {linkedMaster?.amount_type === 'category_wise' && employeeDetails?.category_name && (
                                                        <Badge variant="outline" className="border-amber-200 bg-amber-50 text-[10px] font-semibold text-amber-800">
                                                            {employeeDetails.category_name}
                                                        </Badge>
                                                    )}
                                                    {isFromMaster && linkedMaster && (
                                                        <span className="text-xs font-medium text-slate-400">
                                                            @ ₹{resolveDeductionRate(linkedMaster, employeeDetails?.category_id).toLocaleString('en-IN')}
                                                            {row.mode === 'day' ? `/${t('day')}` : ''}
                                                        </span>
                                                    )}
                                                </div>
                                            )}

                                            {!isFromMaster && (
                                                <div className="flex rounded-lg bg-slate-100 p-0.5">
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDetailUpdate(row.temp_id, 'mode', 'amount')}
                                                        className={`rounded-md px-3 py-1.5 text-[10px] font-bold transition-all ${row.mode === 'amount' ? 'bg-white text-red-600 shadow-sm' : 'text-slate-400'}`}
                                                    >
                                                        ₹ {t('Amount')}
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() => handleDetailUpdate(row.temp_id, 'mode', 'day')}
                                                        className={`rounded-md px-3 py-1.5 text-[10px] font-bold transition-all ${row.mode === 'day' ? 'bg-white text-red-600 shadow-sm' : 'text-slate-400'}`}
                                                    >
                                                        {t('Days')}
                                                    </button>
                                                </div>
                                            )}

                                            <div className={cn('flex items-center gap-2', isFromMaster ? 'ml-auto' : '')}>
                                                <Label className="sr-only">
                                                    {row.mode === 'day' ? t('Days') : t('Amount')}
                                                </Label>
                                                <div className="relative w-28 sm:w-32">
                                                    <Input
                                                        type="number"
                                                        step={row.mode === 'day' ? '1' : '0.01'}
                                                        min="0"
                                                        value={row.value}
                                                        onChange={(e) => handleValueChange(row.temp_id, e.target.value)}
                                                        className="h-9 border-slate-200 pr-8 text-sm font-bold text-red-600"
                                                    />
                                                    <span className="absolute right-2.5 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-400">
                                                        {row.mode === 'day' ? t('Days') : '₹'}
                                                    </span>
                                                </div>
                                                {canSave && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-9 w-9 shrink-0 text-slate-300 hover:bg-red-50 hover:text-red-500"
                                                    onClick={() => removeRow(row.temp_id)}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                    );
                                })}
                            </CardContent>
                        </Card>
                    </div>

                    {/* ─── REMARKS ─────────────────────────────────────── */}
                    <Card className="border-none shadow-sm bg-white">
                        <CardContent className="p-4">
                            <div className="space-y-1.5">
                                <Label className="text-[10px] font-black uppercase text-slate-400 tracking-tighter mb-1.5 block">
                                    <ShieldCheck className="w-3 h-3 inline-block mr-1 mb-0.5" />
                                    {t('Common Payroll Remarks / Internal Notes')}
                                </Label>
                                <Input 
                                    value={data.remark} 
                                    onChange={(e) => setData('remark', e.target.value)} 
                                    className="h-11 text-sm bg-slate-50/50 border-none focus:bg-white transition-all shadow-inner" 
                                    placeholder={t('Enter common notes or reasons for these adjustments...')} 
                                />
                            </div>
                        </CardContent>
                    </Card>
                </form>

                {/* ─── HISTORY SECTION ─────────────────────────────────── */}
                <div className="pt-2 pb-8">
                    {/* Section header */}
                    <div className="flex items-center gap-3 mb-4">
                        <div className="p-2 bg-indigo-100 rounded-xl">
                            <History className="w-5 h-5 text-indigo-600" />
                        </div>
                        <div>
                            <h2 className="text-base font-black text-slate-800 tracking-tight">
                                {t('Entry History')}
                            </h2>
                            <p className="text-[11px] text-slate-400 font-medium">
                                {employeeDetails
                                    ? `All past entries for ${employeeDetails.name} — click Load to edit any record`
                                    : t('Select an employee above to view their history')}
                            </p>
                        </div>
                        {history.length > 0 && (
                            <span className="ml-auto bg-indigo-100 text-indigo-700 text-[11px] font-black px-3 py-1 rounded-full border border-indigo-200">
                                {history.length} {history.length === 1 ? 'entry' : 'entries'}
                            </span>
                        )}
                        {data.employee_id && (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className={`h-8 text-[11px] font-bold border-indigo-200 text-indigo-600 hover:bg-indigo-50 ${history.length === 0 ? 'ml-auto' : 'ml-2'}`}
                                onClick={() => fetchHistory(data.employee_id)}
                                disabled={historyLoading}
                            >
                                {historyLoading ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <History className="w-3.5 h-3.5 mr-1" />}
                                {historyLoading ? 'Loading...' : 'Refresh'}
                            </Button>
                        )}
                    </div>

                    {/* States */}
                    {!data.employee_id ? (
                        <Card className="border-2 border-dashed border-slate-200 bg-slate-50/50 shadow-none">
                            <CardContent className="flex flex-col items-center justify-center py-16 gap-3">
                                <div className="p-4 bg-slate-100 rounded-2xl">
                                    <FolderOpen className="w-8 h-8 text-slate-300" />
                                </div>
                                <p className="text-sm font-bold text-slate-400">{t('No Employee Selected')}</p>
                                <p className="text-xs text-slate-300 font-medium text-center max-w-xs">
                                    {t('Search and select an employee at the top to see their complete earnings & deductions history.')}
                                </p>
                            </CardContent>
                        </Card>
                    ) : historyLoading ? (
                        <Card className="border-none shadow-sm bg-white">
                            <CardContent className="flex items-center justify-center py-16">
                                <Loader2 className="w-6 h-6 animate-spin text-indigo-400 mr-3" />
                                <span className="text-sm text-slate-400 font-medium">{t('Loading history...')}</span>
                            </CardContent>
                        </Card>
                    ) : history.length === 0 ? (
                        <Card className="border-2 border-dashed border-slate-100 bg-white shadow-none">
                            <CardContent className="flex flex-col items-center justify-center py-14 gap-2">
                                <Calendar className="w-8 h-8 text-slate-200" />
                                <p className="text-sm font-bold text-slate-400">{t('No Past Entries Found')}</p>
                                <p className="text-xs text-slate-300">{t('This employee has no recorded earnings or deductions yet.')}</p>
                            </CardContent>
                        </Card>
                    ) : (
                        /* Grouped list */
                        <div className="space-y-6">
                            {Object.entries(groupedHistory)
                                .sort(([a], [b]) => b.localeCompare(a))
                                .map(([monthYear, entries]) => (
                                    <div key={monthYear}>
                                        {/* Month header */}
                                        <div className="flex items-center gap-3 mb-3">
                                            <div className="px-3 py-1 bg-indigo-50 text-indigo-700 rounded-full text-[11px] font-black uppercase tracking-widest border border-indigo-100">
                                                {formatMonth(monthYear)}
                                            </div>
                                            <div className="flex-1 h-px bg-slate-100" />
                                            <span className="text-[10px] text-slate-400 font-bold">
                                                {entries.length} {entries.length === 1 ? 'record' : 'records'}
                                            </span>
                                        </div>

                                        {/* Entry cards */}
                                        <div className="space-y-2">
                                            {entries.map((entry) => {
                                                const earnings = entry.details.filter(d => d.type === 'earning');
                                                const deductions = entry.details.filter(d => d.type === 'deduction');
                                                const net = entry.total_earnings - entry.total_deductions;

                                                return (
                                                    <Card key={entry.id} className="border border-slate-100 shadow-sm bg-white hover:border-indigo-200 hover:shadow-md transition-all group">
                                                        <CardContent className="p-4">
                                                            <div className="flex flex-col md:flex-row md:items-center gap-4">
                                                                {/* Date */}
                                                                <div className="flex items-center gap-3 min-w-[140px]">
                                                                    <div className="w-10 h-10 rounded-xl bg-indigo-50 flex items-center justify-center border border-indigo-100 shrink-0">
                                                                        <Calendar className="w-4 h-4 text-indigo-500" />
                                                                    </div>
                                                                    <div>
                                                                        <div className="text-[9px] text-slate-400 font-bold uppercase tracking-widest">{t('Entry Date')}</div>
                                                                        <div className="text-sm font-black text-slate-800">{formatDate(entry.date)}</div>
                                                                    </div>
                                                                </div>

                                                                {/* Chips */}
                                                                <div className="flex-1 flex flex-wrap gap-1.5 min-w-0">
                                                                    {earnings.map((d, i) => (
                                                                        <span key={`e-${i}`} className="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-50 text-emerald-700 rounded-full text-[10px] font-bold border border-emerald-100 whitespace-nowrap">
                                                                            <ArrowUpRight className="w-2.5 h-2.5 shrink-0" />
                                                                            {d.name || 'Earning'} · {d.mode === 'day' ? `${d.value}d` : `₹${Number(d.value).toLocaleString('en-IN')}`}
                                                                        </span>
                                                                    ))}
                                                                    {deductions.map((d, i) => (
                                                                        <span key={`d-${i}`} className="inline-flex items-center gap-1 px-2 py-0.5 bg-rose-50 text-rose-700 rounded-full text-[10px] font-bold border border-rose-100 whitespace-nowrap">
                                                                            <ArrowDownRight className="w-2.5 h-2.5 shrink-0" />
                                                                            {d.name || 'Deduction'} · {d.mode === 'day' ? `${d.value}d` : `₹${Number(d.value).toLocaleString('en-IN')}`}
                                                                        </span>
                                                                    ))}
                                                                    {entry.details.length === 0 && (
                                                                        <span className="text-xs text-slate-300 italic">No details recorded</span>
                                                                    )}
                                                                </div>

                                                                {/* Totals */}
                                                                <div className="flex items-center gap-3 shrink-0">
                                                                    <div className="text-right">
                                                                        <div className="text-[9px] text-emerald-500 font-black uppercase tracking-widest">Earnings</div>
                                                                        <div className="text-sm font-black text-emerald-600">₹{Number(entry.total_earnings).toLocaleString('en-IN')}</div>
                                                                    </div>
                                                                    <div className="text-right">
                                                                        <div className="text-[9px] text-rose-500 font-black uppercase tracking-widest">Deductions</div>
                                                                        <div className="text-sm font-black text-rose-600">₹{Number(entry.total_deductions).toLocaleString('en-IN')}</div>
                                                                    </div>
                                                                    <div className={`text-right px-2.5 py-1 rounded-lg ${net >= 0 ? 'bg-emerald-50 border border-emerald-100' : 'bg-rose-50 border border-rose-100'}`}>
                                                                        <div className="text-[9px] font-black uppercase tracking-widest text-slate-400">Net</div>
                                                                        <div className={`text-sm font-black ${net >= 0 ? 'text-emerald-700' : 'text-rose-700'}`}>
                                                                            {net >= 0 ? '+' : ''}₹{Math.abs(net).toLocaleString('en-IN')}
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                {/* Load button */}
                                                                <Button
                                                                    type="button"
                                                                    variant="outline"
                                                                    size="sm"
                                                                    className="h-9 px-4 text-[11px] font-black border-indigo-200 text-indigo-600 hover:bg-indigo-50 hover:border-indigo-400 shrink-0 transition-all"
                                                                    onClick={() => loadHistoryEntry(entry)}
                                                                >
                                                                    <ChevronRight className="w-3.5 h-3.5 mr-1" />
                                                                    {t('Load')}
                                                                </Button>
                                                            </div>

                                                            {entry.remark && (
                                                                <div className="mt-3 pt-3 border-t border-slate-50 flex items-start gap-2">
                                                                    <ShieldCheck className="w-3 h-3 text-slate-300 mt-0.5 shrink-0" />
                                                                    <p className="text-[11px] text-slate-400 italic">{entry.remark}</p>
                                                                </div>
                                                            )}
                                                        </CardContent>
                                                    </Card>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                        </div>
                    )}
                </div>
            </div>
        </PageTemplate>
    );
}
