import { useState, useEffect } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from 'react-i18next';
import { useForm } from '@inertiajs/react';
import { toast } from '@/components/custom-toast';
import { Separator } from '@/components/ui/separator';

interface PayslipEditModalProps {
    isOpen: boolean;
    onClose: () => void;
    payslip: any;
}

export function PayslipEditModal({ isOpen, onClose, payslip }: PayslipEditModalProps) {
    const { t } = useTranslation();

    const { data, setData, put, processing, errors, reset } = useForm({
        earnings: {} as Record<string, number | string>,
        deductions: {} as Record<string, number | string>,
        overtime_amount: 0,
        shortfall_amount: 0,
        advances: {} as Record<string, number | string>,
        advance_deduction_amount: 0
    });

    const [totals, setTotals] = useState({
        totalEarnings: 0,
        totalDeductions: 0,
        netPay: 0
    });

    const [totalPendingAdvance, setTotalPendingAdvance] = useState(0);

    useEffect(() => {
        if (payslip && isOpen) {
            const initialEarnings = payslip.payroll_entry?.earnings_breakdown || {};
            const initialDeductions = payslip.payroll_entry?.deductions_breakdown || {};

            // Calculate Total Pending Advance
            const activeAdvances = payslip.employee?.active_advances || [];
            const dbPending = activeAdvances.reduce((sum: number, adv: any) => sum + (parseFloat(adv.amount) - parseFloat(adv.paid_amount)), 0);

            const currentDeduction = parseFloat(initialDeductions['Advance Pay'] || 0);
            const totalPending = dbPending + currentDeduction;

            setTotalPendingAdvance(totalPending);

            // Ensure values are numbers and Basic Salary is first
            const parsedEarnings: Record<string, number> = {};
            if (initialEarnings['Basic Salary'] !== undefined) {
                parsedEarnings['Basic Salary'] = parseFloat(initialEarnings['Basic Salary']);
            }

            Object.keys(initialEarnings).forEach(key => {
                if (key !== 'Basic Salary' && key !== 'Overtime Amount') {
                    parsedEarnings[key] = parseFloat(initialEarnings[key]);
                }
            });

            const parsedDeductions: Record<string, number> = {};
            let shortfallAmount = 0;
            Object.keys(initialDeductions).forEach(key => {
                // Exclude Advance Pay and Attendance Shortfall from generic deductions map as we handle them separately
                if (key === 'Attendance Shortfall') {
                    shortfallAmount = parseFloat(initialDeductions[key]);
                } else if (key !== 'Advance Pay') {
                    parsedDeductions[key] = parseFloat(initialDeductions[key]);
                }
            });

            setData({
                earnings: parsedEarnings,
                deductions: parsedDeductions,
                overtime_amount: parseFloat(payslip.payroll_entry?.overtime_amount || 0),
                shortfall_amount: shortfallAmount,
                advances: {}, // Unused now
                advance_deduction_amount: currentDeduction
            } as any);
        }
    }, [payslip, isOpen]);

    // Recalculate totals whenever data changes
    useEffect(() => {
        const earningsSum = Object.values(data.earnings).reduce<number>((sum, val) => sum + (Number(val) || 0), 0);
        
        // Sum includes all items in the list (Basic + Components). 
        // Overtime is added separately from its dedicated field.
        const totalEarnings = earningsSum + (Number(data.overtime_amount) || 0);
        
        const deductionsSum = Object.values(data.deductions).reduce<number>((sum, val) => sum + (Number(val) || 0), 0);
        const totalDeductions = deductionsSum + (Number(data.advance_deduction_amount) || 0) + (Number(data.shortfall_amount) || 0);

        setTotals({
            totalEarnings,
            totalDeductions,
            netPay: totalEarnings - totalDeductions
        });
    }, [data.earnings, data.deductions, data.overtime_amount, data.advance_deduction_amount, data.shortfall_amount]);

    const handleEarningChange = (key: string, value: string) => {
        setData('earnings', {
            ...data.earnings,
            [key]: value === '' ? '' : (parseFloat(value) || 0)
        });
    };

    const handleDeductionChange = (key: string, value: string) => {
        setData('deductions', {
            ...data.deductions,
            [key]: value === '' ? '' : (parseFloat(value) || 0)
        });
    };

    const handleAdvanceDeductionChange = (value: string) => {
        if (value === '') {
            setData('advance_deduction_amount', '' as any);
            return;
        }

        let amount = parseFloat(value);
        if (isNaN(amount)) return;

        // Clamp value between 0 and totalPendingAdvance
        if (amount < 0) amount = 0;
        if (amount > totalPendingAdvance) amount = totalPendingAdvance;

        setData('advance_deduction_amount', amount);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('hr.payslips.update', payslip.id), {
            onSuccess: () => {
                toast.success(t('Payslip updated successfully'));
                onClose();
            },
            onError: () => {
                toast.error(t('Failed to update payslip'));
            }
        });
    };

    if (!payslip) return null;

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{t('Edit Payslip')}: {payslip.payslip_number}</DialogTitle>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Earnings Section */}
                        <div className="space-y-4">
                            <h3 className="font-semibold text-lg text-emerald-600 border-b pb-2">{t('Earnings')}</h3>
                            {Object.entries(data.earnings).map(([key, value]) => (
                                <div key={key} className="space-y-1">
                                    <Label className="text-xs text-muted-foreground">{key}</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={value}
                                        onChange={(e) => handleEarningChange(key, e.target.value)}
                                        className="h-8"
                                    />
                                </div>
                            ))}
                            {/* Overtime Amount Field */}
                            <div className="space-y-1 pt-2 border-t border-dashed">
                                <Label className="text-xs text-emerald-600 font-medium">{t('Overtime Amount')}</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={data.overtime_amount}
                                    onChange={(e) => setData('overtime_amount', parseFloat(e.target.value) || 0)}
                                    className="h-8 border-emerald-200 focus:ring-emerald-500"
                                />
                            </div>

                            {Object.keys(data.earnings).length === 0 && (
                                <p className="text-sm text-gray-500 italic">{t('No earnings components found.')}</p>
                            )}
                            <div className="flex justify-between items-center font-bold pt-2 border-t mt-4">
                                <span>{t('Total Earnings')}:</span>
                                <span className="text-emerald-700">{window.appSettings?.formatCurrency(totals.totalEarnings)}</span>
                            </div>
                        </div>

                        {/* Deductions Section */}
                        <div className="space-y-4">
                            <h3 className="font-semibold text-lg text-red-600 border-b pb-2">{t('Deductions')}</h3>

                            {/* General Deductions */}
                            {Object.entries(data.deductions).map(([key, value]) => (
                                <div key={key} className="space-y-1">
                                    <Label className="text-xs text-muted-foreground">{key}</Label>
                                    <Input
                                        type="number"
                                        step="0.01"
                                        value={value}
                                        onChange={(e) => handleDeductionChange(key, e.target.value)}
                                        className="h-8"
                                    />
                                </div>
                            ))}

                            {/* Attendance Shortfall Field */}
                            <div className="space-y-1 pt-2 border-t border-dashed">
                                <Label className="text-xs text-red-600 font-medium">{t('Attendance Shortfall')}</Label>
                                <Input
                                    type="number"
                                    step="0.01"
                                    value={data.shortfall_amount}
                                    onChange={(e) => setData('shortfall_amount', parseFloat(e.target.value) || 0)}
                                    className="h-8 border-red-200 focus:ring-red-500"
                                />
                            </div>

                            {/* Advance Deduction Section (Running Balance) */}
                            <div className="space-y-4 pt-2 border-t border-dashed">
                                <h4 className="font-semibold text-sm text-red-600 uppercase">{t('Advance Pay Management')}</h4>
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-1">
                                        <Label className="text-xs text-muted-foreground">{t('Total Pending Advance')}</Label>
                                        <Input
                                            type="text"
                                            value={window.appSettings?.formatCurrency(totalPendingAdvance) || totalPendingAdvance}
                                            disabled
                                            className="h-8 bg-gray-100"
                                        />
                                    </div>
                                    <div className="space-y-1">
                                        <Label className="text-xs text-red-600 font-medium">{t('Deduction This Month')}</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            max={totalPendingAdvance}
                                            value={data.advance_deduction_amount}
                                            onChange={(e) => handleAdvanceDeductionChange(e.target.value)}
                                            className={`h-8 border-red-200 focus:ring-red-500 ${data.advance_deduction_amount > totalPendingAdvance ? 'border-red-500 ring-red-500' : ''}`}
                                        />
                                        {data.advance_deduction_amount >= totalPendingAdvance && totalPendingAdvance > 0 && (
                                            <p className="text-[10px] text-amber-600">{t('Full balance being cleared')}</p>
                                        )}
                                    </div>
                                </div>
                            </div>


                            {Object.keys(data.deductions).length === 0 && (
                                <p className="text-sm text-gray-500 italic">{t('No generic deduction components found.')}</p>
                            )}
                            <div className="flex justify-between items-center font-bold pt-2 border-t mt-4">
                                <span>{t('Total Deductions')}:</span>
                                <span className="text-red-700">{window.appSettings?.formatCurrency(totals.totalDeductions)}</span>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Net Pay Summary */}
                    <div className="bg-gray-50 dark:bg-gray-800 p-4 rounded-lg flex justify-between items-center">
                        <span className="text-lg font-bold">{t('Net Pay')}:</span>
                        <span className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                            {window.appSettings?.formatCurrency(totals.netPay)}
                        </span>
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose} disabled={processing}>
                            {t('Cancel')}
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? t('Saving...') : t('Save Changes & Regenerate PDF')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
