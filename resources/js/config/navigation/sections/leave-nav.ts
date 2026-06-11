import { CalendarOff } from 'lucide-react';
import type { NavSectionBuilder } from '@/config/navigation/types';
import type { NavItem } from '@/types';
import { canViewPermission, hasPermission } from '@/utils/authorization';

export const buildLeaveNav: NavSectionBuilder = ({ permissions, t }) => {
    const children: NavItem[] = [];

    if (
        canViewPermission(permissions, 'leave-applications')
        || hasPermission(permissions, 'manage-own-leave-applications')
    ) {
        children.push({
            title: t('Leave Applications'),
            href: route('hr.leave-applications.index'),
            keywords: ['leave', 'application', 'request'],
        });
    }

    if (
        canViewPermission(permissions, 'leave-balances')
        || hasPermission(permissions, 'manage-own-leave-balances')
    ) {
        children.push({
            title: t('Leave Balances'),
            href: route('hr.leave-balances.index'),
            keywords: ['leave', 'balance'],
        });
    }

    if (canViewPermission(permissions, 'leave-types')) {
        children.push({
            title: t('Leave Types'),
            href: route('hr.leave-types.index'),
            keywords: ['leave type'],
        });
    }

    if (canViewPermission(permissions, 'leave-policies')) {
        children.push({
            title: t('Leave Policies'),
            href: route('hr.leave-policies.index'),
            keywords: ['leave policy'],
        });
    }

    if (children.length === 0) {
        return [];
    }

    return [
        {
            title: t('Leave Management'),
            icon: CalendarOff,
            children,
            group: 'leave',
            keywords: ['leave', 'holiday'],
        },
    ];
};
