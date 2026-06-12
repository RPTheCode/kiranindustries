<?php

namespace App\Services\Mobile;

use App\Models\User;

class MobileMenuBuilder
{
    /**
     * Build mobile menu mirroring desktop sidebar (app-sidebar.tsx) titles and groups.
     *
     * @return list<array{key: string, title: string, children?: list<array{key: string, title: string}>}>
     */
    public function build(User $user): array
    {
        $menu = [];

        if (userHasAnyPermissionForMobileMenu($user, ['manage-dashboard', 'view-dashboard'])
            || userHasPermissionForMobileMenu($user, 'access-mobile-app')) {
            $menu[] = $this->item('dashboard', 'Dashboard');
        }

        $masterChildren = $this->buildMasterChildren($user);
        if ($masterChildren !== []) {
            $menu[] = $this->item('masters', 'Masters', $masterChildren);
        }

        if (userHasAnyPermissionForMobileMenu($user, ['manage-employees', 'manage-any-employees', 'view-employees'])) {
            $menu[] = $this->item('employees', 'Employees');
        } elseif (userHasPermissionForMobileMenu($user, 'manage-own-employees')) {
            $menu[] = $this->item('my_profile', 'My Profile');
        }

        $attendanceChildren = $this->buildAttendanceChildren($user);
        if ($attendanceChildren !== []) {
            $menu[] = $this->item('attendance_bio_sync', 'Attendance & Bio-Sync', $attendanceChildren);
        } elseif (userHasAnyPermissionForMobileMenu($user, [
            'clock-in-out',
            'manage-own-attendance-records',
            'view-attendance-records',
        ])) {
            $menu[] = $this->item('attendance', 'Attendance');
        }

        if (userHasPermissionForMobileMenu($user, 'view-essl-sync')
            || userHasPermissionForMobileMenu($user, 'manage-essl-sync')
            || userHasPermissionForMobileMenu($user, 'sync-essl-log')) {
            $menu[] = $this->item('essl_sync', 'Essl sync');
        }

        $salaryChildren = $this->buildSalaryPayrollChildren($user);
        if ($salaryChildren !== []) {
            $menu[] = $this->item('salary_payroll', 'Salary Payroll', $salaryChildren);
        }

        $leaveChildren = $this->buildLeaveChildren($user);
        if ($leaveChildren !== []) {
            $menu[] = $this->item('leave_management', 'Leave Management', $leaveChildren);
        }

        $reportChildren = $this->buildReportChildren($user);
        if ($reportChildren !== []) {
            $menu[] = $this->item('reports', 'Reports', $reportChildren);
        }

        if ($this->canAccessMobilePayslips($user) && ! $this->menuHasChildTitle($menu, 'Salary Payroll', 'Payslips')) {
            $menu[] = $this->item('payslips', 'Payslips');
        }

        if (userHasPermissionForMobileMenu($user, 'access-mobile-app')) {
            $menu[] = $this->item('profile', 'Profile');
        }

        return $menu;
    }

    /**
     * @return list<array{key: string, title: string}>
     */
    private function buildMasterChildren(User $user): array
    {
        $children = [];

        $map = [
            'branches' => ['Branches', ['manage-branches', 'view-branches']],
            'week_offs' => ['Week Offs', ['manage-week-offs', 'view-week-offs']],
            'shifts' => ['Shifts', ['manage-shifts', 'view-shifts']],
            'departments' => ['Departments', ['manage-departments', 'view-departments']],
            'designations' => ['Designations', ['manage-designations', 'view-designations']],
            'skills' => ['Skills', ['manage-skills', 'view-skills']],
            'sections' => ['Sections', ['manage-sections', 'view-sections']],
            'categories' => ['Categories', ['manage-categories', 'view-categories']],
            'bank_masters' => ['Bank Masters', ['manage-bank-masters', 'view-bank-masters']],
            'resign_reasons' => ['Resign Reasons', ['manage-resign-reasons', 'view-resign-reasons']],
            'overtime' => ['Overtime', ['manage-overtimes', 'view-overtimes']],
            'material_items' => ['Material Items', ['manage-material-items', 'view-material-items']],
            'deduction_master' => ['Deduction Master', []],
            'document_types' => ['Document Types', ['manage-document-types', 'view-document-types']],
            'salary_component_master' => ['Salary Component Master', []],
        ];

        foreach ($map as $key => [$title, $permissions]) {
            if ($key === 'deduction_master' && $this->canAccessEntity($user, 'deduction-types')) {
                $children[] = $this->item($key, $title);
            } elseif ($key === 'salary_component_master' && $this->canAccessEntity($user, 'salary-components')) {
                $children[] = $this->item($key, $title);
            } elseif ($permissions !== [] && userHasAnyPermissionForMobileMenu($user, $permissions)) {
                $children[] = $this->item($key, $title);
            }
        }

        return $children;
    }

