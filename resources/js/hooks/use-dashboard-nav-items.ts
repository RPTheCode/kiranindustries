import { useMemo } from 'react';
import { usePage } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { buildCompanyNavItems } from '@/config/navigation/company-nav';
import { buildSuperAdminNavItems } from '@/config/navigation/super-admin-nav';
import { hasPermission } from '@/utils/authorization';
import type { NavItem } from '@/types';

const ACTIVITY_LOG_USER_TYPES = ['company', 'admin', 'manager', 'staff'];

export function useDashboardNavItems(): NavItem[] {
    const { t, i18n } = useTranslation();
    const { auth } = usePage().props as {
        auth: { user?: { type?: string; role?: string }; permissions?: string[] };
    };

    const userRole = auth.user?.type || auth.user?.role;
    const permissions = auth?.permissions || [];
    const permissionsKey = useMemo(() => JSON.stringify(permissions), [permissions]);

    const canViewActivityLogs =
        ACTIVITY_LOG_USER_TYPES.includes(userRole || '') ||
        hasPermission(permissions, 'view-activity-logs');

    return useMemo(() => {
        if (userRole === 'superadmin') {
            return buildSuperAdminNavItems(t);
        }

        return buildCompanyNavItems({
            permissions,
            t,
            canViewActivityLogs,
            userRole,
        });
    }, [userRole, permissionsKey, i18n.language, canViewActivityLogs, permissions, t]);
}
