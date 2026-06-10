<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SalaryPayrollPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $module = 'Salary Payroll';

        $definitions = [
            // Employee Salary
            ['name' => 'manage-salary-payroll-employee-salary', 'label' => 'Manage Employee Salary'],
            ['name' => 'manage-any-salary-payroll-employee-salary', 'label' => 'Manage Any Employee Salary'],
            ['name' => 'manage-own-salary-payroll-employee-salary', 'label' => 'Manage Own Employee Salary'],
            ['name' => 'view-salary-payroll-employee-salary', 'label' => 'View Employee Salary'],
            ['name' => 'create-salary-payroll-employee-salary', 'label' => 'Create Employee Salary'],
            ['name' => 'edit-salary-payroll-employee-salary', 'label' => 'Edit Employee Salary'],
            ['name' => 'delete-salary-payroll-employee-salary', 'label' => 'Delete Employee Salary'],

            // Bulk Salary Increment
            ['name' => 'manage-salary-payroll-increment', 'label' => 'Manage Bulk Salary Increment'],
            ['name' => 'manage-any-salary-payroll-increment', 'label' => 'Manage Any Bulk Salary Increment'],
            ['name' => 'view-salary-payroll-increment', 'label' => 'View Bulk Salary Increment'],
            ['name' => 'create-salary-payroll-increment', 'label' => 'Apply Bulk Salary Increment'],
            ['name' => 'edit-salary-payroll-increment', 'label' => 'Edit Bulk Salary Increment'],

            // Generate Payroll
            ['name' => 'manage-salary-payroll-runs', 'label' => 'Manage Generate Payroll'],
            ['name' => 'manage-any-salary-payroll-runs', 'label' => 'Manage Any Generate Payroll'],
            ['name' => 'view-salary-payroll-runs', 'label' => 'View Generate Payroll'],
            ['name' => 'create-salary-payroll-runs', 'label' => 'Create Generate Payroll'],
            ['name' => 'edit-salary-payroll-runs', 'label' => 'Edit Generate Payroll'],
            ['name' => 'delete-salary-payroll-runs', 'label' => 'Delete Generate Payroll'],
            ['name' => 'finalize-salary-payroll-runs', 'label' => 'Finalize Generate Payroll'],
            ['name' => 'apply-salary-payroll-attendance-extra', 'label' => 'Apply OT No Extra Days to Net Salary'],

            // Earning / Deduction Entry
            ['name' => 'manage-earning-deduction-entry', 'label' => 'Manage Earning / Deduction'],
            ['name' => 'manage-any-earning-deduction-entry', 'label' => 'Manage Any Earning / Deduction'],
            ['name' => 'manage-own-earning-deduction-entry', 'label' => 'Manage Own Earning / Deduction'],
            ['name' => 'view-earning-deduction-entry', 'label' => 'View Earning / Deduction'],
            ['name' => 'create-earning-deduction-entry', 'label' => 'Create Earning / Deduction'],
            ['name' => 'edit-earning-deduction-entry', 'label' => 'Edit Earning / Deduction'],
            ['name' => 'delete-earning-deduction-entry', 'label' => 'Delete Earning / Deduction'],

            // Payroll Settings (PF / ESIC / PT)
            ['name' => 'manage-payroll-settings', 'label' => 'Manage Payroll Settings'],
            ['name' => 'view-payroll-settings', 'label' => 'View Payroll Settings'],
            ['name' => 'edit-payroll-settings', 'label' => 'Edit Payroll Settings'],

            // Salary Advance
            ['name' => 'manage-salary-advances', 'label' => 'Manage Salary Advance'],
            ['name' => 'manage-any-salary-advances', 'label' => 'Manage Any Salary Advance'],
            ['name' => 'view-salary-advances', 'label' => 'View Salary Advance'],
            ['name' => 'create-salary-advances', 'label' => 'Create Salary Advance'],
            ['name' => 'edit-salary-advances', 'label' => 'Edit Salary Advance'],
            ['name' => 'delete-salary-advances', 'label' => 'Delete Salary Advance'],

            // Salary Loan
            ['name' => 'manage-salary-loans', 'label' => 'Manage Salary Loan'],
            ['name' => 'manage-any-salary-loans', 'label' => 'Manage Any Salary Loan'],
            ['name' => 'view-salary-loans', 'label' => 'View Salary Loan'],
            ['name' => 'create-salary-loans', 'label' => 'Create Salary Loan'],
            ['name' => 'edit-salary-loans', 'label' => 'Edit Salary Loan'],
            ['name' => 'delete-salary-loans', 'label' => 'Delete Salary Loan'],
        ];

        $permissionNames = [];

        foreach ($definitions as $definition) {
            Permission::updateOrCreate(
                ['name' => $definition['name'], 'guard_name' => 'web'],
                [
                    'label' => $definition['label'],
                    'module' => $module,
                    'description' => $definition['label'],
                ]
            );
            $permissionNames[] = $definition['name'];
        }

        foreach (['superadmin', 'super-admin', 'admin', 'company'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($permissionNames);
            }
        }

        // HR / Manager — grant Salary Advance & Salary Loan (same module as payroll)
        $advanceLoanPerms = array_values(array_filter(
            $permissionNames,
            fn (string $name) => str_contains($name, 'salary-advance') || str_contains($name, 'salary-loan')
        ));
        if ($advanceLoanPerms !== []) {
            Role::query()
                ->whereIn('name', ['hr', 'manager'])
                ->each(function (Role $role) use ($advanceLoanPerms): void {
                    $role->givePermissionTo($advanceLoanPerms);
                });
        }

        $this->command?->info('Salary Payroll permissions seeded.');
    }
}
