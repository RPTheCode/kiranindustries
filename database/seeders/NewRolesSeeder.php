<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class NewRolesSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = 1; // Assuming Kiran Industries is ID 1

        $rolesData = [
            [
                'name' => 'admin',
                'label' => 'Admin',
                'description' => 'Full unrestricted access to the entire system.',
                'permissions' => '*' // Special flag for all permissions
            ],
            [
                'name' => 'manager',
                'label' => 'Manager',
                'description' => 'Branch-wise administrative access.',
                'permissions' => [
                    // Dashboard
                    'manage-dashboard',
                    // Attendance (including mispunch / manual entry)
                    'view-attendance-records', 'manage-own-attendance-records', 'create-attendance-records', 'edit-attendance-records', 'delete-attendance-records',
                    'view-attendance-regularizations', 'manage-own-attendance-regularizations', 'create-attendance-regularizations', 'edit-attendance-regularizations', 'approve-attendance-regularizations', 'reject-attendance-regularizations',
                    'clock-in-out',
                    // Employees
                    'view-employees', 'manage-own-employees', 'create-employees', 'edit-employees',
                    // Payroll
                    'view-payroll-runs', 'manage-own-payroll-runs', 'process-payroll-runs',
                    // Leaves
                    'view-leave-applications', 'manage-own-leave-applications', 'approve-leave-applications', 'reject-leave-applications',
                    'view-leave-balances', 'manage-own-leave-balances',
                    // Reports & MIS
                    'view-performance-indicators', 'manage-own-performance-indicators',
                    // Users
                    'view-users', 'manage-own-users',
                    // Media
                    'view-media', 'manage-own-media', 'create-media', 'edit-media'
                ]
            ],
            [
                'name' => 'employee',
                'label' => 'Employee',
                'description' => 'Mobile app and self-service access for workers and staff.',
                'permissions' => [
                    'access-mobile-app',
                    'manage-dashboard',
                    'view-attendance-records',
                    'manage-own-attendance-records',
                    'clock-in-out',
                    'manage-own-employees',
                    'view-leave-applications',
                    'manage-own-leave-applications',
                    'create-leave-applications',
                    'view-leave-balances',
                    'manage-own-leave-balances',
                    'view-media',
                ],
            ],
            [
                'name' => 'staff',
                'label' => 'Staff',
                'description' => 'Branch-wise operational access.',
                'permissions' => [
                    'access-mobile-app',
                    // Dashboard
                    'manage-dashboard',
                    // Attendance
                    'view-attendance-records', 'manage-own-attendance-records',
                    'view-attendance-regularizations', 'manage-own-attendance-regularizations', 'create-attendance-regularizations',
                    'clock-in-out',
                    // Employees
                    'view-employees',
                    // Leaves
                    'view-leave-applications', 'manage-own-leave-applications', 'create-leave-applications',
                    'view-leave-balances', 'manage-own-leave-balances',
                    // Media
                    'view-media'
                ]
            ]
        ];

        foreach ($rolesData as $data) {
            $role = Role::updateOrCreate(
                ['name' => $data['name'], 'created_by' => $companyId],
                [
                    'label' => $data['label'],
                    'description' => $data['description'],
                    'guard_name' => 'web'
                ]
            );

            if ($data['permissions'] === '*') {
                // Get all valid company permissions from config
                $allowedModules = config('role-permissions.company');
                $permissions = Permission::whereIn('module', $allowedModules)->get();
                $role->syncPermissions($permissions);
            } else {
                $permissions = Permission::whereIn('name', $data['permissions'])->get();
                $role->syncPermissions($permissions);
            }
        }

        $this->command->info('New Roles (Admin, Manager, Staff, Employee) created successfully for Company ID 1.');
    }
}
