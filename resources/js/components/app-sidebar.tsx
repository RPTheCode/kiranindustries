import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { useLayout } from '@/contexts/LayoutContext';
import { useSidebarSettings } from '@/contexts/SidebarContext';
import { useBrand } from '@/contexts/BrandContext';
import { type NavItem } from '@/types';
import { Link, usePage, router } from '@inertiajs/react';
import {
    Banknote,
    Building2,
    CalendarOff,
    CreditCard,
    DollarSign,
    FileBarChart2,
    Fingerprint,
    Gift,
    Images,
    Layers,
    LayoutDashboard,
    Palette,
    RefreshCw,
    ScrollText,
    Search,
    Settings2,
    X,
    Shield,
    Ticket,
    UsersRound,
} from 'lucide-react';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLogo from './app-logo';
import { useEffect, useMemo, useState, useRef } from 'react';
import { useTranslation } from 'react-i18next';
import { hasPermission, canAccessEntity, canAccessSalaryPayrollEmployee, canAccessSalaryPayrollIncrement, canAccessSalaryPayrollRuns, canAccessEarningDeductionEntry, canAccessPayrollSettings, canAccessSalaryAdvance, canAccessSalaryLoan } from '@/utils/authorization';
import { toast } from '@/components/custom-toast';
import { getImagePath } from '@/utils/helpers';
import { cn } from '@/lib/utils';



// Filter navigation items based on search query
const filterNavItems = (items: NavItem[], query: string): NavItem[] => {
    if (!query) return items;
    const lowerQuery = query.toLowerCase();

    return items.reduce((acc: NavItem[], item) => {
        const matchesTitle = item.title.toLowerCase().includes(lowerQuery);

        if (matchesTitle) {
            // If parent matches, keep it and all children
            acc.push({ ...item, defaultOpen: true });
            return acc;
        }

        // If item doesn't match, check children
        if (item.children) {
            const filteredChildren = filterNavItems(item.children, query);
            if (filteredChildren.length > 0) {
                acc.push({
                    ...item,
                    children: filteredChildren,
                    defaultOpen: true
                });
            }
        }

        return acc;
    }, []);
};

const ACTIVITY_LOG_USER_TYPES = ['company', 'admin', 'manager', 'staff'];

