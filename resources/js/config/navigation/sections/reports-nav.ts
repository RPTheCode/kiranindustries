import { FileBarChart2 } from 'lucide-react';
import type { NavSectionBuilder } from '@/config/navigation/types';
import type { NavItem } from '@/types';
import { hasPermission } from '@/utils/authorization';

export const buildReportsNav: NavSectionBuilder = ({ permissions, t }) => {
    const children: NavItem[] = [];

    if (hasPermission(permissions, 'view-attendance-reports')) {
        children.push({
            title: t('Attendance Reports'),
            href: route('hr.reports.daily', { type: 'daily' }),
            keywords: ['report', 'attendance', 'daily'],
        });
    }

    if (hasPermission(permissions, 'view-monthly-reports')) {
        children.push({
            title: t('Monthly Reports'),
            href: route('hr.reports.monthly', { type: 'monthly' }),
            keywords: ['report', 'monthly'],
        });
    }

    if (hasPermission(permissions, 'view-master-reports')) {
        children.push({
            title: t('Master Reports'),
            href: route('hr.reports.master', { type: 'master' }),
            keywords: ['report', 'master'],
        });
    }

    if (children.length === 0) {
        return [];
    }

    return [
        {
            title: t('Reports'),
            icon: FileBarChart2,
            children,
            group: 'reports',
            routePattern: 'hr.reports.*',
            keywords: ['report', 'analytics'],
        },
    ];
};
