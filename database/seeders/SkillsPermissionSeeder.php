<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class SkillsPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            // Skills management
            ['name' => 'manage-skills', 'module' => 'skills', 'label' => 'Manage Skills', 'description' => 'Can manage skills'],
            ['name' => 'view-skills', 'module' => 'skills', 'label' => 'View Skills', 'description' => 'View Skills'],
            ['name' => 'create-skills', 'module' => 'skills', 'label' => 'Create Skills', 'description' => 'Can create skills'],
            ['name' => 'edit-skills', 'module' => 'skills', 'label' => 'Edit Skills', 'description' => 'Can edit skills'],
            ['name' => 'delete-skills', 'module' => 'skills', 'label' => 'Delete Skills', 'description' => 'Can delete skills'],
            ['name' => 'toggle-status-skills', 'module' => 'skills', 'label' => 'Toggle Status Skills', 'description' => 'Can toggle status of skills'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission['name'], 'guard_name' => 'web'],
                [
                    'module' => $permission['module'],
                    'label' => $permission['label'],
                    'description' => $permission['description'],
                ]
            );
        }

        // Assign to Super Admin
        $superAdminRole = Role::where('name', 'super-admin')->first();
        if ($superAdminRole) {
            $superAdminRole->givePermissionTo(collect($permissions)->pluck('name'));
        }

        // Assign to Company
        $companyRole = Role::where('name', 'company')->first();
        if ($companyRole) {
            $companyRole->givePermissionTo(collect($permissions)->pluck('name'));
        }
    }
}
