import React, { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { Head, useForm, usePage, router } from '@inertiajs/react';
import { 
    Card, 
    CardContent, 
    CardHeader, 
    CardTitle, 
    CardDescription,
    CardFooter
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    Select, 
    SelectContent, 
    SelectItem, 
    SelectTrigger, 
    SelectValue 
} from '@/components/ui/select';
import { 
    Save, 
    Trash2, 
    User, 
    Briefcase, 
    ShieldCheck, 
    Calendar,
    ArrowLeft,
    PlusCircle,
    MinusCircle,
    Layers,
    Clock,
    Package,
    Check,
    LayoutGrid,
    Info,
    RotateCcw,
    Edit2,
    FileText
} from 'lucide-react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';
import { toast } from 'sonner';

import { Combobox, ComboboxOption } from '@/components/ui/combobox';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";

interface Employee {
    id: number;
    name: string;
    scroll_no: string;
}

interface Material {
    id: number;
    name: string;
    code: string;
    rate: number | string;
}

interface Shift {
    id: number;
    name: string;
}

interface Entry {
    id: number;
    employee_id: number;
    date: string;
    production_qty: string | number;
    rate: string | number;
    amount: string | number;
    remark: string;
    created_at: string;
    employee: {
        user: {
            name: string;
        };
        employee_id: string;
    };
    material_item?: {
        name: string;
    };
    shift?: {
        id: number;
        name: string;
    };
}

interface PageProps {
    employees: Employee[];
    materials: Material[];
    shifts: Shift[];
    recent_entries: Entry[];
    selected_employee_id: string | number | null;
    selected_date: string;
    auth: {
        user: any;
    };
}

export default function DailyProductionAttendanceEntryIndex() {
    const { employees, materials, shifts, recent_entries, selected_employee_id, selected_date } = usePage<PageProps>().props;
    const { t } = useTranslation();

    const [loading, setLoading] = useState(false);
    const [employeeDetails, setEmployeeDetails] = useState<any>(null);
    const [selectedMaterial, setSelectedMaterial] = useState<Material | null>(null);

    const { data, setData, post, processing, reset, errors, clearErrors } = useForm({
        id: '', // For editing existing records
        employee_id: '',
        date: selected_date || new Date().toISOString().slice(0, 10),
        shift_id: '',
        material_item_id: '',
        production_qty: '0',
        rate: '0',
        amount: '0',
        remark: '',
    });

    useEffect(() => {
        if (selected_employee_id && selected_employee_id !== 'null' && selected_employee_id !== 'undefined') {
            handleEmployeeChange(selected_employee_id.toString());
        }
    }, [selected_employee_id]);

    // Recalculate amount whenever qty or rate changes
    useEffect(() => {
        const qty = parseFloat(data.production_qty) || 0;
        const rate = parseFloat(data.rate) || 0;
        setData('amount', (qty * rate).toFixed(2));
    }, [data.production_qty, data.rate]);

    const handleEmployeeChange = async (val: string) => {
        if (!val || val === 'null' || val === 'undefined') return;
        
        setData('employee_id', val);
        clearErrors('employee_id');
        setLoading(true);
        try {
            const response = await axios.get(route('hr.daily-production-attendance-entry.employee-details', val));
            const { employee, existing_entry } = response.data;
            setEmployeeDetails(employee);
            
            // Set default shift if employee has one
            if (employee.shift_id && !existing_entry) {
                setData('shift_id', employee.shift_id.toString());
            }

            if (existing_entry) {
                loadEntryIntoForm(existing_entry);
                toast.info(t('Existing record loaded for this date'));
            } else {
                setData((prevData) => ({
                    ...prevData,
                    employee_id: val,
                    production_qty: '0',
                    rate: '0',
                    amount: '0',
                    remark: '',
                    id: '',
                }));
                setSelectedMaterial(null);
            }
        } catch (error) {
            console.error('Fetch error:', error);
            toast.error(t('Failed to fetch employee details'));
        } finally {
            setLoading(false);
        }
    };

    const loadEntryIntoForm = (entry: any) => {
        setData((prevData) => ({
            ...prevData,
            id: entry.id.toString(),
            employee_id: entry.employee_id.toString(),
            shift_id: entry.shift_id?.toString() || '',
            material_item_id: entry.material_item_id?.toString() || '',
            production_qty: entry.production_qty.toString(),
            rate: entry.rate.toString(),
            amount: entry.amount.toString(),
            remark: entry.remark || '',
            date: entry.date || prevData.date,
        }));
        
        if (entry.material_item_id) {
            const mat = materials.find(m => m.id.toString() === entry.material_item_id.toString());
            setSelectedMaterial(mat || null);
        }
    };

    const handleMaterialChange = (val: string) => {
        setData('material_item_id', val);
        clearErrors('material_item_id');
        const material = materials.find(m => m.id.toString() === val);
        if (material) {
            setSelectedMaterial(material);
            setData('rate', material.rate.toString());
        } else {
            setSelectedMaterial(null);
            setData('rate', '0');
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('hr.daily-production-attendance-entry.store'), {
            onSuccess: () => {
                toast.success(t('Daily production entry saved successfully'));
                // resetForm();
            },
        });
    };

    const resetForm = () => {
        reset();
        setEmployeeDetails(null);
        setSelectedMaterial(null);
        clearErrors();
    };

    const handleDelete = (id: number) => {
        if (confirm(t('Are you sure you want to delete this entry?'))) {
            router.delete(route('hr.daily-production-attendance-entry.destroy', id), {
                onSuccess: () => toast.success(t('Entry deleted successfully')),
            });
        }
    };

    const handleEdit = (entry: Entry) => {
        loadEntryIntoForm(entry);
        handleEmployeeChange(entry.employee_id.toString());
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Transaction'), href: route('hr.daily-production-attendance-entry.index') },
        { title: t('Attendance Production Entry') }
    ];

    const employeeOptions: ComboboxOption[] = employees.map(emp => ({
        label: `[${emp.scroll_no}] ${emp.name}`,
        value: emp.id.toString()
    }));

    return (
        <PageTemplate 
            title={t('Attendance Production Entry')} 
            url={route('hr.daily-production-attendance-entry.index')}
            breadcrumbs={breadcrumbs}
            noPadding
        >
            <div className="max-w-6xl mx-auto p-4 space-y-6 pb-20">
                <form onSubmit={submit} className="space-y-4">
                    {/* Header Action Bar */}
                    <Card className="border-none shadow-sm bg-white">
                        <CardHeader className="p-4 bg-slate-50/50 border-b border-slate-100">
                            <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                                <div className="flex flex-wrap items-center gap-3 w-full md:w-auto">
                                    <div className="flex items-center gap-2">
                                        <Label className="text-[10px] font-bold uppercase text-slate-500">{t('Date')}</Label>
                                        <Input 
                                            type="date" 
                                            value={data.date} 
                                            onChange={(e) => setData('date', e.target.value)}
                                            max={new Date().toISOString().split('T')[0]}
                                            className="h-9 w-40 bg-white border-slate-200"
                                        />
                                    </div>
                                    <div className="flex-1 md:w-64">
                                        <div className="space-y-1">
                                            <Combobox
                                                options={employeeOptions}
                                                value={data.employee_id}
                                                onChange={handleEmployeeChange}
                                                placeholder={t('Search employee...')}
                                                className={`h-9 w-full bg-white border-slate-200 ${errors.employee_id ? 'border-red-500 ring-1 ring-red-500' : ''}`}
                                            />
                                            {errors.employee_id && <p className="text-[10px] text-red-500 font-bold">{errors.employee_id}</p>}
                                        </div>
                                    </div>
                                    <div className="hidden md:block">
                                        <div className="h-9 px-4 bg-slate-100 border border-slate-200 rounded-md flex items-center text-sm font-bold text-slate-600">
                                            {employeeDetails?.scroll_no || '---'}
                                        </div>
                                    </div>
                                </div>
                                <div className="flex items-center gap-2 w-full md:w-auto">
                                    <Button 
                                        type="submit" 
                                        className="flex-1 md:flex-none h-10 bg-primary hover:bg-primary/90 text-white font-bold px-8 shadow-sm transition-all active:scale-95"
                                        disabled={processing || !data.employee_id}
                                    >
                                        <Save className="w-4 h-4 mr-2" />
                                        {data.id ? t('Update Entry') : t('Save Entry')}
                                    </Button>
                                    <Button 
                                        type="button" 
                                        variant="outline" 
                                        className="h-10 text-slate-500 border-slate-200 hover:bg-slate-50 px-4"
                                        onClick={resetForm}
                                    >
                                        <RotateCcw className="w-4 h-4 mr-2" />
                                        {t('Reset')}
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>

                        {/* Employee Quick Info Bar */}
                        {employeeDetails && (
                            <CardContent className="p-0 border-b border-slate-100">
                                <div className="grid grid-cols-2 md:grid-cols-5 gap-0 divide-x divide-slate-100 bg-slate-50/30">
                                    <div className="p-3 px-6">
                                        <div className="text-[10px] text-slate-400 uppercase font-bold mb-1 flex items-center gap-1">
                                            <Briefcase className="w-3 h-3" /> {t('Department')}
                                        </div>
                                        <div className="text-sm font-bold text-slate-700">{employeeDetails.department || 'N/A'}</div>
                                    </div>
                                    <div className="p-3 px-6">
                                        <div className="text-[10px] text-slate-400 uppercase font-bold mb-1 flex items-center gap-1">
                                            <ShieldCheck className="w-3 h-3" /> {t('Designation')}
                                        </div>
                                        <div className="text-sm font-bold text-slate-700">{employeeDetails.designation || 'N/A'}</div>
                                    </div>
                                    <div className="p-3 px-6">
                                        <div className="text-[10px] text-slate-400 uppercase font-bold mb-1 flex items-center gap-1">
                                            <User className="w-3 h-3" /> {t('Category')}
                                        </div>
                                        <div className="text-sm font-bold text-slate-700">{employeeDetails.category || 'N/A'}</div>
                                    </div>
                                    <div className="p-3 px-6">
                                        <div className="text-[10px] text-slate-400 uppercase font-bold mb-1 flex items-center gap-1">
                                            <Layers className="w-3 h-3" /> {t('Section')}
                                        </div>
                                        <div className="text-sm font-bold text-slate-700">{employeeDetails.section || 'N/A'}</div>
                                    </div>
                                    <div className="p-3 px-6">
                                        <div className="text-[10px] text-slate-400 uppercase font-bold mb-1 flex items-center gap-1">
                                            <Calendar className="w-3 h-3" /> {t('Status')}
                                        </div>
                                        <div className="text-sm font-bold text-green-600 flex items-center gap-2">
                                            <span className="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                                            {t('Active')}
                                        </div>
                                    </div>
                                </div>
                            </CardContent>
                        )}
                    </Card>

                    <div className="grid grid-cols-1 lg:grid-cols-12 gap-4">
                        {/* Left: Production Form */}
                        <div className="lg:col-span-8 space-y-4">
                            <Card className="border-none shadow-sm bg-white overflow-hidden">
                                <CardHeader className="p-4 border-b border-slate-50 bg-slate-50/30">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <PlusCircle className="w-4 h-4 text-primary" />
                                            <CardTitle className="text-sm font-bold uppercase tracking-tight text-slate-700">
                                                {data.id ? t('Edit Production Entry') : t('Add Production Details')}
                                            </CardTitle>
                                        </div>
                                        {data.id && (
                                            <Badge variant="outline" className="text-[10px] bg-blue-50 text-blue-600 border-blue-100 uppercase">
                                                {t('Editing ID')}: #{data.id}
                                            </Badge>
                                        )}
                                    </div>
                                </CardHeader>
                                <CardContent className="p-6 space-y-6">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div className="space-y-1.5">
                                            <Label className={`text-xs font-semibold ${errors.material_item_id ? 'text-red-500' : 'text-slate-600'}`}>
                                                {t('Select Material / Operation')} *
                                            </Label>
                                            <div className="flex gap-2">
                                                <div className="flex-1">
                                                    <Select value={data.material_item_id} onValueChange={handleMaterialChange}>
                                                        <SelectTrigger className={`h-10 ${errors.material_item_id ? 'border-red-500 ring-1 ring-red-500' : 'border-slate-200'}`}>
                                                            <SelectValue placeholder={t('Select Material')} />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {materials.map(mat => (
                                                                <SelectItem key={mat.id} value={mat.id.toString()}>
                                                                    {mat.name}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                    {errors.material_item_id && <p className="text-[10px] text-red-500 font-bold mt-1">{errors.material_item_id}</p>}
                                                </div>
                                                <div className="w-20">
                                                    <div className="h-10 flex items-center justify-center bg-slate-50 border border-slate-200 rounded-md text-xs font-black text-primary">
                                                        {selectedMaterial?.code || '---'}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div className="space-y-1.5">
                                            <Label className={`text-xs font-semibold ${errors.shift_id ? 'text-red-500' : 'text-slate-600'}`}>
                                                {t('Shift Selection')} *
                                            </Label>
                                            <Select value={data.shift_id} onValueChange={(v) => { setData('shift_id', v); clearErrors('shift_id'); }}>
                                                <SelectTrigger className={`h-10 ${errors.shift_id ? 'border-red-500 ring-1 ring-red-500' : 'border-slate-200'}`}>
                                                    <SelectValue placeholder={t('Select Shift')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {shifts.map(shift => (
                                                        <SelectItem key={shift.id} value={shift.id.toString()}>
                                                            {shift.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.shift_id && <p className="text-[10px] text-red-500 font-bold mt-1">{errors.shift_id}</p>}
                                        </div>
                                    </div>

                                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6 pt-4 border-t border-slate-50">
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-semibold text-slate-600">{t('Rate')} *</Label>
                                            <div className="relative">
                                                <Input 
                                                    type="number" 
                                                    step="0.01"
                                                    value={data.rate} 
                                                    onChange={(e) => setData('rate', e.target.value)} 
                                                    className="h-12 text-xl font-bold text-primary bg-primary/5 border-primary/10 pl-8 focus:ring-primary/20" 
                                                />
                                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-primary/40 font-bold">₹</span>
                                            </div>
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label className={`text-xs font-semibold ${errors.production_qty ? 'text-red-500' : 'text-slate-600'}`}>{t('Production Quantity')} *</Label>
                                            <Input 
                                                type="number" 
                                                value={data.production_qty} 
                                                onChange={(e) => { setData('production_qty', e.target.value); clearErrors('production_qty'); }} 
                                                className={`h-12 text-2xl font-bold text-slate-800 focus:ring-primary/20 ${errors.production_qty ? 'border-red-500 ring-1 ring-red-500' : 'border-slate-200'}`} 
                                            />
                                            {errors.production_qty && <p className="text-[10px] text-red-500 font-bold mt-1">{errors.production_qty}</p>}
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-semibold text-slate-600">{t('Total Amount')}</Label>
                                            <div className="relative">
                                                <Input 
                                                    readOnly 
                                                    value={data.amount} 
                                                    className="h-12 text-2xl font-bold text-green-600 bg-green-50 border-green-100 pl-8" 
                                                />
                                                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-green-600/40 font-bold">₹</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div className="space-y-1.5 pt-2">
                                        <Label className="text-xs font-semibold text-slate-600">{t('Remarks / Notes')}</Label>
                                        <Input 
                                            value={data.remark} 
                                            onChange={(e) => setData('remark', e.target.value)} 
                                            placeholder={t('Optional production notes...')}
                                            className="h-10 border-slate-200"
                                        />
                                    </div>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Right: Employee Image & Summary */}
                        <div className="lg:col-span-4 space-y-4">
                            <Card className="border-none shadow-sm bg-white overflow-hidden h-full">
                                <CardContent className="p-6 flex flex-col items-center justify-center space-y-6 h-full">
                                    <div className="relative group">
                                        <div className="w-48 h-48 rounded-full border-4 border-slate-50 shadow-md overflow-hidden bg-slate-100">
                                            {employeeDetails?.avatar ? (
                                                <img src={employeeDetails.avatar} alt="Profile" className="w-full h-full object-cover transition-transform group-hover:scale-110" />
                                            ) : (
                                                <div className="w-full h-full flex items-center justify-center bg-slate-100 text-slate-300">
                                                    <User className="w-24 h-24" />
                                                </div>
                                            )}
                                        </div>
                                        <div className="absolute -bottom-2 left-1/2 -translate-x-1/2 bg-white border border-slate-100 px-3 py-1 rounded-full shadow-sm">
                                            <span className="text-[10px] font-black uppercase text-slate-400 tracking-widest">{t('Photo')}</span>
                                        </div>
                                    </div>

                                    <div className="w-full space-y-4 pt-4">
                                        <div className="bg-slate-50/50 p-4 rounded-xl border border-slate-100 space-y-3">
                                            <div className="flex justify-between items-center text-xs">
                                                <span className="text-slate-500 font-medium uppercase tracking-wider">{t('Category')}</span>
                                                <span className="text-slate-800 font-black">{employeeDetails?.category || '---'}</span>
                                            </div>
                                            <div className="h-px bg-slate-200/50"></div>
                                            <div className="flex justify-between items-center text-xs">
                                                <span className="text-slate-500 font-medium uppercase tracking-wider">{t('Employee Name')}</span>
                                                <span className="text-slate-800 font-black text-right">{employeeDetails?.name || '---'}</span>
                                            </div>
                                        </div>
                                        
                                        <div className="flex items-center gap-2 p-3 bg-blue-50/30 rounded-lg border border-blue-100/50 text-blue-600/70">
                                            <Info className="w-4 h-4" />
                                            <span className="text-[10px] font-bold uppercase tracking-tight">{t('Verify production details before saving')}</span>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </form>

                {/* Recent Entries List */}
                <Card className="border-none shadow-sm bg-white overflow-hidden">
                    <CardHeader className="p-4 border-b border-slate-50 bg-slate-50/30 flex flex-row items-center justify-between">
                        <div className="flex items-center gap-2">
                            <LayoutGrid className="w-4 h-4 text-primary" />
                            <CardTitle className="text-sm font-bold uppercase tracking-tight text-slate-700">
                                {t('Recent Production Entries')}
                            </CardTitle>
                        </div>
                        <div className="text-[10px] font-bold text-slate-400 uppercase tracking-widest flex items-center gap-2">
                            <Calendar className="w-3 h-3" /> {t('Date')}: {data.date}
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader className="bg-slate-50/50">
                                <TableRow>
                                    <TableHead className="text-[10px] font-bold uppercase text-slate-500">{t('Employee')}</TableHead>
                                    <TableHead className="text-[10px] font-bold uppercase text-slate-500">{t('Material')}</TableHead>
                                    <TableHead className="text-[10px] font-bold uppercase text-slate-500">{t('Shift')}</TableHead>
                                    <TableHead className="text-[10px] font-bold uppercase text-slate-500 text-right">{t('Rate')}</TableHead>
                                    <TableHead className="text-[10px] font-bold uppercase text-slate-500 text-right">{t('Qty')}</TableHead>
                                    <TableHead className="text-[10px] font-bold uppercase text-slate-500 text-right">{t('Amount')}</TableHead>
                                    <TableHead className="text-[10px] font-bold uppercase text-slate-500 text-center">{t('Created At')}</TableHead>
                                    <TableHead className="text-[10px] font-bold uppercase text-slate-500 text-center w-28">{t('Action')}</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recent_entries.length > 0 ? (
                                    recent_entries.map((entry) => (
                                        <TableRow key={entry.id} className="hover:bg-slate-50/50 transition-colors">
                                            <TableCell>
                                                <div className="flex flex-col">
                                                    <span className="font-bold text-slate-700">{entry.employee.user.name}</span>
                                                    <span className="text-[10px] text-slate-400">#{entry.employee.employee_id}</span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="font-medium text-slate-600">{entry.material_item?.name || '---'}</TableCell>
                                            <TableCell>
                                                <span className="px-2 py-0.5 bg-slate-100 text-[10px] font-bold text-slate-500 rounded-full">
                                                    {entry.shift?.name || '---'}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right font-bold text-slate-600">₹{entry.rate}</TableCell>
                                            <TableCell className="text-right font-black text-slate-800">{entry.production_qty}</TableCell>
                                            <TableCell className="text-right font-black text-green-600">₹{entry.amount}</TableCell>
                                            <TableCell className="text-center text-[10px] text-slate-400 font-medium">
                                                {new Date(entry.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <div className="flex items-center justify-center gap-1">
                                                    <Button 
                                                        variant="ghost" 
                                                        size="icon" 
                                                        className="h-8 w-8 text-blue-500 hover:text-blue-600 hover:bg-blue-50"
                                                        onClick={() => handleEdit(entry)}
                                                        title={t('Edit')}
                                                    >
                                                        <Edit2 className="w-4 h-4" />
                                                    </Button>
                                                    <Button 
                                                        variant="ghost" 
                                                        size="icon" 
                                                        className="h-8 w-8 text-red-500 hover:text-red-600 hover:bg-red-50"
                                                        onClick={() => handleDelete(entry.id)}
                                                        title={t('Delete')}
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={8} className="h-32 text-center text-slate-400 italic">
                                            {t('No entries found for this date')}
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </PageTemplate>
    );
}
