import { Briefcase, UsersRound } from 'lucide-react';
import type { NavSectionBuilder } from '@/config/navigation/types';
import type { NavItem } from '@/types';
import { canViewPermission, hasPermission } from '@/utils/authorization';

export const buildWorkforceNav: NavSectionBuilder = ({ permissions, t }) => {
    const items: NavItem[] = [];

    const canManageAnyEmployees =
        hasPermission(permissions, 'manage-employees')
        || hasPermission(permissions, 'manage-any-employees');

    if (canManageAnyEmployees) {
        items.push({
            title: t('Employees'),
            href: route('hr.employees.index'),
            icon: UsersRound,
            group: 'workforce',
            keywords: ['employee', 'staff', 'team'],
        });
    } else if (hasPermission(permissions, 'manage-own-employees')) {
        items.push({
            title: t('My Profile'),
            href: route('hr.employees.my-profile'),
            icon: UsersRound,
            group: 'workforce',
            keywords: ['profile', 'employee', 'my'],
        });
    }

    const recruitmentChildren: NavItem[] = [];

    if (
        canViewPermission(permissions, 'candidates')
        || canViewPermission(permissions, 'job-postings')
        || canViewPermission(permissions, 'job-requisitions')
    ) {
        recruitmentChildren.push({
            title: t('Overview'),
            href: route('hr.recruitment.hub'),
        });
    }

    if (canViewPermission(permissions, 'job-postings') || canViewPermission(permissions, 'job-requisitions')) {
        recruitmentChildren.push({
            title: t('Jobs'),
            href: route('hr.recruitment.jobs.index'),
            keywords: ['job', 'posting', 'requisition'],
        });
    }

    if (canViewPermission(permissions, 'candidates')) {
        recruitmentChildren.push({
            title: t('Candidates'),
            href: route('hr.recruitment.candidates.index'),
            keywords: ['candidate', 'applicant'],
        });
    }

    if (canViewPermission(permissions, 'interviews')) {
        recruitmentChildren.push({
            title: t('Interviews'),
            href: route('hr.recruitment.interviews.index'),
        });
    }

    if (canViewPermission(permissions, 'offers')) {
        recruitmentChildren.push({
            title: t('Offers'),
            href: route('hr.recruitment.offers.index'),
            keywords: ['offer', 'selection', 'hire'],
        });
    }

    if (
        canViewPermission(permissions, 'job-categories')
        || canViewPermission(permissions, 'job-types')
        || canViewPermission(permissions, 'job-locations')
        || canViewPermission(permissions, 'candidate-sources')
        || canViewPermission(permissions, 'interview-types')
    ) {
        recruitmentChildren.push({
            title: t('Settings'),
            href: route('hr.recruitment.settings.index'),
        });
    }

    if (recruitmentChildren.length > 0) {
        items.push({
            title: t('Recruitment'),
            icon: Briefcase,
            children: recruitmentChildren,
            group: 'workforce',
            keywords: ['recruitment', 'hiring', 'jobs'],
        });
    }

    return items;
};
