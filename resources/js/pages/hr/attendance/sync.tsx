import React, { useState, useEffect } from 'react';
import { useForm, router, Link, usePage } from '@inertiajs/react';
import { PageTemplate } from '@/components/page-template';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { 
    Loader2, RefreshCw, ShieldCheck, Database, Clock, AlertCircle, 
    Search, Calendar as CalendarIcon, User, CheckCircle2, 
    AlertTriangle, Info, Filter, Download, ArrowRight, Zap,
    ChevronLeft, ChevronRight, ChevronDown, ChevronUp, MoreHorizontal, Settings2, Building, Users, LayoutGrid, Layers, MapPin, X, Edit, Pencil, Save, Printer
} from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from '@/components/ui/dialog';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Combobox } from '@/components/ui/combobox';
import { toast } from '@/components/custom-toast';
import { cn } from '@/lib/utils';
import { PunchPairsEditor } from '@/components/attendance/PunchPairsEditor';
import {
    PunchPair,
    parseLogDetailsToPairs,
    buildAttendancePayloadFromPairs,
    getDisplayPunchEventsFromRecord,
    hasMispunchIssues,
    mispunchSummaryText,
} from '@/lib/attendance-punches';

interface Branch { id: number; name: string; }
interface Department { id: number; name: string; }
interface Category { id: number; name: string; }
interface Section { id: number; name: string; }

interface AttendanceRecord {
    id: number;
    employee_code: string;
    attendance_date: string;
    shift_code: string;
    in_time: string | null;
    out_time: string | null;
    in_count: number;
    out_count: number;
    total_minutes: number;
    ot_minutes: number;
    ot_hours: string | null;
    late_in: string | null;
    early_out: string | null;
    duty_value: string;
    status: string;
    base_shift?: string;
    log_details?: string;
    employee_display_name?: string;
    employee: {
        user: { name: string };
    };
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    branches: Branch[];
    departments: Department[];
    categories: Category[];
    sections: Section[];
    last_sync: string | null;
    records: {
        data: AttendanceRecord[];
        links: PaginationLink[];
        total: number;
        from: number;
        to: number;
    };
    filters: {
        search?: string;
        from_date?: string;
        to_date?: string;
        use_dates?: boolean;
        branch_id?: string;
        department_id?: string;
        category_id?: string;
        section_id?: string;
        status?: string;
    };
}

const pairsToLegacyFields = (pairs: PunchPair[]) => ({
    day_in_time: pairs[0]?.in_time || '',
    day_out_time: pairs[0]?.out_time || '',
    night_in_time: pairs[1]?.in_time || '',
    night_out_time: pairs[1]?.out_time || '',
});

const recordToPunchPairs = (record: AttendanceRecord): PunchPair[] =>
    parseLogDetailsToPairs(record.log_details, record.in_time, record.out_time);

const shouldUsePairEditor = (record: AttendanceRecord | null, status: string, pairs: PunchPair[]) => {
    if (!record) return false;
    const isDoubleShift = record.shift_code?.includes(',') || record.shift_code === 'both';
    return isDoubleShift || status === 'MIS' || pairs.length > 1 || hasMispunchIssues(pairs);
};

