<?php

namespace App\Services\Mobile;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class MobileLoginResolver
{
    public const TYPE_EMAIL = 'email';

    public const TYPE_MOBILE = 'mobile';

    public const TYPE_EMPLOYEE_CODE = 'employee_code';

    /**
     * Resolve login identifier type: email, mobile, or employee code.
     */
    public function detectType(string $login): string
    {
        $login = trim($login);

        if (str_contains($login, '@')) {
            return self::TYPE_EMAIL;
        }

        $digits = preg_replace('/\D+/', '', $login) ?? '';
        if (strlen($digits) >= 10) {
            return self::TYPE_MOBILE;
        }

        return self::TYPE_EMPLOYEE_CODE;
    }

    /**
     * Find and authenticate user by email, mobile, or employee code + password.
     */
    public function authenticate(string $login, string $password): ?User
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        $type = $this->detectType($login);

        $user = match ($type) {
            self::TYPE_EMAIL => $this->findUserByEmail($login),
            self::TYPE_MOBILE => $this->findUserByMobile($login),
            default => $this->findUserByEmployeeCode($login),
        };

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    private function findUserByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    private function findUserByMobile(string $mobile): ?User
    {
        $lastTen = $this->normalizePhoneDigits($mobile);
        if (strlen($lastTen) < 10) {
            return null;
        }

        $candidates = Employee::withoutGlobalScopes()
            ->whereNotNull('user_id')
            ->where(function ($query) use ($mobile, $lastTen) {
                $query->where('phone', $mobile)
                    ->orWhere('phone_2', $mobile)
                    ->orWhere('phone', 'like', '%'.$lastTen)
                    ->orWhere('phone_2', 'like', '%'.$lastTen);
            })
            ->get();

        foreach ($candidates as $employee) {
            if ($this->normalizePhoneDigits((string) $employee->phone) === $lastTen
                || $this->normalizePhoneDigits((string) $employee->phone_2) === $lastTen) {
                return User::find($employee->user_id);
            }
        }

        return null;
    }

    private function normalizePhoneDigits(string $value): string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        return strlen($digits) > 10 ? substr($digits, -10) : $digits;
    }

    private function findUserByEmployeeCode(string $code): ?User
    {
        $employee = findEmployeeByCode($code);
        if (! $employee?->user_id) {
            return null;
        }

        return User::find($employee->user_id);
    }
}
