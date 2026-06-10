export const hasRole = (role: string, userRoles: string[] = []) =>
    userRoles.includes(role);

export const hasPermission = (userPermissions: string[], permission: string) =>
    userPermissions.includes(permission);

export const hasAnyPermission = (userPermissions: string[], permissions: string[]) =>
    permissions.some((permission) => userPermissions.includes(permission));

/** True when user can open a master/list screen for the entity slug (e.g. deduction-types). */
export const canAccessEntity = (userPermissions: string[], entity: string) =>
    hasAnyPermission(userPermissions, [
        `view-${entity}`,
        `manage-${entity}`,
        `manage-any-${entity}`,
        `manage-own-${entity}`,
        `create-${entity}`,
        `edit-${entity}`,
        `delete-${entity}`,
    ]);

export const canCreateEntity = (userPermissions: string[], entity: string) =>
    hasAnyPermission(userPermissions, [
        `create-${entity}`,
        `manage-${entity}`,
        `manage-any-${entity}`,
    ]);

export const canEditEntity = (userPermissions: string[], entity: string) =>
    hasAnyPermission(userPermissions, [
        `edit-${entity}`,
        `manage-${entity}`,
        `manage-any-${entity}`,
        `manage-own-${entity}`,
    ]);

export const canDeleteEntity = (userPermissions: string[], entity: string) =>
    hasAnyPermission(userPermissions, [
        `delete-${entity}`,
        `manage-${entity}`,
        `manage-any-${entity}`,
    ]);

const legacyEmployeeSalaries = [
    'view-employee-salaries',
    'manage-employee-salaries',
    'manage-any-employee-salaries',
    'manage-own-employee-salaries',
    'create-employee-salaries',
    'edit-employee-salaries',
    'delete-employee-salaries',
] as const;

export const canAccessSalaryPayrollEmployee = (userPermissions: string[]) =>
    canAccessEntity(userPermissions, 'salary-payroll-employee-salary')
    || hasAnyPermission(userPermissions, [...legacyEmployeeSalaries]);

export const canCreateSalaryPayrollEmployee = (userPermissions: string[]) =>
    canCreateEntity(userPermissions, 'salary-payroll-employee-salary')
    || hasAnyPermission(userPermissions, ['create-employee-salaries', 'edit-employee-salaries', 'manage-employee-salaries', 'manage-any-employee-salaries']);

export const canEditSalaryPayrollEmployee = (userPermissions: string[]) =>
    canEditEntity(userPermissions, 'salary-payroll-employee-salary')
    || hasAnyPermission(userPermissions, ['edit-employee-salaries', 'manage-employee-salaries', 'manage-any-employee-salaries', 'manage-own-employee-salaries']);

export const canAccessSalaryPayrollIncrement = (userPermissions: string[]) =>
    canAccessEntity(userPermissions, 'salary-payroll-increment')
    || canAccessSalaryPayrollEmployee(userPermissions);

export const canApplySalaryPayrollIncrement = (userPermissions: string[]) =>
    canCreateEntity(userPermissions, 'salary-payroll-increment')
    || hasAnyPermission(userPermissions, ['create-employee-salaries', 'edit-employee-salaries', 'manage-employee-salaries', 'manage-any-employee-salaries']);

export const canAccessSalaryPayrollRuns = (userPermissions: string[]) =>
    hasAnyPermission(userPermissions, [
        'view-salary-payroll-runs',
        'create-salary-payroll-runs',
        'edit-salary-payroll-runs',
        'delete-salary-payroll-runs',
        'finalize-salary-payroll-runs',
        'manage-salary-payroll-runs',
        'manage-any-salary-payroll-runs',
    ]) || canAccessSalaryPayrollEmployee(userPermissions);

export const canManageSalaryPayrollRuns = (userPermissions: string[]) =>
    hasAnyPermission(userPermissions, [
        'create-salary-payroll-runs',
        'edit-salary-payroll-runs',
        'delete-salary-payroll-runs',
        'manage-salary-payroll-runs',
        'manage-any-salary-payroll-runs',
    ]) || hasAnyPermission(userPermissions, ['create-employee-salaries', 'edit-employee-salaries', 'manage-employee-salaries', 'manage-any-employee-salaries']);

export const canFinalizeSalaryPayrollRuns = (userPermissions: string[]) =>
    hasAnyPermission(userPermissions, [
        'finalize-salary-payroll-runs',
        'manage-salary-payroll-runs',
        'manage-any-salary-payroll-runs',
    ]) || hasAnyPermission(userPermissions, ['manage-employee-salaries', 'manage-any-employee-salaries']);

export const canApplySalaryPayrollAttendanceExtra = (userPermissions: string[]) =>
    hasAnyPermission(userPermissions, [
        'apply-salary-payroll-attendance-extra',
        'manage-salary-payroll-runs',
        'manage-any-salary-payroll-runs',
    ]) || hasAnyPermission(userPermissions, ['manage-employee-salaries', 'manage-any-employee-salaries']);

export const canAccessEarningDeductionEntry = (userPermissions: string[]) =>
    canAccessEntity(userPermissions, 'earning-deduction-entry');

export const canCreateEarningDeductionEntry = (userPermissions: string[]) =>
    canCreateEntity(userPermissions, 'earning-deduction-entry');

export const canEditEarningDeductionEntry = (userPermissions: string[]) =>
    canEditEntity(userPermissions, 'earning-deduction-entry');

export const canDeleteEarningDeductionEntry = (userPermissions: string[]) =>
    canDeleteEntity(userPermissions, 'earning-deduction-entry');

export const canAccessPayrollSettings = (userPermissions: string[]) =>
    canAccessEntity(userPermissions, 'payroll-settings')
    || hasAnyPermission(userPermissions, ['manage-settings', 'view-settings', 'edit-settings']);

export const canEditPayrollSettings = (userPermissions: string[]) =>
    canEditEntity(userPermissions, 'payroll-settings')
    || hasAnyPermission(userPermissions, ['manage-settings', 'edit-settings']);

export const canAccessSalaryAdvance = (userPermissions: string[]) =>
    canAccessEntity(userPermissions, 'salary-advances');

export const canCreateSalaryAdvance = (userPermissions: string[]) =>
    canCreateEntity(userPermissions, 'salary-advances');

export const canEditSalaryAdvance = (userPermissions: string[]) =>
    canEditEntity(userPermissions, 'salary-advances');

export const canDeleteSalaryAdvance = (userPermissions: string[]) =>
    canDeleteEntity(userPermissions, 'salary-advances');

export const canManageSalaryAdvance = (userPermissions: string[]) =>
    hasAnyPermission(userPermissions, [
        'manage-salary-advances',
        'manage-any-salary-advances',
        'edit-salary-advances',
    ]);

export const canAccessSalaryLoan = (userPermissions: string[]) =>
    canAccessEntity(userPermissions, 'salary-loans');

export const canCreateSalaryLoan = (userPermissions: string[]) =>
    canCreateEntity(userPermissions, 'salary-loans');

export const canEditSalaryLoan = (userPermissions: string[]) =>
    canEditEntity(userPermissions, 'salary-loans');

export const canDeleteSalaryLoan = (userPermissions: string[]) =>
    canDeleteEntity(userPermissions, 'salary-loans');

export const canManageSalaryLoan = (userPermissions: string[]) =>
    hasAnyPermission(userPermissions, [
        'manage-salary-loans',
        'manage-any-salary-loans',
        'edit-salary-loans',
    ]);