import { useEffect, useState } from 'react';
import axios from 'axios';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { MultiSelect } from '@/components/ui/multi-select';
import { Search, UserCheck, AlertCircle } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { toast } from '@/components/custom-toast';

function findDefaultEmployeeRoleId(
  roles: Array<{ id: number; name: string; label?: string }>
): string | null {
  for (const name of ['employee', 'staff']) {
    const match = roles.find((role) => role.name.toLowerCase() === name);
    if (match) {
      return String(match.id);
    }
  }

  return null;
}

type EmployeePreview = {
  id: number;
  code: string;
  emy_code?: string;
  name?: string;
  email?: string;
  department?: string;
  designation?: string;
  branch?: string;
  phone?: string;
  linked_user_id?: number;
  linked_user?: {
    id: number;
    name?: string;
    email?: string;
    type?: string;
    role?: string;
  } | null;
  is_login_enabled?: boolean;
};

interface UserFormModalProps {
  isOpen: boolean;
  onClose: () => void;
  onSubmit: (data: Record<string, unknown>) => void;
  mode: 'create' | 'edit' | 'view';
  title: string;
  initialData?: Record<string, unknown> | null;
  roles: Array<{ id: number; name: string; label?: string }>;
  branches: Array<{ id: number; name: string }>;
  currentUserId?: number;
  onEditLinkedUser?: (userId: number) => void;
}

