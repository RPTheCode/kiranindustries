<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Str;

class LiveDeploySeeder extends Seeder
{
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $companyId = 1;

        $this->command->info('1. Creating Report Permissions...');
        $oldManagePerms = [
            'manage-sections',
            'manage-categories',
            'manage-resign-reasons',
            'manage-overtimes',
            'manage-material-items',
            'manage-production-entry',
            'manage-week-offs',
            'manage-bank-masters'
        ];
        Permission::whereIn('name', $oldManagePerms)->delete();

        $masterEntities = [
            'sections' => 'Sections',
            'categories' => 'Categories',
            'resign-reasons' => 'Resign Reasons',
            'overtimes' => 'Overtimes',
            'material-items' => 'Material Items',
            'production-entry' => 'Production Entry',
            'week-offs' => 'Week Offs',
            'bank-masters' => 'Bank Masters',
        ];

        $reportPerms = [
            ['name' => 'view-attendance-reports', 'label' => 'View Attendance Reports', 'module' => 'Reports'],
            ['name' => 'view-monthly-reports', 'label' => 'View Monthly Reports', 'module' => 'Reports'],
            ['name' => 'view-master-reports', 'label' => 'View Master Reports', 'module' => 'Reports'],
            ['name' => 'sync-essl-log', 'label' => 'ESSL Sync Log', 'module' => 'ESSL Sync Log'],
            // Activity Log permissions
            ['name' => 'view-activity-logs', 'label' => 'View Activity Logs', 'module' => 'Staff & Security'],
            ['name' => 'manage-activity-logs', 'label' => 'Manage Activity Logs', 'module' => 'Staff & Security'],
        ];

        foreach ($masterEntities as $entity => $label) {
            $prefixes = ['view', 'manage-any', 'manage-own', 'create', 'edit', 'delete'];
            foreach ($prefixes as $prefix) {
                $reportPerms[] = [
                    'name' => "{$prefix}-{$entity}",
                    'label' => ucwords(str_replace('-', ' ', $prefix)) . " {$label}",
                    'module' => 'Masters'
                ];
            }
        }

        foreach ($reportPerms as $p) {
            Permission::firstOrCreate([
                'name' => $p['name'],
                'guard_name' => 'web'
            ], [
                'label' => $p['label'],
                'module' => $p['module']
            ]);
        }

        $this->command->info('2. Formatting all permissions to clean modules...');
        $permissions = Permission::all();
        $customOverrides = [
            // Masters Module
            'branches' => 'Masters',
            'departments' => 'Masters',
            'designations' => 'Masters',
            'document-types' => 'Masters',
            'sections' => 'Masters',
            'categories' => 'Masters',
            'bank-masters' => 'Masters',
            'resign-reasons' => 'Masters',
            'overtimes' => 'Masters',
            'material-items' => 'Masters',
            'week-offs' => 'Masters',
            'shifts' => 'Masters',
            'skills' => 'Masters',

            // Employees
            'employees' => 'Employees',
            'employee-salaries' => 'Employees',

            // Attendance & Bio-Sync Module
            'attendance-records' => 'Attendance & Bio-Sync',
            'attendance-regularizations' => 'Attendance & Bio-Sync',
            'daily-production-attendance-entry' => 'Attendance & Bio-Sync',
            'production-entry' => 'Attendance & Bio-Sync',
            'bulk-attendance-add' => 'Attendance & Bio-Sync',

            // ESSL
            'sync-essl-log' => 'ESSL Sync Log',

            // Reports
            'attendance-reports' => 'Reports',
            'monthly-reports' => 'Reports',
            'master-reports' => 'Reports',

            // Payroll Management
            'salary-components' => 'Payroll Management',
            'payroll-runs' => 'Payroll Management',
            'payslips' => 'Payroll Management',
            'payroll-adjustments' => 'Payroll Management',

            // Leave Management
            'leave-types' => 'Leave Management',
            'leave-policies' => 'Leave Management',
            'leave-applications' => 'Leave Management',
            'leave-balances' => 'Leave Management',
            'adjust-leave-balances' => 'Leave Management',

            // Staff & Security Module
            'users' => 'Staff & Security',
            'roles' => 'Staff & Security',

            // Media Library
            'media' => 'Media Library',

            // Dashboard
            'dashboard' => 'Dashboard',

            // Settings
            'settings' => 'Settings',
            'system-settings' => 'Settings',
            'email-settings' => 'Settings',
            'brand-settings' => 'Settings',

            // Hidden (not shown to company roles)
            'promotions' => 'Hidden',
            'resignations' => 'Hidden',
            'terminations' => 'Hidden',
            'attendance-policies' => 'Hidden',
            'time-entries' => 'Hidden',
            'award-types' => 'Hidden',
            'awards' => 'Hidden',
            'trips' => 'Hidden',
            'trip-expenses' => 'Hidden',
            'complaints' => 'Hidden',
            'holidays' => 'Hidden',
            'announcements' => 'Hidden',
            'asset-types' => 'Hidden',
            'assets' => 'Hidden',
            'calendar' => 'Hidden',
            'warnings' => 'Hidden',
            'employee-transfers' => 'Hidden',
        ];

