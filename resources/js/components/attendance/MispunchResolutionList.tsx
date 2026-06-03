import React from 'react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { AlertCircle, Calendar, Edit2, LogOut } from 'lucide-react';
import { getRecordDisplayPairs, formatTime12h as formatTime } from '@/lib/attendance-punches';

export function MispunchResolutionList({ data, loading, onResolveClick }: any) {
    if (loading) return <div className="p-10 text-center text-gray-500 font-bold animate-pulse">Loading mispunches...</div>;

    const mispunches: any[] = [];
    if (data?.employees) {
        data.employees.forEach((empData: any) => {
            Object.entries(empData.days).forEach(([dayStr, record]: [string, any]) => {
                if (record && record.status === 'MIS') {
                    // Skip today if it's MIS because they might just still be at work
                    // But backend typically doesn't mark today as MIS until tomorrow, so it's fine.
                    mispunches.push({
                        emp: empData.employee,
                        day: parseInt(dayStr),
                        record: record
                    });
                }
            });
        });
    }

    if (mispunches.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center p-20 text-center">
                <div className="w-20 h-20 bg-emerald-50 border-4 border-emerald-100 text-emerald-500 rounded-full flex items-center justify-center mb-5 shadow-sm">
                    <svg className="w-10 h-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={3} d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 className="text-xl font-black text-gray-800 tracking-tight">All Clear!</h3>
                <p className="text-gray-500 mt-2 text-sm font-medium">No mispunches found for the selected filters. Everything is perfectly resolved.</p>
            </div>
        );
    }

    return (
        <div className="p-6">
            <div className="mb-4 flex items-center gap-2">
                <div className="w-8 h-8 rounded-lg bg-orange-100 text-orange-600 flex items-center justify-center border border-orange-200">
                    <AlertCircle className="w-4 h-4" />
                </div>
                <div>
                    <h3 className="text-[14px] font-black text-gray-900 tracking-tight">Action Required</h3>
                    <p className="text-[11px] text-gray-500 font-medium">{mispunches.length} mispunches need to be resolved</p>
                </div>
            </div>
            
            <Card className="border-gray-200 dark:border-gray-800 shadow-sm overflow-hidden bg-white dark:bg-gray-950 rounded-xl">
                <Table>
                    <TableHeader className="bg-gray-50/80 dark:bg-gray-900/50 border-b border-gray-100 dark:border-gray-800">
                        <TableRow className="hover:bg-transparent">
                            <TableHead className="w-[120px] font-black text-[10px] uppercase text-gray-400 tracking-widest h-10">Date</TableHead>
                            <TableHead className="font-black text-[10px] uppercase text-gray-400 tracking-widest h-10">Employee</TableHead>
                            <TableHead className="font-black text-[10px] uppercase text-gray-400 tracking-widest h-10">Shift Assgn</TableHead>
                            <TableHead className="w-[200px] font-black text-[10px] uppercase text-gray-400 tracking-widest h-10">Recorded Punches</TableHead>
                            <TableHead className="w-[150px] font-black text-[10px] uppercase text-gray-400 tracking-widest text-right h-10">Action</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {mispunches.map((item, idx) => {
                            const pairs = getRecordDisplayPairs(item.record, item.emp);
                            
                            return (
                                <TableRow key={idx} className="hover:bg-gray-50/50 dark:hover:bg-gray-900/30 transition-colors border-b border-gray-100/50 dark:border-gray-800/50">
                                    <TableCell>
                                        <div className="flex items-center gap-2">
                                            <div className="w-7 h-7 rounded bg-orange-50 dark:bg-orange-500/10 flex items-center justify-center">
                                                <Calendar className="w-3.5 h-3.5 text-orange-500" />
                                            </div>
                                            <div className="flex flex-col">
                                                <span className="font-black text-[12px] text-gray-800 dark:text-gray-200">Day {item.day}</span>
                                                <span className="text-[10px] font-medium text-gray-400">{data?.month_name}</span>
                                            </div>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-col">
                                            <span className="font-black text-[13px] text-gray-900 dark:text-gray-100">{item.emp.name}</span>
                                            <span className="text-[10px] font-mono font-medium text-gray-500 tracking-tight">{item.emp.code} • {item.emp.department}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-col">
                                            <span className="text-[11px] font-bold text-indigo-600 dark:text-indigo-400">{item.emp.shift}</span>
                                            <span className="text-[10px] font-mono font-medium text-gray-500">{item.emp.shift_start} - {item.emp.shift_end}</span>
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex flex-col gap-1 w-full max-h-[80px] overflow-y-auto custom-scrollbar">
                                            {pairs.map((pair, pIdx) => (
                                                <div key={pIdx} className="flex items-center gap-1.5">
                                                    <div className={`flex items-center gap-1 px-1.5 py-0.5 rounded-md border w-[70px] justify-center ${pair.in_time ? 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-100/50 dark:border-emerald-500/20 text-emerald-600' : 'bg-rose-50 dark:bg-rose-500/10 border-rose-200 dark:border-rose-500/20 text-rose-500 border-dashed'}`}>
                                                        <LogOut className="w-2.5 h-2.5 rotate-180 opacity-70" />
                                                        <span className="text-[10px] font-black">{pair.in_time ? formatTime(pair.in_time) : '--:--'}</span>
                                                    </div>
                                                    <div className={`flex items-center gap-1 px-1.5 py-0.5 rounded-md border w-[70px] justify-center ${pair.out_time ? 'bg-emerald-50 dark:bg-emerald-500/10 border-emerald-100/50 dark:border-emerald-500/20 text-emerald-600' : 'bg-rose-50 dark:bg-rose-500/10 border-rose-200 dark:border-rose-500/20 text-rose-500 border-dashed'}`}>
                                                        <LogOut className="w-2.5 h-2.5 opacity-70" />
                                                        <span className="text-[10px] font-black">{pair.out_time ? formatTime(pair.out_time) : '--:--'}</span>
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Button 
                                            size="sm" 
                                            variant="outline" 
                                            className="h-8 text-[11px] font-black bg-white dark:bg-gray-950 border-orange-200 dark:border-orange-800 text-orange-600 dark:text-orange-500 hover:bg-orange-50 dark:hover:bg-orange-500/10 hover:text-orange-700 dark:hover:text-orange-400 shadow-sm"
                                            onClick={() => onResolveClick(item.emp, item.day, item.record)}
                                        >
                                            <Edit2 className="w-3.5 h-3.5 mr-1.5" />
                                            Resolve
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            );
                        })}
                    </TableBody>
                </Table>
            </Card>
        </div>
    );
}