export default function AttendanceSync({ 
    branches = [], 
    departments = [], 
    categories = [], 
    sections = [], 
    last_sync, 
    records, 
    filters 
}: Props) {
    const { auth } = usePage().props as { auth?: { active_branch_id?: number | string } };
    const activeBranchId = auth?.active_branch_id;

    const resolveBranchFilter = (filterBranch?: string) => {
        if (filterBranch && filterBranch !== 'all') {
            return String(filterBranch);
        }
        if (activeBranchId) {
            return String(activeBranchId);
        }
        return 'all';
    };

    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [filterFrom, setFilterFrom] = useState(filters.use_dates ? (filters.from_date || '') : '');
    const [filterTo, setFilterTo] = useState(filters.use_dates ? (filters.to_date || '') : '');
    const [branchId, setBranchId] = useState(() => resolveBranchFilter(filters.branch_id));
    const [deptId, setDeptId] = useState(filters.department_id ? String(filters.department_id) : 'all');
    const [catId, setCatId] = useState(filters.category_id ? String(filters.category_id) : 'all');
    const [secId, setSecId] = useState(filters.section_id ? String(filters.section_id) : 'all');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'MIS');
    const [isRefreshing, setIsRefreshing] = useState(false);
    const [isSyncControlsOpen, setIsSyncControlsOpen] = useState(false);
    const isFirstRender = React.useRef(true);

    useEffect(() => {
        setSearchTerm(filters.search || '');
        setFilterFrom(filters.use_dates ? (filters.from_date || '') : '');
        setFilterTo(filters.use_dates ? (filters.to_date || '') : '');
        setBranchId(resolveBranchFilter(filters.branch_id));
        setDeptId(filters.department_id ? String(filters.department_id) : 'all');
        setCatId(filters.category_id ? String(filters.category_id) : 'all');
        setSecId(filters.section_id ? String(filters.section_id) : 'all');
        setStatusFilter(filters.status || 'MIS');
    }, [filters, activeBranchId]);

    const { data, setData, post, put, processing } = useForm({
        from_date: new Date().toISOString().split('T')[0],
        to_date: new Date().toISOString().split('T')[0],
        branch_id: branchId,
    });

    useEffect(() => {
        setData('branch_id', branchId);
    }, [branchId]);

    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [isBulkEditModalOpen, setIsBulkEditModalOpen] = useState(false);


    useEffect(() => {
        setSelectedIds([]);
        setBulkEdits({});
    }, [records]);

    const [bulkEdits, setBulkEdits] = useState<Record<number, {
        pairs: PunchPair[];
        status: string;
    }>>({});

    const getFormattedTime = (dateStr: string | null) => {
        if (!dateStr) return '';
        try {
            const date = new Date(dateStr);
            const hours = String(date.getHours()).padStart(2, '0');
            const minutes = String(date.getMinutes()).padStart(2, '0');
            return `${hours}:${minutes}`;
        } catch (e) {
            return '';
        }
    };

    // When modal opens, initialize bulkEdits with punch pairs from log_details
    useEffect(() => {
        if (isBulkEditModalOpen) {
            const initialEdits: Record<number, { pairs: PunchPair[]; status: string }> = {};
            selectedIds.forEach(id => {
                const rec = records?.data?.find(r => r.id === id);
                if (rec) {
                    initialEdits[id] = {
                        pairs: recordToPunchPairs(rec),
                        status: rec.status ?? 'MIS',
                    };
                }
            });
            setBulkEdits(initialEdits);
        }
    }, [isBulkEditModalOpen, selectedIds]);

    const handleBulkUpdate = (e: React.FormEvent) => {
        e.preventDefault();
        const editedCount = Object.keys(bulkEdits).length;
        if (editedCount === 0) return;

        const finalEdits: Record<number, any> = {};

        Object.keys(bulkEdits).forEach(idKey => {
            const id = Number(idKey);
            const edit = bulkEdits[id];
            if (!edit) return;

            const payload = buildAttendancePayloadFromPairs(edit.pairs, edit.status);
            finalEdits[id] = {
                in_time: payload.in_time,
                out_time: payload.out_time,
                status: payload.status,
                log_details: payload.log_details,
                actual_work_minutes: payload.actual_work_minutes,
            };
        });

        router.post(route('hr.attendance.sync.bulk-update'), {
            edits: finalEdits
        }, {
            onSuccess: () => {
                toast.success(`Successfully saved corrections for ${editedCount} selected records!`);
                setIsBulkEditModalOpen(false);
                setSelectedIds([]);
                setBulkEdits({});
            },
            onError: () => {
                toast.error('Failed to perform bulk update.');
            }
        });
    };



    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const editForm = useForm<any>({
        in_time: '',
        out_time: '',
        status: '',
        day_in_time: '',
        day_out_time: '',
        night_in_time: '',
        night_out_time: '',
        log_details: '',
    });
    const [selectedRecord, setSelectedRecord] = useState<AttendanceRecord | null>(null);
    const [editPunchPairs, setEditPunchPairs] = useState<PunchPair[]>([]);

    const openEditModal = (record: AttendanceRecord) => {
        setSelectedRecord(record);

        const pairs = recordToPunchPairs(record);
        const legacy = pairsToLegacyFields(pairs);
        const logDetails = record.log_details || '';

        setEditPunchPairs(pairs);

        const formatLocal = (dateStr: string | null) => {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            const offset = d.getTimezoneOffset() * 60000;
            return (new Date(d.getTime() - offset)).toISOString().slice(0, 16);
        };

        editForm.setData({
            in_time: formatLocal(record.in_time),
            out_time: formatLocal(record.out_time),
            status: record.status,
            ...legacy,
            log_details: logDetails,
        });
        setIsEditModalOpen(true);
    };

    const handleUpdate = (e: React.FormEvent) => {
        e.preventDefault();
        if (!selectedRecord) return;

        const dateStr = selectedRecord.attendance_date.split('T')[0];
        const usePairEditor = shouldUsePairEditor(selectedRecord, editForm.data.status, editPunchPairs);

        if (usePairEditor) {
            const payload = buildAttendancePayloadFromPairs(editPunchPairs, editForm.data.status);

            editForm.transform((data) => ({
                ...data,
                in_time: payload.in_time ? `${dateStr} ${payload.in_time}` : '',
                out_time: payload.out_time ? `${dateStr} ${payload.out_time}` : '',
                status: payload.status,
                log_details: payload.log_details,
                actual_work_minutes: payload.actual_work_minutes,
            }));
            editForm.put(route('hr.attendance.sync.update', selectedRecord.id), {
                onSuccess: () => {
                    toast.success('Record updated successfully!');
                    setIsEditModalOpen(false);
                },
                onError: () => {
                    toast.error('Failed to update record.');
                }
            });
        } else {
            // Single punch correction: calculate clean log_details
            const formatClean = (dtStr: string) => {
                if (!dtStr) return '';
                if (dtStr.includes('T')) return dtStr.split('T')[1].substring(0, 5);
                if (dtStr.includes(' ')) return dtStr.split(' ')[1].substring(0, 5);
                return dtStr.substring(0, 5);
            };

            const dayIn = formatClean(editForm.data.in_time);
            const dayOut = formatClean(editForm.data.out_time);
            
            let consolidatedLogs = '';
            if (dayIn && dayOut) {
                consolidatedLogs = `${dayIn} IN, ${dayOut} OUT`;
            } else if (dayIn) {
                consolidatedLogs = `${dayIn} IN`;
            } else if (dayOut) {
                consolidatedLogs = `${dayOut} OUT`;
            }

            const hasBoth = !!dayIn && !!dayOut;
            const status = (hasBoth && editForm.data.status === 'MIS') ? 'P' : editForm.data.status;

            editForm.transform((data) => ({
                ...data,
                status: status,
                log_details: consolidatedLogs
            }));
            editForm.put(route('hr.attendance.sync.update', selectedRecord.id), {
                onSuccess: () => {
                    toast.success('Record updated successfully!');
                    setIsEditModalOpen(false);
                },
                onError: () => {
                    toast.error('Failed to update record.');
                }
            });
        }
    };

    const handleFilter = () => {
        setIsRefreshing(true);
        const params: Record<string, string> = {
            search: searchTerm,
            branch_id: branchId,
            department_id: deptId,
            category_id: catId,
            section_id: secId,
            status: statusFilter,
        };
        if (filterFrom || filterTo) {
            params.use_dates = '1';
            if (filterFrom) params.from_date = filterFrom;
            if (filterTo) params.to_date = filterTo;
        }

        router.get(route('hr.attendance.sync'), params, {
            preserveState: true,
            onFinish: () => setIsRefreshing(false)
        });
    };

    const handleClearFilters = () => {
        setSearchTerm('');
        setFilterFrom('');
        setFilterTo('');
        const clearedBranch = activeBranchId ? String(activeBranchId) : 'all';
        setBranchId(clearedBranch);
        setDeptId('all');
        setCatId('all');
        setSecId('all');
        setStatusFilter('MIS');

        router.get(route('hr.attendance.sync'), {
            search: '',
            branch_id: clearedBranch,
            department_id: 'all',
            category_id: 'all',
            section_id: 'all',
            status: 'MIS',
        }, { preserveState: false });
    };

    // Auto-filter on dropdown change (dates apply only via Search button)
    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }
        handleFilter();
    }, [branchId, deptId, catId, secId, statusFilter]);

    const handleSync = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('hr.attendance.sync.process'), {
            onStart: () => setIsRefreshing(true),
            onSuccess: () => {
                toast.success('Sync completed successfully!');
                setIsRefreshing(false);
            },
            onError: () => {
                toast.error('Failed to process attendance logs.');
                setIsRefreshing(false);
            }
        });
    };



    const getStatusBadge = (record: AttendanceRecord) => {
        const { status, duty_value } = record;
        const d = parseFloat(duty_value);
        
        const isToday = new Date(record.attendance_date).toDateString() === new Date().toDateString();

        const badgeContent = (() => {
            if (status === 'MIS' && !isToday) return (
                <Badge variant="destructive" className="px-2.5 py-1 text-[11px] font-semibold rounded-md border-none bg-red-500 uppercase whitespace-nowrap">
                    Missed
                </Badge>
            );

            if (status === 'HD' || (d === 0.5 && status !== 'P')) return (
                <Badge className="bg-amber-500 text-white border-none px-2.5 py-0.5 text-[10px] font-bold rounded-md uppercase whitespace-nowrap">
                    HD
                </Badge>
            );

            if (status === 'A' || (d === 0 && status !== 'MIS')) return (
                <Badge variant="outline" className="border-slate-200 text-slate-400 px-2.5 py-0.5 text-[10px] font-bold rounded-md bg-slate-50 uppercase whitespace-nowrap">
                    A
                </Badge>
            );

            if (d > 0 || (status === 'MIS' && isToday)) return (
                <Badge className="bg-emerald-500 text-white border-none px-2.5 py-0.5 text-[10px] font-bold rounded-md uppercase whitespace-nowrap">
                    P
                </Badge>
            );

            return (
                <Badge variant="outline" className="border-slate-200 text-slate-400 px-2.5 py-0.5 text-[10px] font-bold rounded-md bg-slate-50 uppercase whitespace-nowrap">
                    A
                </Badge>
            );
        })();

        return (
            <div className="flex items-center justify-end gap-1 shrink-0">
                {badgeContent}
                <Button 
                    variant="ghost" 
                    size="icon" 
                    className="h-7 w-7 text-slate-400 hover:text-primary hover:bg-primary/10 rounded-md shrink-0"
                    onClick={() => openEditModal(record)}
                >
                    <Pencil className="h-3.5 w-3.5" />
                </Button>
            </div>
        );
    };

    const branchOptions = [{ label: "All Branches", value: "all" }, ...branches.map(b => ({ label: b.name, value: b.id.toString() }))];
    const deptOptions = [{ label: "All", value: "all" }, ...departments.map(d => ({ label: d.name, value: d.id.toString() }))];
    const catOptions = [{ label: "All", value: "all" }, ...categories.map(c => ({ label: c.name, value: c.id.toString() }))];
    const secOptions = [{ label: "All", value: "all" }, ...sections.map(s => ({ label: s.name, value: s.id.toString() }))];
    const statusOptions = [
        { label: "All", value: "all" },
        { label: "Present", value: "P" },
        { label: "Half Day", value: "HD" },
        { label: "Absent", value: "A" },
        { label: "Missed Punch", value: "MIS" },
    ];

    const formatRecordDate = (dateStr: string) =>
        new Date(dateStr).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' });

    return (
        <PageTemplate
            title="MisPunch"
            description="Review and correct missed biometric punches"
            url="/mispunch"
            breadcrumbs={[
                { title: 'Dashboard', href: route('dashboard') },
                { title: 'MisPunch' },
            ]}
            noPadding={true}
        >
            <div className="space-y-4 pb-6">
                
                {/* ENGINE CONTROLS ACCORDION */}
                <Collapsible open={isSyncControlsOpen} onOpenChange={setIsSyncControlsOpen} className="space-y-4">
                    <div className="flex items-center justify-between bg-white dark:bg-gray-950 border border-gray-200 dark:border-gray-800 p-3 rounded-xl shadow-sm">
                        <div className="flex items-center gap-3 pl-2">
                            <div className="p-2 bg-blue-50 dark:bg-blue-900/20 text-blue-600 rounded-lg">
                                <Settings2 className="h-4 w-4" />
                            </div>
                            <h2 className="text-sm font-black text-gray-700 dark:text-gray-200 uppercase tracking-widest">Engine Status & Sync Controls</h2>
                        </div>
                        <CollapsibleTrigger asChild>
                            <Button variant="outline" size="sm" className="h-9 border-gray-200 dark:border-gray-800 shadow-sm text-[10px] font-black uppercase tracking-widest px-4">
                                {isSyncControlsOpen ? 'Hide Controls' : 'Show Controls'}
                                {isSyncControlsOpen ? <ChevronUp className="h-4 w-4 ml-2 text-gray-400" /> : <ChevronDown className="h-4 w-4 ml-2 text-gray-400" />}
                            </Button>
                        </CollapsibleTrigger>
                    </div>

                    <CollapsibleContent className="space-y-6 animate-in slide-in-from-top-2 duration-300">
                        {/* STATUS BAR */}
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            {[
                                { title: 'Records Found', val: records?.total?.toLocaleString() || '0', icon: Database, color: 'blue' },
                                { title: 'Last Sync', val: last_sync ? new Date(last_sync).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}) : '---', icon: Clock, color: 'slate' },
                                { title: 'System', val: 'Active', icon: ShieldCheck, color: 'emerald' },
                                { title: 'Processing', val: 'Idle', icon: Zap, color: 'amber' },
                            ].map((s, i) => (
                                <Card key={i} className="shadow-sm border-gray-200 dark:border-gray-800">
                                    <CardContent className="p-4 flex items-center justify-between">
                                        <div>
                                            <p className="text-[11px] font-bold text-gray-500 uppercase tracking-tight">{s.title}</p>
                                            <p className="text-lg font-black dark:text-gray-100">{s.val}</p>
                                        </div>
                                        <div className={cn("p-2 rounded-lg", `bg-${s.color}-50 text-${s.color}-600 dark:bg-${s.color}-900/20 dark:text-${s.color}-400`)}>
                                            <s.icon className="h-4 w-4" />
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        {/* SYNC CONTROL */}
                        <Card className="shadow-sm border-gray-200 dark:border-gray-800">
                            <CardHeader className="p-5 pb-0">
                                <CardTitle className="text-lg font-bold flex items-center justify-between w-full dark:text-gray-100">
                                    <div className="flex items-center gap-2">
                                        <RefreshCw className={cn("h-5 w-5 text-gray-400", processing && "animate-spin")} />
                                        Run Sync Engine
                                    </div>
                                    <span className="text-xs font-black uppercase text-primary/80 bg-primary/5 border border-primary/10 rounded-full px-3 py-1">
                                        Active: {(() => {
                                            if (branchId === 'all') return 'All Branches';
                                            const found = branches.find(b => String(b.id) === String(branchId));
                                            return found ? found.name : 'Branch';
                                        })()}
                                    </span>
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-5 pt-4">
                                <form onSubmit={handleSync} className="flex flex-col lg:flex-row items-end gap-4 bg-gray-50 dark:bg-gray-900/50 p-4 rounded-xl border border-gray-200 dark:border-gray-800">
                                    <div className="flex-1 grid grid-cols-2 gap-4 w-full">
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-bold text-gray-600 dark:text-gray-400">From Date</Label>
                                            <Input type="date" className="h-10 bg-white dark:bg-gray-950 border-gray-200 dark:border-gray-800" value={data.from_date} onChange={e => setData('from_date', e.target.value)} max={new Date().toISOString().split("T")[0]} />
                                        </div>
                                        <div className="space-y-1.5">
                                            <Label className="text-xs font-bold text-gray-600 dark:text-gray-400">To Date</Label>
                                            <Input type="date" className="h-10 bg-white dark:bg-gray-950 border-gray-200 dark:border-gray-800" value={data.to_date} onChange={e => setData('to_date', e.target.value)} max={new Date().toISOString().split("T")[0]} />
                                        </div>
                                    </div>
                                    <Button type="submit" className="w-full lg:w-auto h-10 px-8 font-black" disabled={processing}>
                                        {processing ? 'Processing...' : 'Execute Sync'}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </CollapsibleContent>
                </Collapsible>

                {/* ADVANCED FILTER & TABLE */}
                <Card className="shadow-md overflow-hidden border-slate-200">
                    <CardHeader className="px-4 py-3 border-b space-y-0">
                        <div className="flex flex-col gap-3">
                            <div className="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-2">
                                <div>
                                    <CardTitle className="text-lg font-bold text-slate-800 flex items-center gap-2 flex-wrap">
                                        MisPunch Records
                                        <Badge variant="outline" className="text-xs font-semibold bg-slate-100 border-slate-200 text-slate-600 normal-case">
                                            {(records?.total ?? 0).toLocaleString()} total
                                        </Badge>
                                    </CardTitle>
                                    <CardDescription className="text-xs mt-1 text-slate-500">
                                        Leave dates empty to see all · set dates and click Search to filter
                                    </CardDescription>
                                </div>
                                
                                <div className="flex items-center gap-2 w-full lg:w-auto flex-wrap">
                                    <div className="relative flex-1 min-w-[200px] lg:min-w-[260px]">
                                        <Search className="absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                                        <Input 
                                            placeholder="Search by name or code..." 
                                            className="pl-9 h-10 border-slate-200 bg-white text-sm" 
                                            value={searchTerm}
                                            onChange={e => setSearchTerm(e.target.value)}
                                            onKeyDown={e => e.key === 'Enter' && handleFilter()}
                                        />
                                    </div>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => window.open('/reports/mispunch-download-24h?inline=1', '_blank', 'noopener,noreferrer')}
                                        className="h-10 border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 text-xs font-semibold hidden sm:flex"
                                    >
                                        <Download className="h-3.5 w-3.5 mr-1.5" />
                                        24h PDF
                                    </Button>
                                    <Button 
                                        variant="outline" 
                                        size="sm" 
                                        onClick={handleClearFilters}
                                        className="h-10 border-slate-200 text-slate-600 text-xs font-semibold"
                                    >
                                        <X className="h-3.5 w-3.5 mr-1" />
                                        Clear
                                    </Button>
                                    <Button onClick={handleFilter} size="sm" className="h-10 font-semibold text-xs px-5">
                                        Search
                                    </Button>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 lg:grid-cols-6 gap-3 p-3 bg-slate-50/80 rounded-lg border border-slate-100">
                                <div className="space-y-1.5 min-w-0">
                                    <Label className="text-xs font-medium text-slate-600">Department</Label>
                                    <Combobox 
                                        options={deptOptions}
                                        value={deptId}
                                        onChange={setDeptId}
                                        placeholder="All"
                                        searchPlaceholder="Search department..."
                                        className="h-10 bg-white border-slate-200 text-sm font-normal"
                                    />
                                </div>
                                <div className="space-y-1.5 min-w-0">
                                    <Label className="text-xs font-medium text-slate-600">Category</Label>
                                    <Combobox 
                                        options={catOptions}
                                        value={catId}
                                        onChange={setCatId}
                                        placeholder="All"
                                        searchPlaceholder="Search category..."
                                        className="h-10 bg-white border-slate-200 text-sm font-normal"
                                    />
                                </div>
                                <div className="space-y-1.5 min-w-0">
                                    <Label className="text-xs font-medium text-slate-600">Section</Label>
                                    <Combobox 
                                        options={secOptions}
                                        value={secId}
                                        onChange={setSecId}
                                        placeholder="All"
                                        searchPlaceholder="Search section..."
                                        className="h-10 bg-white border-slate-200 text-sm font-normal"
                                    />
                                </div>
                                <div className="space-y-1.5 min-w-0">
                                    <Label className="text-xs font-medium text-slate-600">Status</Label>
                                    <Select value={statusFilter} onValueChange={setStatusFilter}>
                                        <SelectTrigger className="h-10 bg-white border-slate-200 text-sm font-normal">
                                            <SelectValue placeholder="All" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {statusOptions.map(opt => (
                                                <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="space-y-1.5 min-w-0">
                                    <Label className="text-xs font-medium text-slate-600">From date</Label>
                                    <Input
                                        type="date"
                                        className="h-10 border-slate-200 bg-white text-sm"
                                        value={filterFrom}
                                        onChange={e => setFilterFrom(e.target.value)}
                                        max={filterTo || new Date().toISOString().split('T')[0]}
                                        autoComplete="off"
                                    />
                                </div>
                                <div className="space-y-1.5 min-w-0">
                                    <Label className="text-xs font-medium text-slate-600">To date</Label>
                                    <Input
                                        type="date"
                                        className="h-10 border-slate-200 bg-white text-sm"
                                        value={filterTo}
                                        onChange={e => setFilterTo(e.target.value)}
                                        min={filterFrom || undefined}
                                        max={new Date().toISOString().split('T')[0]}
                                        autoComplete="off"
                                    />
                                </div>
                            </div>
                        </div>
                    </CardHeader>
                    
                    <CardContent className="p-0 relative">
                        {isRefreshing && (
                            <div className="absolute inset-0 bg-white/50 backdrop-blur-sm z-20 flex items-center justify-center">
                                <Loader2 className="h-8 w-8 animate-spin text-primary" />
                            </div>
                        )}

                        {records?.data?.length > 0 && (
                            <div className="flex items-center justify-between px-4 py-2 border-b border-slate-100 bg-white text-xs text-slate-600">
                                <span>
                                    Showing <strong className="text-slate-800">{records.from}–{records.to}</strong> of{' '}
                                    <strong className="text-slate-800">{records.total.toLocaleString()}</strong>
                                </span>
                                {selectedIds.length > 0 && (
                                    <span className="font-semibold text-primary">{selectedIds.length} selected</span>
                                )}
                            </div>
                        )}
                        
                        <div className="overflow-x-auto">
                            <Table className="w-full">
                                <TableHeader>
                                    <TableRow className="bg-slate-100 border-b border-slate-200 hover:bg-slate-100">
                                        <TableHead className="w-10 px-3 py-2.5 text-center">
                                            <input 
                                                type="checkbox" 
                                                className="h-4 w-4 rounded border-slate-300 text-primary accent-primary cursor-pointer"
                                                checked={records?.data?.length > 0 && selectedIds.length === records.data.length}
                                                onChange={(e) => {
                                                    if (e.target.checked) {
                                                        setSelectedIds(records.data.map(r => r.id));
                                                    } else {
                                                        setSelectedIds([]);
                                                    }
                                                }}
                                            />
                                        </TableHead>
                                        <TableHead className="font-semibold text-xs text-slate-600 px-3 py-2.5 min-w-[220px]">Employee</TableHead>
                                        <TableHead className="font-semibold text-xs text-slate-600 text-center px-2 py-2.5 w-[72px]">Shift</TableHead>
                                        <TableHead className="font-semibold text-xs text-slate-600 text-center px-3 py-2.5 min-w-[200px]">Punches</TableHead>
                                        <TableHead className="font-semibold text-xs text-slate-600 text-center px-2 py-2.5 w-[140px]">Work · Late · OT</TableHead>
                                        <TableHead className="font-semibold text-xs text-slate-600 text-center px-2 py-2.5 w-[52px]">Duty</TableHead>
                                        <TableHead className="font-semibold text-xs text-slate-600 text-right px-3 py-2.5 w-[120px]">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {records?.data?.length > 0 ? records.data.map((record) => {
                                        const punchEvents = getDisplayPunchEventsFromRecord(record);
                                        const pairs = recordToPunchPairs(record);
                                        const misHint = record.status === 'MIS' ? mispunchSummaryText(pairs) : '';

                                        return (
                                        <TableRow
                                            key={record.id}
                                            className={cn(
                                                "border-slate-100 transition-colors",
                                                record.status === 'MIS' && "border-l-2 border-l-red-400",
                                                selectedIds.includes(record.id) ? "bg-primary/5" : "even:bg-slate-50/40 hover:bg-slate-50"
                                            )}
                                        >
                                            <TableCell className="w-10 px-3 py-2.5 text-center align-middle">
                                                <input 
                                                    type="checkbox" 
                                                    className="h-4 w-4 rounded border-slate-300 text-primary accent-primary cursor-pointer"
                                                    checked={selectedIds.includes(record.id)}
                                                    onChange={(e) => {
                                                        if (e.target.checked) {
                                                            setSelectedIds([...selectedIds, record.id]);
                                                        } else {
                                                            setSelectedIds(selectedIds.filter(id => id !== record.id));
                                                        }
                                                    }}
                                                />
                                            </TableCell>
                                            <TableCell className="px-3 py-2.5 align-middle">
                                                <div className="min-w-0">
                                                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                                        <span className="text-xs font-medium text-slate-500 shrink-0">
                                                            {formatRecordDate(record.attendance_date)}
                                                        </span>
                                                        <span className="text-xs font-bold text-primary shrink-0">{record.employee_code}</span>
                                                        <span
                                                            className="text-sm font-semibold text-slate-800 truncate max-w-[180px] lg:max-w-[280px]"
                                                            title={record.employee_display_name || record.employee?.user?.name || ''}
                                                        >
                                                            {record.employee_display_name || record.employee?.user?.name || '—'}
                                                        </span>
                                                        {misHint && (
                                                            <Badge variant="outline" className="text-[10px] font-semibold text-red-600 border-red-200 bg-red-50 shrink-0">
                                                                {misHint}
                                                            </Badge>
                                                        )}
                                                    </div>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-center align-middle px-2 py-2.5">
                                                <span className="text-xs font-semibold text-slate-600 whitespace-nowrap">
                                                    {record.base_shift || '—'}<span className="text-slate-300 mx-1">/</span>{record.shift_code || '—'}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-center align-middle px-3 py-2.5">
                                                <div className="flex flex-wrap items-center justify-center gap-1">
                                                    {punchEvents.length > 0 ? (
                                                        punchEvents.map((ev, idx) => (
                                                            <span
                                                                key={idx}
                                                                className={cn(
                                                                    'inline-block text-xs font-semibold px-2 py-0.5 rounded-md border uppercase whitespace-nowrap',
                                                                    ev.type === 'IN'
                                                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                                                                        : 'bg-orange-50 text-orange-700 border-orange-200'
                                                                )}
                                                            >
                                                                {ev.time} {ev.type}
                                                            </span>
                                                        ))
                                                    ) : (
                                                        <span className="text-xs text-slate-400">No punches</span>
                                                    )}
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-center align-middle px-2 py-2.5">
                                                <div className="text-xs leading-relaxed">
                                                    <span className="font-bold text-primary">
                                                        {record.total_minutes > 0
                                                            ? `${Math.floor(record.total_minutes / 60)}h ${record.total_minutes % 60}m`
                                                            : '—'}
                                                    </span>
                                                    <span className="text-slate-300 mx-1">·</span>
                                                    <span className={cn('font-semibold', record.late_in && record.late_in !== '0m' ? 'text-red-500' : 'text-emerald-600')}>
                                                        {record.late_in && record.late_in !== '0m' ? record.late_in : 'OK'}
                                                    </span>
                                                    <span className="text-slate-300 mx-1">·</span>
                                                    <span className="font-medium text-slate-500">
                                                        {record.ot_minutes > 0 ? record.ot_hours : '0m'}
                                                    </span>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-center align-middle px-2 py-2.5">
                                                <span className={cn(
                                                    'text-sm font-bold',
                                                    parseFloat(record.duty_value) >= 1 ? 'text-emerald-600' :
                                                    parseFloat(record.duty_value) > 0 ? 'text-sky-600' : 'text-slate-400'
                                                )}>
                                                    {parseFloat(record.duty_value).toFixed(1)}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right align-middle px-3 py-2.5">
                                                {getStatusBadge(record)}
                                            </TableCell>
                                        </TableRow>
                                        );
                                    }) : (
                                        <TableRow>
                                            <TableCell colSpan={7} className="h-32 text-center py-8">
                                                <div className="flex flex-col items-center justify-center gap-2 opacity-20">
                                                    <LayoutGrid className="h-10 w-10" />
                                                    <p className="text-sm font-black uppercase tracking-widest">No Attendance Records</p>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>

                    {/* FOOTER PAGINATION */}
                    <div className="bg-slate-50 border-t border-slate-100 px-4 py-3 flex flex-col sm:flex-row items-center justify-between gap-2">
                        <div className="text-xs text-slate-500">
                            Page {records?.links?.filter(l => l.active).map(l => l.label).join('') || '1'} · {records?.total?.toLocaleString() || 0} records
                        </div>
                        <div className="flex items-center gap-1">
                            {records?.links?.map((link, i) => {
                                const isPrev = link.label.includes('Previous');
                                const isNext = link.label.includes('Next');
                                
                                return (
                                    <Button 
                                        key={i} 
                                        variant={link.active ? 'default' : 'outline'} 
                                        size="sm" 
                                        disabled={!link.url || link.label === '...'} 
                                        onClick={() => link.url && router.get(link.url)}
                                        className={cn(
                                            "h-7 px-2.5 font-semibold text-[10px] rounded-md",
                                            link.active && "bg-primary text-white border-none",
                                            !link.url && "opacity-30"
                                        )}
                                    >
                                        {isPrev ? <ChevronLeft className="h-4 w-4" /> : 
                                         isNext ? <ChevronRight className="h-4 w-4" /> : 
                                         link.label}
                                    </Button>
                                );
                            })}
                        </div>
                    </div>
                </Card>

                {/* EDIT MODAL */}
                <Dialog open={isEditModalOpen} onOpenChange={setIsEditModalOpen}>
                    <DialogContent className="sm:max-w-[520px] max-h-[90vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle className="text-xl font-black italic uppercase tracking-tight">Manual Punch Update</DialogTitle>
                            <DialogDescription className="text-xs">
                                Correct missed punches or update attendance status for <strong>{selectedRecord?.employee_code}</strong> on {selectedRecord && new Date(selectedRecord.attendance_date).toLocaleDateString()}
                            </DialogDescription>
                        </DialogHeader>
                        
                        <form onSubmit={handleUpdate} className="space-y-4 py-4">
                            {shouldUsePairEditor(selectedRecord, editForm.data.status, editPunchPairs) ? (
                                <div className="space-y-2">
                                    <div className="text-xs font-bold text-amber-700 uppercase tracking-wider border-b border-amber-100 pb-1">
                                        {editForm.data.status === 'MIS' ? 'Mispunch Correction — IN / OUT pairs' : 'Double Shift — IN / OUT pairs'}
                                    </div>
                                    <PunchPairsEditor
                                        pairs={editPunchPairs}
                                        onChange={setEditPunchPairs}
                                        allowPartial={editForm.data.status === 'MIS'}
                                        showSummary
                                    />
                                </div>
                            ) : (
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label className="text-[10px] font-black uppercase text-slate-400">In Time</Label>
                                        <Input
                                            type="datetime-local"
                                            className="h-10 font-bold"
                                            value={editForm.data.in_time}
                                            onChange={e => editForm.setData('in_time', e.target.value)}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-[10px] font-black uppercase text-slate-400">Out Time</Label>
                                        <Input
                                            type="datetime-local"
                                            className="h-10 font-bold"
                                            value={editForm.data.out_time}
                                            onChange={e => editForm.setData('out_time', e.target.value)}
                                        />
                                    </div>
                                </div>
                            )}
                            
                            <div className="space-y-2">
                                <Label className="text-[10px] font-black uppercase text-slate-400">Status</Label>
                                <Select 
                                    value={editForm.data.status} 
                                    onValueChange={val => editForm.setData('status', val)}
                                >
                                    <SelectTrigger className="h-10 font-black">
                                        <SelectValue placeholder="Select Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="P">PRESENT (P)</SelectItem>
                                        <SelectItem value="MIS">MISSED PUNCH (MIS)</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <DialogFooter className="pt-4">
                                <Button 
                                    type="button" 
                                    variant="outline" 
                                    onClick={() => setIsEditModalOpen(false)}
                                    className="font-bold uppercase text-[10px] tracking-widest h-10 px-6"
                                >
                                    Cancel
                                </Button>
                                <Button 
                                    type="submit" 
                                    className="font-black uppercase text-[10px] tracking-widest h-10 px-8"
                                    disabled={editForm.processing}
                                >
                                    {editForm.processing ? 'Saving...' : 'Update Record'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                {/* FLOATING BULK ACTION BAR */}
                {selectedIds.length > 0 && (
                    <div className="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2 sm:gap-2.5 max-w-[calc(100vw-1.5rem)] bg-white/95 dark:bg-slate-900/95 backdrop-blur-sm pl-2.5 pr-1.5 py-1 rounded-full shadow-md border border-slate-200 dark:border-slate-700 animate-in slide-in-from-bottom-4 duration-200">
                        <span className="inline-flex items-center gap-1.5 pl-0.5 text-xs text-slate-600 dark:text-slate-300 whitespace-nowrap shrink-0">
                            <span className="inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-primary px-1.5 text-[10px] font-semibold text-white leading-none">
                                {selectedIds.length}
                            </span>
                            <span className="hidden sm:inline font-medium">selected</span>
                        </span>

                        <div className="h-4 w-px bg-slate-200 dark:bg-slate-600 shrink-0" />

                        <div className="flex items-center gap-0.5 sm:gap-1">
                            <Button
                                variant="ghost"
                                size="sm"
                                type="button"
                                onClick={() => setSelectedIds([])}
                                className="h-7 px-2 text-xs font-medium text-slate-500 hover:text-slate-800 dark:hover:text-white rounded-full"
                            >
                                Clear
                            </Button>

                            <Button
                                size="sm"
                                type="button"
                                onClick={() => {
                                    if (selectedIds.length > 0) {
                                        window.open(`${route('hr.reports.mispunch-form-pdf')}?ids=${selectedIds.join(',')}`, '_blank');
                                    }
                                }}
                                className="h-7 px-2.5 sm:px-3 text-xs font-medium rounded-full bg-sky-600 hover:bg-sky-500 text-white gap-1 border-none shadow-none"
                            >
                                <Printer className="h-3 w-3 shrink-0" />
                                <span className="hidden min-[420px]:inline">Print</span>
                            </Button>

                            <Button
                                size="sm"
                                type="button"
                                onClick={() => setIsBulkEditModalOpen(true)}
                                className="h-7 px-2.5 sm:px-3 text-xs font-medium rounded-full bg-primary hover:bg-primary/90 text-white gap-1 border-none"
                            >
                                <Pencil className="h-3 w-3 shrink-0" />
                                <span className="hidden min-[420px]:inline">Edit</span>
                            </Button>
                        </div>
                    </div>
                )}

                {/* BULK EDIT MODAL */}
                <Dialog open={isBulkEditModalOpen} onOpenChange={setIsBulkEditModalOpen}>
                    <DialogContent className="sm:max-w-[900px] max-h-[90vh] flex flex-col p-4 gap-3">
                        <DialogHeader className="space-y-1">
                            <DialogTitle className="text-base font-semibold leading-tight">Bulk correction</DialogTitle>
                            <DialogDescription className="text-xs text-slate-500">
                                Fill orange fields for missing punches, then save all changes.
                            </DialogDescription>
                        </DialogHeader>
                        
                        <form onSubmit={handleBulkUpdate} className="space-y-2 flex-1 overflow-hidden flex flex-col">
                            <div className="flex-1 overflow-y-auto space-y-4 pr-2 scrollbar-thin max-h-[65vh]">
                                {selectedIds.map(id => {
                                    const rec = records?.data?.find(r => r.id === id);
                                    const edit = bulkEdits[id];
                                    if (!rec || !edit) return null;

                                    const issues = hasMispunchIssues(edit.pairs) ? mispunchSummaryText(edit.pairs) : '';

                                    return (
                                        <div key={id} className="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-950 p-3 space-y-3">
                                            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-2">
                                                <div className="min-w-0 flex-1">
                                                    <p className="text-sm font-black text-slate-800 dark:text-slate-200 uppercase truncate">
                                                        {rec.employee_display_name || rec.employee?.user?.name || '---'}
                                                    </p>
                                                    <p className="text-[10px] font-bold text-slate-500">
                                                        Code {rec.employee_code} · {new Date(rec.attendance_date).toLocaleDateString(undefined, { day: '2-digit', month: 'short', year: 'numeric' })}
                                                        {rec.log_details ? ` · ${rec.log_details}` : ''}
                                                    </p>
                                                    {issues && (
                                                        <p className="text-[10px] font-bold text-orange-700 mt-1">
                                                            Fix: {issues}
                                                        </p>
                                                    )}
                                                </div>
                                                <select
                                                    value={edit.status}
                                                    onChange={(e) => {
                                                        setBulkEdits(prev => ({
                                                            ...prev,
                                                            [id]: { ...prev[id], status: e.target.value },
                                                        }));
                                                    }}
                                                    className={cn(
                                                        'h-8 px-2 text-[9px] font-black rounded-lg border bg-white focus:outline-none cursor-pointer shadow-sm w-[130px]',
                                                        edit.status === 'MIS'
                                                            ? 'border-red-200 text-red-700 bg-red-50'
                                                            : 'border-emerald-200 text-emerald-700 bg-emerald-50'
                                                    )}
                                                >
                                                    <option value="P">PRESENT (P)</option>
                                                    <option value="MIS">MISSED PUNCH</option>
                                                </select>
                                            </div>

                                            <PunchPairsEditor
                                                pairs={edit.pairs}
                                                onChange={(pairs) => setBulkEdits(prev => ({ ...prev, [id]: { ...prev[id], pairs } }))}
                                                allowPartial={edit.status === 'MIS'}
                                                showSummary={false}
                                                minPairs={1}
                                            />
                                        </div>
                                    );
                                })}
                            </div>

                            <DialogFooter className="pt-0 mt-1 gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setIsBulkEditModalOpen(false)}
                                    className="h-8 px-4 text-xs font-medium"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    size="sm"
                                    className="h-8 px-5 text-xs font-medium text-white bg-primary hover:bg-primary/90 border-none"
                                >
                                    Save all
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>
        </PageTemplate>
    );
}