        foreach ($permissions as $permission) {
            $name = $permission->name;
            $prefixes = [
                'manage-any-',
                'manage-own-',
                'manage-',
                'view-',
                'create-',
                'edit-',
                'delete-',
                'approve-',
                'reject-',
                'toggle-status-',
                'download-',
                'process-',
                'publish-',
                'record-',
                'request-',
                'resolve-',
                'send-',
                'subscribe-',
                'trial-',
                'upgrade-',
                'acknowledge-'
            ];

            $entityName = $name;
            foreach ($prefixes as $prefix) {
                if (str_starts_with($entityName, $prefix)) {
                    $entityName = substr($entityName, strlen($prefix));
                    break;
                }
            }

            if ($name === 'clock-in-out') {
                $entityName = 'attendance-records';
            }

            $cleanModule = $entityName;
            if (array_key_exists($entityName, $customOverrides)) {
                $cleanModule = $customOverrides[$entityName];
            } else {
                $cleanModule = Str::title(str_replace('-', ' ', $entityName));
            }

            $permission->module = $cleanModule;
            $permission->save();
        }

        // Apply custom labels
        $this->command->info('2.5 Applying custom labels...');
        \Spatie\Permission\Models\Permission::where('name', 'manage-attendance-records')->update(['label' => 'Access']);
        \Spatie\Permission\Models\Permission::where('name', 'manage-any-attendance-records')->update(['label' => 'Manage Any']);
        \Spatie\Permission\Models\Permission::where('name', 'manage-own-attendance-records')->update(['label' => 'Manage Own']);
        \Spatie\Permission\Models\Permission::where('name', 'create-attendance-records')->update(['label' => 'Manually Entry']);
        \Spatie\Permission\Models\Permission::where('name', 'manage-attendance-regularizations')->update(['label' => 'MisPunch Clear']);
        \Spatie\Permission\Models\Permission::where('name', 'manage-any-attendance-regularizations')->update(['label' => 'Manage Any']);
        \Spatie\Permission\Models\Permission::where('name', 'manage-own-attendance-regularizations')->update(['label' => 'Manage Own']);
        \Spatie\Permission\Models\Permission::where('name', 'edit-attendance-regularizations')->update(['label' => 'Edit Any MisPunch']);

        $this->command->info('3. Assigning all permissions to Super Admin...');
        $superAdmin = Role::where('name', 'superadmin')->orWhere('name', 'super-admin')->first();
        if ($superAdmin) {
            $superAdmin->syncPermissions(Permission::pluck('name')->toArray());
        }

        $this->command->info('4. Rebuilding default Company Roles (admin, manager, staff)...');
        $rolesData = [
            [
                'name' => 'admin',
                'label' => 'Admin',
                'description' => 'Full unrestricted access to the entire system.',
                'permissions' => '*'
            ],
            [
                'name' => 'manager',
                'label' => 'Manager',
                'description' => 'Branch-wise administrative access.',
                'permissions' => [
                    'view-dashboard',
                    'view-attendance-records',
                    'manage-own-attendance-records',
                    'create-attendance-records',
                    'edit-attendance-records',
                    'delete-attendance-records',
                    'view-attendance-regularizations',
                    'manage-own-attendance-regularizations',
                    'create-attendance-regularizations',
                    'edit-attendance-regularizations',
                    'approve-attendance-regularizations',
                    'reject-attendance-regularizations',
                    'clock-in-out',
                    'view-employees',
                    'manage-own-employees',
                    'create-employees',
                    'edit-employees',
                    'view-payroll-runs',
                    'manage-own-payroll-runs',
                    'process-payroll-runs',
                    'view-leave-applications',
                    'manage-own-leave-applications',
                    'approve-leave-applications',
                    'reject-leave-applications',
                    'view-leave-balances',
                    'manage-own-leave-balances',
                    'view-performance-indicators',
                    'manage-own-performance-indicators',
                    'view-users',
                    'manage-own-users',
                    'view-media',
                    'manage-own-media',
                    'create-media',
                    'edit-media',
                    'view-activity-logs'
                ]
            ],
            [
                'name' => 'staff',
                'label' => 'Staff',
                'description' => 'Branch-wise operational access.',
                'permissions' => [
                    'view-dashboard',
                    'view-attendance-records',
                    'manage-own-attendance-records',
                    'view-attendance-regularizations',
                    'manage-own-attendance-regularizations',
                    'create-attendance-regularizations',
                    'clock-in-out',
                    'view-employees',
                    'view-leave-applications',
                    'manage-own-leave-applications',
                    'create-leave-applications',
                    'view-leave-balances',
                    'manage-own-leave-balances',
                    'view-media',
                    'view-activity-logs'
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
                $allowedModules = config('role-permissions.company');
                $permissions = Permission::whereIn('module', $allowedModules)->get();
                $role->syncPermissions($permissions);
            } else {
                $permissions = Permission::whereIn('name', $data['permissions'])->get();
                $role->syncPermissions($permissions);
            }
        }
        $this->command->info('Deployment Seeder Completed successfully!');
    }
}
