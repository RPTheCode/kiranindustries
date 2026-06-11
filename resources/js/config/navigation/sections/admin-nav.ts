import { Images, ScrollText, Settings2, Shield } from 'lucide-react';
import type { NavSectionBuilder } from '@/config/navigation/types';
import type { NavItem } from '@/types';
import { canViewPermission } from '@/utils/authorization';

export const buildAdminNav: NavSectionBuilder = ({ permissions, t, canViewActivityLogs }) => {
    const items: NavItem[] = [];
    const staffChildren: NavItem[] = [];

    if (canViewPermission(permissions, 'roles')) {
        staffChildren.push({
            title: t('Roles'),
            href: route('roles.index'),
            keywords: ['role', 'security'],
        });
    }

    if (canViewPermission(permissions, 'permissions')) {
        staffChildren.push({
            title: t('Permissions'),
            href: route('permissions.index'),
            keywords: ['permission', 'access'],
        });
    }

    if (canViewPermission(permissions, 'users')) {
        staffChildren.push({
            title: t('Users'),
            href: route('users.index'),
            keywords: ['user', 'account'],
        });
    }

    if (staffChildren.length > 0) {
        items.push({
            title: t('Staff & Security'),
            icon: Shield,
            children: staffChildren,
            group: 'admin',
            keywords: ['staff', 'security', 'users', 'roles'],
        });
    }

    if (canViewPermission(permissions, 'media')) {
        items.push({
            title: t('Media Library'),
            href: route('media-library'),
            icon: Images,
            group: 'admin',
            keywords: ['media', 'files', 'images'],
        });
    }

    if (canViewActivityLogs) {
        items.push({
            title: t('Activity Logs'),
            href: route('hr.activity-logs.index'),
            icon: ScrollText,
            group: 'admin',
            keywords: ['activity', 'log', 'audit'],
        });
    }

    if (canViewPermission(permissions, 'settings')) {
        items.push({
            title: t('Settings'),
            href: route('settings'),
            icon: Settings2,
            group: 'admin',
            keywords: ['settings', 'configuration'],
        });
    }

    return items;
};
