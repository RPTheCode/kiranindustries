<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

class GrantMobileAccessCommand extends Command
{
    protected $signature = 'mobile:grant-access {identifier : User id, email, or employee code (emy_code)}';

    protected $description = 'Enable mobile app login for an employee (links Employee role + access-mobile-app)';

    public function handle(): int
    {
        $identifier = trim((string) $this->argument('identifier'));
        $user = $this->resolveUser($identifier);

        if (! $user) {
            $this->error("No user found for: {$identifier}");

            return self::FAILURE;
        }

        $employee = mobileUserEmployee($user);

        $user->update([
            'type' => 'employee',
            'status' => 'active',
            'is_enable_login' => 1,
        ]);

        if ($employee && (int) $employee->user_id !== (int) $user->id) {
            $employee->update(['user_id' => $user->id]);
        }

        $companyId = (int) ($user->created_by ?: 1);
        $employeeRole = Role::where('name', 'employee')
            ->where('created_by', $companyId)
            ->first();

        if ($employeeRole) {
            $user->syncRoles([$employeeRole]);
        } elseif (! $user->can('access-mobile-app')) {
            $user->givePermissionTo('access-mobile-app');
        }

        $user->refresh();

        $this->info('Mobile login enabled.');
        $this->line("User ID: {$user->id}");
        $this->line("Name: {$user->name}");
        $this->line("Email: {$user->email}");
        $this->line('Emp code: '.($employee?->employee_id ?? '—'));
        $this->line('Roles: '.$user->getRoleNames()->implode(', '));
        $this->line('Mobile access: '.(userCanAccessMobileApp($user) ? 'yes' : 'no'));

        return self::SUCCESS;
    }

    private function resolveUser(string $identifier): ?User
    {
        if (ctype_digit($identifier)) {
            $user = User::find((int) $identifier);
            if ($user) {
                return $user;
            }

            $employee = Employee::withoutGlobalScopes()
                ->where('emy_code', $identifier)
                ->orWhere('employee_id', $identifier)
                ->first();

            return $employee ? User::find($employee->user_id) : null;
        }

        return User::where('email', $identifier)->first();
    }
}