    /**
     * @return list<array{key: string, title: string}>
     */
    private function buildAttendanceChildren(User $user): array
    {
        $children = [];

        if (userHasAnyPermissionForMobileMenu($user, [
            'manage-attendance-records',
            'view-attendance-records',
            'manage-attendance',
            'view-attendance',
            'manage-own-attendance-records',
            'clock-in-out',
        ])) {
            $children[] = $this->item('attendance', 'Attendance');
        }

        if (userHasAnyPermissionForMobileMenu($user, [
            'manage-attendance-regularizations',
            'manage-any-attendance-regularizations',
            'manage-attendance-records',
            'manage-any-attendance-records',
        ])) {
            $children[] = $this->item('mispunch', 'MisPunch');
        }

        if (userHasAnyPermissionForMobileMenu($user, ['manage-production-entry', 'view-production-entry'])) {
            $children[] = $this->item('production_entry', 'Production Entry');
        }

        return $children;
    }

    /**
     * @return list<array{key: string, title: string}>
     */
    private function buildSalaryPayrollChildren(User $user): array
    {
        $children = [];

        if ($this->canAccessSalaryPayrollEmployee($user)) {
            $children[] = $this->item('employee_salary', 'Employee Salary');
        }
        if ($this->canAccessSalaryPayrollIncrement($user)) {
            $children[] = $this->item('bulk_salary_increment', 'Bulk Salary Increment');
        }
        if ($this->canAccessSalaryPayrollRuns($user)) {
            $children[] = $this->item('generate_payroll', 'Generate Payroll');
        }
        if ($this->canAccessEntity($user, 'earning-deduction-entry')) {
            $children[] = $this->item('earning_deduction', 'Earning / Deduction');
        }
        if ($this->canAccessEntity($user, 'salary-advances')) {
            $children[] = $this->item('salary_advance', 'Salary Advance');
        }
        if ($this->canAccessEntity($user, 'salary-loans')) {
            $children[] = $this->item('salary_loan', 'Salary Loan');
        }
        if ($this->canAccessPayrollSettings($user)) {
            $children[] = $this->item('payroll_settings', 'Payroll Settings');
        }
        if ($this->canAccessMobilePayslips($user)) {
            $children[] = $this->item('payslips', 'Payslips');
        }

        return $children;
    }

    /**
     * @return list<array{key: string, title: string}>
     */
    private function buildLeaveChildren(User $user): array
    {
        $children = [];

        if (userHasAnyPermissionForMobileMenu($user, ['manage-leave-types', 'view-leave-types'])) {
            $children[] = $this->item('leave_types', 'Leave Types');
        }
        if (userHasAnyPermissionForMobileMenu($user, ['manage-leave-policies', 'view-leave-policies'])) {
            $children[] = $this->item('leave_policies', 'Leave Policies');
        }
        if (userHasAnyPermissionForMobileMenu($user, [
            'manage-leave-applications',
            'view-leave-applications',
            'manage-own-leave-applications',
            'create-leave-applications',
        ])) {
            $children[] = $this->item('leave_applications', 'Leave Applications');
        }
        if (userHasAnyPermissionForMobileMenu($user, [
            'manage-leave-balances',
            'view-leave-balances',
            'manage-own-leave-balances',
        ])) {
            $children[] = $this->item('leave_balances', 'Leave Balances');
        }

        return $children;
    }

