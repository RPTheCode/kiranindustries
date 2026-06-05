<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DeductionTypesPermissionSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            ['name' => 'manage-deduction-types', 'label' => 'Manage Deduction Master', 'module' => 'Masters'],
            ['name' => 'view-deduction-types', 'label' => 'View Deduction Master', 'module' => 'Masters'],
            ['name' => 'manage-any-deduction-types', 'label' => 'Manage Any Deduction Master', 'module' => 'Masters'],
            ['name' => 'manage-own-deduction-types', 'label' => 'Manage Own Deduction Master', 'module' => 'Masters'],
            ['name' => 'create-deduction-types', 'label' => 'Create Deduction Master', 'module' => 'Masters'],
            ['name' => 'edit-deduction-types', 'label' => 'Edit Deduction Master', 'module' => 'Masters'],
            ['name' => 'delete-deduction-types', 'label' => 'Delete Deduction Master', 'module' => 'Masters'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                [
                    'label' => $permission['label'],
                    'module' => $permission['module'],
                    'description' => $permission['label'],
                ]
            );
        }

        $permissionNames = collect($permissions)->pluck('name')->toArray();

        foreach (['superadmin', 'super-admin', 'admin', 'company'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($permissionNames);
            }
        }

        $this->command?->info('Deduction Master permissions seeded.');
    }
}
