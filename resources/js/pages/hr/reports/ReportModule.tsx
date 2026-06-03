import React, { useState, useEffect, useCallback, useMemo } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { FileText, Download, Filter, ChevronRight, Loader2, ChevronLeft, ChevronRight as ChevronRightIcon, Users, BarChart3, FileSpreadsheet, History, RefreshCw, AlertCircle, CheckCircle2, Clock, Trash2, Sparkles } from 'lucide-react';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetDescription } from '@/components/ui/sheet';
import { format } from 'date-fns';
import axios from 'axios';
import { useBrand } from '@/contexts/BrandContext';
import { toast } from '@/components/custom-toast';

function cleanReportError(message?: string | null): string {
    if (!message) {
        return 'Report generation failed. Please try again or check Download History.';
    }

    return message.replace(/\s*-\s*\d+\s*$/, '').trim();
}

interface ReportModuleProps {
    title: string;
    reportType: 'daily' | 'monthly' | 'master';
    departments: any[];
    sections: any[];
    categories: any[];
    designations: any[];
    shifts: any[];
    branches: any[];
    employees: any[];
    userBranchId: any;
}

export default function ReportModule({ 
    title, reportType, 
    departments = [], 
    sections = [], 
    categories = [], 
    designations = [], 
    shifts = [], 
    branches = [], 
    employees = [], 
    userBranchId 
}: ReportModuleProps) {
    const brandColor = 'rgb(30, 41, 120)';

    const renderPunches = (logDetails: string) => {
        if (!logDetails) return <span className="text-slate-400 italic">No logs</span>;
        const punches = logDetails.split(',').map(p => p.trim()).filter(Boolean);
        const pairs: Array<{ in: { time: string, hasM: boolean } | null, out: { time: string, hasM: boolean } | null }> = [];
        let activePair: typeof pairs[0] | null = null;

        punches.forEach(punch => {
            const hasM = punch.includes('(M)');
            const clean = punch.replace('(M)', '').trim();
            const parts = clean.split(/\s+/);
            const time = parts[0] || '';
            const type = (parts[1] || '').toUpperCase();

            if (type === 'IN') {
                if (activePair) {
                    pairs.push(activePair);
                }
                activePair = { in: { time, hasM }, out: null };
            } else if (type === 'OUT') {
                if (activePair && !activePair.out) {
                    activePair.out = { time, hasM };
                    pairs.push(activePair);
                    activePair = null;
                } else {
                    if (activePair) {
                        pairs.push(activePair);
                    }
                    pairs.push({ in: null, out: { time, hasM } });
                    activePair = null;
                }
            }
        });

        if (activePair) {
            pairs.push(activePair);
        }

        return (
            <table className="w-auto border-collapse m-0 p-0 bg-transparent table-auto text-[10px] font-mono leading-none border-none">
                <tbody>
                    <tr className="border-none bg-transparent hover:bg-transparent">
                        <td className="pr-2 font-bold text-emerald-700 text-left border-none bg-transparent p-0" style={{ width: '22px' }}>IN</td>
                        {pairs.map((p, idx) => (
                            <td key={idx} className={`px-2 font-bold text-left border-none bg-transparent p-0 ${p.in ? 'text-slate-900' : 'text-slate-400'}`}>
                                {p.in ? (
                                    <>
                                        {p.in.time}
                                        {p.in.hasM && <span className="text-indigo-600 font-extrabold ml-0.5" style={{ color: '#4338ca' }}>(M)</span>}
                                    </>
                                ) : '-'}
                            </td>
                        ))}
                    </tr>
                    <tr className="border-none bg-transparent hover:bg-transparent">
                        <td className="pr-2 font-bold text-rose-700 text-left border-none bg-transparent p-0" style={{ width: '22px' }}>OUT</td>
                        {pairs.map((p, idx) => (
                            <td key={idx} className={`px-2 font-bold text-left border-none bg-transparent p-0 ${p.out ? 'text-slate-900' : 'text-slate-400'}`}>
                                {p.out ? (
                                    <>
                                        {p.out.time}
                                        {p.out.hasM && <span className="text-indigo-600 font-extrabold ml-0.5" style={{ color: '#4338ca' }}>(M)</span>}
                                    </>
                                ) : '-'}
                            </td>
                        ))}
                    </tr>
                </tbody>
            </table>
        );
    };

    const renderMispunchPairs = (pairs: Array<{ num: number; in: string | null; out: string | null; complete: boolean; issue?: string | null }>) => {
        if (!pairs || pairs.length === 0) {
            return <span className="text-slate-400 italic text-[11px]">No pairs</span>;
        }

        return (
            <table className="w-full border-collapse text-[10px]">
                <thead>
                    <tr className="bg-slate-100 text-slate-500">
                        <th className="px-1.5 py-1 text-left font-bold w-8">#</th>
                        <th className="px-1.5 py-1 text-left font-bold w-14">IN</th>
                        <th className="px-1 py-1 text-center font-bold w-4"></th>
                        <th className="px-1.5 py-1 text-left font-bold w-14">OUT</th>
                        <th className="px-1.5 py-1 text-left font-bold">Remark</th>
                    </tr>
                </thead>
                <tbody>
                    {pairs.map((pair) => (
                        <tr key={pair.num} className={pair.complete ? 'bg-emerald-50/60' : 'bg-red-50/70'}>
                            <td className="px-1.5 py-1 font-bold text-slate-500">{pair.num}</td>
                            <td className={`px-1.5 py-1 font-bold ${!pair.in ? 'text-red-600' : 'text-slate-800'}`}>
                                {pair.in || '—'}
                            </td>
                            <td className="px-1 py-1 text-slate-400 text-center">→</td>
                            <td className={`px-1.5 py-1 font-bold ${!pair.out ? 'text-red-600' : 'text-slate-800'}`}>
                                {pair.out || '—'}
                            </td>
                            <td className={`px-1.5 py-1 font-semibold ${pair.complete ? 'text-emerald-700' : 'text-orange-700'}`}>
                                {pair.complete ? 'OK' : (pair.issue || 'Incomplete')}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        );
    };

    const todayStr = format(new Date(), 'yyyy-MM-dd');

    const [selectedReport, setSelectedReport] = useState<string | null>(
        reportType === 'daily' 
            ? 'biometric_single' 
            : (reportType === 'monthly' ? 'monthly_production' : (reportType === 'master' ? 'department_list' : null))
    );
    const isDailyPresentReport = selectedReport === 'biometric_single';
    const isMispunchReport = selectedReport?.includes('mispunch_dedicated') ?? false;

    /** Support Excel export for workerwise and biometric dedicated reports. */
    const EXCEL_EXPORT_REPORT_IDS = [
        'att_worker', 'att_worker_h', 'att_worker_t', 'att_worker_pa', 
        'biometric_dedicated_code', 'biometric_dedicated_dept',
        'bank_transfer', 'loan_ledger', 'nominee_register', 'salary_ctc'
    ] as const;
    const canExportExcel =
        selectedReport !== null &&
        (EXCEL_EXPORT_REPORT_IDS as readonly string[]).includes(selectedReport);

    const [previewData, setPreviewData] = useState<any[]>([]);
    const [isLoading, setIsLoading] = useState(false);
    const [pagination, setPagination] = useState({ current: 1, last: 1, total: 0 });
    const [deptSearch, setDeptSearch] = useState('');
    const [empSearch, setEmpSearch] = useState('');
    
    // History State
    const [showHistory, setShowHistory] = useState(false);
    const [historyData, setHistoryData] = useState<any[]>([]);
    const [isHistoryLoading, setIsHistoryLoading] = useState(false);
    const [activeDownloadId, setActiveDownloadId] = useState<number | null>(null);
    const [activeDownload, setActiveDownload] = useState<{
        id: number;
        status: string;
        progress: number;
        error_message?: string;
        report_name?: string;
        is_no_records?: boolean;
    } | null>(null);
    const [generationNotice, setGenerationNotice] = useState<{
        type: 'error' | 'info';
        message: string;
    } | null>(null);
    const [historySearch, setHistorySearch] = useState('');
    const [historyPage, setHistoryPage] = useState(1);
    const [historyLastPage, setHistoryLastPage] = useState(1);
    const [selectedHistoryIds, setSelectedHistoryIds] = useState<number[]>([]);

    const fetchHistory = useCallback(async (page = historyPage, search = historySearch) => {
        setIsHistoryLoading(true);
        try {
            const res = await axios.get('/reports/downloads-json', {
                params: { page, search }
            });
            setHistoryData(res.data.data);
            setHistoryLastPage(res.data.last_page);
            setHistoryPage(res.data.current_page);
        } catch (e) {
            console.error("Failed to load history", e);
        } finally {
            setIsHistoryLoading(false);
        }
    }, [historyPage, historySearch]);

    const handleDeleteDownload = async (id: number) => {
        if (!confirm("Are you sure you want to delete this report from history?")) return;
        try {
            await axios.delete(`/reports/downloads/${id}`);
            setHistoryData(prev => prev.filter(item => item.id !== id));
            setSelectedHistoryIds(prev => prev.filter(selectedId => selectedId !== id));
        } catch (e) {
            toast.error('Failed to delete the report.');
            console.error(e);
        }
    };

    const handleDeleteMultiple = async () => {
        if (selectedHistoryIds.length === 0) return;
        if (!confirm(`Are you sure you want to delete ${selectedHistoryIds.length} selected reports?`)) return;
        try {
            await axios.delete('/reports/downloads/bulk-delete', { data: { ids: selectedHistoryIds } });
            setSelectedHistoryIds([]);
            fetchHistory(historyPage, historySearch);
        } catch (e) {
            toast.error('Failed to delete the reports.');
            console.error(e);
        }
    };

    // Auto refresh history drawer
    useEffect(() => {
        if (!showHistory) return;
        fetchHistory(historyPage, historySearch);
        const interval = setInterval(() => fetchHistory(historyPage, historySearch), 5000);
        return () => clearInterval(interval);
    }, [showHistory, fetchHistory, historyPage, historySearch]);

    // Poll report job status while generating (queue worker must be running)
    useEffect(() => {
        if (!activeDownloadId) {
            setActiveDownload(null);
            return;
        }

        let cancelled = false;

        const pollStatus = async () => {
            try {
                const res = await axios.get(`/reports/downloads/${activeDownloadId}/status`);
                if (cancelled) return;

                setActiveDownload(res.data);

                if (res.data.status === 'completed') {
                    const downloadId = activeDownloadId;
                    setActiveDownloadId(null);
                    setActiveDownload(null);

                    if (res.data.is_no_records) {
                        const infoMessage =
                            'No records found for the selected criteria. A summary PDF is available in Download History.';
                        setGenerationNotice({ type: 'info', message: infoMessage });
                        toast.info(infoMessage);
                        setShowHistory(true);
                        fetchHistory();
                        return;
                    }

                    window.location.href = `/reports/downloads/${downloadId}`;
                } else if (res.data.status === 'failed') {
                    const errorMessage = cleanReportError(res.data.error_message);
                    setActiveDownloadId(null);
                    setActiveDownload(null);
                    setGenerationNotice({ type: 'error', message: errorMessage });
                    toast.error(errorMessage);
                    setShowHistory(true);
                    fetchHistory();
                }
            } catch (e) {
                console.error('Failed to poll report status', e);
            }
        };

        pollStatus();
        const interval = setInterval(pollStatus, 2000);
        return () => {
            cancelled = true;
            clearInterval(interval);
        };
    }, [activeDownloadId]);

    // Prevent background scrolling when popup is open
    useEffect(() => {
        if (activeDownloadId) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = 'unset';
        }
        return () => {
            document.body.style.overflow = 'unset';
        };
    }, [activeDownloadId]);


    const [dateRangeMode, setDateRangeMode] = useState<'month' | 'custom'>('month');

    const months = [
        { value: '01', label: 'January' },
        { value: '02', label: 'February' },
        { value: '03', label: 'March' },
        { value: '04', label: 'April' },
        { value: '05', label: 'May' },
        { value: '06', label: 'June' },
        { value: '07', label: 'July' },
        { value: '08', label: 'August' },
        { value: '09', label: 'September' },
        { value: '10', label: 'October' },
        { value: '11', label: 'November' },
        { value: '12', label: 'December' },
    ];

    const currentYear = new Date().getFullYear();
    const years = Array.from({ length: 5 }, (_, i) => (currentYear - 2 + i).toString());

    const handleMonthYearChange = (m: string, y: string) => {
        const daysInMonth = new Date(parseInt(y), parseInt(m), 0).getDate();
        const start = `${y}-${m}-01`;
        const end = `${y}-${m}-${String(daysInMonth).padStart(2, '0')}`;
        setFilters(prev => ({
            ...prev,
            from_date: start,
            to_date: end,
        }));
    };

    const currentMonthForInitial = new Date().getMonth() + 1;
    const currentYearForInitial = new Date().getFullYear();
    const daysInCurrentMonthInitial = new Date(currentYearForInitial, currentMonthForInitial, 0).getDate();
    const startOfCurrentMonthInitial = `${currentYearForInitial}-${String(currentMonthForInitial).padStart(2, '0')}-01`;
    const endOfCurrentMonthInitial = `${currentYearForInitial}-${String(currentMonthForInitial).padStart(2, '0')}-${String(daysInCurrentMonthInitial).padStart(2, '0')}`;

    const [filters, setFilters] = useState({
        branch_id: userBranchId?.toString() || 'all',
        department: 'all',
        section: 'all',
        category: 'all',
        designation: 'all',
        shift: 'all',
        employee_id: 'all',
        from_date: startOfCurrentMonthInitial,
        to_date: endOfCurrentMonthInitial,
        report_type: 'codewise',
        po_status: 'all',
        status: 'all',
        status_minutes: '',
        card_type: 'N',
        hourly_type: 'N'
    });

    // Report Definitions
    const reportOptions = {
        daily: [
            { 
                group: 'Biometric Reports',
                items: [
                    { id: 'biometric_dedicated_code', name: '1. Attendance Codewise', filters: ['branch', 'category', 'department', 'section', 'date_range', 'status', 'employee_id'] },
                    { id: 'biometric_dedicated_dept', name: '2. Attendance Departmentwise', filters: ['branch', 'category', 'department', 'section', 'date_range', 'status', 'employee_id'] },
                    { id: 'biometric_single', name: '3. Biometric Daily Present', filters: ['branch', 'category', 'department', 'section', 'date_range'] },
                    { id: 'biometric_all_punches', name: '4. All Punch Report', filters: ['branch', 'category', 'department', 'section', 'date_range', 'employee_id'] },
                ]
            },
            {
                group: 'MisPunch Reports',
                items: [
                    { id: 'mispunch_dedicated_code', name: '1. MisPunch Codewise', filters: ['branch', 'category', 'department', 'section', 'date_range', 'employee_id'] },
                    { id: 'mispunch_dedicated_dept', name: '2. MisPunch Departmentwise', filters: ['branch', 'category', 'department', 'section', 'date_range', 'employee_id'] },
                ]
            },
            {
                group: 'Manual Reports',
                items: [
                    { id: 'manual_entries', name: '1. Manual Entry List', filters: ['branch', 'category', 'department', 'section', 'date_range', 'employee_id'] },
                ]
            },
            {
                group: 'Attendant Reports',
                items: [
                    { id: 'att_worker', name: '1. Workerwise Attendance (Numeric)', filters: ['branch', 'category', 'department', 'section', 'date_range', 'employee_id', 'card_type', 'hourly_type'] },
                    { id: 'att_worker_h', name: '2. Workerwise Attendance (Hourly)', filters: ['branch', 'category', 'department', 'section', 'date_range', 'employee_id', 'card_type', 'hourly_type'] },
                    { id: 'att_worker_t', name: '3. Workerwise Attendance (Time)', filters: ['branch', 'category', 'department', 'section', 'date_range', 'employee_id', 'card_type', 'hourly_type'] },
                    { id: 'att_worker_pa', name: '4. Workerwise Attendance (P/A Status)', filters: ['branch', 'category', 'department', 'section', 'date_range', 'employee_id', 'card_type', 'hourly_type'] },
                    { id: 'att_dept', name: '5. Departmentwise Attendance', filters: ['branch', 'category', 'department', 'section', 'date_range', 'card_type', 'hourly_type'] },
                    { id: 'att_shift', name: '6. Shiftwise Attendance', filters: ['branch', 'category', 'date_range', 'card_type', 'hourly_type'] },
                    { id: 'att_summary', name: '7. Monthly Summary Report', filters: ['branch', 'category', 'section', 'date_range'] },
                ]
            }
        ],
        monthly: [
            { 
                group: 'Payroll Reports',
                items: [
                    { id: 'monthly_production', name: '1. Monthly Production Report', filters: ['date_range', 'branch', 'category', 'department', 'section', 'employee_id'] },
                    { id: 'monthly_earning', name: '2. Monthly Earning Report', filters: ['date_range', 'branch', 'category', 'department', 'section', 'employee_id'] },
                    { id: 'monthly_deduction_payroll', name: '3. Monthly Deduction Report', filters: ['date_range', 'branch', 'category', 'department', 'section', 'employee_id'] },
                    { id: 'monthly_payroll_summary', name: '4. Monthly Earning-Deduction Summary', filters: ['date_range', 'branch', 'category', 'department', 'section', 'employee_id'] },
                ]
            },
            {
                group: 'Payroll Register & Ledgers',
                items: [
                    { id: 'bank_transfer', name: '5. Bank Transfer Register', filters: ['date_range', 'branch', 'category', 'department', 'section', 'employee_id'] },
                    { id: 'loan_ledger', name: '6. Loan & Advance Ledger', filters: ['branch', 'category', 'department', 'section', 'employee_id'] },
                    { id: 'nominee_register', name: '7. Nominee Declaration Register', filters: ['branch', 'category', 'department', 'section', 'employee_id'] },
                    { id: 'salary_ctc', name: '8. Salary Structure (CTC) Report', filters: ['branch', 'category', 'department', 'section', 'employee_id'] },
                ]
            }
        ],
        master: [
            { 
                group: 'Master Lists',
                items: [
                    { id: 'department_list', name: 'Department Master List', filters: [] },
                    { id: 'designation_list', name: 'Designation Master List', filters: [] },
                    { id: 'category_list', name: 'Category Master List', filters: [] },
                    { id: 'section_list', name: 'Section Master List', filters: [] },
                    { id: 'shift_list', name: 'Shift Master List', filters: [] },
                    { id: 'skill_list', name: 'Skill Master List', filters: [] },
                    { id: 'material_list', name: 'Material Master List', filters: [] },
                ]
            }
        ]
    };

    const currentGroups = reportOptions[reportType] || [];
    const allReports = currentGroups.flatMap(g => g.items);
    const activeReport = allReports.find(r => r.id === selectedReport);

    const branchEmployees = useMemo(() => {
        return employees.filter((e) => {
            if (filters.department !== 'all' && e.department_id && e.department_id !== filters.department) {
                return false;
            }
            if (filters.section !== 'all' && e.section_id && e.section_id !== filters.section) {
                return false;
            }
            return true;
        });
    }, [employees, filters.department, filters.section]);

    const filteredEmployees = useMemo(() => {
        const q = empSearch.trim().toLowerCase();
        if (!q) {
            return branchEmployees;
        }
        return branchEmployees.filter(
            (e) =>
                e.name?.toLowerCase().includes(q) ||
                e.code?.toString().toLowerCase().includes(q)
        );
    }, [branchEmployees, empSearch]);

    const selectedEmployeeLabel = useMemo(() => {
        if (filters.employee_id === 'all') {
            return 'All Employees';
        }
        const emp = employees.find((e) => e.id.toString() === filters.employee_id);
        return emp ? `${emp.code} — ${emp.name}` : 'Select Employee';
    }, [employees, filters.employee_id]);

    // Fetch Preview Data
    const fetchPreview = useCallback(async (page = 1) => {
        if (!selectedReport) return;
        setIsLoading(true);
        try {
            const previewFrom = filters.from_date;
            const previewTo = filters.to_date;

            const previewReportId = ['bank_transfer', 'loan_ledger', 'nominee_register', 'salary_ctc'].includes(selectedReport)
                ? selectedReport
                : (selectedReport === 'biometric_all_punches'
                    ? 'biometric_all_punches'
                    : (isMispunchReport
                        ? 'mispunch_dedicated'
                        : (selectedReport.includes('biometric')
                            ? selectedReport.replace('_h', '').replace('_t', '').replace('_pa', '').replace('_code', '').replace('_dept', '')
                            : selectedReport.replace('_h', '').replace('_t', '').replace('_pa', ''))));

            const response = await axios.get('/reports/preview', {
                params: {
                    report_id: previewReportId,
                    page,
                    ...filters,
                    status: isMispunchReport ? 'MIS' : filters.status,
                    from_date: previewFrom,
                    to_date: previewTo,
                    report_type: selectedReport === 'biometric_single'
                        ? 'codewise'
                        : (selectedReport.includes('_dept') ? 'department' : (selectedReport.includes('_code') ? 'codewise' : (selectedReport === 'biometric_all_punches' ? 'department' : filters.report_type)))
                }
            });
            
            if (response.data && response.data.data) {
                setPreviewData(Array.isArray(response.data.data) ? response.data.data : []);
                setPagination({
                    current: response.data.current_page || 1,
                    last: response.data.last_page || 1,
                    total: response.data.total || 0
                });
            } else {
                setPreviewData([]);
            }
        } catch (error) {
            console.error("Preview fetch failed", error);
        } finally {
            setIsLoading(false);
        }
    }, [selectedReport, filters, todayStr]);

    // Clear employee selection if no longer in branch/dept/section list
    useEffect(() => {
        if (filters.employee_id === 'all') {
            return;
        }
        const stillValid = branchEmployees.some((e) => e.id.toString() === filters.employee_id);
        if (!stillValid) {
            setFilters((prev) => ({ ...prev, employee_id: 'all' }));
        }
    }, [branchEmployees, filters.employee_id]);

    // Daily present report: default to today's date upon selection (but still editable)
    useEffect(() => {
        if (selectedReport === 'biometric_single') {
            setFilters(prev => ({
                ...prev,
                from_date: todayStr,
                to_date: todayStr
            }));
        }
    }, [selectedReport, todayStr]);

    useEffect(() => {
        if (selectedReport) {
            const isMonthly = reportType === 'monthly';
            setDateRangeMode(isMonthly ? 'month' : 'custom');
            if (isMonthly) {
                const m = format(new Date(), 'MM');
                const y = format(new Date(), 'yyyy');
                handleMonthYearChange(m, y);
            }
        }
    }, [selectedReport, reportType]);

    // Auto-fetch on change
    useEffect(() => {
        if (selectedReport) {
            fetchPreview(1);
        }
    }, [selectedReport, filters, fetchPreview]);

    // Sync with global branch changes
    useEffect(() => {
        if (userBranchId !== undefined) {
            setFilters(prev => {
                const newBranchId = userBranchId?.toString() || 'all';
                if (prev.branch_id !== newBranchId) {
                    setEmpSearch('');
                    return {
                        ...prev,
                        branch_id: newBranchId,
                        department: 'all',
                        section: 'all',
                        category: 'all',
                        designation: 'all',
                        shift: 'all',
                        employee_id: 'all'
                    };
                }
                return prev;
            });
        }
    }, [userBranchId]);

    const buildReportParams = () => {
        if (!selectedReport) return null;

        const isDedicated = selectedReport.includes('biometric_dedicated') || selectedReport === 'biometric_all_punches' || isMispunchReport;
        const customPosterReports = ['bank_transfer', 'loan_ledger', 'nominee_register', 'salary_ctc'];
        const isCustomPoster = customPosterReports.includes(selectedReport);
        const reportId = isCustomPoster
            ? selectedReport
            : (isMispunchReport
                ? 'mispunch_dedicated'
                : (selectedReport === 'biometric_all_punches'
                    ? 'biometric_all_punches'
                    : (selectedReport.includes('biometric')
                        ? selectedReport.replace('_h', '').replace('_t', '').replace('_pa', '').replace('_code', '').replace('_dept', '')
                        : selectedReport.replace('_h', '').replace('_t', '').replace('_pa', ''))));
        const rType = selectedReport === 'biometric_single'
            ? 'codewise'
            : (selectedReport.includes('_dept') ? 'department' : (selectedReport.includes('_code') ? 'codewise' : (selectedReport === 'biometric_all_punches' ? 'department' : filters.report_type)));
        const allowEmployeeFilter = activeReport?.filters.includes('employee_id') ?? false;

        const dateFrom = filters.from_date;
        const dateTo = selectedReport === 'biometric_single' ? filters.from_date : filters.to_date;

        const params = new URLSearchParams({
            report_id: reportId,
            ...filters,
            status: isMispunchReport ? 'MIS' : filters.status,
            from_date: dateFrom,
            to_date: dateTo,
            employee_id: allowEmployeeFilter ? filters.employee_id : 'all',
            report_type: rType,
            hourly_type: filters.hourly_type,
            month: dateFrom.split('-')[1],
            year: dateFrom.split('-')[0],
        });

        return { params, isDedicated };
    };

    const handleDownload = () => {
        const built = buildReportParams();
        if (!built) return;

        if (reportType === 'master') {
            const masterTypes: Record<string, string> = {
                'branch_list': 'PLC',
                'department_list': 'DPT',
                'designation_list': 'DSG',
                'material_list': 'MAT',
                'shift_list': 'SHT',
                'skill_list': 'SKL',
                'section_list': 'SEC',
                'category_list': 'CNT',
                'master_listing': 'STF',
            };
            
            const type =
                (selectedReport
                    ? masterTypes[selectedReport as keyof typeof masterTypes]
                    : undefined) || 'STF';
            const qs = new URLSearchParams({ type, branch_id: filters.branch_id });
            
            // Add other filters just in case
            Object.entries(filters).forEach(([key, value]) => {
                if (value !== 'all' && value !== '') {
                    qs.set(key, value.toString());
                }
            });
            
            window.open(`/reports/master-listing?${qs.toString()}`, '_blank');
            return;
        }
        const isAllEmployees = built.params.get('employee_id') === 'all';
        const isMonthlyReport = selectedReport === 'monthly_production' || 
                                selectedReport === 'monthly_earning_deduction' || 
                                selectedReport === 'monthly_earning' || 
                                selectedReport === 'monthly_deduction_payroll' || 
                                selectedReport === 'monthly_payroll_summary' ||
                                selectedReport === 'bank_transfer' ||
                                selectedReport === 'loan_ledger' ||
                                selectedReport === 'nominee_register' ||
                                selectedReport === 'salary_ctc';
        if (!isMonthlyReport && (pagination.total > 400 || (pagination.total === 0 && isAllEmployees))) {
            handleBackgroundDownload();
            return;
        }

        const baseUrl = built.isDedicated ? '/reports/biometric-dedicated' : '/reports/generate';
        window.open(`${baseUrl}?${built.params.toString()}`, '_blank');
        
        // Open the history drawer so user can see the report appear
        setShowHistory(true);
        
        // Refresh history after a short delay so the newly generated direct report appears
        setTimeout(() => {
            fetchHistory();
        }, 3000);
    };

    const handleBackgroundDownload = async () => {
        const built = buildReportParams();
        if (!built) return;

        try {
            setGenerationNotice(null);
            // Send request as form data or json
            const response = await axios.post('/reports/generate-background', Object.fromEntries(built.params.entries()));
            if (response.data.success) {
                const downloadId = response.data.download_id;
                setActiveDownloadId(downloadId);
                setActiveDownload({
                    id: downloadId,
                    status: 'pending',
                    progress: 0,
                });
                fetchHistory();
            }
        } catch (e) {
            toast.error('Failed to start background generation.');
            console.error(e);
        }
    };

    const handleExportExcel = () => {
        if (!canExportExcel || !selectedReport) return;
        const built = buildReportParams();
        if (!built) return;
        
        if (built.isDedicated) {
            built.params.set('export_excel', '1');
            window.open(`/reports/biometric-dedicated?${built.params.toString()}`, '_blank');
            return;
        }

        built.params.set('report_id', selectedReport);
        window.open(`/reports/export-excel?${built.params.toString()}`, '_blank');
    };

    const updateFilter = (key: string, value: any) => {
        setFilters((prev) => {
            const next = { ...prev, [key]: value };
            if (key === 'department' || key === 'section') {
                next.employee_id = 'all';
                setEmpSearch('');
            }
            return next;
        });
    };

    const resetFilters = () => {
        setFilters({
            branch_id: userBranchId?.toString() || 'all',
            department: 'all',
            section: 'all',
            category: 'all',
            designation: 'all',
            shift: 'all',
            employee_id: 'all',
            from_date: startOfCurrentMonthInitial,
            to_date: endOfCurrentMonthInitial,
            report_type: 'codewise',
            po_status: 'all',
            status: 'P',
            status_minutes: '',
            card_type: 'N',
            hourly_type: 'N'
        });
    };

    const showStatusMinutesField = filters.status === 'latein' || filters.status === 'earlyout';

    return (
        <AppLayout>
            <Head title={title} />
            
            <div className="p-6 max-w-[1600px] mx-auto space-y-6">
                {generationNotice && (
                    <div
                        className={`flex items-start gap-3 rounded-xl border px-4 py-3 ${
                            generationNotice.type === 'error'
                                ? 'border-rose-200 bg-rose-50 text-rose-800'
                                : 'border-amber-200 bg-amber-50 text-amber-900'
                        }`}
                    >
                        {generationNotice.type === 'error' ? (
                            <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" />
                        ) : (
                            <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0" />
                        )}
                        <div className="min-w-0 flex-1">
                            <p className="text-sm font-semibold">
                                {generationNotice.type === 'error'
                                    ? 'Report generation failed'
                                    : 'No records found'}
                            </p>
                            <p className="mt-1 text-sm opacity-90">{generationNotice.message}</p>
                        </div>
                        <Button
                            variant="ghost"
                            size="sm"
                            className="shrink-0 h-8 px-2"
                            onClick={() => setGenerationNotice(null)}
                        >
                            Dismiss
                        </Button>
                    </div>
                )}

                <div className="flex items-center justify-between bg-white p-4 rounded-xl border shadow-sm">
                    <div className="flex items-center gap-4">
                        <div 
                            className="p-2.5 rounded-lg shadow-lg"
                            style={{ background: `linear-gradient(135deg, ${brandColor}, #1e1b4b)` }}
                        >
                            <FileText className="w-6 h-6 text-white" />
                        </div>
                        <div>
                            <h1 className="text-2xl font-bold text-slate-900">{title}</h1>
                            <p className="text-slate-500 text-sm">Select a report to view live data and download PDF.</p>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        {historyData.length > 0 && historyData[0].status === 'completed' && historyData[0].file_path && (
                            <div className="hidden sm:flex items-center gap-3 bg-white px-3 py-1.5 rounded-lg border border-slate-200 shadow-sm">
                                <Sparkles className="w-4 h-4 text-indigo-500" />
                                <div className="flex flex-col">
                                    <span className="text-[10px] font-bold text-slate-500 uppercase tracking-wider">Latest Generated</span>
                                    <span className="text-xs font-semibold text-slate-900 truncate max-w-[200px]">{historyData[0].report_name}</span>
                                </div>
                                <a 
                                    href={`/reports/downloads/${historyData[0].id}`}
                                    target="_blank"
                                    className="ml-2 inline-flex items-center justify-center rounded text-[10px] font-bold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 px-2.5 py-1 gap-1 transition-all"
                                >
                                    <Download className="w-3 h-3" /> Get
                                </a>
                            </div>
                        )}
                        <Button 
                            variant="outline" 
                            onClick={() => setShowHistory(true)} 
                            className="gap-2 transition-all hover:shadow-md"
                            style={{ color: brandColor, borderColor: 'rgba(30, 41, 120, 0.2)', backgroundColor: 'white' }}
                        >
                            <History className="w-4 h-4" />
                            Recent Downloads
                        </Button>
                    </div>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
                    {/* LEFT SIDEBAR */}
                    <Card className="lg:col-span-1 shadow-sm border-slate-200 h-fit sticky top-6 bg-white">
                        <CardHeader className="pb-3 border-b bg-slate-50/30">
                            <CardTitle className="text-[10px] font-black uppercase text-slate-400 flex items-center gap-2 tracking-[0.2em]">
                                <Filter className="w-3.5 h-3.5" />
                                Report Categories
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="max-h-[75vh] overflow-y-auto custom-scrollbar">
                                {currentGroups.map((group, gIdx) => (
                                    <div key={gIdx} className="mb-2 last:mb-0">
                                        <div className="px-5 py-3 bg-slate-50/50 text-[10px] font-black text-slate-400 uppercase tracking-widest border-y border-slate-100/50">
                                            {group.group}
                                        </div>
                                        <div className="space-y-0.5 mt-1">
                                            {group.items.map((report) => {
                                                const isSelected = selectedReport === report.id;
                                                return (
                                                    <button
                                                        key={report.id}
                                                        onClick={() => {
                                                            resetFilters();
                                                            setSelectedReport(report.id);
                                                            if (report.id === 'att_worker') updateFilter('hourly_type', 'N');
                                                            if (report.id === 'att_worker_h') updateFilter('hourly_type', 'Y');
                                                            if (report.id === 'att_worker_t') updateFilter('hourly_type', 'T');
                                                            if (report.id === 'att_worker_pa') updateFilter('hourly_type', 'A');
                                                        }}
                                                        className={`w-full text-left px-5 py-4 transition-all flex items-center justify-between group relative ${
                                                            isSelected 
                                                            ? 'bg-blue-50/30' 
                                                            : 'hover:bg-slate-50'
                                                        }`}
                                                        style={isSelected ? { borderRight: `3px solid ${brandColor}` } : {}}
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            <div 
                                                                className={`w-1.5 h-1.5 rounded-full transition-all ${isSelected ? 'scale-125' : 'bg-slate-300 opacity-50 group-hover:opacity-100'}`}
                                                                style={isSelected ? { backgroundColor: brandColor } : {}}
                                                            ></div>
                                                            <span 
                                                                className={`text-[13px] font-bold tracking-tight leading-tight ${isSelected ? 'text-slate-900' : 'text-slate-600 group-hover:text-slate-900'}`}
                                                                style={isSelected ? { color: brandColor } : {}}
                                                            >
                                                                {report.name}
                                                            </span>
                                                        </div>
                                                        <ChevronRight className={`w-4 h-4 transition-all ${isSelected ? 'translate-x-0' : 'text-slate-300 -translate-x-2 opacity-0 group-hover:opacity-100 group-hover:translate-x-0'}`} 
                                                            style={isSelected ? { color: brandColor } : {}}
                                                        />
                                                    </button>
                                                );
                                            })}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>

                    {/* RIGHT PANEL */}
                    <div className="lg:col-span-3 space-y-6">
                        <Card className="shadow-sm border-slate-200 overflow-hidden bg-white">
                            <CardHeader className="pb-4 border-b bg-slate-50/50">
                                <CardTitle className="text-xs font-black flex items-center justify-between">
                                    <div className="flex items-center gap-2 text-slate-500 uppercase tracking-widest">
                                        <Filter className="w-3.5 h-3.5" />
                                        Advanced Configuration
                                    </div>
                                    {selectedReport && (
                                        <span className="text-[10px] font-bold px-2.5 py-1 rounded border"
                                            style={{ color: brandColor, backgroundColor: 'rgba(30, 41, 120, 0.05)', borderColor: 'rgba(30, 41, 120, 0.1)' }}
                                        >
                                            {activeReport?.name}
                                        </span>
                                    )}
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="p-6">
                                {selectedReport && activeReport ? (
                                    <div className="space-y-6">
                                        {isMispunchReport && (
                                            <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                                                Today's records are not included — employees may still be on duty and can punch out later.
                                            </div>
                                        )}
                                        <div className="grid grid-cols-1 md:grid-cols-4 gap-5">
                                            {/* Date Filters */}
                                            {activeReport.filters.includes('date') && (
                                                <div className="space-y-1.5">
                                                    <Label className="text-[10px] font-bold uppercase text-slate-400 ml-0.5">Date</Label>
                                                    <Input type="date" className="h-9 text-xs rounded-lg border-slate-200" value={filters.from_date} onChange={(e) => { updateFilter('from_date', e.target.value); updateFilter('to_date', e.target.value); }} />
                                                </div>
                                            )}
                                            {activeReport.filters.includes('date_range') && (
                                                isDailyPresentReport ? (
                                                    <div className="space-y-1.5 md:col-span-2">
                                                        <Label className="text-[10px] font-bold uppercase text-slate-500">Report Date</Label>
                                                        <Input
                                                            type="date"
                                                            className="h-9 text-sm"
                                                            value={filters.from_date}
                                                            onChange={(e) => { updateFilter('from_date', e.target.value); updateFilter('to_date', e.target.value); }}
                                                        />
                                                    </div>
                                                ) : (
                                                    <>
                                                            <div className="space-y-2 md:col-span-4 border-b pb-3 mb-2 flex items-center justify-between">
                                                                <Label className="text-xs font-black uppercase text-slate-400">Date Range Selection</Label>
                                                                <div className="flex gap-2 bg-slate-100 p-1 rounded-lg">
                                                                    <Button
                                                                        type="button"
                                                                        variant={dateRangeMode === 'month' ? 'default' : 'ghost'}
                                                                        size="sm"
                                                                        onClick={() => {
                                                                            setDateRangeMode('month');
                                                                            const m = format(new Date(filters.from_date), 'MM');
                                                                            const y = format(new Date(filters.from_date), 'yyyy');
                                                                            handleMonthYearChange(m, y);
                                                                        }}
                                                                        className="h-7 text-[10px] font-bold uppercase tracking-wider rounded-md px-3"
                                                                    >
                                                                        Month-wise
                                                                    </Button>
                                                                    <Button
                                                                        type="button"
                                                                        variant={dateRangeMode === 'custom' ? 'default' : 'ghost'}
                                                                        size="sm"
                                                                        onClick={() => setDateRangeMode('custom')}
                                                                        className="h-7 text-[10px] font-bold uppercase tracking-wider rounded-md px-3"
                                                                    >
                                                                        Custom Range
                                                                    </Button>
                                                                </div>
                                                            </div>
                                                        {dateRangeMode === 'month' ? (
                                                            <>
                                                                <div className="space-y-1.5">
                                                                    <Label className="text-[10px] font-bold uppercase text-slate-500">Select Month</Label>
                                                                    <Select
                                                                        value={format(new Date(filters.from_date), 'MM')}
                                                                        onValueChange={(m) => {
                                                                            const y = format(new Date(filters.from_date), 'yyyy');
                                                                            handleMonthYearChange(m, y);
                                                                        }}
                                                                    >
                                                                        <SelectTrigger className="h-9 text-sm"><SelectValue placeholder="Month" /></SelectTrigger>
                                                                        <SelectContent>
                                                                            {months.map(m => <SelectItem key={m.value} value={m.value}>{m.label}</SelectItem>)}
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>
                                                                <div className="space-y-1.5">
                                                                    <Label className="text-[10px] font-bold uppercase text-slate-500">Select Year</Label>
                                                                    <Select
                                                                        value={format(new Date(filters.from_date), 'yyyy')}
                                                                        onValueChange={(y) => {
                                                                            const m = format(new Date(filters.from_date), 'MM');
                                                                            handleMonthYearChange(m, y);
                                                                        }}
                                                                    >
                                                                        <SelectTrigger className="h-9 text-sm"><SelectValue placeholder="Year" /></SelectTrigger>
                                                                        <SelectContent>
                                                                            {years.map(y => <SelectItem key={y} value={y}>{y}</SelectItem>)}
                                                                        </SelectContent>
                                                                    </Select>
                                                                </div>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <div className="space-y-1.5">
                                                                    <Label className="text-[10px] font-bold uppercase text-slate-500">From Date</Label>
                                                                    <Input type="date" className="h-9 text-sm" value={filters.from_date} onChange={(e) => updateFilter('from_date', e.target.value)} />
                                                                </div>
                                                                <div className="space-y-1.5">
                                                                    <Label className="text-[10px] font-bold uppercase text-slate-500">To Date</Label>
                                                                    <Input type="date" className="h-9 text-sm" value={filters.to_date} onChange={(e) => updateFilter('to_date', e.target.value)} />
                                                                </div>
                                                            </>
                                                        )}
                                                    </>
                                                )
                                            )}

                                            {/* Organization Filters */}

                                            {activeReport.filters.includes('department') && (
                                                <div className="space-y-1.5">
                                                    <Label className="text-[10px] font-bold uppercase text-slate-500">Department</Label>
                                                    <Select value={filters.department} onValueChange={(v) => updateFilter('department', v)}>
                                                        <SelectTrigger className="h-9 text-sm"><SelectValue placeholder="Dept" /></SelectTrigger>
                                                        <SelectContent>
                                                            <div className="p-2 border-b">
                                                                 <Input 
                                                                    placeholder="Search dept..." 
                                                                    className="h-8 text-xs" 
                                                                    value={deptSearch}
                                                                    onChange={(e) => setDeptSearch(e.target.value)}
                                                                />
                                                            </div>
                                                            <SelectItem value="all">All Departments</SelectItem>
                                                            {departments
                                                                .filter(d => d.name.toLowerCase().includes(deptSearch.toLowerCase()))
                                                                .map(d => <SelectItem key={d.id} value={d.id.toString()}>{d.name}</SelectItem>)}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            )}

                                            {activeReport.filters.includes('section') && (
                                                <div className="space-y-1.5">
                                                    <Label className="text-[10px] font-bold uppercase text-slate-500">Section</Label>
                                                    <Select value={filters.section} onValueChange={(v) => updateFilter('section', v)}>
                                                        <SelectTrigger className="h-9 text-sm"><SelectValue placeholder="Section" /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">All Sections</SelectItem>
                                                            {sections.map(s => <SelectItem key={s.id} value={s.id.toString()}>{s.name}</SelectItem>)}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            )}

                                            {activeReport.filters.includes('designation') && (
                                                <div className="space-y-1.5">
                                                    <Label className="text-[10px] font-bold uppercase text-slate-500">Designation</Label>
                                                    <Select value={filters.designation} onValueChange={(v) => updateFilter('designation', v)}>
                                                        <SelectTrigger className="h-9 text-sm"><SelectValue placeholder="Designation" /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">All Designations</SelectItem>
                                                            {designations.map(d => <SelectItem key={d.id} value={d.id.toString()}>{d.name}</SelectItem>)}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            )}

                                            {activeReport.filters.includes('shift') && (
                                                <div className="space-y-1.5">
                                                    <Label className="text-[10px] font-bold uppercase text-slate-500">Shift</Label>
                                                    <Select value={filters.shift} onValueChange={(v) => updateFilter('shift', v)}>
                                                        <SelectTrigger className="h-9 text-sm"><SelectValue placeholder="Shift" /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">All Shifts</SelectItem>
                                                            {shifts.map(s => <SelectItem key={s.id} value={s.id.toString()}>{s.name}</SelectItem>)}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            )}

                                            {activeReport.filters.includes('status') && (
                                                <div className="space-y-1.5">
                                                    <Label className="text-[10px] font-bold uppercase text-slate-500">Status</Label>
                                                    <Select
                                                        value={filters.status}
                                                        onValueChange={(v) => {
                                                            setFilters((prev) => ({
                                                                ...prev,
                                                                status: v,
                                                                status_minutes: v === 'latein' ? (prev.status_minutes || '20') : (v === 'earlyout' ? prev.status_minutes : ''),
                                                            }));
                                                        }}
                                                    >
                                                        <SelectTrigger className="h-9 text-sm"><SelectValue placeholder="Status" /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">All Status</SelectItem>
                                                            <SelectItem value="P">Present</SelectItem>
                                                            <SelectItem value="MIS">Mispunch</SelectItem>
                                                            <SelectItem value="overtime">Overtime</SelectItem>
                                                            <SelectItem value="latein">Late In</SelectItem>
                                                            <SelectItem value="earlyout">Early Out</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            )}

                                            {showStatusMinutesField && (
                                                <div className="space-y-1.5">
                                                    <Label className="text-[10px] font-bold uppercase text-slate-500">
                                                        {filters.status === 'latein' ? 'Late In (Min)' : 'Early Out (Min)'}
                                                    </Label>
                                                    <Input
                                                        type="number"
                                                        min={1}
                                                        placeholder="e.g. 15"
                                                        className="h-9 text-sm"
                                                        value={filters.status_minutes}
                                                        onChange={(e) => updateFilter('status_minutes', e.target.value)}
                                                    />
                                                    <p className="text-[9px] text-slate-400 leading-tight">
                                                        Show only records more than this many minutes {filters.status === 'latein' ? 'late' : 'early'} (e.g. 15 = after 8:15 if shift starts 8:00).
                                                    </p>
                                                </div>
                                            )}

                                            {activeReport.filters.includes('category') && (
                                                <div className="space-y-1.5">
                                                    <Label className="text-[10px] font-bold uppercase text-slate-500">Category</Label>
                                                    <Select value={filters.category} onValueChange={(v) => updateFilter('category', v)}>
                                                        <SelectTrigger className="h-9 text-sm"><SelectValue placeholder="Category" /></SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="all">All Categories</SelectItem>
                                                            {categories.map(c => <SelectItem key={c.id} value={c.id.toString()}>{c.name}</SelectItem>)}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            )}

                                             {activeReport.filters.includes('employee_id') && (
                                                <div className="space-y-1.5 md:col-span-2">
                                                    <Label className="text-[10px] font-bold uppercase text-slate-500">Employee</Label>
                                                    <Select
                                                        value={filters.employee_id}
                                                        onValueChange={(v) => updateFilter('employee_id', v)}
                                                        onOpenChange={(open) => { if (!open) setEmpSearch(''); }}
                                                    >
                                                        <SelectTrigger className="h-9 text-sm overflow-hidden whitespace-nowrap">
                                                            <span className="truncate text-xs">{selectedEmployeeLabel}</span>
                                                        </SelectTrigger>
                                                        <SelectContent className="max-h-[320px]">
                                                            <div className="p-2 border-b sticky top-0 bg-white z-10">
                                                                <Input
                                                                    placeholder="Search by emp code or name..."
                                                                    className="h-8 text-xs"
                                                                    value={empSearch}
                                                                    onChange={(e) => setEmpSearch(e.target.value)}
                                                                    onKeyDown={(e) => e.stopPropagation()}
                                                                    onClick={(e) => e.stopPropagation()}
                                                                />
                                                            </div>
                                                            <SelectItem value="all">All Employees</SelectItem>
                                                            {filteredEmployees.length === 0 ? (
                                                                <div className="px-3 py-4 text-center text-xs text-slate-400">
                                                                    No employees found for this branch / filter
                                                                </div>
                                                            ) : (
                                                                filteredEmployees.map((e) => (
                                                                    <SelectItem key={e.id} value={e.id.toString()}>
                                                                        <div className="flex items-center gap-2 max-w-[240px]">
                                                                            <span className="font-mono text-[9px] font-bold bg-slate-100 text-slate-600 px-1 py-0.5 rounded leading-none shrink-0 border border-slate-200">
                                                                                {e.code}
                                                                            </span>
                                                                            <span className="truncate text-xs">{e.name}</span>
                                                                        </div>
                                                                    </SelectItem>
                                                                ))
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            )}

                                             {/* Report Options - Premium Radio Design (Matching Attendance Popup) */}
                                             {activeReport.filters.includes('hourly_type') && (
                                                <div className="space-y-2 md:col-span-2">
                                                    <Label className="text-[10px] font-bold uppercase text-slate-400 ml-1">Report Format</Label>
                                                    <div className="grid grid-cols-4 gap-2 bg-slate-50 dark:bg-slate-900/50 p-1.5 rounded-lg border border-slate-100 dark:border-slate-800">
                                                        {[
                                                            { id: 'N', label: 'Numeric' },
                                                            { id: 'Y', label: 'Hourly' },
                                                            { id: 'T', label: 'Time' },
                                                            { id: 'A', label: 'P/A Status' }
                                                        ].map((type) => (
                                                            <label 
                                                                key={type.id} 
                                                                className={`flex flex-col items-center justify-center p-2 rounded-md cursor-pointer transition-all border ${
                                                                    filters.hourly_type === type.id 
                                                                    ? 'bg-white border-slate-200 shadow-sm relative' 
                                                                    : 'border-transparent hover:bg-slate-100/50 relative'
                                                                }`}
                                                            >
                                                                <input 
                                                                    type="radio" 
                                                                    name="hourlyType" 
                                                                    value={type.id} 
                                                                    checked={filters.hourly_type === type.id} 
                                                                    onChange={(e) => updateFilter('hourly_type', e.target.value)}
                                                                    className="sr-only"
                                                                />
                                                                <span 
                                                                    className={`text-[9px] font-black uppercase mb-0.5 ${filters.hourly_type === type.id ? '' : 'text-slate-400'}`}
                                                                    style={filters.hourly_type === type.id ? { color: brandColor } : {}}
                                                                >
                                                                    {type.id}
                                                                </span>
                                                                <span 
                                                                    className={`text-[10px] font-bold whitespace-nowrap ${filters.hourly_type === type.id ? 'text-slate-900' : 'text-slate-500'}`}
                                                                >
                                                                    {type.label}
                                                                </span>
                                                                {filters.hourly_type === type.id && (
                                                                    <div className="absolute -top-1 -right-1 w-2 h-2 rounded-full border-2 border-white shadow-sm" style={{ backgroundColor: brandColor }}></div>
                                                                )}
                                                            </label>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}

                                             {activeReport.filters.includes('card_type') && (
                                                <div className="space-y-2 md:col-span-2">
                                                    <Label className="text-[10px] font-bold uppercase text-slate-400 ml-1">Card Format</Label>
                                                    <div className="grid grid-cols-2 gap-2 bg-slate-50 dark:bg-slate-900/50 p-1.5 rounded-lg border border-slate-100 dark:border-slate-800">
                                                        {[
                                                            { id: 'N', label: 'No' },
                                                            { id: 'Y', label: 'Yes' }
                                                        ].map((type) => (
                                                            <label 
                                                                key={type.id} 
                                                                className={`flex flex-col items-center justify-center p-2 rounded-md cursor-pointer transition-all border ${
                                                                    filters.card_type === type.id 
                                                                    ? 'bg-white border-slate-200 shadow-sm relative' 
                                                                    : 'border-transparent hover:bg-slate-100/50 relative'
                                                                }`}
                                                            >
                                                                <input 
                                                                    type="radio" 
                                                                    name="cardType" 
                                                                    value={type.id} 
                                                                    checked={filters.card_type === type.id} 
                                                                    onChange={(e) => updateFilter('card_type', e.target.value)}
                                                                    className="sr-only"
                                                                />
                                                                <span 
                                                                    className={`text-[9px] font-black uppercase mb-0.5 ${filters.card_type === type.id ? '' : 'text-slate-400'}`}
                                                                    style={filters.card_type === type.id ? { color: brandColor } : {}}
                                                                >
                                                                    {type.id}
                                                                </span>
                                                                <span 
                                                                    className={`text-[10px] font-bold whitespace-nowrap ${filters.card_type === type.id ? 'text-slate-900' : 'text-slate-500'}`}
                                                                >
                                                                    {type.label}
                                                                </span>
                                                            </label>
                                                        ))}
                                                    </div>
                                                </div>
                                            )}
                                        </div>

                                            <div className="md:col-span-4 flex items-center justify-between pt-5 border-t border-slate-100">
                                                <Button 
                                                    variant="ghost" 
                                                    size="sm" 
                                                    onClick={resetFilters}
                                                    className="text-[10px] font-black uppercase text-slate-400 hover:text-slate-600"
                                                >
                                                    Clear All
                                                </Button>
                                                <div className="flex items-center gap-2">
                                                    {canExportExcel && (
                                                        <Button 
                                                            type="button"
                                                            variant="outline"
                                                            onClick={handleExportExcel}
                                                            className="shadow-sm hover:shadow-md transition-all h-10 px-6 rounded-xl text-xs font-bold gap-2 border-emerald-200 text-emerald-800 bg-emerald-50 hover:bg-emerald-100"
                                                        >
                                                            <FileSpreadsheet className="w-4 h-4" />
                                                            EXPORT EXCEL
                                                        </Button>
                                                    )}
                                                    <div className="flex gap-2 items-center">
                                                        <Button 
                                                            type="button"
                                                            onClick={handleDownload}
                                                            className="shadow-md hover:shadow-lg transition-all h-10 px-8 rounded-xl text-xs font-bold gap-2 text-white"
                                                            style={{ backgroundColor: brandColor }}
                                                        >
                                                            <Download className="w-4 h-4" />
                                                            GENERATE PDF
                                                        </Button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                ) : (
                                    <div className="py-20 text-center space-y-3 bg-slate-50/30 rounded-2xl border border-dashed border-slate-200">
                                        <div className="w-12 h-12 bg-white rounded-full flex items-center justify-center mx-auto shadow-sm">
                                            <FileText className="w-6 h-6 text-slate-300" />
                                        </div>
                                        <p className="text-sm text-slate-400 font-medium tracking-tight px-6">Select a report category from the sidebar to configure filters.</p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>

                        {/* LIVE DATA TABLE */}
                        {reportType === 'daily' && (
                        <Card className="shadow-sm border-slate-200 overflow-hidden bg-white">
                            <CardHeader className="pb-3 border-b" style={{ backgroundColor: brandColor }}>
                                <div className="flex flex-row items-center justify-between">
                                    <CardTitle className="text-[11px] font-black flex items-center gap-2 text-white uppercase tracking-widest">
                                        <BarChart3 className="w-4 h-4" />
                                        Live Data Preview
                                        {isLoading && <Loader2 className="w-3.5 h-3.5 animate-spin text-blue-400" />}
                                    </CardTitle>
                                    <span className="text-[9px] bg-white/10 px-2 py-1 rounded text-white/70 uppercase font-black tracking-widest">
                                        {pagination.total} Records Found
                                    </span>
                                </div>
                            </CardHeader>
                            <CardContent className="p-0">
                                <div className="overflow-x-auto min-h-[400px]">
                                    <table className="w-full text-sm text-left">
                                        <thead className="bg-slate-50 text-slate-400 text-[10px] uppercase font-black border-b tracking-widest">
                                            <tr>
                                                {selectedReport === 'biometric_all_punches' ? (
                                                     <>
                                                        <th className="px-4 py-3 text-center">No</th>
                                                        <th className="px-4 py-3">Date</th>
                                                        <th className="px-5 py-3">Emp Name with code</th>
                                                        <th className="px-4 py-3">Designation</th>
                                                        <th className="px-6 py-3">All Punch Details (In Out)</th>
                                                        <th className="px-4 py-3 text-center">Lunch Time</th>
                                                        <th className="px-4 py-3 text-center">Total Hour</th>
                                                        <th className="px-4 py-3 text-center">Status</th>
                                                     </>
                                                ) : isMispunchReport ? (
                                                     <>
                                                        <th className="px-5 py-3">Code</th>
                                                        <th className="px-5 py-3">Name</th>
                                                        <th className="px-4 py-3">Dept</th>
                                                        <th className="px-4 py-3">Date</th>
                                                        <th className="px-4 py-3">Shift</th>
                                                        <th className="px-5 py-3 min-w-[320px]">IN → OUT Pairs</th>
                                                        <th className="px-4 py-3 text-center">Status</th>
                                                     </>
                                                ) : (
                                                     <>
                                                        <th className="px-5 py-3">Code</th>
                                                        <th className="px-5 py-3">Name</th>
                                                        {(selectedReport?.includes('biometric') || selectedReport?.startsWith('att_worker')) ? (
                                                             <>
                                                                <th className="px-4 py-3">Dept</th>
                                                                <th className="px-4 py-3">Shift</th>
                                                                <th className="px-4 py-3">In</th>
                                                                <th className="px-4 py-3">Out</th>
                                                                <th className="px-4 py-3 text-center">Total</th>
                                                                <th className="px-4 py-3 text-center">OT</th>
                                                                <th className="px-4 py-3 text-center">Duty</th>
                                                                <th className="px-4 py-3 text-center">Status</th>
                                                             </>
                                                        ) : (selectedReport?.startsWith('att_') || selectedReport === 'manual_entries') ? (
                                                             <>
                                                                <th className="px-4 py-3">Dept</th>
                                                                <th className="px-4 py-3">Date</th>
                                                                <th className="px-4 py-3">In</th>
                                                                <th className="px-4 py-3">Out</th>
                                                                <th className="px-4 py-3">Status</th>
                                                             </>
                                                        ) : (
                                                             <>
                                                                <th className="px-4 py-3">Date</th>
                                                                <th className="px-4 py-3">In</th>
                                                                <th className="px-4 py-3">Out</th>
                                                                <th className="px-4 py-3">Late In</th>
                                                                <th className="px-4 py-3">Early Out</th>
                                                                <th className="px-4 py-3">Mis-Punch</th>
                                                             </>
                                                        )}
                                                        {!isMispunchReport && (
                                                            <th className="px-4 py-3 text-right">Branch</th>
                                                        )}
                                                     </>
                                                )}
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {previewData.length > 0 ? previewData.map((row, idx) => {
                                                if (selectedReport === 'biometric_all_punches') {
                                                    const rowNo = pagination.current ? (pagination.current - 1) * 15 + idx + 1 : idx + 1;
                                                    return (
                                                        <tr key={idx} className="hover:bg-slate-50 transition-all duration-200 group">
                                                            <td className="px-4 py-3.5 text-center text-slate-400 font-mono text-[10px] font-black">{rowNo}</td>
                                                            <td className="px-4 py-3.5 text-slate-500 text-[11px] font-bold">{row.date}</td>
                                                            <td className="px-5 py-4">
                                                                <div className="flex flex-col">
                                                                    <span className="font-bold text-slate-900 text-xs tracking-tight">{row.name}</span>
                                                                    <span className="text-[9px] text-slate-400 font-mono uppercase tracking-tighter">{row.code}</span>
                                                                </div>
                                                            </td>
                                                            <td className="px-4 py-3.5 text-slate-600 text-[11px]">{row.designation || '---'}</td>
                                                            <td className="px-6 py-3.5 text-slate-700 text-xs">
                                                                {renderPunches(row.log_details)}
                                                            </td>
                                                            <td className="px-4 py-3.5 text-center text-[11px] text-slate-600 font-medium">{row.lunch_time || '---'}</td>
                                                            <td className="px-4 py-3.5 text-center text-[10px] font-bold text-slate-600">{row.total_hours || '-'}</td>
                                                            <td className="px-6 py-4 text-center">
                                                                <span className={`px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest ${
                                                                    row.status === 'P' ? 'bg-green-100 text-green-700' : 
                                                                    row.status === 'A' ? 'bg-red-50 text-red-600 border border-red-100' :
                                                                    row.status === 'MIS' ? 'bg-orange-100 text-orange-700' :
                                                                    'bg-blue-50 text-blue-700 border border-blue-100'
                                                                }`}>
                                                                    {row.status === 'P' ? 'Present' : (row.status === 'A' ? 'Absent' : row.status)}
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    );
                                                }

                                                if (isMispunchReport) {
                                                    const pairs = row.punch_pairs?.length
                                                        ? row.punch_pairs
                                                        : [{
                                                            num: 1,
                                                            in: row.in_time && row.in_time !== '-' ? row.in_time : null,
                                                            out: row.out_time && row.out_time !== '-' ? row.out_time : null,
                                                            complete: !!(row.in_time && row.in_time !== '-' && row.out_time && row.out_time !== '-'),
                                                            issue: row.issue || null,
                                                        }];
                                                    const badCount = pairs.filter((p: { complete: boolean }) => !p.complete).length;

                                                    return (
                                                        <tr key={idx} className="hover:bg-red-50/30 transition-all duration-200 group">
                                                            <td className="px-6 py-4 text-slate-500 font-mono text-[10px] font-bold">{row.code}</td>
                                                            <td className="px-6 py-4">
                                                                <span className="font-bold text-slate-900 text-xs tracking-tight">{row.name}</span>
                                                            </td>
                                                            <td className="px-4 py-3.5 text-slate-600 text-[11px]">{row.department}</td>
                                                            <td className="px-4 py-3.5 text-slate-500 text-[11px] font-bold">{row.date}</td>
                                                            <td className="px-4 py-3.5 text-slate-500 text-[11px] font-bold">{row.shift || '-'}</td>
                                                            <td className="px-4 py-3.5 align-top">
                                                                {renderMispunchPairs(pairs)}
                                                                {badCount > 0 && (
                                                                    <p className="text-[10px] font-semibold text-orange-700 mt-1">
                                                                        {badCount} incomplete pair{badCount > 1 ? 's' : ''} need correction
                                                                    </p>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3.5 text-center align-top">
                                                                <span className="px-2.5 py-1 rounded-md text-[9px] font-bold uppercase bg-red-100 text-red-700">
                                                                    Missed
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    );
                                                }

                                                return (
                                                    <tr key={idx} className="hover:bg-slate-50 transition-all duration-200 group">
                                                        <td className="px-6 py-4 text-slate-400 font-mono text-[10px] font-black">{row.code}</td>
                                                        <td className="px-6 py-4">
                                                            <div className="flex flex-col">
                                                                <span className="font-bold text-slate-900 text-xs tracking-tight">{row.name}</span>
                                                                <span className="text-[9px] text-slate-400 uppercase font-black tracking-tighter">{row.branch}</span>
                                                            </div>
                                                        </td>
                                                        {(selectedReport?.includes('biometric') || selectedReport?.startsWith('att_worker')) ? (
                                                            <>
                                                                <td className="px-4 py-3.5 text-slate-600 text-[11px]">{row.department}</td>
                                                                <td className="px-4 py-3.5 text-slate-500 text-[11px] font-bold">{row.shift || '-'}</td>
                                                                <td className="px-4 py-3.5 font-bold text-xs" style={{ color: brandColor }}>
                                                                    {row.in_time && row.in_time.includes(' (M)') ? (
                                                                        <span>
                                                                            {row.in_time.replace(' (M)', '')}
                                                                            <span className="font-bold ml-1" style={{ color: '#4338ca' }}>(M)</span>
                                                                        </span>
                                                                    ) : row.in_time}
                                                                </td>
                                                                <td className="px-4 py-3.5 font-bold text-xs" style={{ color: brandColor }}>
                                                                    {row.out_time && row.out_time.includes(' (M)') ? (
                                                                        <span>
                                                                            {row.out_time.replace(' (M)', '')}
                                                                            <span className="font-bold ml-1" style={{ color: '#4338ca' }}>(M)</span>
                                                                        </span>
                                                                    ) : row.out_time}
                                                                </td>
                                                                <td className="px-4 py-3.5 text-center text-[10px] font-bold text-slate-600">{row.total_hours}</td>
                                                                <td className="px-4 py-3.5 text-center text-[10px] font-bold text-orange-600">{row.over_time}</td>
                                                                <td className="px-4 py-3.5 text-center text-[11px] font-black text-blue-600">{row.duty_value}</td>
                                                                <td className="px-6 py-4 text-center">
                                                                    <span className={`px-3 py-1 rounded-lg text-[9px] font-black uppercase tracking-widest ${
                                                                        row.status === 'P' ? 'bg-green-100 text-green-700' : 
                                                                        row.status === 'A' ? 'bg-red-50 text-red-600 border border-red-100' :
                                                                        row.status === 'MIS' ? 'bg-orange-100 text-orange-700' :
                                                                        'bg-blue-50 text-blue-700 border border-blue-100'
                                                                    }`}>
                                                                        {row.status === 'P' ? 'Present' : (row.status === 'A' ? 'Absent' : row.status)}
                                                                    </span>
                                                                </td>
                                                            </>
                                                        ) : selectedReport?.startsWith('att_') ? (
                                                            <>
                                                                <td className="px-4 py-3.5 text-slate-600 text-[11px]">{row.department}</td>
                                                                <td className="px-4 py-3.5 text-slate-500 text-[11px] font-bold">{row.date}</td>
                                                                <td className="px-4 py-3.5 font-bold text-xs" style={{ color: brandColor }}>
                                                                    {row.in_time && row.in_time.includes(' (M)') ? (
                                                                        <span>
                                                                            {row.in_time.replace(' (M)', '')}
                                                                            <span className="font-bold ml-1" style={{ color: '#4338ca' }}>(M)</span>
                                                                        </span>
                                                                    ) : row.in_time}
                                                                </td>
                                                                <td className="px-4 py-3.5 font-bold text-xs" style={{ color: brandColor }}>
                                                                    {row.out_time && row.out_time.includes(' (M)') ? (
                                                                        <span>
                                                                            {row.out_time.replace(' (M)', '')}
                                                                            <span className="font-bold ml-1" style={{ color: '#4338ca' }}>(M)</span>
                                                                        </span>
                                                                    ) : row.out_time}
                                                                </td>
                                                                <td className="px-4 py-3.5">
                                                                    <span className={`px-2 py-0.5 rounded-full text-[9px] font-bold uppercase ${
                                                                        row.status === 'P' ? 'bg-green-100 text-green-700' : 
                                                                        row.status === 'MIS' ? 'bg-orange-100 text-orange-700' :
                                                                        'bg-red-100 text-red-700'
                                                                    }`}>
                                                                        {row.status}
                                                                    </span>
                                                                </td>
                                                            </>
                                                        ) : (
                                                            <>
                                                                <td className="px-4 py-3.5 text-slate-500 text-[11px] font-bold">{row.date}</td>
                                                                <td className="px-4 py-3.5 font-bold text-xs" style={{ color: brandColor }}>
                                                                    {row.in_time && row.in_time.includes(' (M)') ? (
                                                                        <span>
                                                                            {row.in_time.replace(' (M)', '')}
                                                                            <span className="font-bold ml-1" style={{ color: '#4338ca' }}>(M)</span>
                                                                        </span>
                                                                    ) : row.in_time}
                                                                </td>
                                                                <td className="px-4 py-3.5 font-bold text-xs" style={{ color: brandColor }}>
                                                                    {row.out_time && row.out_time.includes(' (M)') ? (
                                                                        <span>
                                                                            {row.out_time.replace(' (M)', '')}
                                                                            <span className="font-bold ml-1" style={{ color: '#4338ca' }}>(M)</span>
                                                                        </span>
                                                                    ) : row.out_time}
                                                                </td>
                                                                <td className="px-4 py-3.5 text-red-500 text-xs">{row.late_in}</td>
                                                                <td className="px-4 py-3.5 text-red-500 text-xs">{row.early_out}</td>
                                                                <td className="px-4 py-3.5 text-xs font-bold text-slate-600">{row.mis_punch}</td>
                                                            </>
                                                        )}
                                                        <td className="px-4 py-3.5 text-right text-slate-500 text-[11px]">{row.branch}</td>
                                                    </tr>
                                                );
                                            }) : (
                                                <tr>
                                                    <td colSpan={7} className="px-4 py-20 text-center text-slate-400 italic">
                                                        {selectedReport ? (isLoading ? 'Loading data...' : 'No records found for the selected filters.') : 'Please select a report to view data.'}
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                                
                                {/* PAGINATION */}
                                {pagination.last > 1 && (
                                    <div className="p-4 border-t bg-slate-50/50 flex items-center justify-between">
                                        <p className="text-xs text-slate-500">
                                            Page <span className="font-bold">{pagination.current}</span> of <span className="font-bold">{pagination.last}</span>
                                        </p>
                                        <div className="flex gap-2">
                                            <Button 
                                                variant="outline" 
                                                size="sm" 
                                                disabled={pagination.current === 1 || isLoading}
                                                onClick={() => fetchPreview(pagination.current - 1)}
                                            >
                                                <ChevronLeft className="w-4 h-4" />
                                            </Button>
                                            <Button 
                                                variant="outline" 
                                                size="sm" 
                                                disabled={pagination.current === pagination.last || isLoading}
                                                onClick={() => fetchPreview(pagination.current + 1)}
                                            >
                                                <ChevronRightIcon className="w-4 h-4" />
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                        )}
                    </div>
                </div>
            </div>

            {/* Auto-Download Loading Modal */}
            {activeDownloadId && (
                <div className="fixed inset-0 z-[20000] bg-slate-900/40 backdrop-blur-sm flex flex-col items-center justify-center p-4">
                    <div className="bg-white rounded-2xl shadow-2xl p-8 max-w-sm w-full text-center space-y-4 animate-in zoom-in-95 duration-300 border border-slate-100">
                        <div className="w-16 h-16 bg-slate-50 rounded-full flex items-center justify-center mx-auto mb-2 relative">
                            <div className="absolute inset-0 rounded-full border-4 border-slate-100"></div>
                            <div className="absolute inset-0 rounded-full border-4 border-t-transparent animate-spin" style={{ borderColor: `${brandColor} transparent transparent transparent` }}></div>
                            <FileText className="w-6 h-6 animate-pulse" style={{ color: brandColor }} />
                        </div>
                        <div>
                            <h3 className="text-xl font-bold text-slate-900 mb-1">Generating Report</h3>
                            <p className="text-sm text-slate-500 leading-relaxed mb-4">
                                Please wait while we process this large dataset. The PDF will download automatically once ready.
                            </p>

                            {/* Progress Bar */}
                            {(() => {
                                const progress = activeDownload?.progress ?? 0;
                                const statusLabel =
                                    activeDownload?.status === 'processing'
                                        ? 'Processing…'
                                        : activeDownload?.status === 'pending'
                                          ? 'Queued — waiting for worker'
                                          : activeDownload?.status === 'failed'
                                            ? 'Failed'
                                            : 'Starting…';
                                return (
                                    <div className="w-full mt-4 space-y-2">
                                        <p className="text-xs text-slate-500">{statusLabel}</p>
                                        <div className="flex justify-between text-xs font-semibold text-slate-600">
                                            <span>Progress</span>
                                            <span>{progress}%</span>
                                        </div>
                                        <div className="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                                            <div
                                                className="h-2.5 rounded-full transition-all duration-500 ease-out"
                                                style={{ width: `${progress}%`, backgroundColor: brandColor }}
                                            />
                                        </div>
                                    </div>
                                );
                            })()}
                        </div>
                        <div className="pt-4 border-t border-slate-100">
                            <Button 
                                variant="outline" 
                                className="w-full text-slate-500 hover:text-slate-700" 
                                onClick={() => setActiveDownloadId(null)}
                            >
                                Run in Background Instead
                            </Button>
                        </div>
                    </div>
                </div>
            )}

            <Sheet open={showHistory} onOpenChange={setShowHistory}>
                <SheetContent 
                    className="w-full sm:max-w-md overflow-y-auto custom-scrollbar p-0 flex flex-col gap-0"
                    style={{ borderTop: '6px solid #1a365d' }}
                >
                    <div className="p-4 pt-5 border-b bg-white relative">
                        <div className="flex flex-col gap-1">
                            <SheetTitle className="flex items-center gap-2 text-lg font-black text-slate-900 tracking-tight pr-8">
                                <div className="p-1.5 rounded-lg bg-[#1a365d]/10 text-[#1a365d]">
                                    <History className="w-4 h-4" />
                                </div>
                                Download History
                            </SheetTitle>
                            <SheetDescription className="pt-1">
                            </SheetDescription>
                        </div>
                        <div className="mt-3 space-y-3">
                            <div className="flex gap-2">
                                <Input 
                                    placeholder="Search reports..." 
                                    value={historySearch}
                                    onChange={(e) => setHistorySearch(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') fetchHistory(1, historySearch);
                                    }}
                                    className="h-9 text-xs rounded-lg border-slate-200 focus-visible:ring-[#1a365d] bg-slate-50"
                                />
                                <Button 
                                    variant="outline" 
                                    size="sm" 
                                    onClick={() => fetchHistory(1, historySearch)} 
                                    disabled={isHistoryLoading}
                                    className="h-9 px-3 shrink-0 rounded-lg border-slate-200 text-[#1a365d] hover:bg-[#1a365d]/5"
                                >
                                    <RefreshCw className={`w-3.5 h-3.5 ${isHistoryLoading ? 'animate-spin' : ''}`} />
                                </Button>
                            </div>
                            
                            <div className="flex items-center justify-between text-sm">
                                <label className="flex items-center gap-2 cursor-pointer">
                                    <input 
                                        type="checkbox" 
                                        className="rounded border-slate-300 text-[#1a365d] focus:ring-[#1a365d]"
                                        checked={historyData.length > 0 && selectedHistoryIds.length === historyData.length}
                                        onChange={(e) => {
                                            if (e.target.checked) {
                                                setSelectedHistoryIds(historyData.map(d => d.id));
                                            } else {
                                                setSelectedHistoryIds([]);
                                            }
                                        }}
                                    />
                                    <span className="text-slate-600 font-medium">Select All</span>
                                </label>
                                
                                {selectedHistoryIds.length > 0 && (
                                    <Button 
                                        variant="destructive" 
                                        size="sm" 
                                        onClick={handleDeleteMultiple}
                                        className="h-8 text-xs gap-1.5"
                                    >
                                        <Trash2 className="w-3.5 h-3.5" />
                                        Delete ({selectedHistoryIds.length})
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                    
                    {/* Latest report section removed */}

                    <div className="p-6 space-y-4">
                        {historyData.length === 0 ? (
                            <div className="text-center py-8 text-slate-500">
                                <FileText className="w-12 h-12 text-slate-200 mx-auto mb-3" />
                                <p>No recent downloads found.</p>
                            </div>
                        ) : (
                            historyData.map(download => (
                                <div key={download.id} className="p-4 rounded-xl border border-slate-200 bg-white shadow-sm space-y-3 relative overflow-hidden group hover:border-[#1a365d] transition-colors">
                                    {download.status === 'processing' && <div className="absolute top-0 left-0 w-full h-1 bg-[#1a365d] animate-pulse" />}
                                    
                                    <div className="flex justify-between items-start">
                                        <div className="flex items-start gap-3">
                                            <input 
                                                type="checkbox" 
                                                className="mt-1 rounded border-slate-300 text-[#1a365d] focus:ring-[#1a365d] cursor-pointer"
                                                checked={selectedHistoryIds.includes(download.id)}
                                                onChange={(e) => {
                                                    if (e.target.checked) {
                                                        setSelectedHistoryIds(prev => [...prev, download.id]);
                                                    } else {
                                                        setSelectedHistoryIds(prev => prev.filter(id => id !== download.id));
                                                    }
                                                }}
                                            />
                                            <div>
                                                <div className="font-semibold text-xs text-slate-900 line-clamp-2" title={download.report_name}>{download.report_name}</div>
                                                <div className="text-[10px] text-slate-500 font-medium mt-0.5">
                                                    {format(new Date(download.created_at), 'dd MMM yyyy, hh:mm a')}
                                                </div>
                                            </div>
                                        </div>
                                        {download.branch_name && (
                                            <div className="shrink-0 ml-2">
                                                <span className="inline-flex items-center px-2 py-0.5 rounded text-[9px] font-bold tracking-wide uppercase bg-slate-100 text-slate-600 border border-slate-200">
                                                    {download.branch_name}
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                    
                                    <div className="text-xs text-slate-500 mt-1">
                                        {(download.status === 'processing' || download.status === 'pending') ? (
                                            <div className="flex items-center gap-1.5 text-amber-600">
                                                <Clock className="w-3 h-3 animate-spin" />
                                                Processing ({download.progress || 0}%)
                                                <div className="w-16 h-1.5 ml-2 bg-amber-100 rounded-full overflow-hidden">
                                                    <div className="bg-amber-500 h-full rounded-full transition-all" style={{ width: `${download.progress || 0}%` }}></div>
                                                </div>
                                            </div>
                                        ) : download.status === 'completed' ? (
                                            <div className="flex items-center gap-1 text-emerald-600">
                                                <CheckCircle2 className="w-3 h-3" /> Ready
                                            </div>
                                        ) : null}
                                    </div>

                                    <div className="flex justify-between items-center">
                                        <div>
                                            {download.status === 'completed' && (
                                                <span className="inline-flex items-center gap-1.5 text-xs font-medium text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full">
                                                    <CheckCircle2 className="w-3.5 h-3.5" /> Ready
                                                </span>
                                            )}
                                            {download.status === 'processing' && (
                                                <span className="inline-flex items-center gap-1.5 text-xs font-medium text-[#1a365d] bg-[#1a365d]/10 px-2.5 py-1 rounded-full">
                                                    <Loader2 className="w-3.5 h-3.5 animate-spin" /> Processing
                                                </span>
                                            )}
                                            {download.status === 'pending' && (
                                                <span className="inline-flex items-center gap-1.5 text-xs font-medium text-amber-700 bg-amber-50 px-2.5 py-1 rounded-full">
                                                    <Clock className="w-3.5 h-3.5" /> In Queue
                                                </span>
                                            )}
                                            {download.status === 'failed' && (
                                                <span className="inline-flex items-center gap-1.5 text-xs font-medium text-rose-700 bg-rose-50 px-2.5 py-1 rounded-full">
                                                    <AlertCircle className="w-3.5 h-3.5" /> Failed
                                                </span>
                                            )}
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <button 
                                                onClick={() => handleDeleteDownload(download.id)}
                                                className="p-1.5 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                                                title="Delete Report"
                                            >
                                                <Trash2 className="w-4 h-4" />
                                            </button>
                                            
                                            {download.status === 'completed' && download.file_path && (
                                                <a 
                                                    href={`/reports/downloads/${download.id}`}
                                                    target="_blank"
                                                    className="inline-flex items-center justify-center whitespace-nowrap rounded-md text-xs font-medium bg-[#1a365d] !text-white hover:opacity-90 hover:!text-white h-8 px-3 gap-1.5 transition-all shadow-sm"
                                                >
                                                    <Download className="w-3.5 h-3.5" /> Download
                                                </a>
                                            )}
                                        </div>
                                    </div>
                                    
                                    {download.status === 'failed' && download.error_message && (
                                        <div className="text-xs p-2.5 bg-rose-50 text-rose-700 rounded-lg font-mono break-all mt-2 border border-rose-100">
                                            {download.error_message}
                                        </div>
                                    )}
                                </div>
                            ))
                        )}
                        
                        {historyLastPage > 1 && (
                            <div className="flex justify-between items-center pt-4 mt-2">
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    disabled={historyPage === 1 || isHistoryLoading}
                                    onClick={() => fetchHistory(historyPage - 1, historySearch)}
                                >
                                    Previous
                                </Button>
                                <span className="text-xs text-slate-500 font-medium">Page {historyPage} of {historyLastPage}</span>
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    disabled={historyPage === historyLastPage || isHistoryLoading}
                                    onClick={() => fetchHistory(historyPage + 1, historySearch)}
                                >
                                    Next
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Sticky Footer Info */}
                    <div className="sticky bottom-0 left-0 right-0 py-3 px-4 bg-amber-50 border-t border-amber-200/60 z-10 mt-auto flex items-center justify-center gap-2 text-[11px] font-medium text-amber-700 shadow-[0_-4px_6px_-1px_rgba(0,0,0,0.02)]">
                        <AlertCircle className="w-3.5 h-3.5 shrink-0 text-amber-600" />
                        <span className="truncate">
                            Background reports appear here & auto-delete after <strong className="font-bold text-amber-900">3 days</strong>.
                        </span>
                    </div>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