    /**
     * @return list<array{key: string, title: string}>
     */
    private function buildReportChildren(User $user): array
    {
        $children = [];

        if (userHasPermissionForMobileMenu($user, 'view-attendance-reports')) {
            $children[] = $this->item('attendance_reports', 'Attendance Reports');
        }
        if (userHasPermissionForMobileMenu($user, 'view-monthly-reports')) {
            $children[] = $this->item('monthly_reports', 'Monthly Reports');
        }
        if (userHasPermissionForMobileMenu($user, 'view-master-reports')) {
            $children[] = $this->item('master_reports', 'Master Reports');
        }

        return $children;
    }

    /**
     * @param  list<array{key: string, title: string}>|null  $children
     * @return array{key: string, title: string, children?: list<array{key: string, title: string}>}
     */
    private function item(string $key, string $title, ?array $children = null): array
    {
        $item = ['key' => $key, 'title' => $title];

        if ($children !== null && $children !== []) {
            $item['children'] = $children;
        }

        return $item;
    }

    private function canAccessEntity(User $user, string $entity): bool
    {
        return userHasAnyPermissionForMobileMenu($user, [
            "view-{$entity}",
            "manage-{$entity}",
            "manage-any-{$entity}",
            "manage-own-{$entity}",
            "create-{$entity}",
            "edit-{$entity}",
            "delete-{$entity}",
        ]);
    }

    private function canAccessSalaryPayrollEmployee(User $user): bool
    {
        return $this->canAccessEntity($user, 'salary-payroll-employee-salary')
            || userHasAnyPermissionForMobileMenu($user, [
                'view-employee-salaries',
                'manage-employee-salaries',
                'manage-any-employee-salaries',
                'manage-own-employee-salaries',
            ]);
    }

    private function canAccessSalaryPayrollIncrement(User $user): bool
    {
        return $this->canAccessEntity($user, 'salary-payroll-increment')
            || $this->canAccessSalaryPayrollEmployee($user);
    }

    private function canAccessSalaryPayrollRuns(User $user): bool
    {
        return userHasAnyPermissionForMobileMenu($user, [
            'view-salary-payroll-runs',
            'create-salary-payroll-runs',
            'edit-salary-payroll-runs',
            'delete-salary-payroll-runs',
            'finalize-salary-payroll-runs',
            'manage-salary-payroll-runs',
            'manage-any-salary-payroll-runs',
        ]) || $this->canAccessSalaryPayrollEmployee($user);
    }

    private function canAccessPayrollSettings(User $user): bool
    {
        return $this->canAccessEntity($user, 'payroll-settings')
            || userHasAnyPermissionForMobileMenu($user, ['manage-settings', 'view-settings', 'edit-settings', 'view-payroll-settings', 'edit-payroll-settings', 'manage-payroll-settings']);
    }

    private function canAccessMobilePayslips(User $user): bool
    {
        return userHasAnyPermissionForMobileMenu($user, [
            'download-payslips',
            'view-payslips',
            'view-salary-payroll-employee-salary',
            'manage-own-payslips',
            'manage-own-salary-payroll-employee-salary',
            'view-employee-salaries',
        ]);
    }

    /**
     * @param  list<array{key: string, title: string, children?: list<array{key: string, title: string}>}>  $menu
     */
    private function menuHasChildTitle(array $menu, string $parentTitle, string $childTitle): bool
    {
        foreach ($menu as $item) {
            if (($item['title'] ?? '') !== $parentTitle) {
                continue;
            }

            foreach ($item['children'] ?? [] as $child) {
                if (($child['title'] ?? '') === $childTitle) {
                    return true;
                }
            }
        }

        return false;
    }
}
