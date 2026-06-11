<?php

namespace App\Services\SalaryPayroll;

use App\Models\SalaryPayroll\SalaryPayrollEntry;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class PayslipAuthorizationService
{
    /** @var list<string> */
    public const MODULE_PERMISSIONS = [
        'view-payslips',
        'manage-payslips',
        'manage-any-payslips',
        'manage-own-payslips',
        'download-payslips',
        'create-payslips',
        'send-payslips',
    ];

    public function resolveUser(?User $user = null): ?User
    {
        return $user ?? Auth::user();
    }

    public function isCompanyAdmin(?User $user = null): bool
    {
        $user = $this->resolveUser($user);

        return $user && in_array($user->type, ['superadmin', 'super admin', 'company', 'admin'], true);
    }

    public function canAccessModule(?User $user = null): bool
    {
        $user = $this->resolveUser($user);
        if (! $user) {
            return false;
        }

        if ($this->isCompanyAdmin($user)) {
            return true;
        }

        return userHasAnyPermission($user, self::MODULE_PERMISSIONS);
    }

    public function canViewAll(?User $user = null): bool
    {
        $user = $this->resolveUser($user);
        if (! $user) {
            return false;
        }

        if ($this->isCompanyAdmin($user)) {
            return true;
        }

        return userHasAnyPermission($user, [
            'manage-any-payslips',
            'view-payslips',
            'manage-payslips',
        ]);
    }

    public function isSelfServiceOnly(?User $user = null): bool
    {
        $user = $this->resolveUser($user);
        if (! $user || $this->canViewAll($user)) {
            return false;
        }

        return $this->canAccessModule($user);
    }

    public function canPreview(?User $user = null): bool
    {
        return $this->canAccessModule($user);
    }

    public function canDownload(?User $user = null): bool
    {
        $user = $this->resolveUser($user);
        if (! $user) {
            return false;
        }

        if ($this->isCompanyAdmin($user)) {
            return true;
        }

        return userHasAnyPermission($user, [
            'download-payslips',
            'manage-payslips',
            'manage-any-payslips',
            'manage-own-payslips',
        ]);
    }

    public function canDownloadAll(?User $user = null): bool
    {
        return $this->canViewAll($user) && $this->canDownload($user);
    }

    public function canOpenPayrollRun(?User $user = null): bool
    {
        $user = $this->resolveUser($user);
        if (! $user) {
            return false;
        }

        if ($this->isCompanyAdmin($user)) {
            return true;
        }

        return $this->canViewAll($user) && userHasAnyPermission($user, [
            'view-salary-payroll-runs',
            'manage-salary-payroll-runs',
            'manage-any-salary-payroll-runs',
            'finalize-salary-payroll-runs',
            'create-salary-payroll-runs',
            'edit-salary-payroll-runs',
        ]);
    }

    public function assertCanPreviewEntry(SalaryPayrollEntry $entry, ?User $user = null): void
    {
        $user = $this->resolveUser($user);

        if (! $this->canPreview($user)) {
            abort(403, __('You do not have permission to view payslips.'));
        }

        if ($this->canViewAll($user)) {
            return;
        }

        if ($user && (int) $entry->employee_id === (int) $user->id) {
            return;
        }

        abort(403, __('You can only view your own payslip.'));
    }

    public function assertCanDownloadEntry(SalaryPayrollEntry $entry, ?User $user = null): void
    {
        $this->assertCanPreviewEntry($entry, $user);

        if (! $this->canDownload($user)) {
            abort(403, __('You do not have permission to download payslips.'));
        }
    }

    /**
     * @return array<string, bool>
     */
    public function frontendCapabilities(?User $user = null): array
    {
        return [
            'can_access' => $this->canAccessModule($user),
            'can_preview' => $this->canPreview($user),
            'can_download' => $this->canDownload($user),
            'can_view_all' => $this->canViewAll($user),
            'is_self_service' => $this->isSelfServiceOnly($user),
            'can_download_all' => $this->canDownloadAll($user),
            'can_open_payroll_run' => $this->canOpenPayrollRun($user),
        ];
    }
}
