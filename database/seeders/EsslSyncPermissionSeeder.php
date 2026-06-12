<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class EsslSyncPermissionSeeder extends Seeder
{
    public const MODULE = 'ESSL Sync Log';

    /** @return list<string> */
    public static function allPermissionNames(): array
    {
        return [
            'view-essl-sync',
            'sync-essl-manual',
            'manage-essl-auto-sync',
            'export-essl-sync',
            'manage-essl-sync',
            'sync-essl-log', // legacy — full access alias
        ];
    }

    /** @return list<string> */
    public static function granularPermissionNames(): array
    {
        return [
            'view-essl-sync',
            'sync-essl-manual',
            'manage-essl-auto-sync',
            'export-essl-sync',
            'manage-essl-sync',
        ];
    }

    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        $definitions = [
            ['name' => 'view-essl-sync', 'label' => 'View ESSL Device Sync'],
            ['name' => 'sync-essl-manual', 'label' => 'Run Manual ESSL Sync'],
            ['name' => 'manage-essl-auto-sync', 'label' => 'Manage Automatic ESSL Sync'],
            ['name' => 'export-essl-sync', 'label' => 'Export ESSL Sync Logs'],
            ['name' => 'manage-essl-sync', 'label' => 'Manage ESSL Device Sync (Full)'],
            ['name' => 'sync-essl-log', 'label' => 'ESSL Sync Log (Legacy Full Access)'],
        ];

        foreach ($definitions as $definition) {
            Permission::updateOrCreate(
                ['name' => $definition['name'], 'guard_name' => 'web'],
                [
                    'label' => $definition['label'],
                    'module' => self::MODULE,
                    'description' => $definition['label'],
                ]
            );
        }

        $full = self::granularPermissionNames();
        $hr = $full;
        $manager = ['view-essl-sync', 'sync-essl-manual', 'export-essl-sync'];
        $staff = ['view-essl-sync'];

        foreach (['superadmin', 'super-admin', 'admin', 'company'] as $roleName) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->givePermissionTo($full);
            }
        }

        foreach (['hr'] as $roleName) {
            Role::where('name', $roleName)->each(fn (Role $role) => $role->givePermissionTo($hr));
        }

        foreach (['manager'] as $roleName) {
            Role::where('name', $roleName)->each(fn (Role $role) => $role->givePermissionTo($manager));
        }

        foreach (['staff'] as $roleName) {
            Role::where('name', $roleName)->each(fn (Role $role) => $role->givePermissionTo($staff));
        }

        // Anyone with legacy full permission gets all granular permissions
        Role::query()
            ->whereHas('permissions', fn ($q) => $q->where('name', 'sync-essl-log'))
            ->each(fn (Role $role) => $role->givePermissionTo($full));

        $this->command?->info('ESSL Sync permissions seeded (view, manual, auto, export, manage).');
    }
}
