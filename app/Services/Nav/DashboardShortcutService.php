<?php

namespace App\Services\Nav;

use App\Models\Setting;
use App\Models\User;

class DashboardShortcutService
{
    public const SETTING_KEY = 'dashboard_shortcuts';

    public const HIDDEN_KEY = 'dashboard_shortcuts_hidden';

    public const MAX_SHORTCUTS = 8;

    public function getForUser(User $user): array
    {
        $stored = $this->getStoredIds($user);

        if ($stored === null) {
            return [];
        }

        return array_slice($stored, 0, self::MAX_SHORTCUTS);
    }

    /** Optional quick-pick ideas — user chooses; never applied automatically. */
    public function getSuggestedIds(User $user): array
    {
        return array_slice($this->buildCandidateIds($user), 0, 4);
    }

    public function isHiddenForUser(User $user): bool
    {
        $setting = Setting::where('user_id', $user->id)
            ->where('key', self::HIDDEN_KEY)
            ->first();

        if (!$setting) {
            return false;
        }

        return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
    }

    public function setHiddenForUser(User $user, bool $hidden): bool
    {
        Setting::updateOrCreate(
            ['user_id' => $user->id, 'key' => self::HIDDEN_KEY],
            ['value' => $hidden ? '1' : '0']
        );

        return $hidden;
    }

    public function saveForUser(User $user, array $ids): array
    {
        $sanitized = $this->sanitizeIds($ids);
        $sanitized = array_slice($sanitized, 0, self::MAX_SHORTCUTS);

        Setting::updateOrCreate(
            ['user_id' => $user->id, 'key' => self::SETTING_KEY],
            ['value' => json_encode(array_values($sanitized))]
        );

        return $sanitized;
    }

    /**
     * @return list<string>|null null when the user has never saved shortcuts
     */
    public function getStoredIds(User $user): ?array
    {
        $setting = Setting::where('user_id', $user->id)
            ->where('key', self::SETTING_KEY)
            ->first();

        if (!$setting) {
            return null;
        }

        $decoded = json_decode($setting->value, true);

        return is_array($decoded) ? $this->sanitizeIds($decoded) : [];
    }

    /**
     * @return list<string>
     */
    private function buildCandidateIds(User $user): array
    {
        $candidates = match ($user->type) {
            'employee' => [
                ['route' => 'hr.employees.my-profile', 'permission' => 'manage-own-employees'],
                ['route' => 'hr.attendance.module', 'permission' => 'view-attendance-records'],
                ['route' => 'hr.attendance.module', 'permission' => 'view-attendance'],
                ['route' => 'hr.salary-payroll.payslips.index', 'permission' => 'view-payslips'],
                ['route' => 'hr.salary-payroll.payslips.index', 'permission' => 'manage-own-payslips'],
                ['route' => 'hr.leave-applications.index', 'permission' => 'manage-own-leave-applications'],
                ['route' => 'hr.leave-applications.index', 'permission' => 'view-leave-applications'],
                ['route' => 'hr.leave-balances.index', 'permission' => 'manage-own-leave-balances'],
            ],
            'superadmin', 'super admin' => [
                ['route' => 'dashboard', 'permission' => null],
                ['route' => 'companies.index', 'permission' => null],
                ['route' => 'users.index', 'permission' => null],
                ['route' => 'settings', 'permission' => null],
            ],
            default => [
                ['route' => 'dashboard', 'permission' => 'view-dashboard'],
                ['route' => 'dashboard', 'permission' => 'manage-dashboard'],
                ['route' => 'hr.employees.index', 'permission' => 'view-employees'],
                ['route' => 'hr.employees.index', 'permission' => 'manage-employees'],
                ['route' => 'hr.attendance.module', 'permission' => 'view-attendance-records'],
                ['route' => 'hr.salary-payroll.payslips.index', 'permission' => 'view-payslips'],
                ['route' => 'hr.salary-payroll.generate.index', 'permission' => 'view-salary-payroll-runs'],
                ['route' => 'hr.leave-applications.index', 'permission' => 'view-leave-applications'],
            ],
        };

        $ids = [];

        foreach ($candidates as $candidate) {
            if ($candidate['permission'] && !userHasPermission($user, $candidate['permission'])) {
                continue;
            }

            $path = $this->pathFromRoute($candidate['route']);

            if ($path && !in_array($path, $ids, true)) {
                $ids[] = $path;
            }

            if (count($ids) >= self::MAX_SHORTCUTS) {
                break;
            }
        }

        return $ids;
    }

    /**
     * @param  list<mixed>  $ids
     * @return list<string>
     */
    private function sanitizeIds(array $ids): array
    {
        $clean = [];

        foreach ($ids as $id) {
            if (!is_string($id)) {
                continue;
            }

            $id = trim($id);

            if ($id === '' || strlen($id) > 512) {
                continue;
            }

            if (!str_starts_with($id, '/')) {
                continue;
            }

            if (!preg_match('/^\/[a-zA-Z0-9_\-\/%.?=&]*$/', $id)) {
                continue;
            }

            if (!in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }

        return $clean;
    }

    private function pathFromRoute(string $name, array $parameters = []): ?string
    {
        try {
            $url = route($name, $parameters, false);

            return $this->normalizePath($url);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizePath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $query = parse_url($url, PHP_URL_QUERY);

        if (!str_starts_with($path, '/')) {
            $path = '/'.$path;
        }

        $path = rtrim($path, '/') ?: '/';

        return $query ? $path.'?'.$query : $path;
    }
}
