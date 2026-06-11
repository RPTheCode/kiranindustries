import { Banknote } from 'lucide-react';
import type { NavSectionBuilder } from '@/config/navigation/types';
import type { NavItem } from '@/types';
import {
    canAccessEarningDeductionEntry,
    canAccessPayrollSettings,
    canAccessPayslips,
    canAccessSalaryAdvance,
    canAccessSalaryLoan,
    canAccessSalaryPayrollEmployee,
    canAccessSalaryPayrollIncrement,
    canAccessSalaryPayrollRuns,
    isPayslipSelfServiceOnly,
} from '@/utils/authorization';

export const buildSalaryPayrollNav: NavSectionBuilder = ({ permissions, t }) => {
    const children: NavItem[] = [];

    if (canAccessSalaryPayrollEmployee(permissions)) {
        children.push({
            title: t('Employee Salary'),
            href: route('hr.salary-payroll.employee-salary.index'),
            keywords: ['salary', 'employee'],
        });
    }

    if (canAccessEarningDeductionEntry(permissions)) {
        children.push({
            title: t('Earning / Deduction'),
            href: route('hr.earning-deduction.index'),
            keywords: ['earning', 'deduction'],
        });
    }

    if (canAccessSalaryPayrollRuns(permissions)) {
        children.push({
            title: t('Run Monthly Payroll'),
            href: route('hr.salary-payroll.generate.index'),
            description: t('Calculate salary for the selected month'),
            keywords: ['payroll', 'generate', 'monthly', 'run'],
        });
    }

    if (canAccessPayslips(permissions)) {
        children.push({
            title: t('Payslips'),
            href: route('hr.salary-payroll.payslips.index'),
            description: t('View and download employee payslips'),
            keywords: ['payslip', 'salary slip', 'pay'],
        });
    }

    if (canAccessSalaryPayrollIncrement(permissions)) {
        children.push({
            title: t('Salary Increments'),
            href: route('hr.salary-payroll.salary-increment.index'),
            keywords: ['increment', 'raise'],
        });
    }

    if (canAccessSalaryAdvance(permissions)) {
        children.push({
            title: t('Salary Advance'),
            href: route('hr.salary-advances.index'),
            keywords: ['advance'],
        });
    }

    if (canAccessSalaryLoan(permissions)) {
        children.push({
            title: t('Salary Loan'),
            href: route('hr.salary-loans.index'),
            keywords: ['loan'],
        });
    }

    if (canAccessPayrollSettings(permissions)) {
        children.push({
            title: t('Payroll Settings'),
            href: route('hr.payroll-settings.index'),
            keywords: ['settings', 'payroll'],
        });
    }

    if (children.length === 0) {
        return [];
    }

    if (isPayslipSelfServiceOnly(permissions)) {
        const payslipIndex = children.findIndex((item) => item.href?.includes('/payslips'));
        if (payslipIndex > 0) {
            const [payslipItem] = children.splice(payslipIndex, 1);
            children.unshift(payslipItem);
        }
    }

    return [
        {
            title: t('Salary Payroll'),
            icon: Banknote,
            children,
            group: 'salary-payroll',
            keywords: ['payroll', 'salary', 'payslip'],
        },
    ];
};