export function UserFormModal({
  isOpen,
  onClose,
  onSubmit,
  mode,
  title,
  initialData,
  roles,
  branches,
  currentUserId,
  onEditLinkedUser,
}: UserFormModalProps) {
  const { t } = useTranslation();
  const [formData, setFormData] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: '',
    branches: [] as string[],
    employee_code: '',
    phone: '',
  });
  const [employeePreview, setEmployeePreview] = useState<EmployeePreview | null>(null);
  const [searching, setSearching] = useState(false);
  const [lookupError, setLookupError] = useState('');

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    setFormData({
      name: (initialData?.name as string) || '',
      email: (initialData?.email as string) || '',
      password: '',
      password_confirmation: '',
      roles: initialData?.roles
        ? String(initialData.roles)
        : '',
      branches: Array.isArray(initialData?.branches)
        ? (initialData.branches as string[])
        : [],
      employee_code: (initialData?.employee_code as string) || '',
      phone: (initialData?.phone as string) || '',
    });
    setEmployeePreview(null);
    setLookupError('');

    if (initialData?.employee_code) {
      lookupEmployee(String(initialData.employee_code), false);
    }
  }, [isOpen, initialData]);

  const updateField = (name: string, value: string | string[]) => {
    setFormData((prev) => ({ ...prev, [name]: value }));
  };

  const lookupEmployee = async (code?: string, showToast = true) => {
    const empCode = (code ?? formData.employee_code).trim();
    if (!empCode) {
      setLookupError(t('Enter employee code to search.'));
      return;
    }

    setSearching(true);
    setLookupError('');
    setEmployeePreview(null);

    try {
      const response = await axios.get(route('users.lookup-employee'), {
        params: { code: empCode },
      });
      const employee = response.data.employee as EmployeePreview;
      setEmployeePreview(employee);

      const employeeEmailOwnedByOther =
        employee.email &&
        employee.linked_user_id &&
        (!currentUserId || employee.linked_user_id !== currentUserId);

      const hasExistingLoginOnCreate =
        mode === 'create' && !!employee.linked_user_id && !!employee.linked_user;
      const employeeRoleId = findDefaultEmployeeRoleId(roles);

      setFormData((prev) => {
        const shouldAutoSelectEmployeeRole =
          !!employeeRoleId &&
          !hasExistingLoginOnCreate &&
          (mode === 'create' || !prev.roles);

        return {
          ...prev,
          employee_code: empCode,
          name: prev.name || employee.name || prev.name,
          email: prev.email || (employeeEmailOwnedByOther ? prev.email : (employee.email || prev.email)),
          phone: prev.phone || employee.phone || prev.phone,
          roles: shouldAutoSelectEmployeeRole ? employeeRoleId! : prev.roles,
        };
      });
      if (showToast) {
        toast.success(t('Employee found.'));
      }
    } catch {
      setLookupError(t('Employee not found for this code.'));
      if (showToast) {
        toast.error(t('Employee not found for this code.'));
      }
    } finally {
      setSearching(false);
    }
  };

  const handleSubmit = () => {
    if (!formData.name || !formData.email || !formData.roles) {
      toast.error(t('Please fill all required fields.'));
      return;
    }

    if (mode === 'create' && employeeHasExistingLogin) {
      toast.error(t('This employee already has a login account. Use "Edit existing login" to assign Admin role.'));
      return;
    }

    if (mode === 'create' && (!formData.password || formData.password !== formData.password_confirmation)) {
      toast.error(t('Password and confirmation must match.'));
      return;
    }

    if (
      employeePreview?.email &&
      formData.email === employeePreview.email &&
      employeePreview.linked_user_id &&
      (!currentUserId || employeePreview.linked_user_id !== currentUserId)
    ) {
      toast.error(t('This email is already used by the employee\'s existing login. Keep a different email for this account — the employee will still link on save.'));
      return;
    }

    const payload: Record<string, unknown> = {
      name: formData.name,
      email: formData.email,
      roles: formData.roles,
      branches: formData.branches,
      employee_code: formData.employee_code || undefined,
      phone: formData.phone || undefined,
    };

    if (mode === 'create') {
      payload.password = formData.password;
      payload.password_confirmation = formData.password_confirmation;
    } else if (formData.password) {
      payload.password = formData.password;
      payload.password_confirmation = formData.password_confirmation;
    }

    onSubmit(payload);
  };

  const isLinkedToOtherUser =
    employeePreview?.linked_user_id &&
    currentUserId &&
    employeePreview.linked_user_id !== currentUserId;

  const employeeHasExistingLogin =
    mode === 'create' &&
    !!employeePreview?.linked_user_id &&
    !!employeePreview?.linked_user;

  return (
    <Dialog open={isOpen} onOpenChange={onClose}>
      <DialogContent className="max-w-lg max-h-[90vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>{title}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4 py-2">
          <div className="rounded-lg border border-dashed border-primary/40 bg-primary/5 p-4 space-y-3">
            <Label className="text-sm font-semibold">{t('Link Employee (Emp Code)')}</Label>
            <p className="text-xs text-muted-foreground">
              {t('Search by employee code to auto-fill details and link this login to the workforce record.')}
            </p>
            <div className="flex gap-2">
              <Input
                value={formData.employee_code}
                onChange={(e) => updateField('employee_code', e.target.value)}
                placeholder={t('e.g. 853')}
                disabled={mode === 'view'}
                onKeyDown={(e) => {
                  if (e.key === 'Enter') {
                    e.preventDefault();
                    lookupEmployee();
                  }
                }}
              />
              <Button
                type="button"
                variant="secondary"
                onClick={() => lookupEmployee()}
                disabled={mode === 'view' || searching}
              >
                <Search className="h-4 w-4 mr-1" />
                {searching ? t('Searching...') : t('Search')}
              </Button>
            </div>
            {lookupError && (
              <p className="text-xs text-red-500 flex items-center gap-1">
                <AlertCircle className="h-3 w-3" />
                {lookupError}
              </p>
            )}
            {employeeHasExistingLogin && (
              <div className="rounded-md border border-red-200 bg-red-50 dark:bg-red-950/30 p-3 text-sm space-y-2">
                <p className="text-red-700 dark:text-red-300 font-medium flex items-center gap-1">
                  <AlertCircle className="h-4 w-4 shrink-0" />
                  {t('Login already exists for this employee')}
                </p>
                <p className="text-xs text-red-600 dark:text-red-400">
                  {t('Account: {{email}} · Current role: {{role}}. Do not create a new user — edit the existing login and set Role to Admin.', {
                    email: employeePreview?.linked_user?.email || '—',
                    role: employeePreview?.linked_user?.role || employeePreview?.linked_user?.type || '—',
                  })}
                </p>
                {employeePreview?.linked_user?.id && onEditLinkedUser && (
                  <Button
                    type="button"
                    size="sm"
                    variant="destructive"
                    onClick={() => onEditLinkedUser(employeePreview.linked_user!.id)}
                  >
                    {t('Edit existing login')}
                  </Button>
                )}
              </div>
            )}
            {employeePreview && (
              <div className="rounded-md border bg-white dark:bg-gray-900 p-3 text-sm space-y-1">
                <div className="flex items-center gap-2 font-medium text-emerald-700 dark:text-emerald-400">
                  <UserCheck className="h-4 w-4" />
                  {employeePreview.name || t('Employee')} ({employeePreview.code})
                </div>
                {employeePreview.department && (
                  <div className="text-muted-foreground">{employeePreview.department} · {employeePreview.designation}</div>
                )}
                {employeePreview.branch && (
                  <div className="text-muted-foreground">{t('Branch')}: {employeePreview.branch}</div>
                )}
                {employeePreview.phone && (
                  <div className="text-muted-foreground">{t('Mobile')}: {employeePreview.phone}</div>
                )}
                {employeePreview.email && (
                  <div className="text-muted-foreground">{t('Work email')}: {employeePreview.email}</div>
                )}
                {isLinkedToOtherUser && (
                  <p className="text-xs text-amber-600 mt-2">
                    {t('This employee is linked to another login ({{email}}). Saving will link the employee record to this account — keep your login email above as-is.', {
                      email: employeePreview.email || t('other user'),
                    })}
                  </p>
                )}
              </div>
            )}
          </div>

          <div className="space-y-2">
            <Label>{t('Name')} *</Label>
            <Input
              value={formData.name}
              onChange={(e) => updateField('name', e.target.value)}
              disabled={mode === 'view'}
            />
          </div>

          <div className="space-y-2">
            <Label>{t('Email')} *</Label>
            <Input
              type="email"
              value={formData.email}
              onChange={(e) => updateField('email', e.target.value)}
              disabled={mode === 'view' || employeeHasExistingLogin}
            />
            <p className="text-xs text-muted-foreground">
              {t('Use a new unique email. Do not reuse supperadmin@gmail.com or the employee work email if already taken.')}
            </p>
          </div>

          <div className="space-y-2">
            <Label>{t('Mobile Number')}</Label>
            <Input
              value={formData.phone}
              onChange={(e) => updateField('phone', e.target.value)}
              placeholder={t('For mobile app login')}
              disabled={mode === 'view' || employeeHasExistingLogin}
            />
          </div>

          {mode === 'create' && !employeeHasExistingLogin && (
            <>
              <div className="space-y-2">
                <Label>{t('Password')} *</Label>
                <Input
                  type="password"
                  value={formData.password}
                  onChange={(e) => updateField('password', e.target.value)}
                />
              </div>
              <div className="space-y-2">
                <Label>{t('Confirm Password')} *</Label>
                <Input
                  type="password"
                  value={formData.password_confirmation}
                  onChange={(e) => updateField('password_confirmation', e.target.value)}
                />
              </div>
            </>
          )}

          {mode === 'edit' && (
            <>
              <div className="space-y-2">
                <Label>{t('New Password')}</Label>
                <Input
                  type="password"
                  value={formData.password}
                  onChange={(e) => updateField('password', e.target.value)}
                  placeholder={t('Leave blank to keep current password')}
                />
              </div>
              {formData.password && (
                <div className="space-y-2">
                  <Label>{t('Confirm Password')}</Label>
                  <Input
                    type="password"
                    value={formData.password_confirmation}
                    onChange={(e) => updateField('password_confirmation', e.target.value)}
                  />
                </div>
              )}
            </>
          )}

          <div className="space-y-2">
            <Label>{t('Role')} *</Label>
            <p className="text-xs text-muted-foreground">
              {t('When you link an employee by code, Role defaults to Employee. Change it if this login needs Admin or another role.')}
            </p>
            <Select
              value={formData.roles}
              onValueChange={(value) => updateField('roles', value)}
              disabled={mode === 'view'}
            >
              <SelectTrigger>
                <SelectValue placeholder={t('Select role')} />
              </SelectTrigger>
              <SelectContent>
                {roles.map((role) => (
                  <SelectItem key={role.id} value={role.id.toString()}>
                    {role.label || role.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label>{t('Assigned Branches')}</Label>
            <MultiSelect
              options={branches.map((branch) => ({
                value: branch.id.toString(),
                label: branch.name,
              }))}
              selected={formData.branches}
              onChange={(values) => updateField('branches', values)}
              placeholder={t('Select branches')}
              disabled={mode === 'view'}
            />
          </div>
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={onClose}>
            {t('Cancel')}
          </Button>
          {mode !== 'view' && !employeeHasExistingLogin && (
            <Button type="button" onClick={handleSubmit}>
              {t('Save')}
            </Button>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
