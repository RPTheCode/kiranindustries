import type { NavItem } from '@/types';
import { reorderNavForRole } from '@/lib/nav-utils';
import type { NavBuildContext } from '@/config/navigation/types';
import { buildOverviewNav } from '@/config/navigation/sections/overview-nav';
import { buildMastersNav } from '@/config/navigation/sections/masters-nav';
import { buildWorkforceNav } from '@/config/navigation/sections/workforce-nav';
import { buildAttendanceNav } from '@/config/navigation/sections/attendance-nav';
import { buildSalaryPayrollNav } from '@/config/navigation/sections/salary-payroll-nav';
import { buildLeaveNav } from '@/config/navigation/sections/leave-nav';
import { buildReportsNav } from '@/config/navigation/sections/reports-nav';
import { buildAdminNav } from '@/config/navigation/sections/admin-nav';

const SECTION_BUILDERS = [
    buildOverviewNav,
    buildMastersNav,
    buildWorkforceNav,
    buildAttendanceNav,
    buildSalaryPayrollNav,
    buildLeaveNav,
    buildReportsNav,
    buildAdminNav,
];

export function buildCompanyNavItems(ctx: NavBuildContext): NavItem[] {
    const items = SECTION_BUILDERS.flatMap((build) => build(ctx));

    return reorderNavForRole(items, ctx.userRole);
}
