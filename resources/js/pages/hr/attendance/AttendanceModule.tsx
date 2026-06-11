import React, { useState, useEffect, useRef } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
    Search, Filter, Calendar as CalendarIcon, CheckCircle2, XCircle, Clock, AlertCircle, 
    MoreHorizontal, User, Download, Plus, Edit, Timer, ChevronLeft, ChevronRight, 
    FileSpreadsheet, Layers, Table, MapPin, Star, LogOut, FileText, Minus, CircleDot, Info,
    ClipboardList, PieChart, ChevronRight as ChevronRightIcon, FileDown, Calendar,
    Calculator, ArrowRight, ListTodo, Users, Loader2
} from 'lucide-react';
import axios from 'axios';
import { toast } from '@/components/custom-toast';
import { Badge } from '@/components/ui/badge';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { 
    DropdownMenu, 
    DropdownMenuContent, 
    DropdownMenuItem, 
    DropdownMenuTrigger, 
    DropdownMenuSub, 
    DropdownMenuSubTrigger, 
    DropdownMenuSubContent, 
    DropdownMenuSeparator 
} from '@/components/ui/dropdown-menu';
import { PunchPairsEditor } from '@/components/attendance/PunchPairsEditor';
import { AttendanceDayModalBody } from '@/components/attendance/AttendanceDayModalBody';
import { MispunchResolutionList } from '@/components/attendance/MispunchResolutionList';
import {
    PunchPair,
    createPunchPair,
    defaultPunchPairs,
    pairsFromShiftBounds,
    parseLogDetailsToPairs,
    validatePunchPairs,
    hasMispunchIssues,
    buildAttendancePayloadFromPairs,
    resolveShiftBounds,
    resolveHalfDayShiftBounds,
    resolveSlotForHalfDay,
    buildHalfDayPunchPairsFromSlot,
    getHalfDayMinutesFromSlot,
    formatMinutesAsHours,
    getEmployeeShiftSchedule,
    getRecordDisplayPairs,
    getDaySpanFromPairs,
    hasActualPunchData,
    calcTotalMinutesFromPairs,
    formatTime12h as formatTime,
} from '@/lib/attendance-punches';


const SCROLLBAR_STYLES = `
    .custom-scrollbar::-webkit-scrollbar {
        width: 12px;
        height: 12px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
        background: #f1f1f1;
        border-radius: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: #ccc;
        border-radius: 6px;
        border: 3px solid #f1f1f1;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: #999;
    }
    input[type="month"]::-webkit-calendar-picker-indicator {
        background: transparent;
        bottom: 0;
        color: transparent;
        cursor: pointer;
        height: auto;
        left: 0;
        position: absolute;
        right: 0;
        top: 0;
        width: auto;
        z-index: 10;
    }
`;

interface AttendanceRecord {
    id?: number;
    status: string;
    in_time: string | null;
    out_time: string | null;
    total_minutes: number;
    ot_minutes: number;
    is_manual?: boolean;
    is_holiday?: boolean;
    is_weekly_off?: boolean;
    duty_value?: number;
    shift_slot_id?: string;
    log_details?: string | null;
    manual_remarks?: string | null;
}

interface EmployeeData {
    employee: {
        id: number;
        name: string;
        code: string;
        department: string;
        designation: string;
        category: string;
        shift: string;
    };
    days: Record<number, AttendanceRecord>;
    summary: {
        present: number;
        absent: number;
        half_day: number;
        mis: number;
        ot_hours: number;
        ot_minutes: number;
        total_worked_hours: number;
        total_worked_minutes: number;
    };
}

const fmtMins = (mins: number) => {
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (m === 0) return `${h}h`;
    return `${h}h ${m}m`;
};

const COLOR_MAP: Record<string, string> = {
    red: 'text-red-600 bg-red-50 border-red-200',
    orange: 'text-orange-600 bg-orange-50 border-orange-200',
    green: 'text-emerald-600 bg-emerald-50 border-emerald-200',
    blue: 'text-blue-600 bg-blue-50 border-blue-200',
};

