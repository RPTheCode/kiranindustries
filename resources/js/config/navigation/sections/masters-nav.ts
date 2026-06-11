import { Layers } from 'lucide-react';
import type { NavSectionBuilder } from '@/config/navigation/types';
import type { NavItem } from '@/types';
import { canAccessEntity, canViewPermission } from '@/utils/authorization';

function pushIfAllowed(
    items: NavItem[],
    permissions: string[],
    slug: string,
    title: string,
    href: string,
    keywords?: string[],
) {
    if (!canViewPermission(permissions, slug)) {
        return;
    }

    items.push({ title, href, keywords });
}

export const buildMastersNav: NavSectionBuilder = ({ permissions, t }) => {
    const organization: NavItem[] = [];

    pushIfAllowed(organization, permissions, 'branches', t('Branches'), route('hr.branches.index'), ['branch', 'office']);
    pushIfAllowed(organization, permissions, 'departments', t('Departments'), route('hr.departments.index'), ['department', 'dept']);
    pushIfAllowed(organization, permissions, 'designations', t('Designations'), route('hr.designations.index'), ['designation', 'job title']);
    pushIfAllowed(organization, permissions, 'sections', t('Sections'), route('hr.sections.index'), ['section']);
    pushIfAllowed(organization, permissions, 'shifts', t('Shifts'), route('hr.shifts.index'), ['shift']);
    pushIfAllowed(organization, permissions, 'week-offs', t('Week Offs'), route('hr.week-offs.index'), ['week off', 'weekly off']);
    pushIfAllowed(organization, permissions, 'skills', t('Skills'), route('hr.skills.index'), ['skill']);
    pushIfAllowed(organization, permissions, 'categories', t('Categories'), route('hr.categories.index'), ['category']);
    pushIfAllowed(organization, permissions, 'material-items', t('Material Items'), route('hr.material-items.index'), ['material']);
    pushIfAllowed(organization, permissions, 'resign-reasons', t('Resign Reasons'), route('hr.resign-reasons.index'), ['resign', 'exit']);

    const payrollSetup: NavItem[] = [];

    if (canAccessEntity(permissions, 'salary-components')) {
        payrollSetup.push({
            title: t('Salary Component Master'),
            href: route('hr.salary-components.index'),
            keywords: ['salary', 'component', 'payroll'],
        });
    }

    if (canAccessEntity(permissions, 'deduction-types')) {
        payrollSetup.push({
            title: t('Deduction Master'),
            href: route('hr.deduction-types.index'),
            keywords: ['deduction', 'payroll'],
        });
    }

    pushIfAllowed(payrollSetup, permissions, 'bank-masters', t('Bank Masters'), route('hr.bank-masters.index'), ['bank']);
    pushIfAllowed(payrollSetup, permissions, 'overtimes', t('Overtime'), route('hr.overtimes.index'), ['overtime', 'ot']);

    const general: NavItem[] = [];

    pushIfAllowed(general, permissions, 'document-types', t('Document Types'), route('hr.document-types.index'), ['document']);

    const masterChildren: NavItem[] = [];

    if (organization.length > 0) {
        masterChildren.push({
            title: t('Organization'),
            children: organization,
            description: t('Branches, departments, shifts, and related masters'),
        });
    }

    if (payrollSetup.length > 0) {
        masterChildren.push({
            title: t('Payroll Setup'),
            children: payrollSetup,
            description: t('Salary components, deductions, and payroll masters'),
        });
    }

    if (general.length > 0) {
        masterChildren.push({
            title: t('General'),
            children: general,
            description: t('Other company setup records'),
        });
    }

    if (masterChildren.length === 0) {
        return [];
    }

    return [
        {
            title: t('Company Setup'),
            icon: Layers,
            children: masterChildren,
            group: 'setup',
            keywords: ['masters', 'setup', 'branch', 'department'],
        },
    ];
};
