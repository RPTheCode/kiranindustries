import { Fingerprint, RefreshCw } from 'lucide-react';
import type { NavSectionBuilder } from '@/config/navigation/types';
import type { NavItem } from '@/types';
import { canViewPermission, hasPermission } from '@/utils/authorization';

export const buildAttendanceNav: NavSectionBuilder = ({ permissions, t }) => {
    const items: NavItem[] = [];
    const attendanceItems: NavItem[] = [];

    if (
        canViewPermission(permissions, 'attendance-records')
        || canViewPermission(permissions, 'attendance')
    ) {
        attendanceItems.push({
            title: t('Attendance'),
            href: route('hr.attendance.module'),
            keywords: ['attendance', 'punch', 'bio'],
        });
    }

    const canManageMispunch =
        hasPermission(permissions, 'manage-attendance-regularizations')
        || hasPermission(permissions, 'manage-any-attendance-regularizations')
        || hasPermission(permissions, 'manage-attendance-records')
        || hasPermission(permissions, 'manage-any-attendance-records');

    if (canManageMispunch) {
        attendanceItems.push({
            title: t('Missed Punch'),
            href: route('hr.attendance.sync', { status: 'MIS' }),
            keywords: ['mispunch', 'missed punch', 'correction'],
        });
    }

    if (canViewPermission(permissions, 'production-entry')) {
        attendanceItems.push({
            title: t('Production Entry'),
            href: route('hr.daily-production-attendance-entry.index'),
            keywords: ['production'],
        });
    }

    if (attendanceItems.length > 0) {
        items.push({
            title: t('Attendance'),
            icon: Fingerprint,
            children: attendanceItems,
            group: 'attendance',
            keywords: ['attendance', 'bio-sync', 'punch'],
        });
    }

    if (hasPermission(permissions, 'sync-essl-log')) {
        items.push({
            title: t('ESSL Device Sync'),
            href: route('hr.essl-sync.index'),
            icon: RefreshCw,
            group: 'attendance',
            keywords: ['essl', 'device', 'sync', 'biometric'],
        });
    }

    return items;
};