export default function AttendanceModule({ branches, departments, sections, categories, designations, initial_filters, shifts_for_rules = [], self_service_only = false }: any) {
    const [filters, setFilters] = useState(initial_filters);
    const [loading, setLoading] = useState(false);
    const [data, setData] = useState<{ employees: EmployeeData[], days_in_month: number, month_name: string, pagination?: any } | null>(null);
    const [activeTab, setActiveTab] = useState('status');
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedRecord, setSelectedRecord] = useState<{emp: any, day: number, record: AttendanceRecord} | null>(null);
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');

    const handleCellClick = self_service_only
        ? undefined
        : (emp: any, d: number, r: any) => setSelectedRecord({ emp, day: d, record: r });

    useEffect(() => {
        // Handle tab from URL query param
        const urlParams = new URLSearchParams(window.location.search);
        const tab = urlParams.get('tab');
        if (tab === 'mispunch') {
            setActiveTab('mispunch');
            setFilters({...filters, status: 'MIS', page: 1});
        }
    }, []);

    // Sync branch filter when global active branch changes
    useEffect(() => {
        if (initial_filters.branch_id && initial_filters.branch_id !== filters.branch_id) {
            setFilters(prev => ({ 
                ...prev, 
                branch_id: initial_filters.branch_id, 
                page: 1 
            }));
        }
    }, [initial_filters.branch_id]);
    const [rulesDialogOpen, setRulesDialogOpen] = useState(false);
    const [recordModalMode, setRecordModalMode] = useState<'view' | 'edit'>('view');
    const [editForm, setEditForm] = useState<any>({
        status: '',
        remarks: '',
        shift_slot_id: '',
    });
    const [editPunchPairs, setEditPunchPairs] = useState<PunchPair[]>(defaultPunchPairs());
    const [manualEntryOpen, setManualEntryOpen] = useState(false);
    const [manualSearchQuery, setManualSearchQuery] = useState('');
    const [manualSearchResults, setManualSearchResults] = useState<any[]>([]);
    const [searchingManual, setSearchingManual] = useState(false);
    const [selectedManualEmp, setSelectedManualEmp] = useState<any>(null);
    const [manualForm, setManualForm] = useState<any>({
        date: new Date().toISOString().substring(0, 10),
        status: 'P',
        remarks: '',
        shift_slot_id: '',
    });
    const [manualPunchPairs, setManualPunchPairs] = useState<PunchPair[]>(defaultPunchPairs());

    const [bulkAttendanceOpen, setBulkAttendanceOpen] = useState(false);
    const [bulkApplying, setBulkApplying] = useState(false);
    const [bulkForm, setBulkForm] = useState<any>({
        target: 'all', // all, department, designation, category, shift, employee
        department_id: '',
        designation_id: '',
        category_id: '',
        shift_id: '',
        employee_id: '',
        from_date: new Date().toISOString().substring(0, 10),
        to_date: new Date().toISOString().substring(0, 10),
        status: 'P',
        time_mode: 'shift', // 'shift' or 'custom'
        multi_shift_slot: 'first', // 'first' or 'second'
        in_time: '09:00',
        out_time: '18:00',
        remarks: 'Bulk assigned',
        overwrite: false,
    });

    const resetBulkForm = () => {
        setBulkForm({
            target: 'all',
            department_id: '',
            designation_id: '',
            category_id: '',
            shift_id: '',
            employee_id: '',
            from_date: new Date().toISOString().substring(0, 10),
            to_date: new Date().toISOString().substring(0, 10),
            status: 'P',
            time_mode: 'shift',
            multi_shift_slot: 'first',
            in_time: '09:00',
            out_time: '18:00',
            remarks: 'Bulk assigned',
            overwrite: false,
        });
        setManualSearchQuery('');
        setSelectedManualEmp(null);
    };

    const editShiftBounds = React.useMemo(() => {
        if (!selectedRecord?.emp) return undefined;
        if (editForm.shift_slot_id === 'custom') return undefined;
        if (editForm.status === 'MIS' && hasMispunchIssues(editPunchPairs)) return undefined;
        if (editForm.status === 'HD') {
            return resolveHalfDayShiftBounds(selectedRecord.emp, editForm.shift_slot_id);
        }
        return resolveShiftBounds(selectedRecord.emp, editForm.shift_slot_id);
    }, [selectedRecord?.emp, editForm.shift_slot_id, editForm.status, editPunchPairs]);

    const manualShiftBounds = React.useMemo(() => {
        if (!selectedManualEmp) return undefined;
        if (manualForm.status === 'HD') {
            return resolveHalfDayShiftBounds(selectedManualEmp, manualForm.shift_slot_id);
        }
        return resolveShiftBounds(selectedManualEmp, manualForm.shift_slot_id);
    }, [selectedManualEmp, manualForm.shift_slot_id, manualForm.status]);

    const applyHalfDayFromShift = (
        emp: { slots?: { id?: number | string; slot_name?: string }[] },
        slotId: string | undefined,
        setPairs: (p: PunchPair[]) => void,
        setForm: React.Dispatch<React.SetStateAction<any>>
    ) => {
        if (slotId === 'both') {
            toast.error('For half day, select one shift slot (not double shift).');
            return;
        }
        const slot = resolveSlotForHalfDay(emp, slotId);
        if (!slot) {
            toast.error('No shift slot found. Set shift on employee master first.');
            return;
        }
        setPairs(buildHalfDayPunchPairsFromSlot(slot));
        setForm((prev: any) => ({
            ...prev,
            shift_slot_id: String(slot.id),
            status: 'HD',
        }));
        const mins = getHalfDayMinutesFromSlot(slot);
        toast.success(`Half day: ${slot.slot_name} — ${formatMinutesAsHours(mins)} per shift rules`);
    };

    const applySlotPunchPairs = (
        slots: any[],
        slotId: string,
        setPairs: (p: PunchPair[]) => void
    ) => {
        if (slotId === 'both' && slots.length > 1) {
            const s0 = slots[0];
            const s1 = slots[1];
            setPairs([
                createPunchPair(
                    s0.start_time?.substring(0, 5) || '08:00',
                    s0.end_time?.substring(0, 5) || '20:00'
                ),
                createPunchPair(
                    s1.start_time?.substring(0, 5) || '20:00',
                    s1.end_time?.substring(0, 5) || '08:00'
                ),
            ]);
            return;
        }
        const slot = slots.find((s: any) => s.id.toString() === slotId);
        if (slot) {
            const start = slot.start_time?.substring(0, 5) || '08:00';
            const end = slot.end_time?.substring(0, 5) || '20:00';
            setPairs(pairsFromShiftBounds({ start, end }));
        }
    };

    const [reportDialogOpen, setReportDialogOpen] = useState(false);
    const [selectedReportId, setSelectedReportId] = useState('');
    const [reportOptions, setReportOptions] = useState({
        hourly_type: 'N',
        card_type: 'N',
        status: 'all',
        from_date: '',
        to_date: '',
        report_type: 'codewise'
    });

    const openReportDialog = (reportId: string, options: any = {}) => {
        // Calculate default from/to date based on filters.month
        const [year, month] = filters.month.split('-');
        const defaultFrom = `${year}-${month}-01`;
        const lastDay = new Date(parseInt(year), parseInt(month), 0).getDate();
        const defaultTo = `${year}-${month}-${String(lastDay).padStart(2, '0')}`;

        setSelectedReportId(reportId);
        setReportOptions({
            hourly_type: options.hourly_type || 'N',
            card_type: options.card_type || 'N',
            status: options.status || 'all',
            from_date: defaultFrom,
            to_date: defaultTo,
            report_type: options.report_type || 'codewise'
        });
        setReportDialogOpen(true);
    };

    const summaryTotals = data?.summary || { present: 0, absent: 0, half_day: 0, mis: 0, ot_count: 0 };

    const searchEmployees = async (query: string) => {
        if (query.length < 2) return;
        setSearchingManual(true);
        try {
            const response = await axios.get(route('hr.attendance.grid-data'), { params: { search: query, per_page: 5 } });
            setManualSearchResults(response.data.employees.data || response.data.employees || []);
        } catch (error) {
            console.error('Search failed', error);
        } finally {
            setSearchingManual(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            if (manualSearchQuery && !selectedManualEmp) searchEmployees(manualSearchQuery);
        }, 500);
        return () => clearTimeout(timer);
    }, [manualSearchQuery]);

    // Auto-fetch existing attendance when employee and date are selected in Manual Entry
    useEffect(() => {
        const fetchExistingAttendance = async () => {
            if (selectedManualEmp && manualForm.date && manualEntryOpen) {
                try {
                    const response = await axios.get(route('hr.attendance.grid-data'), { 
                        params: { 
                            search: selectedManualEmp.code, 
                            month: manualForm.date.substring(0, 7),
                            branch_id: filters.branch_id
                        } 
                    });
                    
                    const empData = response.data.employees?.data?.[0] || response.data.employees?.[0];
                    if (empData) {
                        const day = parseInt(manualForm.date.substring(8, 10));
                        const record = empData.days?.[day];
                        if (record) {
                            setManualForm(prev => ({
                                ...prev,
                                status: record.status || 'P',
                                remarks: record.manual_remarks || '',
                                shift_slot_id: record.shift_slot_id || '',
                            }));
                            setManualPunchPairs(
                                parseLogDetailsToPairs(
                                    record.log_details,
                                    record.in_time,
                                    record.out_time
                                )
                            );
                        } else {
                            setManualForm(prev => ({
                                ...prev,
                                status: 'P',
                                remarks: '',
                                shift_slot_id: '',
                            }));
                            const bounds = resolveShiftBounds(selectedManualEmp, '');
                            setManualPunchPairs(
                                bounds
                                    ? pairsFromShiftBounds(bounds)
                                    : defaultPunchPairs(
                                          selectedManualEmp.shift_start || '09:00',
                                          selectedManualEmp.shift_end || '18:00'
                                      )
                            );
                        }
                    }
                } catch (error) {
                    console.error('Failed to fetch existing attendance:', error);
                }
            }
        };

        fetchExistingAttendance();
    }, [selectedManualEmp?.id, manualForm.date, manualEntryOpen]);

    const STATUS_LABELS: Record<string, string> = {
        P: 'Present',
        A: 'Absent',
        HD: 'Half Day',
        MIS: 'Mispunch',
        W: 'Week Off',
        H: 'Holiday',
    };

    const openRecordForEdit = () => {
        if (!selectedRecord) return;
        const record = selectedRecord.record;
        const emp = selectedRecord.emp;
        const parsed = parseLogDetailsToPairs(
            record.log_details,
            record.in_time,
            record.out_time
        );
        const misIncomplete = hasMispunchIssues(parsed);
        let slotId =
            record.shift_slot_id ||
            (emp.slots?.length === 1 ? String(emp.slots[0].id) : '');
        if (record.status === 'MIS' && misIncomplete && emp.is_multi_shift) {
            slotId = 'custom';
        }
        setEditForm({
            status: record.status,
            remarks: record.manual_remarks || '',
            shift_slot_id: slotId,
        });
        const bounds = resolveShiftBounds(emp, slotId);
        if (record.status === 'HD') {
            const slot = resolveSlotForHalfDay(emp, slotId);
            setEditPunchPairs(slot ? buildHalfDayPunchPairsFromSlot(slot) : parsed);
        } else {
            setEditPunchPairs(
                !record.log_details && bounds && slotId !== 'custom'
                    ? pairsFromShiftBounds(bounds)
                    : parsed.length
                      ? parsed
                      : bounds
                        ? pairsFromShiftBounds(bounds)
                        : defaultPunchPairs()
            );
        }
        setRecordModalMode('edit');
    };

    const resetManualForm = () => {
        setSelectedManualEmp(null);
        setManualSearchQuery('');
        setManualSearchResults([]);
        setManualForm({
            date: new Date().toISOString().substring(0, 10),
            status: 'P',
            remarks: '',
            shift_slot_id: '',
        });
        setManualPunchPairs(defaultPunchPairs());
    };

    const fetchData = async () => {
        setLoading(true);
        try {
            const response = await axios.get(route('hr.attendance.grid-data'), { params: { ...filters, search: searchQuery } });
            setData(response.data);
        } catch (error) {
            console.error('Error fetching attendance data:', error);
            toast.error('Failed to fetch attendance data');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        const timer = setTimeout(() => {
            fetchData();
        }, 500); // 500ms debounce
        return () => clearTimeout(timer);
    }, [filters.month, filters.department_id, filters.section_id, filters.category_id, filters.branch_id, filters.status, filters.page, filters.per_page, searchQuery]);

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        setFilters({...filters, page: 1});
    };

    const getStatusIcon = (status: string, record: any = {}) => {
        const s = status || record.status;
        const baseStyle = "w-7 h-7 rounded-md flex items-center justify-center text-[10px] font-black shadow-sm";
        
        if (s === 'P') return (
            <div className={`${baseStyle} bg-emerald-50 text-emerald-600 border border-emerald-200/50 hover:shadow-emerald-100`} title="Present">
                <CheckCircle2 className="w-4 h-4" />
            </div>
        );
        if (s === 'A') return (
            <div className={`${baseStyle} bg-rose-50 text-rose-600 border border-rose-200/50 hover:shadow-rose-100`} title="Absent">
                <XCircle className="w-4 h-4" />
            </div>
        );
        if (s === 'H' || record.is_holiday) return (
            <div className={`${baseStyle} bg-amber-100 text-amber-700 border border-amber-200`} title="Holiday">
                <Star className="w-4 h-4 fill-amber-500 text-amber-500" />
            </div>
        );
        if (s === 'W' || record.is_weekly_off) return (
            <div className={`${baseStyle} bg-slate-100 text-slate-600 border border-slate-200`} title="Week Off">
                <CalendarIcon className="w-4 h-4" />
            </div>
        );
        if (s === 'MIS') return (
            <div className={`${baseStyle} bg-orange-50 text-orange-600 border border-orange-200/50 hover:shadow-orange-100`} title="Mispunch">
                <AlertCircle className="w-4 h-4" />
            </div>
        );
        if (s === 'HD') return (
            <div
                className={`${baseStyle} bg-yellow-100 text-yellow-800 border-2 border-yellow-400 shadow-sm`}
                title="Half Day (0.5 duty)"
            >
                <span className="text-[11px] font-black leading-none">½</span>
            </div>
        );
        if (s === 'L') return (
            <div className={`${baseStyle} bg-cyan-50 text-cyan-600 border border-cyan-200/50`} title="Leave">
                <LogOut className="w-4 h-4 rotate-180" />
            </div>
        );
        return <span className="text-[10px] font-bold text-gray-400">{status || '-'}</span>;
    };

    const formatTime = (time: string | null) => {
        if (!time) return '--:--';
        return time;
    };

    const handleDownloadReport = (reportId: string, options: any = {}) => {
        const [year, month] = filters.month.split('-');
        
        const params = new URLSearchParams({
            report_id: reportId,
            from_date: options.from_date,
            to_date: options.to_date,
            branch_id: filters.branch_id,
            department: filters.department_id,
            section: filters.section_id,
            category: filters.category_id,
            status: options.status || 'all',
            report_type: options.report_type || 'codewise',
            hourly_type: options.hourly_type || 'N',
            card_type: options.card_type || 'N',
            po_status: 'all',
            month: month,
            year: year
        });

        let baseUrl = '/reports/generate';
        if (reportId === 'biometric_dedicated') {
            baseUrl = '/reports/biometric-dedicated';
        } else if (['PLC', 'DPT', 'DSG', 'SHT', 'CNT', 'SEC', 'BNK', 'SKL', 'MAT'].includes(reportId)) {
            baseUrl = '/reports/master-listing';
            params.set('type', reportId);
        }

        const url = `${baseUrl}?${params.toString()}`;
        window.open(url, '_blank');
    };

    const formatHours = (minutes: number) => {
        if (!minutes) return '0h';
        const h = Math.floor(minutes / 60);
        const m = minutes % 60;
        return m > 0 ? `${h}h ${m}m` : `${h}h`;
    };

    return (
        <AppLayout>
            <Head title="Attendance Management" />
            <style>{SCROLLBAR_STYLES}</style>
            <div className="p-4 space-y-4 max-w-full">
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div className="flex items-center gap-3">
                        <div className="p-2 bg-primary/10 rounded-xl">
                            <CalendarIcon className="w-6 h-6 text-primary" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">Attendance Dashboard</h1>
                            <p className="text-sm text-muted-foreground font-medium">{data?.month_name || 'Loading records...'}</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                                {/* <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline" size="sm" className="bg-white dark:bg-gray-900 shadow-sm border-gray-200 flex items-center gap-2">
                                            <FileText className="w-4 h-4 text-primary" />
                                            Reports
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end" className="w-64 p-2 shadow-2xl border-gray-200/60 dark:border-gray-800">
                                        <div className="px-2 py-2 text-[10px] font-black uppercase tracking-widest text-gray-400">Attendance Reports</div>
                                        
                                        <DropdownMenuSub>
                                            <DropdownMenuSubTrigger className="flex items-center gap-2 py-2.5">
                                                <ClipboardList className="w-4 h-4 text-indigo-500" />
                                                <span>1. Biometric Reports</span>
                                            </DropdownMenuSubTrigger>
                                            <DropdownMenuSubContent className="w-64">
                                                <DropdownMenuItem onClick={() => openReportDialog('biometric_dedicated', { report_type: 'codewise' })} className="flex items-center gap-2 py-2">
                                                    <div className="w-1.5 h-1.5 rounded-full bg-emerald-500" /> 1. Biometric Codewise
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => openReportDialog('biometric_dedicated', { report_type: 'department' })} className="flex items-center gap-2 py-2">
                                                    <div className="w-1.5 h-1.5 rounded-full bg-blue-500" /> 2. Biometric Departmentwise
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem onClick={() => openReportDialog('biometric_single')} className="flex items-center gap-2 py-2">
                                                    <User className="w-3.5 h-3.5 text-blue-500" /> Biometric Daily Present
                                                </DropdownMenuItem>
                                            </DropdownMenuSubContent>
                                        </DropdownMenuSub>

                                        <DropdownMenuSub>
                                            <DropdownMenuSubTrigger className="flex items-center gap-2 py-2.5">
                                                <CalendarIcon className="w-4 h-4 text-emerald-500" />
                                                <span>2. Attendant Reports</span>
                                            </DropdownMenuSubTrigger>
                                            <DropdownMenuSubContent className="w-64">
                                                <DropdownMenuItem onClick={() => openReportDialog('att_worker', { hourly_type: 'N' })} className="py-2">
                                                    1. Workerwise Attendance (Numeric)
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => openReportDialog('att_worker', { hourly_type: 'Y' })} className="py-2">
                                                    2. Workerwise Attendance (Hourly)
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => openReportDialog('att_worker', { hourly_type: 'T' })} className="py-2">
                                                    3. Workerwise Attendance (Time)
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem onClick={() => openReportDialog('att_dept')} className="py-2">
                                                    4. Departmentwise Attendance
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => openReportDialog('att_shift')} className="py-2">
                                                    5. Shiftwise Attendance
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => openReportDialog('att_summary')} className="py-2">
                                                    6. Monthly Summary Report
                                                </DropdownMenuItem>
                                            </DropdownMenuSubContent>
                                        </DropdownMenuSub>

                                        <DropdownMenuSub>
                                            <DropdownMenuSubTrigger className="flex items-center gap-2 py-2.5">
                                                <Search className="w-4 h-4 text-orange-500" />
                                                <span>3. Master Master List</span>
                                            </DropdownMenuSubTrigger>
                                            <DropdownMenuSubContent className="w-64">
                                                <DropdownMenuItem onClick={() => handleDownloadReport('PLC', { from_date: '', to_date: '' })} className="py-2">
                                                    1. Branch Master List
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => handleDownloadReport('DPT', { from_date: '', to_date: '' })} className="py-2">
                                                    2. Department Master List
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => handleDownloadReport('DSG', { from_date: '', to_date: '' })} className="py-2">
                                                    3. Designation Master List
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => handleDownloadReport('SHT', { from_date: '', to_date: '' })} className="py-2">
                                                    4. Shift Master List
                                                </DropdownMenuItem>
                                                <DropdownMenuItem onClick={() => handleDownloadReport('SEC', { from_date: '', to_date: '' })} className="py-2">
                                                    5. Section Master List
                                                </DropdownMenuItem>
                                            </DropdownMenuSubContent>
                                        </DropdownMenuSub>

                                        <DropdownMenuSeparator />
                                        
                                        <DropdownMenuItem onClick={() => openReportDialog('emp_monthly')} className="flex items-center gap-2 py-2.5 font-medium">
                                            <PieChart className="w-4 h-4 text-amber-500" />
                                            Employee Card Printing
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu> */}
                                {!self_service_only && (
                                    <>
                                        <Button 
                                            variant="outline" 
                                            size="sm"
                                            onClick={() => setRulesDialogOpen(true)}
                                            className="h-9 border-amber-200 bg-amber-50/50 text-amber-700 hover:bg-amber-100 font-bold gap-2 px-3 rounded-lg mr-2"
                                        >
                                            <Info className="w-4 h-4" />
                                            Rules
                                        </Button>
                                        <Button size="sm" className="shadow-md shadow-primary/20 mr-2" onClick={() => setBulkAttendanceOpen(true)}>
                                            <Layers className="w-4 h-4 mr-2" /> Bulk Attendance
                                        </Button>
                                        <Button size="sm" className="shadow-md shadow-primary/20" onClick={() => setManualEntryOpen(true)}>
                                            <Plus className="w-4 h-4 mr-2" /> Manual Entry
                                        </Button>
                                    </>
                                )}
                    </div>
                </div>

                <Card className="bg-white/80 backdrop-blur-md border-gray-200/60 dark:bg-gray-900/80 dark:border-gray-800 shadow-xl shadow-gray-200/20">
                    <CardContent className="p-5">
                        <form onSubmit={handleSearch} className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-x-4 gap-y-4">
                            <div className="space-y-1.5 min-w-[160px]">
                                <label className="text-[11px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Period</label>
                                <div className="relative">
                                    <Input 
                                        type="month" 
                                        value={filters.month} 
                                        onChange={(e) => setFilters({...filters, month: e.target.value, page: 1})}
                                        onClick={(e) => (e.currentTarget as any).showPicker()}
                                        className="h-11 border-gray-200 dark:border-gray-800 focus:ring-primary/20 text-[14px] font-bold px-4 bg-white dark:bg-gray-950 transition-all hover:border-primary/40 cursor-pointer shadow-sm appearance-none"
                                    />
                                    <CalendarIcon className="absolute right-3 top-3.5 h-4 w-4 text-gray-400 pointer-events-none" />
                                </div>
                            </div>

                            {!self_service_only && (
                                <>
                                    <div className="space-y-1.5">
                                        <label className="text-[11px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Department</label>
                                        <Select value={filters.department_id} onValueChange={(v) => setFilters({...filters, department_id: v, page: 1})}>
                                            <SelectTrigger className="h-11 border-gray-200 dark:border-gray-800 text-[13px] font-medium bg-white dark:bg-gray-950">
                                                <SelectValue placeholder="All Departments" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All Departments</SelectItem>
                                                {departments.map((d: any) => (
                                                    <SelectItem key={d.id} value={d.id.toString()}>{d.name}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className="text-[11px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Section</label>
                                        <Select value={filters.section_id} onValueChange={(v) => setFilters({...filters, section_id: v, page: 1})}>
                                            <SelectTrigger className="h-11 border-gray-200 dark:border-gray-800 text-[13px] font-medium bg-white dark:bg-gray-950">
                                                <SelectValue placeholder="All Sections" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All Sections</SelectItem>
                                                {sections.map((s: any) => (
                                                    <SelectItem key={s.id} value={s.id.toString()}>{s.name}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className="text-[11px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Category</label>
                                        <Select value={filters.category_id} onValueChange={(v) => setFilters({...filters, category_id: v, page: 1})}>
                                            <SelectTrigger className="h-11 border-gray-200 dark:border-gray-800 text-[13px] font-medium bg-white dark:bg-gray-950">
                                                <SelectValue placeholder="All Categories" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="all">All Categories</SelectItem>
                                                {categories.map((c: any) => (
                                                    <SelectItem key={c.id} value={c.id.toString()}>{c.name}</SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="space-y-1.5">
                                        <label className="text-[11px] font-bold uppercase tracking-widest text-gray-400 dark:text-gray-500">Search</label>
                                        <div className="relative">
                                            <Search className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                                            <Input 
                                                placeholder="Name/Code..." 
                                                className="pl-10 h-11 border-gray-200 dark:border-gray-800 focus:ring-primary/20 text-[13px] bg-white dark:bg-gray-950"
                                                value={searchQuery}
                                                onChange={(e) => {
                                                    setSearchQuery(e.target.value);
                                                    setFilters(prev => ({ ...prev, page: 1 }));
                                                }}
                                            />
                                        </div>
                                    </div>
                                </>
                            )}
                        </form>
                    </CardContent>
                </Card>


                {/* Tabs & Grid */}
                <Card className="border-gray-200/60 dark:border-gray-800 shadow-2xl shadow-gray-200/40 dark:shadow-none bg-white dark:bg-gray-950">
                    <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
                        <div className="px-4 md:px-6 pt-6 pb-2 border-b border-gray-100 dark:border-gray-800/50 flex flex-col md:flex-row md:items-center justify-between gap-4 w-full overflow-hidden">
                            <div className="overflow-x-auto custom-scrollbar flex-1 flex justify-center">
                                <TabsList className="bg-gray-100/30 dark:bg-gray-800/30 p-1.5 rounded-xl h-16 border border-gray-200/50 dark:border-gray-700/50 flex items-center gap-3 w-max mx-auto">
                                <TabsTrigger value="status" className="rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-lg px-5 h-12 text-[10px] font-black transition-all flex flex-col items-center justify-center gap-1 border border-transparent data-[state=active]:border-emerald-100 group">
                                    <CheckCircle2 className="w-4 h-4 text-emerald-500 group-data-[state=active]:scale-110 transition-transform" /> 
                                    <span className="text-gray-400 group-data-[state=active]:text-emerald-700">STATUS</span>
                                </TabsTrigger>
                                <TabsTrigger value="inout" className="rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-lg px-5 h-12 text-[10px] font-black transition-all flex flex-col items-center justify-center gap-1 border border-transparent data-[state=active]:border-blue-100 group">
                                    <Clock className="w-4 h-4 text-blue-500 group-data-[state=active]:scale-110 transition-transform" /> 
                                    <span className="text-gray-400 group-data-[state=active]:text-blue-700">IN/OUT</span>
                                </TabsTrigger>
                                <TabsTrigger value="hours" className="rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-lg px-5 h-12 text-[10px] font-black transition-all flex flex-col items-center justify-center gap-1 border border-transparent data-[state=active]:border-gray-200 group">
                                    <Timer className="w-4 h-4 text-gray-500 group-data-[state=active]:scale-110 transition-transform" /> 
                                    <span className="text-gray-400 group-data-[state=active]:text-gray-700">HOURS</span>
                                </TabsTrigger>
                                {!self_service_only && (
                                    <TabsTrigger value="mispunch" className="rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-lg px-5 h-12 text-[10px] font-black transition-all flex flex-col items-center justify-center gap-1 border border-transparent data-[state=active]:border-orange-100 group">
                                        <AlertCircle className="w-4 h-4 text-orange-500 group-data-[state=active]:scale-110 transition-transform" /> 
                                        <span className="text-gray-400 group-data-[state=active]:text-orange-700">MISPUNCH</span>
                                    </TabsTrigger>
                                )}
                                <TabsTrigger value="overtime" className="rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-lg px-5 h-12 text-[10px] font-black transition-all flex flex-col items-center justify-center gap-1 border border-transparent data-[state=active]:border-blue-100 group">
                                    <Timer className="w-4 h-4 text-blue-500 group-data-[state=active]:scale-110 transition-transform" /> 
                                    <span className="text-gray-400 group-data-[state=active]:text-blue-700">OVERTIME</span>
                                </TabsTrigger>
                                <TabsTrigger value="logdetails" className="rounded-lg data-[state=active]:bg-white data-[state=active]:shadow-lg px-5 h-12 text-[10px] font-black transition-all flex flex-col items-center justify-center gap-1 border border-transparent data-[state=active]:border-purple-100 group">
                                    <FileText className="w-4 h-4 text-purple-500 group-data-[state=active]:scale-110 transition-transform" /> 
                                    <span className="text-gray-400 group-data-[state=active]:text-purple-700">LOG DETAILS</span>
                                </TabsTrigger>
                            </TabsList>
                            </div>
                            {activeTab === 'mispunch' && (
                                <div className="flex bg-gray-100 dark:bg-gray-800/50 rounded-lg p-1.5 border border-gray-200/50 dark:border-gray-700 shadow-inner w-fit shrink-0">
                                    <Button 
                                        type="button"
                                        variant={viewMode === 'grid' ? 'default' : 'ghost'} 
                                        size="sm" 
                                        className={`h-10 px-5 text-[12px] font-black tracking-tight rounded-md transition-all ${viewMode === 'grid' ? 'bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100 shadow-sm' : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-100'}`}
                                        onClick={() => setViewMode('grid')}
                                    >
                                        <Table className="w-4 h-4 mr-2" /> Grid View
                                    </Button>
                                    <Button 
                                        type="button"
                                        variant={viewMode === 'list' ? 'default' : 'ghost'} 
                                        size="sm" 
                                        className={`h-10 px-5 text-[12px] font-black tracking-tight rounded-md transition-all ${viewMode === 'list' ? 'bg-orange-500 text-white shadow-md hover:bg-orange-600' : 'text-gray-500 hover:text-gray-900 dark:hover:text-gray-100'}`}
                                        onClick={() => setViewMode('list')}
                                    >
                                        <ListTodo className="w-4 h-4 mr-2" /> Action List
                                    </Button>
                                </div>
                            )}

                            <div className={activeTab !== 'mispunch' ? "ml-auto" : ""}>
                                <TooltipProvider delayDuration={100}>
                                    <Tooltip>
                                        <TooltipTrigger asChild>
                                            <Button variant="ghost" size="icon" className="h-10 w-10 text-gray-400 hover:text-blue-500 hover:bg-blue-50 dark:hover:bg-blue-900/20 rounded-full shrink-0">
                                                <Info className="h-5 w-5" />
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent side="left" align="start" sideOffset={10} className="w-max max-w-none p-0 bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 shadow-xl rounded-xl z-[100]">
                                            <div className="flex items-center gap-6 min-w-max p-4">
                                                <div className="flex items-center gap-5">
                                                    <div className="flex items-center gap-1.5 group cursor-help" title="Present">
                                                        <div className="w-5 h-5 rounded-md bg-emerald-50 flex items-center justify-center text-emerald-600 shadow-sm border border-emerald-100">
                                                            <CheckCircle2 className="w-3 h-3" />
                                                        </div>
                                                        <span className="text-[10px] font-bold text-gray-600 uppercase tracking-tighter">Present</span>
                                                    </div>
                                                    <div className="flex items-center gap-1.5 group cursor-help" title="Absent">
                                                        <div className="w-5 h-5 rounded-md bg-rose-50 flex items-center justify-center text-rose-600 shadow-sm border border-rose-100">
                                                            <XCircle className="w-3 h-3" />
                                                        </div>
                                                        <span className="text-[10px] font-bold text-gray-600 uppercase tracking-tighter">Absent</span>
                                                    </div>
                                                    <div className="flex items-center gap-1.5 group cursor-help" title="Half Day (0.5 duty)">
                                                        <div className="w-5 h-5 rounded-md bg-yellow-100 flex items-center justify-center text-yellow-800 shadow-sm border-2 border-yellow-400">
                                                            <span className="text-[9px] font-black">½</span>
                                                        </div>
                                                        <span className="text-[10px] font-bold text-gray-600 uppercase tracking-tighter">Half Day</span>
                                                    </div>
                                                    <div className="flex items-center gap-1.5 group cursor-help" title="Mispunch">
                                                        <div className="w-5 h-5 rounded-md bg-orange-50 flex items-center justify-center text-orange-600 shadow-sm border border-orange-100">
                                                            <AlertCircle className="w-3 h-3" />
                                                        </div>
                                                        <span className="text-[10px] font-bold text-gray-600 uppercase tracking-tighter">Mispunch</span>
                                                    </div>
                                                    <div className="flex items-center gap-1.5 group cursor-help" title="Week Off">
                                                        <div className="w-5 h-5 rounded-md bg-slate-100 flex items-center justify-center text-slate-600 shadow-sm border border-slate-200">
                                                            <CalendarIcon className="w-3 h-3" />
                                                        </div>
                                                        <span className="text-[10px] font-bold text-gray-600 uppercase tracking-tighter">Week Off</span>
                                                    </div>
                                                    <div className="flex items-center gap-1.5 group cursor-help" title="Future/Unmarked">
                                                        <div className="w-5 h-5 rounded-md bg-gray-50 flex items-center justify-center text-gray-300 shadow-sm border border-gray-100">
                                                            <Minus className="w-3 h-3" />
                                                        </div>
                                                        <span className="text-[10px] font-bold text-gray-400 uppercase tracking-tighter italic">Future</span>
                                                    </div>
                                                    
                                                    <div className="flex items-center gap-1.5 group cursor-help" title="Manually Adjusted">
                                                        <div className="w-5 h-5 rounded-md bg-indigo-600 flex items-center justify-center text-white shadow-sm border border-indigo-700">
                                                            <span className="text-[9px] font-black">M</span>
                                                        </div>
                                                        <span className="text-[10px] font-bold text-gray-600 uppercase tracking-tighter">Manual Entry</span>
                                                    </div>
                                                </div>

                                                <div className="h-5 w-px bg-gray-200 dark:bg-gray-800 hidden md:block"></div>

                                                <div className="flex items-center gap-4 bg-gray-50 dark:bg-gray-900/50 px-3 py-1.5 rounded-lg border border-gray-100/50 dark:border-gray-800">
                                                    <div className="flex items-center gap-2">
                                                        <div className="flex items-center gap-1.5">
                                                            <kbd className="px-1.5 py-0.5 text-[9px] font-black bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded shadow-sm text-primary leading-none">ALT</kbd>
                                                            <Plus className="w-2.5 h-2.5 text-gray-400" />
                                                            <div className="w-5 h-5 rounded-md bg-primary/5 flex items-center justify-center text-primary border border-primary/10 shadow-sm">
                                                                <CircleDot className="w-3 h-3" />
                                                            </div>
                                                        </div>
                                                        <span className="text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Horizontal Scroll</span>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <div className="w-5 h-5 rounded-md bg-emerald-50 flex items-center justify-center text-emerald-600 border border-emerald-100 shadow-sm">
                                                            <CircleDot className="w-3 h-3" />
                                                        </div>
                                                        <span className="text-[10px] font-bold text-gray-500 uppercase tracking-tighter">Vertical Scroll</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </div>
                        </div>
                        
                        <div className="relative pt-4">
                            <TabsContent value="status" className="m-0 border-none">
                                <AttendanceGrid data={data} type="status" getStatusIcon={getStatusIcon} loading={loading} onCellClick={handleCellClick} />
                            </TabsContent>
                            <TabsContent value="inout" className="m-0 border-none">
                                <AttendanceGrid data={data} type="inout" getStatusIcon={getStatusIcon} formatTime={formatTime} loading={loading} onCellClick={handleCellClick} />
                            </TabsContent>
                            <TabsContent value="hours" className="m-0 border-none">
                                <AttendanceGrid data={data} type="hours" getStatusIcon={getStatusIcon} formatHours={formatHours} loading={loading} onCellClick={handleCellClick} />
                            </TabsContent>
                            {!self_service_only && (
                                <TabsContent value="mispunch" className="m-0 border-none">
                                    {viewMode === 'list' ? (
                                        <MispunchResolutionList data={data} loading={loading} onResolveClick={(emp: any, d: number, r: any) => setSelectedRecord({emp, day: d, record: r})} />
                                    ) : (
                                        <AttendanceGrid data={data} type="mispunch" getStatusIcon={getStatusIcon} formatTime={formatTime} loading={loading} onCellClick={handleCellClick} />
                                    )}
                                </TabsContent>
                            )}
                            <TabsContent value="overtime" className="m-0 border-none">
                                <AttendanceGrid data={data} type="overtime" getStatusIcon={getStatusIcon} formatHours={formatHours} loading={loading} onCellClick={handleCellClick} />
                            </TabsContent>
                            <TabsContent value="logdetails" className="m-0 border-none">
                                <AttendanceGrid data={data} type="logdetails" getStatusIcon={getStatusIcon} loading={loading} onCellClick={handleCellClick} />
                            </TabsContent>
                        </div>
                    </Tabs>
                    
                    {data?.pagination && (
                        <div className="px-6 py-4 bg-gray-50/50 dark:bg-gray-900/50 border-t border-gray-100 dark:border-gray-800 flex items-center justify-between">
                            <div className="text-xs font-bold text-gray-400 uppercase tracking-widest">
                                Showing <span className="text-gray-900 dark:text-white">{data.pagination.from}</span> to <span className="text-gray-900 dark:text-white">{data.pagination.to}</span> of <span className="text-gray-900 dark:text-white">{data.pagination.total}</span> employees
                            </div>
                            <div className="flex items-center gap-2">
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    className="h-8 w-8 p-0 rounded-lg"
                                    disabled={filters.page <= 1}
                                    onClick={() => setFilters({...filters, page: filters.page - 1})}
                                >
                                    <ChevronLeft className="w-4 h-4" />
                                </Button>
                                <div className="px-3 py-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-700 rounded-lg text-xs font-black shadow-sm">
                                    {data.pagination.current_page} / {data.pagination.last_page}
                                </div>
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    className="h-8 w-8 p-0 rounded-lg"
                                    disabled={filters.page >= data.pagination.last_page}
                                    onClick={() => setFilters({...filters, page: filters.page + 1})}
                                >
                                    <ChevronRight className="w-4 h-4" />
                                </Button>
                            </div>
                        </div>
                    )}
                </Card>

                {/* Detail / Edit Modal (single popup) */}
                <Dialog
                    open={!!selectedRecord}
                    onOpenChange={(open) => {
                        if (!open) {
                            setSelectedRecord(null);
                            setRecordModalMode('view');
                        }
                    }}
                >
                    <DialogContent className="max-w-2xl sm:max-w-3xl max-h-[92vh] flex flex-col p-0 gap-0 overflow-hidden">
                        <DialogHeader className="px-6 pt-6 pb-2 shrink-0">
                            <DialogTitle className="flex items-center gap-2">
                                <CalendarIcon className="w-5 h-5 text-primary" />
                                {recordModalMode === 'edit' ? 'Edit' : ''} Attendance — {selectedRecord?.day} {data?.month_name}
                            </DialogTitle>
                            <DialogDescription>
                                {recordModalMode === 'edit'
                                    ? 'Pick status, then enter IN/OUT times. Save when done.'
                                    : 'Summary for this day — tap Edit to change.'}
                            </DialogDescription>
                        </DialogHeader>
                        
                        {selectedRecord && (
                            <>
                            <div className="flex-1 overflow-y-auto px-6 py-4 custom-scrollbar min-h-0">
                                <AttendanceDayModalBody
                                    mode={recordModalMode}
                                    dayLabel={String(selectedRecord.day)}
                                    monthName={data?.month_name ?? ''}
                                    emp={selectedRecord.emp}
                                    record={selectedRecord.record}
                                    editForm={editForm}
                                    onEditFormChange={(patch) => setEditForm({ ...editForm, ...patch })}
                                    editPunchPairs={editPunchPairs}
                                    onEditPunchPairsChange={setEditPunchPairs}
                                    editShiftBounds={editShiftBounds}
                                    formatHours={formatHours}
                                    onStatusChange={(v) => {
                                        const emp = selectedRecord.emp;
                                        if (v === 'HD') {
                                            applyHalfDayFromShift(
                                                emp,
                                                editForm.shift_slot_id,
                                                setEditPunchPairs,
                                                setEditForm
                                            );
                                        } else if (v === 'A') {
                                            setEditForm({ ...editForm, status: v });
                                            setEditPunchPairs([]);
                                        } else if (v === 'MIS') {
                                            const slotId =
                                                editForm.shift_slot_id ||
                                                (hasMispunchIssues(editPunchPairs) && emp.is_multi_shift
                                                    ? 'custom'
                                                    : '');
                                            setEditForm({ ...editForm, status: v, shift_slot_id: slotId });
                                        } else {
                                            setEditForm({ ...editForm, status: v });
                                            if (v === 'P' && editForm.shift_slot_id && editForm.shift_slot_id !== 'custom') {
                                                applySlotPunchPairs(emp.slots, editForm.shift_slot_id, setEditPunchPairs);
                                            }
                                        }
                                    }}
                                    onSelectShift={(slotId) => {
                                        const emp = selectedRecord.emp;
                                        if (slotId === 'custom') {
                                            setEditForm((prev: { status: string; remarks: string; shift_slot_id: string }) => ({
                                                ...prev,
                                                shift_slot_id: 'custom',
                                                status: prev.status === 'A' ? 'MIS' : prev.status,
                                            }));
                                            return;
                                        }
                                        if (editForm.status === 'HD') {
                                            applyHalfDayFromShift(emp, slotId, setEditPunchPairs, setEditForm);
                                        } else {
                                            applySlotPunchPairs(emp.slots, slotId, setEditPunchPairs);
                                            setEditForm((prev: { status: string; remarks: string; shift_slot_id: string }) => ({
                                                ...prev,
                                                shift_slot_id: slotId,
                                                status: prev.status === 'A' || prev.status === '' ? 'P' : prev.status,
                                            }));
                                        }
                                    }}
                                    onMarkPresent={() => {
                                        const emp = selectedRecord.emp;
                                        const slotId =
                                            editForm.shift_slot_id ||
                                            (emp.slots?.length === 1 ? String(emp.slots[0].id) : '');
                                        setEditForm({ ...editForm, status: 'P', shift_slot_id: slotId });
                                        if (slotId && emp.slots?.length) {
                                            applySlotPunchPairs(emp.slots, slotId, setEditPunchPairs);
                                        } else {
                                            const bounds = resolveShiftBounds(emp, '');
                                            setEditPunchPairs(
                                                bounds ? pairsFromShiftBounds(bounds) : defaultPunchPairs()
                                            );
                                        }
                                    }}
                                />
                            </div>

                            <DialogFooter className="px-6 py-4 border-t bg-muted/30 shrink-0 flex-col gap-2">
                                {recordModalMode === 'view' ? (
                                    <>
                                        <Button className="w-full h-11 gap-2 text-base font-bold shadow-md shadow-primary/20" onClick={openRecordForEdit}>
                                            <Edit className="w-5 h-5" />
                                            Edit this day's attendance
                                        </Button>
                                        <Button variant="ghost" className="w-full text-muted-foreground" onClick={() => setSelectedRecord(null)}>
                                            Close
                                        </Button>
                                    </>
                                ) : (
                                    <>
                                        <Button variant="outline" className="w-full sm:w-auto" onClick={() => setRecordModalMode('view')}>
                                            Back
                                        </Button>
                                        <Button
                                            className="w-full sm:flex-1"
                                            onClick={async () => {
                                                try {
                                                    if (editForm.status !== 'A') {
                                                        const incomplete = hasMispunchIssues(editPunchPairs);
                                                        const validation = validatePunchPairs(
                                                            editPunchPairs,
                                                            editShiftBounds,
                                                            {
                                                                allowPartial:
                                                                    editForm.status === 'MIS' || incomplete,
                                                            }
                                                        );
                                                        if (!validation.valid) {
                                                            toast.error(validation.message);
                                                            return;
                                                        }
                                                        if (
                                                            incomplete &&
                                                            editForm.status === 'P'
                                                        ) {
                                                            toast.error(
                                                                'Complete all missing IN/OUT times before marking Present.'
                                                            );
                                                            return;
                                                        }
                                                    }
                                                    const finalPayload = editForm.status === 'A'
                                                        ? { ...editForm, in_time: null, out_time: null, log_details: '' }
                                                        : buildAttendancePayloadFromPairs(editPunchPairs, editForm.status, {
                                                            remarks: editForm.remarks,
                                                            shift_slot_id: editForm.shift_slot_id,
                                                        });

                                                    await axios.post(route('hr.attendance.update-record'), {
                                                        employee_id: selectedRecord?.emp.id,
                                                        date: `${filters.month}-${selectedRecord?.day.toString().padStart(2, '0')}`,
                                                        source: activeTab === 'mispunch' ? 'mispunch' : 'attendance',
                                                        ...finalPayload,
                                                    });
                                                    toast.success('Attendance saved');
                                                    setSelectedRecord(null);
                                                    setRecordModalMode('view');
                                                    fetchData();
                                                } catch (e) {
                                                    toast.error('Save failed');
                                                }
                                            }}
                                        >
                                            Save attendance
                                        </Button>
                                    </>
                                )}
                            </DialogFooter>
                            </>
                        )}
                    </DialogContent>
                </Dialog>

                {/* Manual Entry Dialog */}
                <Dialog open={manualEntryOpen} onOpenChange={(open) => {
                    if (!open) resetManualForm();
                    setManualEntryOpen(open);
                }}>
                    <DialogContent className="max-w-2xl sm:max-w-3xl flex flex-col max-h-[90vh]">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Plus className="w-5 h-5 text-primary" /> Add Attendance Manually
                            </DialogTitle>
                            <DialogDescription>
                                Follow the steps below to add or correct an employee's attendance for any day.
                            </DialogDescription>
                        </DialogHeader>
                        
                        <div className="space-y-5 py-4 max-h-[70vh] overflow-y-auto px-1 custom-scrollbar">

                            {/* ── Step ①: Find Employee ── */}
                            <div className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <div className="w-7 h-7 rounded-full bg-primary flex items-center justify-center text-white text-sm font-black shrink-0">①</div>
                                    <span className="text-sm font-semibold text-gray-700">Find the employee</span>
                                </div>
                                <div className="relative">
                                    <Search className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
                                    <Input 
                                        placeholder="Type employee name or code..." 
                                        className="pl-10 h-10 border-gray-200 focus:ring-primary/20"
                                        value={manualSearchQuery}
                                        onChange={(e) => setManualSearchQuery(e.target.value)}
                                    />
                                    {searchingManual && (
                                        <div className="absolute right-3 top-3">
                                            <div className="w-4 h-4 rounded-full border-2 border-primary/20 border-t-primary animate-spin" />
                                        </div>
                                    )}
                                </div>
                                
                                {manualSearchResults.length > 0 && !selectedManualEmp && (
                                    <div className="border rounded-xl overflow-hidden shadow-xl bg-white border-gray-100 divide-y max-h-48 overflow-y-auto custom-scrollbar">
                                        {manualSearchResults.map((res: any) => (
                                            <div 
                                                key={res.employee.id} 
                                                className="p-3 hover:bg-primary/5 cursor-pointer flex items-center gap-3 transition-colors"
                                                onClick={() => {
                                                    setSelectedManualEmp(res.employee);
                                                    setManualSearchQuery(`${res.employee.name} (${res.employee.code})`);
                                                    setManualSearchResults([]);
                                                    setManualForm((prev: typeof manualForm) => ({
                                                        ...prev,
                                                        shift_slot_id: '',
                                                    }));
                                                    const bounds = resolveShiftBounds(res.employee, '');
                                                    setManualPunchPairs(
                                                        bounds
                                                            ? pairsFromShiftBounds(bounds)
                                                            : defaultPunchPairs(
                                                                  res.employee.shift_start || '09:00',
                                                                  res.employee.shift_end || '18:00'
                                                              )
                                                    );
                                                }}
                                            >
                                                <div className="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-[11px] font-black text-primary">
                                                    {res.employee.name.charAt(0)}
                                                </div>
                                                <div className="flex-1">
                                                    <p className="text-sm font-bold text-gray-900">{res.employee.name}</p>
                                                    <p className="text-[10px] font-medium text-gray-400 uppercase tracking-tighter">{res.employee.code} · {res.employee.designation}</p>
                                                </div>
                                                <ChevronRight className="w-4 h-4 text-gray-300" />
                                            </div>
                                        ))}
                                    </div>
                                )}

                                {/* Selected employee card */}
                                {selectedManualEmp && (
                                    <div className="flex items-center gap-3 p-3 bg-primary/5 rounded-xl border border-primary/20 animate-in fade-in">
                                        <div className="w-9 h-9 rounded-full bg-primary/10 flex items-center justify-center text-[11px] font-black text-primary shrink-0">
                                            {selectedManualEmp.name.charAt(0)}
                                        </div>
                                        <div className="flex-1 min-w-0">
                                            <p className="text-sm font-bold text-gray-900 truncate">{selectedManualEmp.name}</p>
                                            <p className="text-[10px] text-gray-400 font-medium uppercase">{selectedManualEmp.code} · {selectedManualEmp.designation}</p>
                                        </div>
                                        <Button variant="ghost" size="sm" className="h-7 text-[11px] text-primary hover:bg-primary/10 shrink-0" onClick={() => {
                                            setSelectedManualEmp(null);
                                            setManualSearchQuery('');
                                        }}>Change</Button>
                                    </div>
                                )}
                            </div>

                            {/* ── Steps ② ③ ④ — only show after employee selected ── */}
                            {selectedManualEmp && (
                                <div className="space-y-5 animate-in fade-in slide-in-from-top-2">

                                    {/* ── Step ②: Date & Status ── */}
                                    <div className="space-y-3">
                                        <div className="flex items-center gap-2">
                                            <div className="w-7 h-7 rounded-full bg-primary flex items-center justify-center text-white text-sm font-black shrink-0">②</div>
                                            <span className="text-sm font-semibold text-gray-700">Pick date and attendance status</span>
                                        </div>
                                        <div className="p-4 rounded-xl border border-gray-200 bg-gray-50 space-y-3">
                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Date</Label>
                                                <Input 
                                                    type="date" 
                                                    value={manualForm.date} 
                                                    onChange={(e) => setManualForm({...manualForm, date: e.target.value})} 
                                                    className="h-10 bg-white" 
                                                />
                                            </div>
                                            <div className="space-y-1.5">
                                                <Label className="text-xs font-bold text-gray-500 uppercase tracking-wider">Status</Label>
                                                <div className="grid grid-cols-2 gap-2">
                                                    {[
                                                        { value: 'P', label: 'Present', desc: 'Employee came to work', color: 'border-emerald-300 bg-emerald-50 text-emerald-800' },
                                                        { value: 'A', label: 'Absent', desc: 'Did not come', color: 'border-rose-300 bg-rose-50 text-rose-800' },
                                                        { value: 'HD', label: 'Half Day', desc: 'Came for half shift', color: 'border-amber-300 bg-amber-50 text-amber-800' },
                                                        { value: 'MIS', label: 'Mispunch', desc: 'Machine missed punch', color: 'border-orange-300 bg-orange-50 text-orange-800' },
                                                    ].map((opt) => {
                                                        const isActive = manualForm.status === opt.value;
                                                        return (
                                                            <button
                                                                key={opt.value}
                                                                type="button"
                                                                onClick={() => {
                                                                    if (opt.value === 'HD') {
                                                                        applyHalfDayFromShift(
                                                                            selectedManualEmp,
                                                                            manualForm.shift_slot_id,
                                                                            setManualPunchPairs,
                                                                            setManualForm
                                                                        );
                                                                    } else if (opt.value === 'A') {
                                                                        setManualForm({ ...manualForm, status: opt.value });
                                                                        setManualPunchPairs([]);
                                                                    } else {
                                                                        setManualForm({ ...manualForm, status: opt.value });
                                                                    }
                                                                }}
                                                                className={`flex flex-col items-start px-3 py-2.5 rounded-xl border-2 text-left transition-all ${
                                                                    isActive
                                                                        ? opt.color + ' ring-2 ring-offset-1 ring-primary/30 shadow-sm'
                                                                        : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300'
                                                                }`}
                                                            >
                                                                <span className="font-bold text-sm leading-tight">{opt.label}</span>
                                                                <span className={`text-[10px] leading-tight mt-0.5 ${isActive ? 'opacity-75' : 'text-gray-400'}`}>{opt.desc}</span>
                                                            </button>
                                                        );
                                                    })}
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Absent — no times needed */}
                                    {manualForm.status === 'A' && (
                                        <div className="rounded-xl border-2 border-dashed border-rose-200 bg-rose-50/50 p-4 text-center space-y-1">
                                            <p className="text-sm font-bold text-rose-800">Absent — no IN/OUT times needed</p>
                                            <p className="text-xs text-rose-600">You can add a note below and save directly.</p>
                                        </div>
                                    )}

                                    {/* Half-day shift bounds hint */}
                                    {manualForm.status === 'HD' && manualShiftBounds && (
                                        <div className="flex items-center gap-2 p-3 rounded-xl bg-yellow-50 border border-yellow-200 text-yellow-900 text-xs">
                                            <span className="text-lg font-black">½</span>
                                            <span>
                                                Half day from shift rules:{' '}
                                                <strong>{formatTime12h(manualShiftBounds.start)} → {formatTime12h(manualShiftBounds.end)}</strong>
                                            </span>
                                        </div>
                                    )}

                                    {/* ── Step ③: Times ── (only if not Absent) */}
                                    {manualForm.status !== 'A' && (
                                        <div className="space-y-3">
                                            <div className="flex items-center gap-2">
                                                <div className="w-7 h-7 rounded-full bg-primary flex items-center justify-center text-white text-sm font-black shrink-0">③</div>
                                                <span className="text-sm font-semibold text-gray-700">Enter clock-in and clock-out times</span>
                                            </div>

                                            {/* Multi-shift slot picker */}
                                            {selectedManualEmp.is_multi_shift && selectedManualEmp.slots?.length > 0 && (
                                                <div className="space-y-2 p-4 bg-amber-50/60 rounded-xl border border-amber-200/80">
                                                    <Label className="text-xs font-bold text-amber-900 uppercase tracking-wide flex items-center gap-2">
                                                        <Timer className="w-3.5 h-3.5" />
                                                        {manualForm.status === 'HD' ? 'Select shift (half day auto-fill)' : 'Which shift? (auto-fill times)'}
                                                    </Label>
                                                    <Select
                                                        value={manualForm.shift_slot_id || undefined}
                                                        onValueChange={(slotId) => {
                                                            if (manualForm.status === 'HD') {
                                                                applyHalfDayFromShift(
                                                                    selectedManualEmp,
                                                                    slotId,
                                                                    setManualPunchPairs,
                                                                    setManualForm
                                                                );
                                                            } else {
                                                                applySlotPunchPairs(selectedManualEmp.slots, slotId, setManualPunchPairs);
                                                                setManualForm((prev: typeof manualForm) => ({
                                                                    ...prev,
                                                                    shift_slot_id: slotId,
                                                                    status: prev.status === 'A' || prev.status === '' ? 'P' : prev.status,
                                                                }));
                                                            }
                                                        }}
                                                    >
                                                        <SelectTrigger className="h-10 bg-white"><SelectValue placeholder="Select shift to auto-fill times" /></SelectTrigger>
                                                        <SelectContent>
                                                            {selectedManualEmp.slots.map((slot: any) => {
                                                                const start = slot.start_time ? slot.start_time.substring(0, 5) : '';
                                                                const end = slot.end_time ? slot.end_time.substring(0, 5) : '';
                                                                const hdMins = slot.half_day_mins ?? 0;
                                                                return (
                                                                    <SelectItem key={slot.id} value={slot.id.toString()}>
                                                                        {slot.slot_name} ({start} – {end})
                                                                        {manualForm.status === 'HD' && hdMins > 0
                                                                            ? ` — ½ ${formatMinutesAsHours(hdMins)}`
                                                                            : ''}
                                                                    </SelectItem>
                                                                );
                                                            })}
                                                            {manualForm.status !== 'HD' && selectedManualEmp.slots.length > 1 && (
                                                                <SelectItem value="both">
                                                                    {selectedManualEmp.slots.map((s: any) => s.slot_name).join(' + ')} (2 pairs)
                                                                </SelectItem>
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            )}

                                            {/* Single shift HD auto-fill button */}
                                            {!selectedManualEmp.is_multi_shift && manualForm.status === 'HD' && selectedManualEmp.slots?.length <= 1 && (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className="w-full border-yellow-300 text-yellow-800 gap-2"
                                                    onClick={() =>
                                                        applyHalfDayFromShift(
                                                            selectedManualEmp,
                                                            manualForm.shift_slot_id,
                                                            setManualPunchPairs,
                                                            setManualForm
                                                        )
                                                    }
                                                >
                                                    Apply half day times from shift
                                                </Button>
                                            )}

                                            <PunchPairsEditor
                                                pairs={manualPunchPairs}
                                                shiftBounds={manualShiftBounds}
                                                onChange={(pairs) => {
                                                    setManualPunchPairs(pairs);
                                                    if (manualForm.status === 'A' || manualForm.status === '') {
                                                        setManualForm({ ...manualForm, status: 'P' });
                                                    }
                                                }}
                                            />
                                        </div>
                                    )}

                                    {/* ── Step ④: Note ── */}
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2">
                                            <div className="w-7 h-7 rounded-full bg-primary flex items-center justify-center text-white text-sm font-black shrink-0">④</div>
                                            <span className="text-sm font-semibold text-gray-700">Add a note <span className="text-gray-400 font-normal">(optional)</span></span>
                                        </div>
                                        <Textarea
                                            placeholder="e.g. Device was offline, corrected by HR..."
                                            value={manualForm.remarks}
                                            onChange={(e) => setManualForm({...manualForm, remarks: e.target.value})}
                                            className="min-h-[72px]"
                                        />
                                    </div>
                                </div>
                            )}
                        </div>

                        <DialogFooter className="bg-gray-50/50 p-4 -mx-6 -mb-6 border-t border-gray-100 rounded-b-xl flex-col gap-2">
                            <Button 
                                disabled={!selectedManualEmp || !manualForm.date}
                                onClick={async () => {
                                    try {
                                        if (manualForm.status !== 'A') {
                                            const validation = validatePunchPairs(manualPunchPairs, manualShiftBounds);
                                            if (!validation.valid) {
                                                toast.error(validation.message);
                                                return;
                                            }
                                        }
                                        const finalPayload = manualForm.status === 'A'
                                            ? { status: 'A', in_time: null, out_time: null, log_details: '' }
                                            : buildAttendancePayloadFromPairs(manualPunchPairs, manualForm.status, {
                                                remarks: manualForm.remarks,
                                                shift_slot_id: manualForm.shift_slot_id,
                                            });

                                        await axios.post(route('hr.attendance.update-record'), {
                                            employee_id: selectedManualEmp.id,
                                            date: manualForm.date,
                                            source: activeTab === 'mispunch' ? 'mispunch' : 'attendance',
                                            ...finalPayload,
                                        });
                                        toast.success('Manual entry saved successfully');
                                        setManualEntryOpen(false);
                                        resetManualForm();
                                        fetchData();
                                    } catch (error) {
                                        toast.error('Failed to save entry');
                                    }
                                }}
                                className="w-full h-11 text-base font-bold shadow-lg shadow-primary/20 gap-2"
                            >
                                <Plus className="w-4 h-4" />
                                {selectedManualEmp
                                    ? `Save attendance for ${selectedManualEmp.name.split(' ')[0]}`
                                    : 'Save attendance'}
                            </Button>
                            <Button variant="ghost" className="w-full text-muted-foreground" onClick={() => {
                                setManualEntryOpen(false);
                                resetManualForm();
                            }}>Cancel</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Bulk Attendance Dialog */}
                <Dialog open={bulkAttendanceOpen} onOpenChange={(open) => {
                    if (!open) resetBulkForm();
                    setBulkAttendanceOpen(open);
                }}>
                    <DialogContent className="max-w-xl p-0 overflow-hidden rounded-3xl border-none shadow-2xl bg-white dark:bg-gray-950">
                        <DialogHeader className="p-6 bg-[#1a365d] text-white shrink-0 relative overflow-hidden">
                            <div className="absolute top-0 right-0 w-32 h-32 bg-white/5 rounded-full -mr-16 -mt-16 blur-2xl"></div>
                            <div className="absolute bottom-0 left-0 w-24 h-24 bg-blue-400/10 rounded-full -ml-12 -mb-12 blur-xl"></div>
                            
                            <div className="flex items-center gap-4 relative z-10">
                                <div className="p-3 bg-white/10 rounded-2xl backdrop-blur-md border border-white/10 shadow-inner">
                                    <Layers className="w-6 h-6 text-blue-200" />
                                </div>
                                <div>
                                    <DialogTitle className="text-xl font-bold tracking-tight">Bulk Attendance</DialogTitle>
                                    <DialogDescription className="text-blue-100/70 font-medium text-xs mt-1">
                                        Assign attendance to multiple employees at once over a date range.
                                    </DialogDescription>
                                </div>
                            </div>
                        </DialogHeader>

                        <div className="p-6 space-y-6 max-h-[70vh] overflow-y-auto custom-scrollbar">
                            
                            <div className="space-y-4">
                                <div className="flex items-center gap-2 px-1">
                                    <Users className="h-4 w-4 text-primary" />
                                    <span className="text-[11px] font-black text-gray-400 uppercase tracking-widest">1. Target Audience</span>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-1.5 col-span-2">
                                        <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Assign to</Label>
                                        <Select 
                                            value={bulkForm.target} 
                                            onValueChange={(v) => setBulkForm({...bulkForm, target: v})}
                                        >
                                            <SelectTrigger className="h-11 rounded-xl border-gray-200 text-sm font-medium">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent className="max-h-[250px]">
                                                <SelectItem value="all">All Employees</SelectItem>
                                                <SelectItem value="department">By Department</SelectItem>
                                                <SelectItem value="designation">By Designation</SelectItem>
                                                <SelectItem value="category">By Category</SelectItem>
                                                <SelectItem value="shift">By Shift</SelectItem>
                                                <SelectItem value="employee">Specific Employee</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    
                                    {bulkForm.target === 'department' && (
                                        <div className="space-y-1.5 col-span-2 animate-in fade-in slide-in-from-top-2">
                                            <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Select Department</Label>
                                            <Select value={bulkForm.department_id} onValueChange={(v) => setBulkForm({...bulkForm, department_id: v})}>
                                                <SelectTrigger className="h-11 rounded-xl border-gray-200 text-sm font-medium">
                                                    <SelectValue placeholder="Choose Department" />
                                                </SelectTrigger>
                                                <SelectContent className="max-h-[250px]">
                                                    {departments.map((d: any) => (
                                                        <SelectItem key={d.id} value={d.id.toString()}>{d.name}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    )}

                                    {bulkForm.target === 'designation' && (
                                        <div className="space-y-1.5 col-span-2 animate-in fade-in slide-in-from-top-2">
                                            <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Select Designation</Label>
                                            <Select value={bulkForm.designation_id} onValueChange={(v) => setBulkForm({...bulkForm, designation_id: v})}>
                                                <SelectTrigger className="h-11 rounded-xl border-gray-200 text-sm font-medium">
                                                    <SelectValue placeholder="Choose Designation (e.g. Director)" />
                                                </SelectTrigger>
                                                <SelectContent className="max-h-[250px]">
                                                    {designations?.map((d: any) => (
                                                        <SelectItem key={d.id} value={d.id.toString()}>{d.name}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    )}

                                    {bulkForm.target === 'category' && (
                                        <div className="space-y-1.5 col-span-2 animate-in fade-in slide-in-from-top-2">
                                            <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Select Category</Label>
                                            <Select value={bulkForm.category_id} onValueChange={(v) => setBulkForm({...bulkForm, category_id: v})}>
                                                <SelectTrigger className="h-11 rounded-xl border-gray-200 text-sm font-medium">
                                                    <SelectValue placeholder="Choose Category" />
                                                </SelectTrigger>
                                                <SelectContent className="max-h-[250px]">
                                                    {categories?.map((c: any) => (
                                                        <SelectItem key={c.id} value={c.id.toString()}>{c.name}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    )}

                                    {bulkForm.target === 'shift' && (
                                        <div className="space-y-1.5 col-span-2 animate-in fade-in slide-in-from-top-2">
                                            <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Select Shift</Label>
                                            <Select value={bulkForm.shift_id} onValueChange={(v) => setBulkForm({...bulkForm, shift_id: v})}>
                                                <SelectTrigger className="h-11 rounded-xl border-gray-200 text-sm font-medium">
                                                    <SelectValue placeholder="Choose Shift" />
                                                </SelectTrigger>
                                                <SelectContent className="max-h-[250px]">
                                                    {shifts_for_rules?.map((s: any) => (
                                                        <SelectItem key={s.id} value={s.id.toString()}>
                                                            {s.short_code ? `${s.short_code} — ${s.name}` : s.name}
                                                            {s.is_multi ? ' (Multi)' : ''}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    )}

                                    {bulkForm.target === 'employee' && (
                                        <div className="space-y-1.5 col-span-2 animate-in fade-in slide-in-from-top-2">
                                            <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Search Employee</Label>
                                            <div className="relative">
                                                <Search className="absolute left-3 top-3 h-5 w-5 text-gray-400" />
                                                <Input 
                                                    placeholder="Type name or code..." 
                                                    value={manualSearchQuery}
                                                    onChange={(e) => {
                                                        setManualSearchQuery(e.target.value);
                                                        setSelectedManualEmp(null);
                                                    }}
                                                    className="pl-10 h-11 rounded-xl border-gray-200 text-sm font-medium"
                                                />
                                                {searchingManual && (
                                                    <div className="absolute right-3 top-3">
                                                        <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-primary"></div>
                                                    </div>
                                                )}
                                            </div>
                                            {manualSearchResults.length > 0 && !selectedManualEmp && (
                                                <div className="absolute z-50 w-full mt-1 bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-xl shadow-xl max-h-48 overflow-y-auto p-1">
                                                    {manualSearchResults.map((res: any) => (
                                                        <div 
                                                            key={res.employee.id} 
                                                            className="px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer rounded-lg flex items-center gap-3 transition-colors"
                                                            onClick={() => {
                                                                setSelectedManualEmp(res.employee);
                                                                setBulkForm({...bulkForm, employee_id: res.employee.id});
                                                                setManualSearchQuery(res.employee.name);
                                                                setManualSearchResults([]);
                                                            }}
                                                        >
                                                            <div className="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                                                                {(res.employee.name || 'NA').substring(0, 2).toUpperCase()}
                                                            </div>
                                                            <div>
                                                                <div className="text-sm font-bold">{res.employee.name || 'Unknown'}</div>
                                                                <div className="text-xs text-gray-500">{res.employee.code || res.employee.emy_code} • {res.employee.department?.name || res.employee.designation || 'N/A'}</div>
                                                            </div>
                                                        </div>
                                                    ))}
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="space-y-4">
                                <div className="flex items-center gap-2 px-1">
                                    <CalendarIcon className="h-4 w-4 text-primary" />
                                    <span className="text-[11px] font-black text-gray-400 uppercase tracking-widest">2. Date Range</span>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-1.5">
                                        <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">From Date</Label>
                                        <Input 
                                            type="date"
                                            value={bulkForm.from_date}
                                            onChange={(e) => setBulkForm({...bulkForm, from_date: e.target.value})}
                                            className="h-11 rounded-xl border-gray-200 font-medium"
                                        />
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">To Date</Label>
                                        <Input 
                                            type="date"
                                            value={bulkForm.to_date}
                                            onChange={(e) => setBulkForm({...bulkForm, to_date: e.target.value})}
                                            className="h-11 rounded-xl border-gray-200 font-medium"
                                        />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-4 bg-gray-50 dark:bg-gray-900/50 p-4 rounded-2xl border border-gray-100 dark:border-gray-800">
                                <div className="flex items-center gap-2 px-1 mb-2">
                                    <ClipboardList className="h-4 w-4 text-primary" />
                                    <span className="text-[11px] font-black text-gray-400 uppercase tracking-widest">3. Attendance Details</span>
                                </div>
                                <div className="grid grid-cols-3 gap-4">
                                    <div className="space-y-1.5 col-span-3">
                                        <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Status</Label>
                                        <div className="flex gap-2 p-1 bg-gray-100 dark:bg-gray-800/50 rounded-xl overflow-x-auto custom-scrollbar border border-gray-200/50">
                                            {[
                                                { id: 'P', label: 'Present', color: 'emerald' },
                                                { id: 'A', label: 'Absent', color: 'rose' },
                                                { id: 'W', label: 'Week Off', color: 'slate' },
                                                { id: 'H', label: 'Holiday', color: 'amber' }
                                            ].map(opt => (
                                                <button
                                                    key={opt.id}
                                                    type="button"
                                                    onClick={() => setBulkForm({...bulkForm, status: opt.id})}
                                                    className={`
                                                        flex-1 px-4 py-2.5 rounded-lg text-sm font-bold transition-all whitespace-nowrap
                                                        ${bulkForm.status === opt.id 
                                                            ? `bg-${opt.color}-50 text-${opt.color}-700 shadow-sm border border-${opt.color}-200` 
                                                            : 'text-gray-500 hover:bg-white hover:text-gray-900 border border-transparent'}
                                                    `}
                                                >
                                                    {opt.label}
                                                </button>
                                            ))}
                                        </div>
                                    </div>

                                    {bulkForm.status !== 'A' && (
                                        <>
                                            <div className="space-y-1.5 col-span-3">
                                                <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Time Mode</Label>
                                                <div className="flex gap-2">
                                                    <label className={`flex items-center gap-2 p-2.5 rounded-xl border ${bulkForm.time_mode === 'shift' ? 'bg-primary/5 border-primary text-primary' : 'bg-white border-gray-200'} cursor-pointer flex-1`}>
                                                        <input type="radio" checked={bulkForm.time_mode === 'shift'} onChange={() => setBulkForm({...bulkForm, time_mode: 'shift'})} className="hidden" />
                                                        <Clock className="w-4 h-4" />
                                                        <span className="text-sm font-bold">Default Shift</span>
                                                    </label>
                                                    <label className={`flex items-center gap-2 p-2.5 rounded-xl border ${bulkForm.time_mode === 'custom' ? 'bg-primary/5 border-primary text-primary' : 'bg-white border-gray-200'} cursor-pointer flex-1`}>
                                                        <input type="radio" checked={bulkForm.time_mode === 'custom'} onChange={() => setBulkForm({...bulkForm, time_mode: 'custom'})} className="hidden" />
                                                        <Edit className="w-4 h-4" />
                                                        <span className="text-sm font-bold">Custom Time</span>
                                                    </label>
                                                </div>
                                            </div>

                                            {bulkForm.time_mode === 'shift' && (
                                                <div className="space-y-1.5 col-span-3 animate-in fade-in slide-in-from-top-2">
                                                    {bulkForm.target === 'employee' && selectedManualEmp && !selectedManualEmp.is_multi_shift ? (
                                                        <>
                                                            <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Applied Shift Time</Label>
                                                            <div className="h-11 rounded-xl border border-gray-200 bg-gray-50 flex items-center px-3 text-sm font-bold text-gray-500">
                                                                <Clock className="w-4 h-4 mr-2 text-primary" />
                                                                {selectedManualEmp.shift || 'General Shift'}
                                                            </div>
                                                        </>
                                                    ) : (
                                                        <>
                                                            <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Multi-Shift Employees Preference</Label>
                                                            <Select value={bulkForm.multi_shift_slot} onValueChange={(v) => setBulkForm({...bulkForm, multi_shift_slot: v})}>
                                                                <SelectTrigger className="h-11 rounded-xl border-gray-200 text-sm font-bold bg-white">
                                                                    <SelectValue placeholder="Select Slot" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="first">Apply Slot 1 (Day / Default)</SelectItem>
                                                                    <SelectItem value="second">Apply Slot 2 (Night)</SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </>
                                                    )}
                                                </div>
                                            )}

                                            {bulkForm.time_mode === 'custom' && (
                                                <>
                                                    <div className="space-y-1.5 col-span-3 sm:col-span-1">
                                                        <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">In Time</Label>
                                                        <Input 
                                                            type="time" 
                                                            value={bulkForm.in_time}
                                                            onChange={(e) => setBulkForm({...bulkForm, in_time: e.target.value})}
                                                            className="h-11 rounded-xl font-bold bg-white"
                                                        />
                                                    </div>
                                                    <div className="space-y-1.5 col-span-3 sm:col-span-1">
                                                        <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Out Time</Label>
                                                        <Input 
                                                            type="time" 
                                                            value={bulkForm.out_time}
                                                            onChange={(e) => setBulkForm({...bulkForm, out_time: e.target.value})}
                                                            className="h-11 rounded-xl font-bold bg-white"
                                                        />
                                                    </div>
                                                </>
                                            )}
                                            
                                            <div className="space-y-1.5 col-span-3 sm:col-span-1 flex flex-col justify-end">
                                                <label className="flex items-center gap-2 p-2.5 bg-white border border-gray-200 rounded-xl cursor-pointer hover:bg-gray-50 transition-colors h-11">
                                                    <input 
                                                        type="checkbox" 
                                                        checked={bulkForm.overwrite}
                                                        onChange={(e) => setBulkForm({...bulkForm, overwrite: e.target.checked})}
                                                        className="w-4 h-4 text-primary rounded border-gray-300 focus:ring-primary"
                                                    />
                                                    <span className="text-xs font-bold text-gray-700">Overwrite Existing</span>
                                                </label>
                                            </div>
                                        </>
                                    )}
                                    <div className="space-y-1.5 col-span-3">
                                        <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">Remarks</Label>
                                        <Textarea
                                            placeholder="e.g. Bulk assigned by Admin..."
                                            value={bulkForm.remarks}
                                            onChange={(e) => setBulkForm({...bulkForm, remarks: e.target.value})}
                                            className="min-h-[72px] rounded-xl bg-white"
                                        />
                                    </div>
                                </div>
                            </div>

                        </div>

                        <DialogFooter className="bg-gray-50/50 p-4 border-t border-gray-100 flex-col sm:flex-row gap-2">
                            <Button 
                                disabled={
                                    bulkApplying ||
                                    (bulkForm.target === 'department' && !bulkForm.department_id) ||
                                    (bulkForm.target === 'designation' && !bulkForm.designation_id) ||
                                    (bulkForm.target === 'category' && !bulkForm.category_id) ||
                                    (bulkForm.target === 'shift' && !bulkForm.shift_id) ||
                                    (bulkForm.target === 'employee' && !bulkForm.employee_id)
                                }
                                onClick={async () => {
                                    setBulkApplying(true);
                                    try {
                                        const response = await axios.post(
                                            route('hr.attendance.bulk-present'),
                                            bulkForm,
                                            { timeout: 300000 }
                                        );
                                        toast.success(response.data.message);
                                        setBulkAttendanceOpen(false);
                                        resetBulkForm();
                                        await fetchData();
                                    } catch (error: any) {
                                        const msg =
                                            error.response?.data?.message ||
                                            error.response?.data?.error ||
                                            (error.response?.data?.errors
                                                ? Object.values(error.response.data.errors).flat().join(', ')
                                                : null) ||
                                            'Failed to apply bulk attendance';
                                        toast.error(msg);
                                    } finally {
                                        setBulkApplying(false);
                                    }
                                }}
                                className="w-full sm:w-auto sm:flex-1 h-11 text-base font-bold shadow-lg shadow-primary/20 gap-2 bg-[#1a365d] hover:bg-[#2c5282] text-white"
                            >
                                {bulkApplying ? (
                                    <Loader2 className="w-4 h-4 animate-spin" />
                                ) : (
                                    <CheckCircle2 className="w-4 h-4" />
                                )}
                                {bulkApplying ? 'Applying…' : 'Apply Bulk Attendance'}
                            </Button>
                            <Button variant="ghost" className="w-full sm:w-auto h-11 text-muted-foreground font-medium" onClick={() => {
                                setBulkAttendanceOpen(false);
                                resetBulkForm();
                            }}>
                                Cancel
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Report Configuration Dialog */}
                <Dialog open={reportDialogOpen} onOpenChange={setReportDialogOpen}>
                    <DialogContent className="max-w-md p-0 overflow-hidden rounded-2xl border-none shadow-2xl">
                        <DialogHeader className="p-6 bg-[#1a365d] text-white">
                            <div className="flex items-center gap-3">
                                <div className="p-2 bg-white/10 rounded-lg">
                                    <FileText className="w-5 h-5 text-blue-200" />
                                </div>
                                <div>
                                    <DialogTitle className="text-xl font-bold">Report Configuration</DialogTitle>
                                    <DialogDescription className="text-blue-100/70 text-xs">Customize your report output parameters</DialogDescription>
                                </div>
                            </div>
                        </DialogHeader>

                        <div className="p-6 space-y-6 bg-white dark:bg-gray-950">
                            <div className="space-y-4">
                                <div className="flex items-center gap-2 px-1">
                                    <CalendarIcon className="h-4 w-4 text-primary" />
                                    <span className="text-[11px] font-black text-gray-400 uppercase tracking-widest">Period Selection</span>
                                </div>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-1.5">
                                        <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">From Date</Label>
                                        <div className="relative">
                                            <Input 
                                                type="date" 
                                                value={reportOptions.from_date}
                                                onChange={(e) => setReportOptions({...reportOptions, from_date: e.target.value})}
                                                className="h-11 border-gray-200 dark:border-gray-800 text-xs font-bold pl-3"
                                            />
                                        </div>
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label className="text-[11px] font-black text-gray-400 uppercase tracking-widest ml-1">To Date</Label>
                                        <div className="relative">
                                            <Input 
                                                type="date" 
                                                value={reportOptions.to_date}
                                                onChange={(e) => setReportOptions({...reportOptions, to_date: e.target.value})}
                                                className="h-11 border-gray-200 dark:border-gray-800 text-xs font-bold pl-3"
                                            />
                                        </div>
                                    </div>
                                </div>
                                <div className="p-3 bg-blue-50/50 dark:bg-blue-900/10 rounded-xl border border-blue-100/50 dark:border-blue-800/50">
                                    <p className="text-[10px] text-blue-600 dark:text-blue-400 font-bold uppercase tracking-tight">
                                        Filters applied: {filters.branch_id === 'all' ? 'All Branches' : 'Selected Branch'} • {filters.department_id === 'all' ? 'All Depts' : 'Selected Dept'}
                                    </p>
                                </div>
                            </div>

                            {['att_worker', 'att_dept', 'att_shift'].includes(selectedReportId) && (
                                <div className="space-y-4 animate-in fade-in duration-300">
                                    <div className="space-y-3">
                                        <Label className="text-xs font-bold text-gray-500 uppercase ml-1">Hourly Format (Y/N/T)</Label>
                                        <div className="flex items-center gap-4 bg-gray-50 dark:bg-gray-900 p-3 rounded-xl border border-gray-100 dark:border-gray-800">
                                            {['N', 'Y', 'T'].map((type) => (
                                                <label key={type} className="flex items-center gap-2 cursor-pointer group">
                                                    <input 
                                                        type="radio" 
                                                        name="hourlyType" 
                                                        value={type} 
                                                        checked={reportOptions.hourly_type === type} 
                                                        onChange={(e) => setReportOptions({...reportOptions, hourly_type: e.target.value})}
                                                        className="h-4 w-4 accent-primary"
                                                    />
                                                    <span className="text-xs font-bold text-gray-600 group-hover:text-primary transition-colors">
                                                        {type === 'N' ? 'N - Numeric' : type === 'Y' ? 'Y - Hourly' : 'T - Time'}
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>

                                    <div className="space-y-3">
                                        <Label className="text-xs font-bold text-gray-500 uppercase ml-1">Card Format (N/Y/A)</Label>
                                        <div className="flex items-center gap-4 bg-gray-50 dark:bg-gray-900 p-3 rounded-xl border border-gray-100 dark:border-gray-800">
                                            {['N', 'Y', 'A'].map((type) => (
                                                <label key={type} className="flex items-center gap-2 cursor-pointer group">
                                                    <input 
                                                        type="radio" 
                                                        name="cardType" 
                                                        value={type} 
                                                        checked={reportOptions.card_type === type} 
                                                        onChange={(e) => setReportOptions({...reportOptions, card_type: e.target.value})}
                                                        className="h-4 w-4 accent-primary"
                                                    />
                                                    <span className="text-xs font-bold text-gray-600 group-hover:text-primary transition-colors">
                                                        {type === 'N' ? 'N - No' : type === 'Y' ? 'Y - Yes' : 'A - P/A Status'}
                                                    </span>
                                                </label>
                                            ))}
                                        </div>
                                    </div>
                                </div>
                            )}

                            {selectedReportId === 'biometric' && (
                                <div className="space-y-3 animate-in fade-in duration-300">
                                    <Label className="text-xs font-bold text-gray-500 uppercase ml-1">Filter by Status</Label>
                                    <Select value={reportOptions.status} onValueChange={(v) => setReportOptions({...reportOptions, status: v})}>
                                        <SelectTrigger className="h-11 rounded-xl border-gray-200">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">All Status</SelectItem>
                                            <SelectItem value="P">Present Only</SelectItem>
                                            <SelectItem value="A">Absent Only</SelectItem>
                                            <SelectItem value="MIS">Mispunch Only</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}
                        </div>

                        <div className="p-6 bg-gray-50 dark:bg-gray-900/50 flex gap-3 border-t border-gray-100 dark:border-gray-800">
                            <Button 
                                onClick={() => {
                                    handleDownloadReport(selectedReportId, reportOptions);
                                    setReportDialogOpen(false);
                                }}
                                className="flex-1 h-12 bg-[#1a365d] hover:bg-[#2c5282] text-white font-bold rounded-xl shadow-lg transition-all gap-2"
                            >
                                <FileDown className="w-4 h-4" />
                                Generate Report
                            </Button>
                            <Button 
                                variant="outline" 
                                onClick={() => setReportDialogOpen(false)}
                                className="h-12 px-6 rounded-xl font-bold"
                            >
                                Cancel
                            </Button>
                        </div>
                    </DialogContent>
                </Dialog>
            </div>
            <Dialog open={rulesDialogOpen} onOpenChange={setRulesDialogOpen}>
                <DialogContent className="max-w-2xl max-h-[90vh] flex flex-col p-0 overflow-hidden border-none shadow-2xl">
                    <DialogHeader className="p-6 bg-gradient-to-br from-[#1a365d] to-[#2c5282] text-white shrink-0">
                        <div className="flex items-center gap-4">
                            <div className="p-3 bg-white/10 rounded-2xl backdrop-blur-md">
                                <Info className="w-6 h-6 text-amber-300" />
                            </div>
                            <div>
                                <DialogTitle className="text-2xl font-bold tracking-tight">System Calculation Rules</DialogTitle>
                                <DialogDescription className="text-blue-100/70">How your attendance data is processed</DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <div className="p-6 space-y-6 bg-white dark:bg-gray-950 flex-1 overflow-y-auto custom-scrollbar">

                        {/* Engine description */}
                        <div className="p-4 rounded-2xl bg-slate-900 text-white shadow-lg">
                            <div className="flex items-center gap-2 mb-3">
                                <Clock className="w-3.5 h-3.5 text-emerald-400" />
                                <span className="text-[10px] font-black uppercase tracking-widest">Dynamic Duty Engine</span>
                            </div>
                            <p className="text-[9px] text-slate-400 leading-relaxed mb-3">
                                Each shift's duty status is determined by comparing the employee's worked minutes against the slot's configured thresholds. Rules are stored per-slot in <strong className="text-white">shift_duty_rules</strong>.
                            </p>
                            <div className="flex items-center justify-between text-[10px] gap-2">
                                <div className="text-center bg-white/5 p-2 rounded-xl border border-white/10 flex-1">
                                    <div className="font-bold text-slate-400 uppercase text-[8px]">Absent (0.0)</div>
                                    <div className="font-black text-red-400">&lt; 50%</div>
                                </div>
                                <ArrowRight className="w-3 h-3 text-slate-600 shrink-0" />
                                <div className="text-center bg-white/5 p-2 rounded-xl border border-white/10 flex-1">
                                    <div className="font-bold text-slate-400 uppercase text-[8px]">Half Day (0.5)</div>
                                    <div className="font-black text-orange-400">50%+</div>
                                </div>
                                <ArrowRight className="w-3 h-3 text-slate-600 shrink-0" />
                                <div className="text-center bg-white/5 p-2 rounded-xl border border-white/10 flex-1">
                                    <div className="font-bold text-slate-400 uppercase text-[8px]">Full Day (1.0)</div>
                                    <div className="font-black text-emerald-400">75%+</div>
                                </div>
                            </div>
                        </div>

                        {/* Dynamic shift-wise breakdown */}
                        <div className="space-y-4">
                            <h4 className="text-[10px] font-black text-slate-400 uppercase tracking-[0.2em] px-1 flex items-center gap-2">
                                <Table className="w-3 h-3" />
                                Shift-Wise Rules (Live from Database)
                            </h4>

                            {shifts_for_rules.length === 0 ? (
                                <div className="p-4 rounded-2xl border border-dashed border-slate-200 text-center">
                                    <p className="text-[10px] text-slate-400">No active shifts configured.</p>
                                </div>
                            ) : (
                                <div className="space-y-3">
                                    {shifts_for_rules.map((shift: any) => (
                                        <div key={shift.id} className="rounded-2xl border border-slate-100 bg-white overflow-hidden shadow-sm">
                                            {/* Shift header */}
                                            <div className="flex items-center justify-between px-3 py-2 bg-slate-50 border-b border-slate-100">
                                                <div className="flex items-center gap-2">
                                                    <span className="text-[10px] font-black text-slate-700 uppercase tracking-tight">{shift.short_code}</span>
                                                    <span className="text-[9px] text-slate-400">·</span>
                                                    <span className="text-[9px] font-semibold text-slate-500">{shift.name}</span>
                                                </div>
                                                <span className={`text-[8px] font-black uppercase px-1.5 py-0.5 rounded ${shift.is_multi ? 'bg-purple-100 text-purple-700' : 'bg-blue-100 text-blue-700'}`}>
                                                    {shift.is_multi ? 'Multi' : 'Fixed'}
                                                </span>
                                            </div>

                                            {/* Slot rows */}
                                            {shift.slots.length === 0 ? (
                                                <div className="px-3 py-2 text-[9px] text-amber-600 font-bold uppercase">Setup required</div>
                                            ) : (
                                                <table className="w-full text-[10px]">
                                                    <thead className="border-b border-slate-50">
                                                        <tr>
                                                            {shift.is_multi && <th className="px-3 py-1.5 text-left font-black text-slate-400 uppercase text-[9px]">Slot</th>}
                                                            <th className="px-3 py-1.5 text-left font-black text-slate-400 uppercase text-[9px]">Schedule</th>
                                                            <th className="px-3 py-1.5 text-center font-black text-slate-400 uppercase text-[9px]">Dur</th>
                                                            <th className="px-3 py-1.5 text-center font-black text-orange-600 uppercase text-[9px]">Half Day</th>
                                                            <th className="px-3 py-1.5 text-center font-black text-emerald-600 uppercase text-[9px]">Full Day</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-50">
                                                        {shift.slots.map((slot: any, si: number) => (
                                                            <tr key={si} className="hover:bg-slate-50/50 transition-colors">
                                                                {shift.is_multi && (
                                                                    <td className="px-3 py-1.5 font-black text-slate-500 uppercase text-[9px]">{slot.slot_name}</td>
                                                                )}
                                                                <td className="px-3 py-1.5 font-mono text-slate-600">{slot.start_time} → {slot.end_time}</td>
                                                                <td className="px-3 py-1.5 text-center">
                                                                    <span className="font-bold text-slate-600">{fmtMins(slot.duration_mins)}</span>
                                                                </td>
                                                                <td className="px-3 py-1.5 text-center">
                                                                    <span className="inline-flex items-center justify-center px-1.5 py-0.5 rounded-md bg-orange-50 text-orange-700 font-black border border-orange-200">
                                                                        {fmtMins(slot.half_day_mins)}
                                                                    </span>
                                                                </td>
                                                                <td className="px-3 py-1.5 text-center">
                                                                    <span className="inline-flex items-center justify-center px-1.5 py-0.5 rounded-md bg-emerald-50 text-emerald-700 font-black border border-emerald-200">
                                                                        {fmtMins(slot.full_day_mins)}
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        ))}
                                                    </tbody>
                                                </table>
                                            )}

                                            {/* Detailed rules per slot (expandable inline) */}
                                            {shift.slots.map((slot: any, si: number) => slot.rules.length > 0 && (
                                                <div key={`rules-${si}`} className="px-3 pb-2 pt-1 border-t border-slate-50">
                                                    {shift.is_multi && (
                                                        <div className="text-[8px] font-black text-slate-400 uppercase mb-1">{slot.slot_name} Rules</div>
                                                    )}
                                                    <div className="flex flex-wrap gap-1">
                                                        {slot.rules.map((rule: any, ri: number) => (
                                                            <span key={ri} className={`inline-flex items-center gap-1 text-[8px] font-bold px-1.5 py-0.5 rounded border ${COLOR_MAP[rule.color] || 'text-slate-600 bg-slate-50 border-slate-200'}`}>
                                                                <span className="font-black">{rule.status}</span>
                                                                <span className="opacity-60">{fmtMins(rule.min_minutes)}–{rule.max_minutes === 1440 ? '∞' : fmtMins(rule.max_minutes)}</span>
                                                            </span>
                                                        ))}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>

                        <div className="grid grid-cols-2 gap-3">
                            <div className="p-3 rounded-2xl bg-amber-50 border border-amber-100 flex items-center gap-3">
                                <CalendarIcon className="w-4 h-4 text-amber-500 shrink-0" />
                                <div className="text-[9px] font-bold text-amber-900 uppercase leading-tight">Holiday Sync <span className="block text-[8px] font-medium opacity-70">Auto-Detect</span></div>
                            </div>
                            <div className="p-3 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center gap-3">
                                <User className="w-4 h-4 text-indigo-500 shrink-0" />
                                <div className="text-[9px] font-bold text-indigo-900 uppercase leading-tight">Individual Off <span className="block text-[8px] font-medium opacity-70">Auto-Detect</span></div>
                            </div>
                        </div>

                        <div className="p-3 rounded-2xl border border-dashed border-slate-200 text-center">
                            <p className="text-[9px] text-slate-400 font-medium">All thresholds come directly from <strong>shift_duty_rules</strong> — edit shifts at <a href="/shifts" className="text-blue-500 underline">/shifts</a> to update them.</p>
                        </div>

                        <div className="h-[1px] bg-slate-100 dark:bg-slate-800" />

                        {/* Horizontal Divider */}
                        <div className="h-[1px] bg-slate-100 dark:bg-slate-800 my-4" />

                        {/* Missed Punch & Overtime Short Notes */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            {/* Missed Punch Card */}
                            <div className="p-4 rounded-2xl bg-red-50/50 dark:bg-red-950/20 border border-red-100 dark:border-red-900/50 space-y-2">
                                <div className="flex items-center gap-2">
                                    <AlertCircle className="w-4 h-4 text-red-500 shrink-0" />
                                    <h5 className="text-[11px] font-black text-red-800 uppercase tracking-tight">Missed Punch Rules</h5>
                                </div>
                                <ul className="text-[9px] font-bold text-slate-500 dark:text-slate-400 list-disc pl-4 space-y-1">
                                    <li>Marks record as <strong>Missed Punch (MIS)</strong> if either <strong>In</strong> or <strong>Out</strong> is missing.</li>
                                    <li>Duplicate punches in same direction (e.g. <strong>IN</strong> followed by <strong>IN</strong>) are treated as standalone unmatched items.</li>
                                    <li>Standalone <strong>OUT</strong> punches without preceding IN entries trigger missed punch status.</li>
                                    <li><strong>Auto-Present:</strong> Clearing/filling both timings automatically restores status to <strong>Present (P)</strong>.</li>
                                </ul>
                            </div>

                            {/* Overtime Card */}
                            <div className="p-4 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/50 space-y-2">
                                <div className="flex items-center gap-2">
                                    <Timer className="w-4 h-4 text-emerald-500 shrink-0" />
                                    <h5 className="text-[11px] font-black text-emerald-800 uppercase tracking-tight">Overtime (OT) Rules</h5>
                                </div>
                                <ul className="text-[9px] font-bold text-slate-500 dark:text-slate-400 list-disc pl-4 space-y-1">
                                    <li>Calculates overtime when actual worked duration exceeds assigned shift duration.</li>
                                    <li>Excludes standard break slots automatically as defined in shift master slots.</li>
                                    <li>Supports overnight shifts crossing midnight into the next day smoothly.</li>
                                    <li>Requires minimum shift completion thresholds to validate calculated OT hours.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <DialogFooter className="p-4 bg-gray-50 border-t border-gray-100 shrink-0">
                        <Button onClick={() => setRulesDialogOpen(false)} className="px-8 font-bold">Understood</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

        </AppLayout>
    );
}

function AttendanceGrid({ data, type, getStatusIcon, formatTime, formatHours, loading, onCellClick }: any) {
    const scrollRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const el = scrollRef.current;
        if (el) {
            const onWheel = (e: WheelEvent) => {
                // If Alt is pressed, scroll horizontally
                if (e.altKey && e.deltaY !== 0) {
                    el.scrollLeft += e.deltaY;
                    e.preventDefault();
                } 
                // If no Alt and it's a vertical scroll, allow it (don't prevent default)
                // The browser will handle vertical scroll naturally since overflow-y is auto
            };
            el.addEventListener('wheel', onWheel, { passive: false });
            return () => el.removeEventListener('wheel', onWheel);
        }
    }, []);

    if (loading) return (
        <div className="p-32 flex flex-col items-center justify-center space-y-4 animate-pulse">
            <div className="w-12 h-12 rounded-full border-4 border-primary/20 border-t-primary animate-spin" />
            <p className="text-sm font-bold text-gray-400">Synchronizing records...</p>
        </div>
    );
    
    if (!data || data.employees.length === 0) return (
        <div className="p-32 flex flex-col items-center justify-center space-y-4">
            <div className="p-4 bg-gray-50 rounded-2xl">
                <Search className="w-10 h-10 text-gray-300" />
            </div>
            <p className="text-sm font-bold text-gray-400 text-center">No employee records found matching<br/>the selected filters.</p>
        </div>
    );

    const today = new Date();
    const currentDay = today.getDate();
    const days = Array.from({ length: data.days_in_month }, (_, i) => i + 1);

    return (
        <div className="relative border-t border-gray-100 dark:border-gray-800 bg-gray-50/50 w-full overflow-hidden">
            <div 
                ref={scrollRef}
                className="w-full max-w-[calc(100vw-300px)] max-h-[70vh] overflow-auto custom-scrollbar select-none"
            >
                <table className="border-separate border-spacing-0 table-fixed min-w-max" style={{ minWidth: `${days.length * 75 + 420}px` }}>
                    <thead className="sticky top-0 z-[120] shadow-sm">
                        <tr className="bg-gray-50/95 dark:bg-gray-900/95 backdrop-blur-md">
                            <th className="sticky left-0 top-0 z-[110] bg-gray-50/95 dark:bg-gray-900/95 backdrop-blur-md px-6 py-4 text-left w-[300px] min-w-[300px] font-black text-[10px] uppercase tracking-[0.2em] text-gray-400 border-b border-r border-gray-200 dark:border-gray-800 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                                Employee Information
                            </th>
                            {days.map(d => (
                                <th key={d} className="sticky top-0 z-40 px-2 py-4 text-center w-[75px] min-w-[75px] font-black text-xs text-gray-500 border-b border-r border-gray-200/50 dark:border-gray-800/50 bg-gray-50/95 dark:bg-gray-900/95 backdrop-blur-md">
                                    {d}
                                </th>
                            ))}
                            <th className="sticky right-0 top-0 z-[110] bg-gray-50/95 dark:bg-gray-900/95 backdrop-blur-md px-6 py-4 text-center w-[120px] min-w-[120px] font-black text-[10px] uppercase tracking-widest text-gray-400 border-b border-l border-gray-200 dark:border-gray-800 shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                                Summary
                            </th>
                        </tr>
                    </thead>
                    <tbody className="bg-white dark:bg-gray-950">
                        {data.employees.map((empData: any, idx: number) => (
                            <tr key={empData.employee.id} className="group">
                                <td className="sticky left-0 z-[100] bg-white dark:bg-gray-950 px-6 py-4 border-b border-r border-gray-200 dark:border-gray-800 group-hover:bg-[#f8faff] dark:group-hover:bg-[#0f172a] transition-none shadow-[4px_0_10px_-4px_rgba(0,0,0,0.1)] w-[300px] overflow-hidden">
                                    <div className="flex items-center gap-4">
                                        <div className="w-9 h-9 rounded-full bg-gray-100 dark:bg-gray-800 flex items-center justify-center text-xs font-black text-gray-400">
                                            {empData.employee.name.charAt(0)}
                                        </div>
                                        <div className="flex flex-col min-w-0 py-1 space-y-1.5">
                                            <div className="flex flex-wrap items-center gap-2">
                                                <span className="font-bold text-[13px] text-gray-900 dark:text-gray-100 truncate max-w-[140px] group-hover:text-primary transition-colors leading-none" title={empData.employee.name}>{empData.employee.name}</span>
                                                <div className="flex items-center gap-1.5 shrink-0">
                                                    <span className="text-[9px] font-black font-mono text-gray-500 bg-gray-100 dark:bg-gray-800 px-1.5 py-0.5 rounded border border-gray-200 dark:border-gray-700 leading-none">{empData.employee.code}</span>
                                                    <span className="text-[9px] text-emerald-600 dark:text-emerald-400 font-bold uppercase tracking-tighter bg-emerald-50 dark:bg-emerald-900/20 border border-emerald-100 dark:border-emerald-800/50 px-1.5 py-0.5 rounded leading-none">
                                                        {empData.employee.category}
                                                    </span>
                                                </div>
                                            </div>
                                            <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                <div className="flex items-center gap-1.5 text-gray-500 shrink-0">
                                                    <Layers className="w-3 h-3 opacity-70" />
                                                    <span className="text-[10px] font-semibold truncate max-w-[100px]" title={empData.employee.department}>{empData.employee.department}</span>
                                                </div>
                                                <span className="text-gray-300 dark:text-gray-700 text-[10px]">•</span>
                                                <div className="flex items-center gap-1 text-gray-500 shrink-0">
                                                    <span className="text-[10px] font-medium truncate max-w-[90px]" title={empData.employee.designation}>{empData.employee.designation}</span>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-1 border px-1.5 py-0.5 rounded w-fit text-primary bg-primary/10 border-primary/20 dark:bg-primary/20 dark:text-primary dark:border-primary/30" style={{ color: 'var(--theme-color)' }}>
                                                <Clock className="w-2.5 h-2.5 shrink-0" />
                                                <span className="text-[9px] font-bold tracking-tight truncate max-w-[80px]" title={empData.employee.shift}>{empData.employee.shift}</span>
                                                <span className="text-[9px] font-semibold opacity-70 ml-0.5 shrink-0">({empData.employee.shift_start}-{empData.employee.shift_end})</span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                {days.map(d => {
                                    const record = empData.days[d];
                                    const isFuture = d > currentDay;
                                    const isToday = d === currentDay;

                                    return (
                                        <td key={d} className="p-1 border-b border-r border-gray-100 dark:border-gray-800/40 text-center transition-colors w-[75px] h-[60px]">
                                            <div 
                                                className="flex items-center justify-center h-full w-full cursor-pointer hover:bg-primary/5 rounded-lg m-0"
                                                onClick={() => onCellClick(empData.employee, d, record || { status: isFuture ? '' : 'A' })}
                                            >
                                                {!record ? (
                                                     !isFuture && <div className="w-1.5 h-1.5 rounded-full bg-rose-400/40" title="No Record" />
                                                ) : (
                                                    <div className="relative w-full h-full flex items-center justify-center">
                                                        {record.is_manual && record.status !== 'A' && (
                                                            <div className="absolute -top-1.5 -right-1.5 w-4 h-4 bg-indigo-600 text-white flex items-center justify-center text-[9px] font-black rounded border-2 border-white shadow-sm z-20" title="Manually Adjusted">
                                                                M
                                                            </div>
                                                        )}
                                                        {type === 'status' && (
                                                            record.status === 'MIS' && isToday ? (
                                                                <div className="w-7 h-7 rounded-md flex items-center justify-center text-[11px] font-bold bg-blue-50 text-blue-500 border border-blue-100" title="Active Shift">
                                                                    <Clock className="w-4 h-4 animate-pulse" />
                                                                </div>
                                                            ) : getStatusIcon(record.status, record)
                                                        )}
                                                        {type === 'inout' && (
                                                            <div className="flex flex-col items-center space-y-0.5 w-full max-w-[72px]">
                                                                {hasActualPunchData(record) || record.in_time || record.out_time ? (
                                                                    <div className="flex flex-col gap-1 w-full max-h-[50px] overflow-y-auto custom-scrollbar">
                                                                        {getRecordDisplayPairs(record, empData.employee).map((pair, idx) => (
                                                                            <div key={idx} className="flex flex-col items-center space-y-0.5 w-full">
                                                                                <div className="flex items-center gap-1 px-1.5 py-0.5 bg-emerald-50 dark:bg-emerald-500/10 rounded border border-emerald-100/50 w-full justify-center">
                                                                                    <LogOut className="w-2.5 h-2.5 text-emerald-600 rotate-180" />
                                                                                    <span className="text-[10px] text-emerald-600 font-bold">{pair.in_time ? formatTime(pair.in_time) : '--:--'}</span>
                                                                                </div>
                                                                                <div className="flex items-center gap-1 px-1.5 py-0.5 bg-rose-50 dark:bg-rose-500/10 rounded border border-rose-100/50 w-full justify-center">
                                                                                    <LogOut className="w-2.5 h-2.5 text-rose-400" />
                                                                                    <span className="text-[10px] text-rose-500 font-medium">{pair.out_time ? formatTime(pair.out_time) : '--:--'}</span>
                                                                                </div>
                                                                            </div>
                                                                        ))}
                                                                    </div>
                                                                ) : !isFuture ? (
                                                                    <div
                                                                        className="flex flex-col items-center gap-0.5 px-1 py-0.5 rounded border border-dashed border-indigo-200 bg-indigo-50/40 dark:border-indigo-800 dark:bg-indigo-950/20"
                                                                        title="Assigned shift (no punch)"
                                                                    >
                                                                        {getEmployeeShiftSchedule(empData.employee).map((slot, si) => (
                                                                            <span key={si} className="text-[8px] font-mono font-semibold text-indigo-600 dark:text-indigo-400 leading-tight text-center">
                                                                                {slot.start_time}–{slot.end_time}
                                                                            </span>
                                                                        ))}
                                                                    </div>
                                                                ) : null}
                                                            </div>
                                                        )}
                                                        {type === 'logdetails' && (
                                                            <div className="flex flex-col items-center justify-center w-full max-w-[72px] overflow-hidden">
                                                                {record.log_details ? (
                                                                    <div 
                                                                        className={`w-full max-h-[50px] overflow-y-auto custom-scrollbar text-center break-all text-[8px] font-mono font-bold px-1.5 py-1 rounded-md shadow-sm ${
                                                                            record.status === 'MIS' ? 'text-orange-700 bg-orange-50 border border-orange-200' :
                                                                            record.status === 'P' ? 'text-emerald-700 bg-emerald-50 border border-emerald-200' :
                                                                            record.status === 'HD' ? 'text-amber-700 bg-amber-50 border border-amber-200' :
                                                                            'text-gray-600 bg-gray-50 dark:bg-gray-900 border border-gray-200'
                                                                        }`} 
                                                                        title={record.log_details}
                                                                    >
                                                                        {record.log_details.split(',').map((p: string, i: number) => (
                                                                            <div key={i} className="mb-0.5 whitespace-nowrap leading-tight">{p.trim()}</div>
                                                                        ))}
                                                                    </div>
                                                                ) : (
                                                                    !isFuture ? <Minus className="w-3 h-3 text-gray-300" /> : null
                                                                )}
                                                            </div>
                                                        )}
                                                        {type === 'hours' && (
                                                            <div className="flex flex-col items-center">
                                                                <div className={`flex items-center gap-1 ${record.total_minutes > 0 ? 'text-gray-700 dark:text-gray-200' : 'text-gray-200 dark:text-gray-800'}`}>
                                                                    <Timer className="w-3 h-3 opacity-70" />
                                                                    <span className="text-[12px] font-bold">
                                                                        {record.total_minutes > 0 ? formatHours(record.total_minutes) : (!isFuture ? '0h' : '-')}
                                                                    </span>
                                                                </div>
                                                                {record.ot_minutes > 0 && (
                                                                    <Badge variant="outline" className="text-[9px] h-4 px-1 mt-0.5 bg-blue-50 text-blue-600 border-blue-100 font-black flex items-center gap-0.5">
                                                                        <Plus className="w-2 h-2" />
                                                                        {Math.round(record.ot_minutes/60 * 10)/10}h
                                                                    </Badge>
                                                                )}
                                                            </div>
                                                        )}
                                                        {type === 'overtime' && (
                                                            <div className="flex items-center justify-center">
                                                                {record.ot_minutes > 0 ? (
                                                                    <div className="flex items-center gap-1 text-blue-600 bg-blue-50 h-7 px-2 rounded-md border border-blue-200 shadow-sm animate-in zoom-in-50 duration-300">
                                                                        <Timer className="w-3 h-3" />
                                                                        <span className="text-[10px] font-black whitespace-nowrap">
                                                                            {formatHours(record.ot_minutes)}
                                                                        </span>
                                                                    </div>
                                                                ) : (
                                                                    !isFuture ? getStatusIcon(record.status, record) : null
                                                                )}
                                                            </div>
                                                        )}
                                                        {type === 'mispunch' && (
                                                            <div className="flex items-center justify-center w-full max-w-[72px]">
                                                                {record.status === 'MIS' && !isToday ? (
                                                                    <div className="flex flex-col items-center gap-1 w-full">
                                                                        <div className="flex items-center gap-1 text-[8px] font-black text-orange-600 bg-orange-100 px-1.5 py-0.5 rounded border border-orange-200 uppercase mb-0.5 shadow-sm">
                                                                            <AlertCircle className="w-2 h-2" /> Mispunch
                                                                        </div>
                                                                        <div className="flex flex-col gap-1 w-full max-h-[35px] overflow-y-auto custom-scrollbar">
                                                                            {getRecordDisplayPairs(record, empData.employee).map((pair, idx) => (
                                                                                <div key={idx} className="flex flex-col items-center space-y-0.5 w-full">
                                                                                    <div className="flex items-center gap-1 px-1 py-0.5 bg-emerald-50 dark:bg-emerald-500/10 rounded border border-emerald-100/50 w-full justify-center">
                                                                                        <LogOut className="w-2 h-2 text-emerald-600 rotate-180" />
                                                                                        <span className="text-[8px] text-emerald-600 font-bold">{pair.in_time ? formatTime(pair.in_time) : '--:--'}</span>
                                                                                    </div>
                                                                                    <div className="flex items-center gap-1 px-1 py-0.5 bg-rose-50 dark:bg-rose-500/10 rounded border border-rose-100/50 w-full justify-center">
                                                                                        <LogOut className="w-2 h-2 text-rose-400" />
                                                                                        <span className="text-[8px] text-rose-500 font-medium">{pair.out_time ? formatTime(pair.out_time) : '--:--'}</span>
                                                                                    </div>
                                                                                </div>
                                                                            ))}
                                                                        </div>
                                                                    </div>
                                                                ) : (
                                                                    !isFuture ? getStatusIcon(record.status, record) : null
                                                                )}
                                                            </div>
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        </td>
                                    );
                                })}
                                <td className="sticky right-0 z-30 bg-gray-50/90 dark:bg-gray-950/90 p-2 text-center border-b border-l border-gray-200 dark:border-gray-800 backdrop-blur-sm group-hover:bg-gray-100 transition-colors w-[120px] shadow-[-4px_0_10px_-4px_rgba(0,0,0,0.05)]">
                                    <div className="flex flex-col items-center">
                                        <div className="flex items-center gap-1.5 mb-1">
                                            {type === 'hours' && <Timer className="w-3.5 h-3.5 text-gray-400" />}
                                            {type === 'overtime' && <Timer className="w-3.5 h-3.5 text-blue-500" />}
                                            {type === 'mispunch' && <AlertCircle className="w-3.5 h-3.5 text-orange-500" />}
                                            {type === 'status' && <CheckCircle2 className="w-3.5 h-3.5 text-emerald-500" />}
                                            {type === 'inout' && <Clock className="w-3.5 h-3.5 text-blue-500" />}
                                            {type === 'logdetails' && <FileText className="w-3.5 h-3.5 text-purple-500" />}
                                            <span className="text-[14px] font-black text-gray-900 dark:text-gray-100">
                                                {type === 'hours' ? formatHours(empData.summary.total_worked_minutes) : 
                                                 type === 'overtime' ? formatHours(empData.summary.ot_minutes) :
                                                 type === 'mispunch' ? empData.summary.mis :
                                                 type === 'inout' ? `${empData.summary.present} P` :
                                                 type === 'logdetails' ? Object.values(empData.days).reduce((acc: number, r: any) => acc + (r?.log_details ? r.log_details.split(',').filter((p: string) => p.trim() !== '').length : 0), 0) :
                                                 empData.summary.present}
                                            </span>
                                        </div>
                                        <span className="text-[9px] font-bold text-gray-400 uppercase tracking-[0.15em]">
                                            {type === 'hours' ? 'Total Time' : 
                                             type === 'overtime' ? 'OT Total' :
                                             type === 'mispunch' ? 'Exceptions' :
                                             type === 'logdetails' ? 'Logs' :
                                             'Days Present'}
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
