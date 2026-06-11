import { LayoutDashboard } from 'lucide-react';
import type { NavSectionBuilder } from '@/config/navigation/types';
import { canViewPermission } from '@/utils/authorization';

export const buildOverviewNav: NavSectionBuilder = ({ permissions, t }) => {
    if (!canViewPermission(permissions, 'dashboard')) {
        return [];
    }

    return [
        {
            title: t('Dashboard'),
            href: route('dashboard'),
            icon: LayoutDashboard,
            group: 'overview',
        },
    ];
};