export function AppSidebar() {
    const { t, i18n } = useTranslation();
    const { auth, globalSettings } = usePage().props as any;
    const userRole = auth.user?.type || auth.user?.role;
    const permissions = auth?.permissions || [];
    const isSaas = globalSettings?.is_saas;

    const canViewActivityLogs =
        ACTIVITY_LOG_USER_TYPES.includes(userRole) ||
        hasPermission(permissions, 'view-activity-logs');

    // Get current direction
    const isRtl = document.documentElement.dir === 'rtl';

    // Business switch handler removed

    const getSuperAdminNavItems = (): NavItem[] => [
        {
            title: t('Dashboard'),
            href: route('dashboard'),
            icon: LayoutDashboard,
        },

        {
            title: t('Companies'),
            href: route('companies.index'),
            icon: Building2,
        },
        {
            title: t('Media Library'),
            href: route('media-library'),
            icon: Images,
        },


        {
            title: t('Plans'),
            icon: CreditCard,
            children: [
                {
                    title: t('Plan'),
                    href: route('plans.index')
                },
                {
                    title: t('Plan Request'),
                    href: route('plan-requests.index')
                },
                {
                    title: t('Plan Orders'),
                    href: route('plan-orders.index')
                }
            ]
        },
        {
            title: t('Coupons'),
            href: route('coupons.index'),
            icon: Ticket,
        },

        {
            title: t('Currencies'),
            href: route('currencies.index'),
            icon: DollarSign,
        },
        {
            title: t('Referral Program'),
            href: route('referral.index'),
            icon: Gift,
        },
        {
            title: t('Landing Page'),
            icon: Palette,
            children: [
                {
                    title: t('Landing Page'),
                    href: route('landing-page')
                },
                {
                    title: t('Custom Pages'),
                    href: route('landing-page.custom-pages.index')
                }
            ]
        },
        // {
        //     title: t('Email Templates'),
        //     href: route('email-templates.index'),
        //     icon: Mail,
        // },
        {
            title: t('Settings'),
            href: route('settings'),
            icon: Settings2,
        }
    ];

    const getCompanyNavItems = (): NavItem[] => {
        const items: NavItem[] = [];
        // Dashboard - only show if user has dashboard permission
        if ((hasPermission(permissions, 'manage-dashboard') || hasPermission(permissions, 'view-dashboard'))) {
            items.push({
                title: t('Dashboard'),
                href: route('dashboard'),
                icon: LayoutDashboard,
                group: 'overview',
            });
        }

        // Masters Section
        const masterChildren = [];
        
        if ((hasPermission(permissions, 'manage-branches') || hasPermission(permissions, 'view-branches'))) {
            masterChildren.push({ title: t('Branches'), href: route('hr.branches.index') });
        }
        if ((hasPermission(permissions, 'manage-week-offs') || hasPermission(permissions, 'view-week-offs'))) {
            masterChildren.push({ title: t('Week Offs'), href: route('hr.week-offs.index') });
        }
        if ((hasPermission(permissions, 'manage-shifts') || hasPermission(permissions, 'view-shifts'))) {
            masterChildren.push({ title: t('Shifts'), href: route('hr.shifts.index') });
        }
        if ((hasPermission(permissions, 'manage-departments') || hasPermission(permissions, 'view-departments'))) {
            masterChildren.push({ title: t('Departments'), href: route('hr.departments.index') });
        }
        if ((hasPermission(permissions, 'manage-designations') || hasPermission(permissions, 'view-designations'))) {
            masterChildren.push({ title: t('Designations'), href: route('hr.designations.index') });
        }
        if ((hasPermission(permissions, 'manage-skills') || hasPermission(permissions, 'view-skills'))) {
            masterChildren.push({ title: t('Skills'), href: route('hr.skills.index') });
        }
        if ((hasPermission(permissions, 'manage-sections') || hasPermission(permissions, 'view-sections'))) {
            masterChildren.push({ title: t('Sections'), href: route('hr.sections.index') });
        }
        if ((hasPermission(permissions, 'manage-categories') || hasPermission(permissions, 'view-categories'))) {
            masterChildren.push({ title: t('Categories'), href: route('hr.categories.index') });
        }
        if ((hasPermission(permissions, 'manage-bank-masters') || hasPermission(permissions, 'view-bank-masters'))) {
            masterChildren.push({ title: t('Bank Masters'), href: route('hr.bank-masters.index') });
        }
        if ((hasPermission(permissions, 'manage-resign-reasons') || hasPermission(permissions, 'view-resign-reasons'))) {
            masterChildren.push({ title: t('Resign Reasons'), href: route('hr.resign-reasons.index') });
        }
        if ((hasPermission(permissions, 'manage-overtimes') || hasPermission(permissions, 'view-overtimes'))) {
            masterChildren.push({ title: t('Overtime'), href: route('hr.overtimes.index') });
        }
        if ((hasPermission(permissions, 'manage-material-items') || hasPermission(permissions, 'view-material-items'))) {
            masterChildren.push({ title: t('Material Items'), href: route('hr.material-items.index') });
        }
        if (canAccessEntity(permissions, 'deduction-types')) {
            masterChildren.push({ title: t('Deduction Master'), href: route('hr.deduction-types.index') });
        }

        if ((hasPermission(permissions, 'manage-document-types') || hasPermission(permissions, 'view-document-types'))) {
            masterChildren.push({
                title: t('Document Types'),
                href: route('hr.document-types.index')
            });
        }

        if (canAccessEntity(permissions, 'salary-components')) {
            masterChildren.push({
                title: t('Salary Component Master'),
                href: route('hr.salary-components.index')
            });
        }

        if (masterChildren.length > 0) {
            items.push({
                title: t('Masters'),
                icon: Layers,
                children: masterChildren,
                group: 'setup',
            });
        }

        // Employee Section
        const canManageAnyEmployees = hasPermission(permissions, 'manage-employees')
            || hasPermission(permissions, 'manage-any-employees');

        if (canManageAnyEmployees) {
            items.push({
                title: t('Employees'),
                href: route('hr.employees.index'),
                icon: UsersRound,
                group: 'workforce',
            });
        } else if (hasPermission(permissions, 'manage-own-employees')) {
            items.push({
                title: t('My Profile'),
                href: route('hr.employees.my-profile'),
                icon: UsersRound,
                group: 'workforce',
            });
        }

        // Attendance Section
        const attendanceItems = [];
        
        if ((hasPermission(permissions, 'manage-attendance-records') || hasPermission(permissions, 'view-attendance-records')) || (hasPermission(permissions, 'manage-attendance') || hasPermission(permissions, 'view-attendance'))) {
            attendanceItems.push({
                title: t('Attendance'),
                href: route('hr.attendance.module')
            });
        }
        
        const canManageMispunch = hasPermission(permissions, 'manage-attendance-regularizations')
            || hasPermission(permissions, 'manage-any-attendance-regularizations')
            || hasPermission(permissions, 'manage-attendance-records')
            || hasPermission(permissions, 'manage-any-attendance-records');

        if (canManageMispunch) {
            attendanceItems.push({
                title: t('MisPunch'),
                href: route('hr.attendance.sync', { status: 'MIS' }),
            });
        }
        
        if ((hasPermission(permissions, 'manage-production-entry') || hasPermission(permissions, 'view-production-entry'))) {
            attendanceItems.push({
                title: t('Production Entry'),
                href: route('hr.daily-production-attendance-entry.index')
            });
        }

        if (attendanceItems.length > 0) {
            items.push({
                title: t('Attendance & Bio-Sync'),
                icon: Fingerprint,
                children: attendanceItems,
                group: 'attendance',
            });
        }

        if (hasPermission(permissions, 'sync-essl-log')) {
            items.push({
                title: t('Essl sync'),
                href: route('hr.essl-sync.index'),
                icon: RefreshCw,
                group: 'attendance',
            });
        }

        // Salary payroll module
        const salaryPayrollChildren = [];
        if (canAccessSalaryPayrollEmployee(permissions)) {
            salaryPayrollChildren.push({
                title: t('Employee Salary'),
                href: route('hr.salary-payroll.employee-salary.index'),
            });
        }
        if (canAccessSalaryPayrollIncrement(permissions)) {
            salaryPayrollChildren.push({
                title: t('Bulk Salary Increment'),
                href: route('hr.salary-payroll.salary-increment.index'),
            });
        }
        if (canAccessSalaryPayrollRuns(permissions)) {
            salaryPayrollChildren.push({
                title: t('Generate Payroll'),
                href: route('hr.salary-payroll.generate.index'),
            });
        }
        if (canAccessEarningDeductionEntry(permissions)) {
            salaryPayrollChildren.push({
                title: t('Earning / Deduction'),
                href: route('hr.earning-deduction.index'),
            });
        }
        if (canAccessSalaryAdvance(permissions)) {
            salaryPayrollChildren.push({
                title: t('Salary Advance'),
                href: route('hr.salary-advances.index'),
            });
        }
        if (canAccessSalaryLoan(permissions)) {
            salaryPayrollChildren.push({
                title: t('Salary Loan'),
                href: route('hr.salary-loans.index'),
            });
        }
        if (canAccessPayrollSettings(permissions)) {
            salaryPayrollChildren.push({
                title: t('Payroll Settings'),
                href: route('hr.payroll-settings.index'),
            });
        }
        if (salaryPayrollChildren.length > 0) {
            items.push({
                title: t('Salary Payroll'),
                icon: Banknote,
                children: salaryPayrollChildren,
                group: 'salary-payroll',
            });
        }

        // Reports Section
        const reportChildren = [];
        
        if (hasPermission(permissions, 'view-attendance-reports')) {
            reportChildren.push({
                title: t('Attendance Reports'),
                href: route('hr.reports.daily', { type: 'daily' })
            });
        }
        
        if (hasPermission(permissions, 'view-monthly-reports')) {
            reportChildren.push({
                title: t('Monthly Reports'),
                href: route('hr.reports.monthly', { type: 'monthly' })
            });
        }
        
        if (hasPermission(permissions, 'view-master-reports')) {
            reportChildren.push({
                title: t('Master Reports'),
                href: route('hr.reports.master', { type: 'master' })
            });
        }

        if (reportChildren.length > 0) {
            items.push({
                title: t('Reports'),
                icon: FileBarChart2,
                children: reportChildren,
                group: 'reports',
                routePattern: 'hr.reports.*',
            });
        }

        // Leave Management as separate menu
        const leaveChildren = [];

        if ((hasPermission(permissions, 'manage-leave-types') || hasPermission(permissions, 'view-leave-types'))) {
            leaveChildren.push({
                title: t('Leave Types'),
                href: route('hr.leave-types.index')
            });
        }

        if ((hasPermission(permissions, 'manage-leave-policies') || hasPermission(permissions, 'view-leave-policies'))) {
            leaveChildren.push({
                title: t('Leave Policies'),
                href: route('hr.leave-policies.index')
            });
        }

        if ((hasPermission(permissions, 'manage-leave-applications') || hasPermission(permissions, 'view-leave-applications') || hasPermission(permissions, 'manage-own-leave-applications'))) {
            leaveChildren.push({
                title: t('Leave Applications'),
                href: route('hr.leave-applications.index')
            });
        }

        if ((hasPermission(permissions, 'manage-leave-balances') || hasPermission(permissions, 'view-leave-balances') || hasPermission(permissions, 'manage-own-leave-balances'))) {
            leaveChildren.push({
                title: t('Leave Balances'),
                href: route('hr.leave-balances.index')
            });
        }

        if (leaveChildren.length > 0) {
            items.push({
                title: t('Leave Management'),
                icon: CalendarOff,
                children: leaveChildren,
                group: 'leave',
            });
        }



        // Other menu items with permission checks

        // HR Module
        const hrChildren = [];
        if ((hasPermission(permissions, 'manage-document-types') || hasPermission(permissions, 'view-document-types'))) {
            hrChildren.push({
                title: t('Document Types'),
                href: route('hr.document-types.index')
            });
        }


        if ((hasPermission(permissions, 'manage-award-types') || hasPermission(permissions, 'view-award-types'))) {
            hrChildren.push({
                title: t('Award Types'),
                href: route('hr.award-types.index')
            });
        }

        if ((hasPermission(permissions, 'manage-awards') || hasPermission(permissions, 'view-awards'))) {
            hrChildren.push({
                title: t('Awards'),
                href: route('hr.awards.index')
            });
        }

        if ((hasPermission(permissions, 'manage-promotions') || hasPermission(permissions, 'view-promotions'))) {
            hrChildren.push({
                title: t('Promotions'),
                href: route('hr.promotions.index')
            });
        }


        // Performance Module
        const performanceChildren = [];

        if ((hasPermission(permissions, 'manage-performance-indicator-categories') || hasPermission(permissions, 'view-performance-indicator-categories'))) {
            performanceChildren.push({
                title: t('Indicator Categories'),
                href: route('hr.performance.indicator-categories.index')
            });
        }

        if ((hasPermission(permissions, 'manage-performance-indicators') || hasPermission(permissions, 'view-performance-indicators'))) {
            performanceChildren.push({
                title: t('Indicators'),
                href: route('hr.performance.indicators.index')
            });
        }

        if ((hasPermission(permissions, 'manage-goal-types') || hasPermission(permissions, 'view-goal-types'))) {
            performanceChildren.push({
                title: t('Goal Types'),
                href: route('hr.performance.goal-types.index')
            });
        }

        if ((hasPermission(permissions, 'manage-employee-goals') || hasPermission(permissions, 'view-employee-goals'))) {
            performanceChildren.push({
                title: t('Employee Goals'),
                href: route('hr.performance.employee-goals.index')
            });
        }

        if ((hasPermission(permissions, 'manage-review-cycles') || hasPermission(permissions, 'view-review-cycles'))) {
            performanceChildren.push({
                title: t('Review Cycles'),
                href: route('hr.performance.review-cycles.index')
            });
        }

        if ((hasPermission(permissions, 'manage-employee-reviews') || hasPermission(permissions, 'view-employee-reviews'))) {
            performanceChildren.push({
                title: t('Employee Reviews'),
                href: route('hr.performance.employee-reviews.index')
            });
        }

        if (performanceChildren.length > 0) {
            hrChildren.push({
                title: t('Performance'),
                children: performanceChildren
            });
        }

        if ((hasPermission(permissions, 'manage-resignations') || hasPermission(permissions, 'view-resignations'))) {
            hrChildren.push({
                title: t('Resignations'),
                href: route('hr.resignations.index')
            });
        }

        if ((hasPermission(permissions, 'manage-terminations') || hasPermission(permissions, 'view-terminations'))) {
            hrChildren.push({
                title: t('Terminations'),
                href: route('hr.terminations.index')
            });
        }

        if ((hasPermission(permissions, 'manage-warnings') || hasPermission(permissions, 'view-warnings'))) {
            hrChildren.push({
                title: t('Warnings'),
                href: route('hr.warnings.index')
            });
        }

        if ((hasPermission(permissions, 'manage-trips') || hasPermission(permissions, 'view-trips'))) {
            hrChildren.push({
                title: t('Trips'),
                href: route('hr.trips.index')
            });
        }

        if ((hasPermission(permissions, 'manage-complaints') || hasPermission(permissions, 'view-complaints'))) {
            hrChildren.push({
                title: t('Complaints'),
                href: route('hr.complaints.index')
            });
        }

        /*
        if ((hasPermission(permissions, 'manage-employee-transfers') || hasPermission(permissions, 'view-employee-transfers'))) {
            hrChildren.push({
                title: t('Transfers'),
                href: route('hr.transfers.index')
            });
        }
        */

        if ((hasPermission(permissions, 'manage-holidays') || hasPermission(permissions, 'view-holidays'))) {
            hrChildren.push({
                title: t('Holidays'),
                href: route('hr.holidays.index')
            });
        }

        if ((hasPermission(permissions, 'manage-announcements') || hasPermission(permissions, 'view-announcements'))) {
            hrChildren.push({
                title: t('Announcements'),
                href: route('hr.announcements.index')
            });
        }

        // Asset Management submenu
        const assetChildren = [];

        if ((hasPermission(permissions, 'manage-asset-types') || hasPermission(permissions, 'view-asset-types'))) {
            assetChildren.push({
                title: t('Asset Types'),
                href: route('hr.asset-types.index')
            });
        }

        if ((hasPermission(permissions, 'manage-assets') || hasPermission(permissions, 'view-assets'))) {
            assetChildren.push({
                title: t('Assets'),
                href: route('hr.assets.index')
            });
        }

        if ((hasPermission(permissions, 'manage-assets') || hasPermission(permissions, 'view-assets'))) {
            assetChildren.push({
                title: t('Dashboard'),
                href: route('hr.assets.dashboard')
            });
        }

        if ((hasPermission(permissions, 'manage-assets') || hasPermission(permissions, 'view-assets'))) {
            assetChildren.push({
                title: t('Depreciation'),
                href: route('hr.assets.depreciation-report')
            });
        }

        if (assetChildren.length > 0) {
            hrChildren.push({
                title: t('Asset Management'),
                children: assetChildren
            });
        }

        // Training Management submenu
        const trainingChildren = [];

        if ((hasPermission(permissions, 'manage-training-types') || hasPermission(permissions, 'view-training-types'))) {
            trainingChildren.push({
                title: t('Training Types'),
                href: route('hr.training-types.index')
            });
        }

        if ((hasPermission(permissions, 'manage-training-programs') || hasPermission(permissions, 'view-training-programs'))) {
            trainingChildren.push({
                title: t('Training Programs'),
                href: route('hr.training-programs.index')
            });
        }

        if ((hasPermission(permissions, 'manage-training-sessions') || hasPermission(permissions, 'view-training-sessions'))) {
            trainingChildren.push({
                title: t('Training Sessions'),
                href: route('hr.training-sessions.index')
            });
        }

        if ((hasPermission(permissions, 'manage-employee-trainings') || hasPermission(permissions, 'view-employee-trainings'))) {
            trainingChildren.push({
                title: t('Employee Trainings'),
                href: route('hr.employee-trainings.index')
            });
        }

        // end
        if (trainingChildren.length > 0) {
            hrChildren.push({
                title: t('Training Management'),
                children: trainingChildren
            });
        }

        // if (hrChildren.length > 0) {
        //     items.push({
        //         title: t('HR Management'),
        //         icon: Briefcase,
        //         children: hrChildren
        //     });
        // }


        // Staff section - only show if user has any staff-related permissions
        const staffChildren = [];
        if ((hasPermission(permissions, 'manage-roles') || hasPermission(permissions, 'view-roles'))) {
            staffChildren.push({
                title: t('Roles'),
                href: route('roles.index')
            });
        }
        if ((hasPermission(permissions, 'manage-permissions') || hasPermission(permissions, 'view-permissions'))) {
            staffChildren.push({
                title: t('Permissions'),
                href: route('permissions.index')
            });
        }
        if ((hasPermission(permissions, 'manage-users') || hasPermission(permissions, 'view-users'))) {
            staffChildren.push({
                title: t('Users'),
                href: route('users.index')
            });
        }

        if (staffChildren.length > 0) {
            items.push({
                title: t('Staff & Security'),
                icon: Shield,
                children: staffChildren,
                group: 'admin',
            });
        }

        if ((hasPermission(permissions, 'manage-media') || hasPermission(permissions, 'view-media'))) {
            items.push({
                title: t('Media Library'),
                href: route('media-library'),
                icon: Images,
                group: 'admin',
            });
        }

        // Recruitment Management as separate menu
        const recruitmentChildren = [];

        if ((hasPermission(permissions, 'manage-job-categories') || hasPermission(permissions, 'view-job-categories'))) {
            recruitmentChildren.push({
                title: t('Job Categories'),
                href: route('hr.recruitment.job-categories.index')
            });
        }

        if ((hasPermission(permissions, 'manage-job-requisitions') || hasPermission(permissions, 'view-job-requisitions'))) {
            recruitmentChildren.push({
                title: t('Job Requisitions'),
                href: route('hr.recruitment.job-requisitions.index')
            });
        }

        if ((hasPermission(permissions, 'manage-job-types') || hasPermission(permissions, 'view-job-types'))) {
            recruitmentChildren.push({
                title: t('Job Types'),
                href: route('hr.recruitment.job-types.index')
            });
        }

        if ((hasPermission(permissions, 'manage-job-locations') || hasPermission(permissions, 'view-job-locations'))) {
            recruitmentChildren.push({
                title: t('Job Locations'),
                href: route('hr.recruitment.job-locations.index')
            });
        }

        if ((hasPermission(permissions, 'manage-job-postings') || hasPermission(permissions, 'view-job-postings'))) {
            recruitmentChildren.push({
                title: t('Job Postings'),
                href: route('hr.recruitment.job-postings.index')
            });
        }

        if ((hasPermission(permissions, 'manage-candidate-sources') || hasPermission(permissions, 'view-candidate-sources'))) {
            recruitmentChildren.push({
                title: t('Candidate Sources'),
                href: route('hr.recruitment.candidate-sources.index')
            });
        }

        if ((hasPermission(permissions, 'manage-candidates') || hasPermission(permissions, 'view-candidates'))) {
            recruitmentChildren.push({
                title: t('Candidates'),
                href: route('hr.recruitment.candidates.index')
            });
        }

        if ((hasPermission(permissions, 'manage-interview-types') || hasPermission(permissions, 'view-interview-types'))) {
            recruitmentChildren.push({
                title: t('Interview Types'),
                href: route('hr.recruitment.interview-types.index')
            });
        }

        if ((hasPermission(permissions, 'manage-interview-rounds') || hasPermission(permissions, 'view-interview-rounds'))) {
            recruitmentChildren.push({
                title: t('Interview Rounds'),
                href: route('hr.recruitment.interview-rounds.index')
            });
        }

        if ((hasPermission(permissions, 'manage-interviews') || hasPermission(permissions, 'view-interviews'))) {
            recruitmentChildren.push({
                title: t('Interviews'),
                href: route('hr.recruitment.interviews.index')
            });
        }

        if ((hasPermission(permissions, 'manage-interview-feedback') || hasPermission(permissions, 'view-interview-feedback'))) {
            recruitmentChildren.push({
                title: t('Interview Feedback'),
                href: route('hr.recruitment.interview-feedback.index')
            });
        }

        if ((hasPermission(permissions, 'manage-candidate-assessments') || hasPermission(permissions, 'view-candidate-assessments'))) {
            recruitmentChildren.push({
                title: t('Candidate Assessments'),
                href: route('hr.recruitment.candidate-assessments.index')
            });
        }

        if ((hasPermission(permissions, 'manage-offer-templates') || hasPermission(permissions, 'view-offer-templates'))) {
            recruitmentChildren.push({
                title: t('Offer Templates'),
                href: route('hr.recruitment.offer-templates.index')
            });
        }

        if ((hasPermission(permissions, 'manage-offers') || hasPermission(permissions, 'view-offers'))) {
            recruitmentChildren.push({
                title: t('Offers'),
                href: route('hr.recruitment.offers.index')
            });
        }

        if ((hasPermission(permissions, 'manage-onboarding-checklists') || hasPermission(permissions, 'view-onboarding-checklists'))) {
            recruitmentChildren.push({
                title: t('Onboarding Checklists'),
                href: route('hr.recruitment.onboarding-checklists.index')
            });
        }

        if ((hasPermission(permissions, 'manage-checklist-items') || hasPermission(permissions, 'view-checklist-items'))) {
            recruitmentChildren.push({
                title: t('Checklist Items'),
                href: route('hr.recruitment.checklist-items.index')
            });
        }

        if ((hasPermission(permissions, 'manage-candidate-onboarding') || hasPermission(permissions, 'view-candidate-onboarding'))) {
            recruitmentChildren.push({
                title: t('Candidate Onboarding'),
                href: route('hr.recruitment.candidate-onboarding.index')
            });
        }

        /*
        if (recruitmentChildren.length > 0) {
            items.push({
                title: t('Recruitment'),
                icon: Users,
                children: recruitmentChildren
            });
        }
        */

        // Contract Management as separate menu
        const contractChildren = [];

        if ((hasPermission(permissions, 'manage-contract-types') || hasPermission(permissions, 'view-contract-types'))) {
            contractChildren.push({
                title: t('Contract Types'),
                href: route('hr.contracts.contract-types.index')
            });
        }

        if ((hasPermission(permissions, 'manage-employee-contracts') || hasPermission(permissions, 'view-employee-contracts'))) {
            contractChildren.push({
                title: t('Employee Contracts'),
                href: route('hr.contracts.employee-contracts.index')
            });
        }



        if ((hasPermission(permissions, 'manage-contract-renewals') || hasPermission(permissions, 'view-contract-renewals'))) {
            contractChildren.push({
                title: t('Contract Renewals'),
                href: route('hr.contracts.contract-renewals.index')
            });
        }

        if ((hasPermission(permissions, 'manage-contract-templates') || hasPermission(permissions, 'view-contract-templates'))) {
            contractChildren.push({
                title: t('Contract Templates'),
                href: route('hr.contracts.contract-templates.index')
            });
        }

        /*
        if (contractChildren.length > 0) {
            items.push({
                title: t('Contract Management'),
                icon: FileText,
                children: contractChildren
            });
        }
        */

        // Document Management as separate menu
        const documentChildren = [];

        if ((hasPermission(permissions, 'manage-document-categories') || hasPermission(permissions, 'view-document-categories'))) {
            documentChildren.push({
                title: t('Document Categories'),
                href: route('hr.documents.document-categories.index')
            });
        }

        if ((hasPermission(permissions, 'manage-hr-documents') || hasPermission(permissions, 'view-hr-documents'))) {
            documentChildren.push({
                title: t('HR Documents'),
                href: route('hr.documents.hr-documents.index')
            });
        }



        if ((hasPermission(permissions, 'manage-document-acknowledgments') || hasPermission(permissions, 'view-document-acknowledgments'))) {
            documentChildren.push({
                title: t('Acknowledgments'),
                href: route('hr.documents.document-acknowledgments.index')
            });
        }

        if ((hasPermission(permissions, 'manage-document-templates') || hasPermission(permissions, 'view-document-templates'))) {
            documentChildren.push({
                title: t('Document Templates'),
                href: route('hr.documents.document-templates.index')
            });
        }

        // if (documentChildren.length > 0) {
        //     items.push({
        //         title: t('Document Management'),
        //         icon: Folder,
        //         children: documentChildren
        //     });
        // }



        // Meeting Management submenu
        const meetingChildren = [];

        if ((hasPermission(permissions, 'manage-meeting-types') || hasPermission(permissions, 'view-meeting-types'))) {
            meetingChildren.push({
                title: t('Meeting Types'),
                href: route('meetings.meeting-types.index')
            });
        }

        if ((hasPermission(permissions, 'manage-meeting-rooms') || hasPermission(permissions, 'view-meeting-rooms'))) {
            meetingChildren.push({
                title: t('Meeting Rooms'),
                href: route('meetings.meeting-rooms.index')
            });
        }

        if ((hasPermission(permissions, 'manage-meetings') || hasPermission(permissions, 'view-meetings'))) {
            meetingChildren.push({
                title: t('Meetings'),
                href: route('meetings.meetings.index')
            });
        }

        if ((hasPermission(permissions, 'manage-meeting-attendees') || hasPermission(permissions, 'view-meeting-attendees'))) {
            meetingChildren.push({
                title: t('Meeting Attendees'),
                href: route('meetings.meeting-attendees.index')
            });
        }

        if ((hasPermission(permissions, 'manage-meeting-minutes') || hasPermission(permissions, 'view-meeting-minutes'))) {
            meetingChildren.push({
                title: t('Meeting Minutes'),
                href: route('meetings.meeting-minutes.index')
            });
        }

        if ((hasPermission(permissions, 'manage-action-items') || hasPermission(permissions, 'view-action-items'))) {
            meetingChildren.push({
                title: t('Action Items'),
                href: route('meetings.action-items.index')
            });
        }
        /*
        if (meetingChildren.length > 0) {
            items.push({
                title: t('Meetings'),
                icon: Calendar,
                children: meetingChildren
            });
        }
        */
        // if (hasPermission(permissions, 'view-calendar') || (hasPermission(permissions, 'manage-calendar') || hasPermission(permissions, 'view-calendar'))) {
        //     items.push({
        //         title: t('Calendar'),
        //         href: route('calendar.index'),
        //         icon: Calendar,
        //     });
        // }



        // Duplicate attendance block removed to fix build error

        // Time Tracking as separate menu
        const timeTrackingChildren = [];

        if ((hasPermission(permissions, 'manage-time-entries') || hasPermission(permissions, 'view-time-entries'))) {
            timeTrackingChildren.push({
                title: t('Time Entries'),
                href: route('hr.time-entries.index')
            });
        }

        // if (timeTrackingChildren.length > 0) {
        //     items.push({
        //         title: t('Time Tracking'),
        //         icon: Timer,
        //         children: timeTrackingChildren
        //     });
        // }



        /*
        // Reports Section
        items.push({
            title: t('Reports'),
            href: route('hr.reports.index'),
            icon: BarChart,
        });
        */

        // Plans section
        const planChildren = [];
        if ((hasPermission(permissions, 'manage-plans') || hasPermission(permissions, 'view-plans'))) {
            planChildren.push({
                title: t('Plans'),
                href: route('plans.index')
            });
        }

        if (hasPermission(permissions, 'view-plan-requests')) {
            planChildren.push({
                title: t('Plan Requests'),
                href: route('plan-requests.index')
            });
        }

        if (hasPermission(permissions, 'view-plan-orders')) {
            planChildren.push({
                title: t('Plan Orders'),
                href: route('plan-orders.index')
            });
        }

        // if (planChildren.length > 0) {
        //     items.push({
        //         title: t('Plans'),
        //         icon: CreditCard,
        //         children: planChildren
        //     });
        // }

        // if ((hasPermission(permissions, 'manage-referral') || hasPermission(permissions, 'view-referral'))) {
        //     items.push({
        //         title: t('Referral Program'),
        //         href: route('referral.index'),
        //         icon: Gift,
        //     });
        // }

        // Currencies - only show in non-SaaS mode for company users
        // if (!isSaas && (hasPermission(permissions, 'manage-currencies') || hasPermission(permissions, 'view-currencies'))) {
        //     items.push({
        //         title: t('Currencies'),
        //         href: route('currencies.index'),
        //         icon: Coins,
        //     });
        // }


        // Landing Page - only show in non-SaaS mode for company users
        // if (!isSaas && (hasPermission(permissions, 'manage-landing-page') || hasPermission(permissions, 'view-landing-page'))) {
        //     items.push({
        //         title: t('Landing Page'),
        //         icon: Palette,
        //         children: [
        //             {
        //                 title: t('Landing Page'),
        //                 href: route('landing-page')
        //             },
        //             {
        //                 title: t('Custom Pages'),
        //                 href: route('landing-page.custom-pages.index')
        //             }
        //         ]
        if (canViewActivityLogs) {
            items.push({
                title: t('Activity Logs'),
                href: route('hr.activity-logs.index'),
                icon: ScrollText,
                group: 'admin',
            });
        }

        if ((hasPermission(permissions, 'manage-settings') || hasPermission(permissions, 'view-settings'))) {
            items.push({
                title: t('Settings'),
                href: route('settings'),
                icon: Settings2,
                group: 'admin',
            });
        }

        return items;
    };

    const permissionsKey = useMemo(() => JSON.stringify(permissions), [permissions]);

    const mainNavItems = useMemo(
        () => (userRole === 'superadmin' ? getSuperAdminNavItems() : getCompanyNavItems()),
        [userRole, permissionsKey, i18n.language, isSaas, canViewActivityLogs]
    );

    const { position, effectivePosition } = useLayout();
    const { variant, collapsible, style } = useSidebarSettings();
    const { logoLight, logoDark, favicon, titleText, updateBrandSettings } = useBrand();
    const [sidebarStyle, setSidebarStyle] = useState({});
    const [searchQuery, setSearchQuery] = useState('');

    useEffect(() => {

        // Apply styles based on sidebar style
        if (style === 'colored') {
            setSidebarStyle({ backgroundColor: 'var(--primary)', color: 'white' });
        } else if (style === 'gradient') {
            setSidebarStyle({
                background: 'linear-gradient(to bottom, var(--primary), color-mix(in srgb, var(--primary), transparent 20%))',
                color: 'white'
            });
        } else {
            setSidebarStyle({});
        }
    }, [style]);

    const filteredNavItems = useMemo(
        () => filterNavItems(mainNavItems, searchQuery),
        [mainNavItems, searchQuery]
    );

    // Get the first available menu item's href for logo link
    const getFirstAvailableHref = () => {
        if (filteredNavItems.length === 0) return route('dashboard');

        const firstItem = filteredNavItems[0];
        if (firstItem.href) {
            return firstItem.href;
        } else if (firstItem.children && firstItem.children.length > 0) {
            return firstItem.children[0].href || route('dashboard');
        }
        return route('dashboard');
    };

    return (
        <Sidebar
            side={effectivePosition}
            collapsible={collapsible}
            variant={variant}
            className={style !== 'plain' ? 'sidebar-custom-style' : ''}
        >
            <SidebarHeader
                className={cn(style !== 'plain' ? 'sidebar-styled' : '', 'border-b border-sidebar-border/60')}
                style={sidebarStyle}
            >
                <div className="flex flex-col gap-2 px-2.5 py-2.5 group-data-[collapsible=icon]:items-center group-data-[collapsible=icon]:px-2">
                    <Link
                        href={getFirstAvailableHref()}
                        className="group-data-[collapsible=icon]:hidden flex w-full items-center justify-center"
                    >
                        <div className="flex min-h-[56px] w-full items-center justify-center rounded-md border border-sidebar-border/50 bg-background/60 px-3 py-2.5 shadow-none">
                            {(() => {
                                const isDark = document.documentElement.classList.contains('dark');
                                const currentLogo = isDark ? logoLight : logoDark;
                                const displayUrl = getImagePath(currentLogo) ?? currentLogo;

                                return displayUrl ? (
                                    <img
                                        key={currentLogo}
                                        src={displayUrl}
                                        alt={titleText || 'Logo'}
                                        className="max-h-11 w-full max-w-full object-contain object-center"
                                        onError={() => updateBrandSettings({ [isDark ? 'logoLight' : 'logoDark']: '' })}
                                    />
                                ) : (
                                    <div className="truncate text-center text-sm font-semibold tracking-tight">
                                        {titleText || 'K'}
                                    </div>
                                );
                            })()}
                        </div>
                    </Link>

                    <div className="group-data-[collapsible=icon]:hidden relative w-full">
                        <Search className="pointer-events-none absolute left-2 top-2 h-3.5 w-3.5 text-muted-foreground" />
                        <Input
                            placeholder={t('Search menu...')}
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Escape') {
                                    setSearchQuery('');
                                }
                            }}
                            aria-label={t('Search menu...')}
                            className="h-8 w-full border-sidebar-border/50 bg-background/60 pl-7 pr-8 text-sm transition-colors focus:bg-background"
                        />
                        {searchQuery && (
                            <button
                                type="button"
                                onClick={() => setSearchQuery('')}
                                className="absolute right-1.5 top-1.5 flex h-5 w-5 items-center justify-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-600"
                                aria-label={t('Clear search')}
                            >
                                <X className="h-3.5 w-3.5" />
                            </button>
                        )}
                    </div>

                    <Link
                        href={getFirstAvailableHref()}
                        className="hidden h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-md border border-sidebar-border/50 bg-background/60 group-data-[collapsible=icon]:flex"
                    >
                        {(() => {
                            const displayFavicon = favicon ? getImagePath(favicon) : '';

                            return displayFavicon ? (
                                <img
                                    key={favicon}
                                    src={displayFavicon}
                                    alt={titleText || 'Icon'}
                                    className="h-7 w-7 object-contain"
                                    onError={() => updateBrandSettings({ favicon: '' })}
                                />
                            ) : (
                                <div className="flex h-9 w-9 items-center justify-center rounded-md bg-primary text-sm font-bold text-white shadow-sm">
                                    {(titleText || 'W').charAt(0).toUpperCase()}
                                </div>
                            );
                        })()}
                    </Link>
                </div>
            </SidebarHeader>

            <SidebarContent>
                <div style={sidebarStyle} className={`flex h-full min-h-0 flex-col ${style !== 'plain' ? 'sidebar-styled' : ''}`}>
                    <div className="min-h-0 flex-1 overflow-y-auto overflow-x-hidden sidebar-scroll px-0.5 pb-1.5">
                        <NavMain
                            items={filteredNavItems}
                            position={effectivePosition}
                            searchQuery={searchQuery}
                        />
                    </div>
                </div>
            </SidebarContent>

            <SidebarFooter>
                {/* <NavFooter items={footerNavItems} className="mt-auto" position={position} /> */}
                {/* Profile menu moved to header */}
            </SidebarFooter>
        </Sidebar>
    );
}