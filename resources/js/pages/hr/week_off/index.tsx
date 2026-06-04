import { useState, useEffect } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Check, Save, Building2, Layers, MousePointerClick, User, Calendar as CalendarIcon, Search, Trash2, Plus, FileText } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';
import { MultiSelect } from '@/components/ui/multi-select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Input } from '@/components/ui/input';
import { Combobox } from '@/components/ui/combobox';

export default function WeekOffIndex() {
    const { t } = useTranslation();
    const { 
        weekOff, activeBranchId, branches, departments, designations, sections, shifts, categories, 
        employmentType: propEmploymentType, employees 
    } = usePage().props as any;

    const [employmentType, setEmploymentType] = useState<'Employee' | 'Labour'>(propEmploymentType || 'Employee');

    // Initialize separate states
    const initialType = weekOff?.type || 'weekly';
    const initialSettings = weekOff?.settings || {};

    const [type, setType] = useState<'weekly' | 'monthly'>(initialType);
    const [loading, setLoading] = useState(false);

    // Bulk Update State
    const [applyMode, setApplyMode] = useState<'current' | 'all' | 'selected'>('current');
    const [selectedBranchIds, setSelectedBranchIds] = useState<string[]>([]);

    // Split settings based on initial type to avoid mixups
    const [weeklySettings, setWeeklySettings] = useState<string[]>(
        initialType === 'weekly' && Array.isArray(initialSettings) ? initialSettings : []
    );

    const [monthlySettings, setMonthlySettings] = useState<Record<string, string[]>>(
        initialType === 'monthly' && !Array.isArray(initialSettings) ? initialSettings : {}
    );

    // Individual Off State
    const [selectedEmployeeIds, setSelectedEmployeeIds] = useState<string[]>([]);
    const [recurringWeekOffs, setRecurringWeekOffs] = useState<string[]>([]);
    const [offDates, setOffDates] = useState<string[]>([]);
    const [newOffDate, setNewOffDate] = useState<string>('');
    const [searchQuery, setSearchQuery] = useState<string>('');
    const [currentPage, setCurrentPage] = useState(1);
    const itemsPerPage = 50;

    // Filter States for Selection
    const [filterBranchId, setFilterBranchId] = useState<string>(activeBranchId?.toString() || 'all');
    const [filterDeptId, setFilterDeptId] = useState<string>('all');
    const [filterShiftId, setFilterShiftId] = useState<string>('all');
    const [filterCategoryId, setFilterCategoryId] = useState<string>('all');

    // Individual Config Mode
    const [individualType, setIndividualType] = useState<'weekly' | 'monthly'>('weekly');
    const [individualMonthlySettings, setIndividualMonthlySettings] = useState<Record<string, string[]>>({});

    useEffect(() => {
        setFilterBranchId(activeBranchId?.toString() || 'all');
        setFilterDeptId('all');
        setFilterShiftId('all');
        setFilterCategoryId('all');
        setCurrentPage(1);
    }, [activeBranchId]);

    const selectionFilteredEmployees = employees.filter((e: any) => {
        const matchesBranch = filterBranchId === 'all' || e.branch_id?.toString() === filterBranchId;
        const matchesDept = filterDeptId === 'all' || e.department_id?.toString() === filterDeptId;
        const matchesShift = filterShiftId === 'all' || e.shift_id?.toString() === filterShiftId;
        const matchesCategory = filterCategoryId === 'all' || e.category_id?.toString() === filterCategoryId;
        
        return matchesBranch && matchesDept && matchesShift && matchesCategory;
    });

    const filteredEmployees = employees.filter((e: any) => {
        const matchesSearch = !searchQuery || 
            (e.user?.name || '').toLowerCase().includes(searchQuery.toLowerCase()) || 
            (e.employee_id || '').includes(searchQuery);
            
        const matchesBranch = filterBranchId === 'all' || e.branch_id?.toString() === filterBranchId;
        const matchesDept = filterDeptId === 'all' || e.department_id?.toString() === filterDeptId;
        const matchesShift = filterShiftId === 'all' || e.shift_id?.toString() === filterShiftId;
        const matchesCategory = filterCategoryId === 'all' || e.category_id?.toString() === filterCategoryId;
        
        return matchesSearch && matchesBranch && matchesDept && matchesShift && matchesCategory;
    });

    useEffect(() => {
        const newType = weekOff?.type || 'weekly';
        const newSettings = weekOff?.settings || {};

        setType(newType);

        if (newType === 'weekly') {
            setWeeklySettings(Array.isArray(newSettings) ? newSettings : []);
            setMonthlySettings({});
        } else {
            setMonthlySettings(!Array.isArray(newSettings) ? newSettings : {});
            setWeeklySettings([]);
        }
    }, [weekOff]);

    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    const weeks = [1, 2, 3, 4, 5];

    const handleWeeklyChange = (day: string) => {
        if (weeklySettings.includes(day)) {
            setWeeklySettings(weeklySettings.filter((d) => d !== day));
        } else {
            setWeeklySettings([...weeklySettings, day]);
        }
    };

    const handleMonthlyChange = (week: number, day: string) => {
        const currentYearWeek = monthlySettings[week] || [];
        let newWeekDays;
        if (currentYearWeek.includes(day)) {
            newWeekDays = currentYearWeek.filter((d) => d !== day);
        } else {
            newWeekDays = [...currentYearWeek, day];
        }
        setMonthlySettings({ ...monthlySettings, [week]: newWeekDays });
    };

    const handleIndividualMonthlyChange = (week: number, day: string) => {
        const currentWeekDays = individualMonthlySettings[week] || [];
        let newWeekDays;
        if (currentWeekDays.includes(day)) {
            newWeekDays = currentWeekDays.filter((d) => d !== day);
        } else {
            newWeekDays = [...currentWeekDays, day];
        }
        setIndividualMonthlySettings({ ...individualMonthlySettings, [week]: newWeekDays });
    };

    const handleTypeChange = (newType: 'weekly' | 'monthly') => {
        setType(newType);
    };

    const handleEmploymentTypeChange = (val: 'Employee' | 'Labour') => {
        setEmploymentType(val);
        router.get(route('hr.week-offs.index'), { employment_type: val }, { preserveState: true, preserveScroll: true });
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setLoading(true);
        const settingsToSave = type === 'weekly' ? weeklySettings : monthlySettings;
        router.post(route('hr.week-offs.store'), {
            type,
            settings: settingsToSave,
            apply_mode: applyMode,
            selected_branch_ids: selectedBranchIds,
            employment_type: employmentType
        }, {
            onSuccess: () => setLoading(false),
            onError: () => setLoading(false)
        });
    };

    // Individual Off Methods
    const handleEmployeeSelect = (ids: string[]) => {
        setSelectedEmployeeIds(ids);
        if (ids.length === 1) {
            const emp = employees.find((e: any) => e.id.toString() === ids[0]);
            if (emp) {
                setRecurringWeekOffs(emp.week_off ? emp.week_off.split(',') : []);
                if (emp.individual_week_offs) {
                    setOffDates(emp.individual_week_offs.map((off: any) => off.off_date.split('T')[0]));
                } else {
                    setOffDates([]);
                }
            }
        } else {
            setRecurringWeekOffs([]);
            setOffDates([]);
        }
    };

    const addOffDate = () => {
        if (!newOffDate) return;
        if (offDates.includes(newOffDate)) {
            toast.error(t('Date already added'));
            return;
        }
        setOffDates([...offDates, newOffDate]);
        setNewOffDate('');
    };

    const removeOffDate = (date: string) => {
        setOffDates(offDates.filter(d => d !== date));
    };

    const saveIndividualOffs = () => {
        if (selectedEmployeeIds.length === 0) {
            toast.error(t('Please select at least one employee'));
            return;
        }
        setLoading(true);

        const recurringData = individualType === 'weekly' 
            ? recurringWeekOffs 
            : JSON.stringify(individualMonthlySettings);

        router.post(route('hr.week-offs.individual'), {
            employee_ids: selectedEmployeeIds,
            recurring_week_offs: recurringData,
            week_off_type: individualType,
            off_dates: offDates
        }, {
            onSuccess: () => {
                setLoading(false);
                toast.success(t('Individual changes saved successfully'));
            },
            onError: () => setLoading(false)
        });
    };

    const breadcrumbs = [
        { title: t('Dashboard'), href: route('dashboard') },
        { title: t('Week Offs') }
    ];

    return (
        <PageTemplate
            title={t("Week Off Management")}
            breadcrumbs={breadcrumbs}
            url="/week-offs"
            actions={[
                {
                    label: t('Download Report'),
                    icon: <FileText className="h-4 w-4 mr-2" />,
                    variant: 'outline' as const,
                    className: 'border-slate-300 text-slate-700 hover:bg-slate-50',
                    onClick: () => {
                        window.open(route('hr.reports.master_listing', { type: 'WOF', branch_id: activeBranchId }), '_blank');
                    }
                }
            ]}
        >
            <div className="max-w-5xl mx-auto">
                <Tabs defaultValue="individual" className="w-full">
                    <TabsContent value="individual">
                        <Card className="border-none shadow-xl">
                            <CardHeader className="bg-gradient-to-r from-[#1e2978]/10 to-transparent">
                                <CardTitle>{t('Employee Individual Offs')}</CardTitle>
                                <CardDescription>{t('Override branch settings for specific employees.')}</CardDescription>
                            </CardHeader>
                            <CardContent className="p-6 space-y-6">
                                <div className="max-w-2xl mx-auto space-y-6">
                                    <div className="space-y-4">
                                        <div className="space-y-4">
                                            <div className="flex items-center justify-between">
                                                <Label className="text-sm font-bold text-muted-foreground uppercase tracking-wider">{t('Step 1: Select Employees')}</Label>
                                                <div className="bg-slate-100 px-3 py-1 rounded-full text-[9px] font-black text-slate-500 uppercase tracking-widest">
                                                    {selectionFilteredEmployees.length} {t('Matching')}
                                                </div>
                                            </div>
                                            
                                            {/* Advanced Filters */}
                                            <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                                                <Combobox
                                                    value={filterBranchId}
                                                    onChange={setFilterBranchId}
                                                    options={[{ label: t('All Branches'), value: 'all' }, ...branches.map((b: any) => ({ label: b.name, value: b.id.toString() }))]}
                                                    placeholder={t('Branch')}
                                                    className="h-9 text-[10px]"
                                                />
                                                <Combobox
                                                    value={filterDeptId}
                                                    onChange={setFilterDeptId}
                                                    options={[
                                                        { label: t('All Depts'), value: 'all' }, 
                                                        ...departments
                                                            .filter((d: any) => filterBranchId === 'all' || d.branch_id?.toString() === filterBranchId)
                                                            .map((d: any) => ({ label: d.name, value: d.id.toString() }))
                                                    ]}
                                                    placeholder={t('Department')}
                                                    className="h-9 text-[10px]"
                                                />
                                                <Combobox
                                                    value={filterShiftId}
                                                    onChange={setFilterShiftId}
                                                    options={[
                                                        { label: t('All Shifts'), value: 'all' }, 
                                                        ...shifts
                                                            .filter((s: any) => filterBranchId === 'all' || s.branch_id?.toString() === filterBranchId)
                                                            .map((s: any) => ({ label: s.name, value: s.id.toString() }))
                                                    ]}
                                                    placeholder={t('Shift')}
                                                    className="h-9 text-[10px]"
                                                />
                                                <Combobox
                                                    value={filterCategoryId}
                                                    onChange={setFilterCategoryId}
                                                    options={[
                                                        { label: t('All Categories'), value: 'all' }, 
                                                        ...categories
                                                            .filter((c: any) => filterBranchId === 'all' || c.branch_id?.toString() === filterBranchId || c.branch_id === null)
                                                            .map((c: any) => ({ label: c.name, value: c.id.toString() }))
                                                    ]}
                                                    placeholder={t('Category')}
                                                    className="h-9 text-[10px]"
                                                />
                                            </div>

                                            <MultiSelect 
                                                selected={selectedEmployeeIds} 
                                                onChange={handleEmployeeSelect} 
                                                options={selectionFilteredEmployees
                                                    .map((e:any) => ({label: `${e.user?.name || t('Unknown')} (${e.employee_id})`, value: e.id.toString()}))}
                                                placeholder={t('Search employees...')}
                                                className="min-h-12 text-base"
                                            />
                                        </div>

                                        <div className="space-y-4 pt-4 border-t border-slate-100">
                                            <div className="flex items-center justify-between">
                                                <div className="flex flex-col gap-1">
                                                    <Label className="text-sm font-bold text-muted-foreground uppercase tracking-wider">{t('Step 2: Recurring Week-Offs')}</Label>
                                                    <div className="flex bg-slate-100 p-0.5 rounded-lg w-fit mt-1">
                                                        <button 
                                                            onClick={() => setIndividualType('weekly')}
                                                            className={cn(
                                                                "px-3 py-1 text-[10px] font-black rounded-md transition-all",
                                                                individualType === 'weekly' ? "bg-white text-[#1e2978] shadow-sm" : "text-slate-500 hover:text-slate-700"
                                                            )}
                                                        >
                                                            {t('Weekly')}
                                                        </button>
                                                        <button 
                                                            onClick={() => setIndividualType('monthly')}
                                                            className={cn(
                                                                "px-3 py-1 text-[10px] font-black rounded-md transition-all",
                                                                individualType === 'monthly' ? "bg-white text-[#1e2978] shadow-sm" : "text-slate-500 hover:text-slate-700"
                                                            )}
                                                        >
                                                            {t('Monthly')}
                                                        </button>
                                                    </div>
                                                </div>
                                                <div className="flex gap-2">
                                                    <Button 
                                                        variant="outline" 
                                                        size="sm" 
                                                        className="h-7 text-[10px] uppercase font-bold"
                                                        onClick={() => {
                                                            if (individualType === 'weekly') {
                                                                setRecurringWeekOffs(['Saturday', 'Sunday']);
                                                            } else {
                                                                const newMonthly: Record<string, string[]> = {};
                                                                weeks.forEach(w => newMonthly[w] = ['Saturday', 'Sunday']);
                                                                setIndividualMonthlySettings(newMonthly);
                                                            }
                                                        }}
                                                    >
                                                        {t('Both Weekends')}
                                                    </Button>
                                                    <Button 
                                                        variant="outline" 
                                                        size="sm" 
                                                        className="h-7 text-[10px] uppercase font-bold"
                                                        onClick={() => {
                                                            if (individualType === 'weekly') {
                                                                setRecurringWeekOffs(['Sunday']);
                                                            } else {
                                                                const newMonthly: Record<string, string[]> = {};
                                                                weeks.forEach(w => newMonthly[w] = ['Sunday']);
                                                                setIndividualMonthlySettings(newMonthly);
                                                            }
                                                        }}
                                                    >
                                                        {t('Only Sunday')}
                                                    </Button>
                                                    <Button 
                                                        variant="ghost" 
                                                        size="sm" 
                                                        className="h-7 text-[10px] uppercase font-bold text-red-500 hover:text-red-600"
                                                        onClick={() => {
                                                            if (individualType === 'weekly') {
                                                                setRecurringWeekOffs([]);
                                                            } else {
                                                                setIndividualMonthlySettings({});
                                                            }
                                                        }}
                                                    >
                                                        {t('Clear')}
                                                    </Button>
                                                </div>
                                            </div>
                                            
                                            {individualType === 'weekly' ? (
                                                <div className="grid grid-cols-4 md:grid-cols-7 gap-3">
                                                    {days.map((day) => {
                                                        const isSelected = recurringWeekOffs.includes(day);
                                                        const isWeekend = day === 'Saturday' || day === 'Sunday';
                                                        
                                                        return (
                                                            <button
                                                                key={day}
                                                                type="button"
                                                                onClick={() => {
                                                                    if (isSelected) {
                                                                        setRecurringWeekOffs(recurringWeekOffs.filter(d => d !== day));
                                                                    } else {
                                                                        setRecurringWeekOffs([...recurringWeekOffs, day]);
                                                                    }
                                                                }}
                                                                className={cn(
                                                                    "flex flex-col items-center justify-center p-3 rounded-2xl border-2 transition-all duration-200 group relative min-h-[85px]",
                                                                    isSelected 
                                                                        ? "bg-[#1e2978] border-[#1e2978] text-white shadow-lg shadow-[#1e2978]/20 scale-105 z-10" 
                                                                        : "bg-white border-slate-100 text-slate-600 hover:border-[#1e2978]/30 hover:bg-[#1e2978]/5",
                                                                    isWeekend && !isSelected && "bg-slate-50 border-slate-200"
                                                                )}
                                                            >
                                                                <span className="text-[10px] font-black uppercase tracking-tighter opacity-70 mb-1">
                                                                    {day.substring(0, 3)}
                                                                </span>
                                                                <span className="text-sm font-bold truncate w-full text-center">
                                                                    {t(day)}
                                                                </span>
                                                                {isWeekend && !isSelected && (
                                                                    <span className="absolute -top-2 left-1/2 -translate-x-1/2 bg-slate-200 text-slate-500 text-[8px] px-1.5 py-0.5 rounded-full font-black uppercase tracking-widest border border-white">
                                                                        {t('Weekend')}
                                                                    </span>
                                                                )}
                                                                {isSelected && (
                                                                    <div className="absolute -top-2 -right-2 bg-white text-[#1e2978] rounded-full p-0.5 shadow-md border border-[#1e2978]/10">
                                                                        <Check className="w-3 h-3 stroke-[4px]" />
                                                                    </div>
                                                                )}
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            ) : (
                                                <div className="space-y-4">
                                                    {weeks.map((week) => (
                                                        <div key={week} className="space-y-2">
                                                            <div className="flex items-center gap-3">
                                                                <div className="h-px flex-1 bg-slate-100" />
                                                                <span className="text-[10px] font-black text-slate-400 uppercase tracking-widest">{t('Week')} {week}</span>
                                                                <div className="h-px flex-1 bg-slate-100" />
                                                            </div>
                                                            <div className="grid grid-cols-4 md:grid-cols-7 gap-2">
                                                                {days.map((day) => {
                                                                    const isSelected = (individualMonthlySettings[week] || []).includes(day);
                                                                    return (
                                                                        <button
                                                                            key={day}
                                                                            type="button"
                                                                            onClick={() => handleIndividualMonthlyChange(week, day)}
                                                                            className={cn(
                                                                                "py-2 rounded-xl border text-[10px] font-bold transition-all",
                                                                                isSelected 
                                                                                    ? "bg-[#1e2978] border-[#1e2978] text-white shadow-lg shadow-[#1e2978]/10" 
                                                                                    : "bg-white border-slate-100 text-slate-400 hover:border-[#1e2978]/20"
                                                                            )}
                                                                        >
                                                                            {t(day.substring(0, 3))}
                                                                        </button>
                                                                    );
                                                                })}
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>

                                        {/* <div className="space-y-2 pt-4">
                                            <Label className="text-sm font-bold text-muted-foreground uppercase tracking-wider">{t('Step 3: Add Specific Off Dates')}</Label>
                                            <div className="flex gap-2">
                                                <div className="relative flex-1">
                                                    <CalendarIcon className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground" />
                                                    <Input 
                                                        type="date" 
                                                        value={newOffDate} 
                                                        onChange={(e) => setNewOffDate(e.target.value)} 
                                                        className="pl-10 h-12"
                                                    />
                                                </div>
                                                <Button type="button" onClick={addOffDate} className="h-12 px-6">
                                                    <Plus className="w-4 h-4 mr-2" /> {t('Add')}
                                                </Button>
                                            </div>
                                        </div> */}
                                        
                                        <div className="pt-8">
                                            <Button 
                                                onClick={saveIndividualOffs} 
                                                disabled={loading || selectedEmployeeIds.length === 0} 
                                                className="w-full h-12 rounded-xl bg-[#1e2978] hover:bg-[#1e2978]/90 shadow-lg shadow-[#1e2978]/20"
                                            >
                                                <Save className="w-4 h-4 mr-2" /> {loading ? t('Saving...') : t('Save Individual Changes')}
                                            </Button>
                                        </div>
                                    </div>
                                </div>

                                {/* LIST SECTION: Current Overrides */}
                                    <div className="mt-16 space-y-6">
                                        <div className="flex flex-col md:flex-row md:items-end justify-between gap-4 border-b border-slate-100 pb-6">
                                            <div>
                                                <h3 className="text-2xl font-black text-[#1e2978] tracking-tight">{t('Current Individual Overrides')}</h3>
                                                <p className="text-sm text-slate-500 font-medium">{t('Manage and review all active employee overrides')}</p>
                                            </div>
                                            <div className="flex items-center gap-3">
                                                <div className="relative group">
                                                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 group-focus-within:text-[#1e2978] transition-colors" />
                                                    <Input 
                                                        placeholder={t('Search overrides...')} 
                                                        className="pl-10 h-10 w-64 bg-slate-50 border-none rounded-xl focus-visible:ring-2 focus-visible:ring-[#1e2978]/20"
                                                        onChange={(e) => setSearchQuery(e.target.value)}
                                                    />
                                                </div>
                                                <div className="bg-[#1e2978] text-white text-[10px] font-black px-4 py-2 rounded-xl uppercase tracking-widest shadow-lg shadow-[#1e2978]/20">
                                                    {employees.length} {t('Total Employees')}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="bg-white rounded-[2rem] border border-slate-100 shadow-sm overflow-hidden flex flex-col">
                                            <div className="overflow-x-auto max-h-[600px] overflow-y-auto scrollbar-thin scrollbar-thumb-slate-200 scrollbar-track-transparent">
                                                <table className="w-full text-left border-collapse">
                                                    <thead className="sticky top-0 z-20 bg-slate-50 shadow-sm">
                                                        <tr className="border-b border-slate-100">
                                                            <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">{t('Employee')}</th>
                                                            <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">{t('Employee Code')}</th>
                                                            <th className="px-6 py-4 text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">{t('Assigned Off Days')}</th>
                                                            <th className="px-6 py-4 text-right text-[10px] font-black text-slate-400 uppercase tracking-[0.2em]">{t('Actions')}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-50">
                                                        {(() => {
                                                            const totalPages = Math.ceil(filteredEmployees.length / itemsPerPage);
                                                             const paginated = filteredEmployees.slice((currentPage - 1) * itemsPerPage, currentPage * itemsPerPage);

                                                            return (
                                                                <>
                                                                    {paginated.map((emp: any) => (
                                                                        <tr key={emp.id} className="hover:bg-slate-50/50 transition-colors group">
                                                                            <td className="px-6 py-4">
                                                                                <div className="flex items-center gap-3">
                                                                                    <div className="h-9 w-9 rounded-xl bg-[#1e2978]/5 text-[#1e2978] flex items-center justify-center font-black text-[10px] border border-[#1e2978]/10 group-hover:bg-[#1e2978] group-hover:text-white transition-all">
                                                                                        {(emp.user?.name || '??').substring(0, 2).toUpperCase()}
                                                                                    </div>
                                                                                    <span className="text-sm font-bold text-slate-700 group-hover:text-[#1e2978] transition-colors">{emp.user?.name || t('Unknown')}</span>
                                                                                </div>
                                                                            </td>
                                                                            <td className="px-6 py-4">
                                                                                <span className="text-xs font-black text-slate-400 font-mono tracking-wider">{emp.employee_id}</span>
                                                                            </td>
                                                                            <td className="px-6 py-4">
                                                                                <div className="flex flex-wrap gap-1.5">
                                                                                    {(emp.week_off === null || emp.week_off === '' || emp.week_off.toLowerCase() === 'none') ? (
                                                                                        <span className="text-[9px] font-black uppercase px-2.5 py-1 rounded-lg border bg-amber-50 text-amber-600 border-amber-200 shadow-sm">
                                                                                            {t('None')}
                                                                                        </span>
                                                                                     ) : (emp.week_off_type === 'monthly' || (emp.week_off || '').startsWith('{')) ? (() => {
                                                                                        try {
                                                                                            const parsed = JSON.parse(emp.week_off);
                                                                                            const allDays = Object.values(parsed).flat() as string[];
                                                                                            const uniqueDays = [...new Set(allDays.map(d => d.substring(0, 3)))].join(', ');
                                                                                            return (
                                                                                                <span className="text-[9px] font-black uppercase px-2.5 py-1 rounded-lg border bg-purple-50 text-purple-600 border-purple-200 shadow-sm flex items-center gap-1.5" title={uniqueDays}>
                                                                                                    <CalendarIcon className="w-3 h-3" /> {t('Monthly')} ({uniqueDays})
                                                                                                </span>
                                                                                            );
                                                                                        } catch(e) {
                                                                                            return <span className="text-[9px] font-black uppercase px-2.5 py-1 rounded-lg border bg-purple-50 text-purple-600 border-purple-200 shadow-sm">{t('Monthly')}</span>;
                                                                                        }
                                                                                    })() : (
                                                                                        (emp.week_off || '').split(',').filter(Boolean).map((day: string) => (
                                                                                            <span 
                                                                                                key={day} 
                                                                                                className={cn(
                                                                                                    "text-[9px] font-black uppercase px-2.5 py-1 rounded-lg border shadow-sm",
                                                                                                    (day === 'Saturday' || day === 'Sunday') 
                                                                                                        ? "bg-[#1e2978] text-white border-[#1e2978]" 
                                                                                                        : "bg-white text-[#1e2978] border-[#1e2978]/20"
                                                                                                )}
                                                                                            >
                                                                                                {t(day.substring(0, 3))}
                                                                                            </span>
                                                                                        ))
                                                                                    )}
                                                                                </div>
                                                                            </td>
                                                                            <td className="px-6 py-4 text-right">
                                                                                <div className="flex justify-end gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                                                                    <Button 
                                                                                        variant="ghost" 
                                                                                        size="icon" 
                                                                                        className="h-8 w-8 text-[#1e2978] hover:bg-[#1e2978]/5 rounded-lg"
                                                                                        onClick={() => {
                                                                                            setSelectedEmployeeIds([emp.id.toString()]);
                                                                                            const isMonthly = emp.week_off_type === 'monthly' || (emp.week_off || '').startsWith('{');
                                                                                            setIndividualType(isMonthly ? 'monthly' : 'weekly');
                                                                                            
                                                                                            if (isMonthly) {
                                                                                                try {
                                                                                                    setIndividualMonthlySettings(JSON.parse(emp.week_off));
                                                                                                    setRecurringWeekOffs([]);
                                                                                                } catch(e) {
                                                                                                    setIndividualMonthlySettings({});
                                                                                                }
                                                                                            } else {
                                                                                                const currentOffs = (emp.week_off || '').split(',').filter(d => d && d.toLowerCase() !== 'none');
                                                                                                setRecurringWeekOffs(currentOffs);
                                                                                                setIndividualMonthlySettings({});
                                                                                            }
                                                                                            window.scrollTo({ top: 0, behavior: 'smooth' });
                                                                                        }}
                                                                                    >
                                                                                        <MousePointerClick className="w-4 h-4" />
                                                                                    </Button>
                                                                                    <Button 
                                                                                        variant="ghost" 
                                                                                        size="icon" 
                                                                                        className="h-8 w-8 text-red-500 hover:bg-red-50 rounded-lg"
                                                                                        onClick={() => {
                                                                                            if (confirm(t('Are you sure you want to remove this individual override?'))) {
                                                                                                router.post(route('hr.week-offs.individual'), {
                                                                                                    employee_ids: [emp.id],
                                                                                                    recurring_week_offs: [],
                                                                                                    off_dates: [],
                                                                                                    remove: true
                                                                                                }, {
                                                                                                    onSuccess: () => toast.success(t('Override removed successfully'))
                                                                                                });
                                                                                            }
                                                                                        }}
                                                                                    >
                                                                                        <Trash2 className="w-4 h-4" />
                                                                                    </Button>
                                                                                </div>
                                                                            </td>
                                                                        </tr>
                                                                    ))}
                                                                    {filteredEmployees.length === 0 && (
                                                                        <tr>
                                                                            <td colSpan={4} className="py-20 text-center">
                                                                                <div className="bg-slate-50 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4 border-2 border-dashed border-slate-200">
                                                                                    <User className="h-8 w-8 text-slate-300" />
                                                                                </div>
                                                                                <p className="text-base text-slate-400 italic font-bold tracking-tight">{t('No active individual overrides found.')}</p>
                                                                            </td>
                                                                        </tr>
                                                                    )}
                                                                </>
                                                            );
                                                        })()}
                                                    </tbody>
                                                </table>
                                            </div>

                                            {/* Pagination Controls */}
                                            {(() => {
                                                const totalPages = Math.ceil(filteredEmployees.length / itemsPerPage);
                                                if (totalPages <= 1) return null;

                                                return (
                                                    <div className="bg-slate-50 px-6 py-4 border-t border-slate-100 flex items-center justify-between">
                                                        <p className="text-xs font-black text-slate-400 uppercase tracking-widest">
                                                            {t('Showing')} <span className="text-[#1e2978]">{(currentPage - 1) * itemsPerPage + 1}</span> {t('to')} <span className="text-[#1e2978]">{Math.min(currentPage * itemsPerPage, filteredEmployees.length)}</span> {t('of')} <span className="text-[#1e2978]">{filteredEmployees.length}</span>
                                                        </p>
                                                        <div className="flex gap-2">
                                                            <Button 
                                                                variant="outline" 
                                                                size="sm" 
                                                                className="h-8 rounded-lg px-3 text-[10px] font-black uppercase tracking-widest"
                                                                disabled={currentPage === 1}
                                                                onClick={() => setCurrentPage(prev => Math.max(1, prev - 1))}
                                                            >
                                                                {t('Prev')}
                                                            </Button>
                                                            <div className="flex items-center px-3 text-[10px] font-black text-[#1e2978]">
                                                                {t('Page')} {currentPage} {t('of')} {totalPages}
                                                            </div>
                                                            <Button 
                                                                variant="outline" 
                                                                size="sm" 
                                                                className="h-8 rounded-lg px-3 text-[10px] font-black uppercase tracking-widest"
                                                                disabled={currentPage === totalPages}
                                                                onClick={() => setCurrentPage(prev => Math.min(totalPages, prev + 1))}
                                                            >
                                                                {t('Next')}
                                                            </Button>
                                                        </div>
                                                    </div>
                                                );
                                            })()}
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        </TabsContent>
                    </Tabs>
                </div>
        </PageTemplate>
    );
}


