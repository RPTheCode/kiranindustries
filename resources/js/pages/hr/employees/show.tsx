// pages/hr/employees/show.tsx
import { useState, type ReactNode } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { hasPermission } from '@/utils/authorization';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from 'react-i18next';
import {
  Edit,
  Trash2,
  Download,
  FileText,
  Phone,
  Mail,
  MapPin,
  Briefcase,
  User,
  Users,
  ArrowLeft,
  Eye,
  Landmark,
  Copy,
} from 'lucide-react';
import { getImagePath } from '@/utils/helpers';
import { cn } from '@/lib/utils';

type InfoRow = { label: string; value: ReactNode };

function isEmpty(value: ReactNode) {
  return value === null || value === undefined || value === '' || value === '—';
}

function InfoTable({ rows, hideEmpty = true }: { rows: InfoRow[]; hideEmpty?: boolean }) {
  const visible = hideEmpty ? rows.filter((r) => !isEmpty(r.value)) : rows;

  if (visible.length === 0) {
    return <p className="text-xs text-slate-400 py-2">—</p>;
  }

  return (
    <div className="divide-y divide-slate-100">
      {visible.map((row, i) => (
        <div key={i} className="grid grid-cols-[minmax(110px,32%)_1fr] gap-x-3 py-1.5 text-sm leading-snug">
          <span className="text-xs text-slate-500">{row.label}</span>
          <span className="font-medium text-slate-900 break-words">{row.value}</span>
        </div>
      ))}
    </div>
  );
}

function Panel({
  title,
  children,
  className,
}: {
  title: string;
  children: ReactNode;
  className?: string;
}) {
  return (
    <div className={cn('rounded-lg border border-slate-200/80 bg-white dark:bg-gray-900', className)}>
      <div className="px-3 py-2 border-b border-slate-100 bg-slate-50/60">
        <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-600">{title}</h3>
      </div>
      <div className="px-3 py-2">{children}</div>
    </div>
  );
}

function FlagPill({ label, on, yesLabel, noLabel }: { label: string; on: boolean; yesLabel: string; noLabel: string }) {
  return (
    <span
      className={cn(
        'inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-medium',
        on ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200' : 'bg-slate-100 text-slate-500',
      )}
    >
      {label}: {on ? yesLabel : noLabel}
    </span>
  );
}

export default function EmployeeShow() {
  const { t } = useTranslation();
  const { auth, employee, employeeSalary, salaryComponents = [] } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const getInitials = useInitials();
  const emp = employee?.employee;
  /** Route key for show/edit/update/toggle — employees table id (not user id). */
  const employeeRecordId = emp?.id as number | undefined;
  const userId = employee?.id as number;

  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [activeTab, setActiveTab] = useState('overview');
  const [showEmptyFields, setShowEmptyFields] = useState(false);

  const formatDate = (date?: string | null) => {
    if (!date) return null;
    return window.appSettings?.formatDateTime(date, false) || new Date(date).toLocaleDateString();
  };

  const copyText = (text: string, label: string) => {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => toast.success(t(':label copied', { label })));
  };

  const weekOffLabel = () => {
    if (!emp?.week_off) return null;
    if (emp.week_off_type === 'monthly' || (emp.week_off || '').startsWith('{')) {
      try {
        const parsed = JSON.parse(emp.week_off);
        const allDays = Object.values(parsed).flat() as string[];
        const uniqueDays = [...new Set(allDays.map((d) => d.substring(0, 3)))].join(', ');
        return `${t('Monthly')} (${uniqueDays})`;
      } catch {
        return emp.week_off;
      }
    }
    return emp.week_off;
  };

  const shiftLabel = emp?.shift?.short_code || emp?.shift?.name || null;

  const earnings = salaryComponents.filter((c: any) => c.type === 'earning');
  const deductions = salaryComponents.filter((c: any) => c.type === 'deduction');

  const netPayable = (
    parseFloat(emp?.gross_salary || '0') -
    Object.keys(employeeSalary?.components || {}).reduce((acc, id) => {
      const comp = salaryComponents.find((c: any) => c.id.toString() === id.toString());
      return comp?.type === 'deduction' ? acc + parseFloat(employeeSalary.components[id] || '0') : acc;
    }, 0)
  ).toFixed(2);

  const canToggleStatus =
    hasPermission(permissions, 'toggle-status-employees') || hasPermission(permissions, 'edit-employees');

  const handleEdit = () => {
    if (!employeeRecordId) {
      toast.error(t('Employee record not found'));
      return;
    }
    router.get(route('hr.employees.edit', employeeRecordId));
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting employee...'));
    router.delete(route('hr.employees.destroy', userId), {
      onSuccess: (page: any) => {
        toast.dismiss();
        if (page.props.flash.success) toast.success(t(page.props.flash.success));
        else if (page.props.flash.error) toast.error(t(page.props.flash.error));
        router.get(route('hr.employees.index'));
      },
      onError: (errors) => {
        toast.dismiss();
        toast.error(typeof errors === 'string' ? t(errors) : t('Failed to delete employee'));
      },
    });
  };

  const handleToggleStatus = () => {
    if (!employeeRecordId) return;
    const newStatus = employee.status === 'active' ? 'inactive' : 'active';
    toast.loading(newStatus === 'active' ? t('Activating employee...') : t('Deactivating employee...'));
    router.put(route('hr.employees.toggle-status', employeeRecordId), {}, {
      onSuccess: (page: any) => {
        toast.dismiss();
        if (page.props.flash.success) toast.success(t(page.props.flash.success));
        else if (page.props.flash.error) toast.error(t(page.props.flash.error));
      },
      onError: () => {
        toast.dismiss();
        toast.error(t('Failed to update employee status'));
      },
    });
  };

  const handleDeleteDocument = (documentId: number) => {
    toast.loading(t('Deleting document...'));
    router.delete(route('hr.employees.documents.destroy', [userId, documentId]), {
      onSuccess: (page: any) => {
        toast.dismiss();
        if (page.props.flash.success) toast.success(t(page.props.flash.success));
        else if (page.props.flash.error) toast.error(t(page.props.flash.error));
      },
      onError: () => {
        toast.dismiss();
        toast.error(t('Failed to delete document'));
      },
    });
  };

  const pageActions = [
    {
      label: t('Back'),
      icon: <ArrowLeft className="h-3.5 w-3.5" />,
      variant: 'outline' as const,
      onClick: () => router.get(route('hr.employees.index')),
    },
  ];

  if (hasPermission(permissions, 'edit-employees')) {
    pageActions.push({
      label: t('Edit'),
      icon: <Edit className="h-3.5 w-3.5" />,
      variant: 'default' as const,
      onClick: handleEdit,
    });
  }

  if (hasPermission(permissions, 'view-employees')) {
    pageActions.push({
      label: t('PDF'),
      icon: <FileText className="h-3.5 w-3.5" />,
      variant: 'outline' as const,
      onClick: () => window.open(route('hr.employees.export', userId), '_blank'),
    });
  }

  if (hasPermission(permissions, 'delete-employees')) {
    pageActions.push({
      label: t('Delete'),
      icon: <Trash2 className="h-3.5 w-3.5" />,
      variant: 'outline' as const,
      onClick: () => setIsDeleteModalOpen(true),
    });
  }

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Employees'), href: route('hr.employees.index') },
    { title: emp?.employee_id ? `${emp.employee_id} · ${employee?.name}` : employee?.name || t('Employee') },
  ];

  const workRows: InfoRow[] = [
    { label: t('Branch'), value: emp?.branch?.name },
    { label: t('Department'), value: emp?.department?.name },
    { label: t('Designation'), value: emp?.designation?.name },
    { label: t('Category'), value: emp?.category?.name },
    { label: t('Section'), value: emp?.section?.name },
    { label: t('Shift'), value: shiftLabel },
    { label: t('Week Off'), value: weekOffLabel() },
    { label: t('Working Days'), value: emp?.working_days },
    { label: t('Joining Date'), value: formatDate(emp?.date_of_joining) },
    { label: t('Confirm Date'), value: formatDate(emp?.confirm_date) },
    { label: t('Employment Status'), value: emp?.employment_status },
    { label: t('P / OP Status'), value: emp?.po_status },
    { label: t('Daily Worker'), value: emp?.daily_option ? t('Yes') : t('No') },
    { label: t('HOD'), value: emp?.hod_flag ? t('Yes') : t('No') },
    { label: t('Education'), value: emp?.education },
    { label: t('Experience'), value: emp?.experience },
  ];

  const personalRows: InfoRow[] = [
    { label: t('Father Name'), value: emp?.father_name },
    { label: t('Gender'), value: emp?.gender },
    { label: t('Date of Birth'), value: formatDate(emp?.date_of_birth) },
    { label: t('Marital Status'), value: emp?.marital_status },
    { label: t('Wedding Date'), value: formatDate(emp?.wedding_date) },
    { label: t('Blood Group'), value: emp?.blood_group },
    { label: t('Height'), value: emp?.height },
    { label: t('Weight'), value: emp?.weight ? `${emp.weight} kg` : null },
  ];

  const pageTitle = emp?.employee_id
    ? `${employee?.name} (${emp.employee_id})`
    : employee?.name || t('Employee');

  return (
    <PageTemplate
      title={pageTitle}
      url={`/employees/${employeeRecordId ?? userId}`}
      actions={pageActions}
      breadcrumbs={breadcrumbs}
      noPadding
    >
      {/* Compact profile strip */}
      <div className="rounded-lg border border-slate-200/80 bg-white dark:bg-gray-900 mb-2">
        <div className="flex flex-wrap items-center gap-x-3 gap-y-2 px-3 py-2.5">
          <div className="h-11 w-11 shrink-0 rounded-full bg-primary/10 text-primary flex items-center justify-center text-sm font-bold overflow-hidden">
            {employee.avatar ? (
              <img src={getImagePath(employee.avatar)} alt={employee.name} className="h-full w-full object-cover" />
            ) : (
              getInitials(employee.name)
            )}
          </div>

          {emp?.employee_id && (
            <button
              type="button"
              onClick={() => copyText(String(emp.employee_id), t('Emp code'))}
              className="inline-flex items-center gap-1 rounded border border-primary/25 bg-primary/10 px-2 py-0.5 font-mono text-sm font-bold text-primary hover:bg-primary/15"
              title={t('Click to copy')}
            >
              {emp.employee_id}
              <Copy className="h-3 w-3 opacity-60" />
            </button>
          )}

          <button
            type="button"
            onClick={() => canToggleStatus && handleToggleStatus()}
            disabled={!canToggleStatus}
            className={cn(
              'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase',
              employee.status === 'active'
                ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200'
                : 'bg-slate-100 text-slate-500 ring-1 ring-slate-200',
              canToggleStatus && 'cursor-pointer hover:opacity-80',
            )}
          >
            <span className={cn('h-1.5 w-1.5 rounded-full', employee.status === 'active' ? 'bg-emerald-500' : 'bg-slate-400')} />
            {employee.status === 'active' ? t('Active') : t('Inactive')}
          </button>

          <span className="hidden sm:inline text-slate-300">|</span>

          <div className="flex flex-wrap items-center gap-1.5 min-w-0 text-xs text-slate-600">
            {emp?.designation?.name && (
              <span className="rounded bg-slate-100 px-1.5 py-0.5 font-medium text-slate-700">{emp.designation.name}</span>
            )}
            {emp?.department?.name && (
              <span className="rounded bg-slate-100 px-1.5 py-0.5">{emp.department.name}</span>
            )}
            {emp?.category?.name && (
              <span className="rounded bg-slate-100 px-1.5 py-0.5">{emp.category.name}</span>
            )}
            {shiftLabel && <span className="rounded bg-blue-50 px-1.5 py-0.5 text-blue-700">{shiftLabel}</span>}
          </div>

          <div className="flex flex-wrap items-center gap-3 ml-auto text-xs text-slate-600">
            {emp?.phone && (
              <button type="button" onClick={() => copyText(emp.phone, t('Phone'))} className="inline-flex items-center gap-1 hover:text-primary">
                <Phone className="h-3 w-3" />
                {emp.phone}
              </button>
            )}
            {employee.email && (
              <span className="inline-flex items-center gap-1 max-w-[180px] truncate">
                <Mail className="h-3 w-3 shrink-0" />
                {employee.email}
              </span>
            )}
            {emp?.date_of_joining && (
              <span className="inline-flex items-center gap-1">
                <Briefcase className="h-3 w-3" />
                {formatDate(emp.date_of_joining)}
              </span>
            )}
            <span className="font-semibold text-slate-800">₹{emp?.gross_salary || '0'}</span>
          </div>
        </div>
      </div>

      {/* Tabs */}
      <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
        <div className="flex items-center justify-between gap-2 mb-2">
          <TabsList className="h-8 p-0.5 bg-slate-100/80 rounded-md">
            <TabsTrigger value="overview" className="h-7 text-xs px-2.5 rounded data-[state=active]:bg-white data-[state=active]:shadow-sm">
              {t('Overview')}
            </TabsTrigger>
            <TabsTrigger value="contact" className="h-7 text-xs px-2.5 rounded data-[state=active]:bg-white data-[state=active]:shadow-sm">
              {t('Contact')}
            </TabsTrigger>
            <TabsTrigger value="payroll" className="h-7 text-xs px-2.5 rounded data-[state=active]:bg-white data-[state=active]:shadow-sm">
              {t('Payroll')}
            </TabsTrigger>
            <TabsTrigger value="documents" className="h-7 text-xs px-2.5 rounded data-[state=active]:bg-white data-[state=active]:shadow-sm">
              {t('Documents')}
            </TabsTrigger>
          </TabsList>

          <button
            type="button"
            onClick={() => setShowEmptyFields((v) => !v)}
            className="text-[10px] text-slate-500 hover:text-primary whitespace-nowrap"
          >
            {showEmptyFields ? t('Hide empty fields') : t('Show empty fields')}
          </button>
        </div>

        <TabsContent value="overview" className="mt-0">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-2">
            <Panel title={t('Work Details')}>
              <InfoTable rows={workRows} hideEmpty={!showEmptyFields} />
            </Panel>
            <Panel title={t('Personal')}>
              <InfoTable rows={personalRows} hideEmpty={!showEmptyFields} />
            </Panel>
          </div>
        </TabsContent>

        <TabsContent value="contact" className="mt-0">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-2">
            <Panel title={t('Contact & Address')}>
              <InfoTable
                hideEmpty={!showEmptyFields}
                rows={[
                  { label: t('Phone'), value: emp?.phone },
                  { label: t('Phone 2'), value: emp?.phone_2 },
                  { label: t('Email'), value: employee.email },
                  { label: t('Permanent Address'), value: emp?.permanent_address || emp?.address_line_1 },
                  { label: t('Local Address'), value: emp?.address_line_2 },
                  { label: t('City'), value: emp?.city },
                  { label: t('State'), value: emp?.state },
                  { label: t('Pincode'), value: emp?.postal_code },
                  { label: t('Place'), value: emp?.place },
                ]}
              />
            </Panel>
            <Panel title={t('Government IDs')}>
              <InfoTable
                hideEmpty={!showEmptyFields}
                rows={[
                  { label: t('PAN'), value: emp?.pan_card_number },
                  { label: t('Aadhaar'), value: emp?.aadhar_card_number },
                  { label: t('UAN'), value: emp?.uan_number },
                  { label: t('Driving License'), value: emp?.driving_license || emp?.driving_licence },
                  { label: t('Election Card'), value: emp?.election_card },
                ]}
              />
            </Panel>
          </div>
        </TabsContent>

        <TabsContent value="payroll" className="mt-0 space-y-2">
          <Panel title={t('Bank & Salary')}>
            <InfoTable
              hideEmpty={!showEmptyFields}
              rows={[
                { label: t('Bank Name'), value: emp?.bank_name },
                { label: t('Account No.'), value: emp?.account_number },
                { label: t('IFSC'), value: emp?.ifsc_code || emp?.bank_identifier_code },
                { label: t('Account Type'), value: emp?.account_type || emp?.bank_type },
                { label: t('PF No.'), value: emp?.pf_number },
                { label: t('ESIC No.'), value: emp?.esic_number },
              ]}
            />

            <div className="mt-2 grid grid-cols-1 md:grid-cols-2 gap-2">
              <div className="rounded border border-slate-100 overflow-hidden">
                <div className="px-2 py-1 bg-emerald-50/80 text-[10px] font-semibold uppercase text-emerald-700">{t('Earnings')}</div>
                <div className="divide-y divide-slate-50">
                  {earnings.map((comp: any) => (
                    <div key={comp.id} className="flex justify-between px-2 py-1 text-xs">
                      <span className="text-slate-600">{comp.name}</span>
                      <span className="font-medium">₹{employeeSalary?.components?.[comp.id] || '0'}</span>
                    </div>
                  ))}
                  <div className="flex justify-between px-2 py-1.5 text-xs font-semibold bg-emerald-50/50">
                    <span>{t('Gross')}</span>
                    <span className="text-emerald-700">₹{emp?.gross_salary || '0'}</span>
                  </div>
                </div>
              </div>

              <div className="rounded border border-slate-100 overflow-hidden">
                <div className="px-2 py-1 bg-red-50/80 text-[10px] font-semibold uppercase text-red-700">{t('Deductions')}</div>
                <div className="divide-y divide-slate-50">
                  {deductions.length > 0 ? (
                    deductions.map((comp: any) => (
                      <div key={comp.id} className="flex justify-between px-2 py-1 text-xs">
                        <span className="text-slate-600">{comp.name}</span>
                        <span className="font-medium">₹{employeeSalary?.components?.[comp.id] || '0'}</span>
                      </div>
                    ))
                  ) : (
                    <div className="px-2 py-2 text-xs text-slate-400">{t('No deductions')}</div>
                  )}
                  <div className="flex justify-between px-2 py-1.5 text-xs font-semibold border-t border-slate-200">
                    <span>{t('Net Payable')}</span>
                    <span className="text-primary">₹{netPayable}</span>
                  </div>
                </div>
              </div>
            </div>

            <div className="flex flex-wrap gap-1 mt-2">
              <FlagPill label={t('PF')} on={!!emp?.pf_flag} yesLabel={t('Yes')} noLabel={t('No')} />
              <FlagPill label={t('ESIC')} on={!!emp?.esic_flag} yesLabel={t('Yes')} noLabel={t('No')} />
              <FlagPill label={t('PTax')} on={!!emp?.ptax_flag} yesLabel={t('Yes')} noLabel={t('No')} />
              <FlagPill label={t('Bonus')} on={!!emp?.bonus_flag} yesLabel={t('Yes')} noLabel={t('No')} />
              <FlagPill label={t('OT')} on={!!emp?.ot_flag} yesLabel={t('Yes')} noLabel={t('No')} />
              {emp?.ot_hours ? (
                <span className="text-[10px] text-slate-500 px-1">{t('OT Hours')}: {emp.ot_hours}</span>
              ) : null}
            </div>
          </Panel>
        </TabsContent>

        <TabsContent value="documents" className="mt-0">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-2">
            <Panel title={t('Nominees')}>
              {emp?.nominees?.length > 0 ? (
                <div className="divide-y divide-slate-100">
                  {emp.nominees.map((nominee: any, idx: number) => (
                    <div key={idx} className="py-1.5 text-sm">
                      <p className="font-medium text-slate-900">{nominee.name}</p>
                      <p className="text-xs text-slate-500">
                        {nominee.relation || '—'} · {nominee.percentage}%
                        {nominee.aadhar_number ? ` · ${t('Aadhaar')}: ${nominee.aadhar_number}` : ''}
                      </p>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-xs text-slate-400 py-2">{t('No nominees added')}</p>
              )}
            </Panel>

            <Panel title={t('Documents')}>
              {emp?.documents?.length > 0 ? (
                <div className="divide-y divide-slate-100">
                  {emp.documents.map((document: any) => (
                    <div key={document.id} className="flex items-center justify-between gap-2 py-1.5">
                      <div className="min-w-0">
                        <p className="text-sm font-medium text-slate-800 truncate">{document.document_type?.name}</p>
                        <p className="text-[10px] text-slate-500">{document.id_number || t('No ID')}</p>
                      </div>
                      <div className="flex shrink-0">
                        <Button
                          size="icon"
                          variant="ghost"
                          className="h-7 w-7 text-blue-600"
                          onClick={() => window.open(getImagePath(document.file_path), '_blank')}
                        >
                          <Eye className="h-3.5 w-3.5" />
                        </Button>
                        <Button
                          size="icon"
                          variant="ghost"
                          className="h-7 w-7 text-primary"
                          onClick={() =>
                            window.open(route('hr.employees.documents.download', [userId, document.id]), '_blank')
                          }
                        >
                          <Download className="h-3.5 w-3.5" />
                        </Button>
                        {hasPermission(permissions, 'edit-employees') && (
                          <Button
                            size="icon"
                            variant="ghost"
                            className="h-7 w-7 text-red-600"
                            onClick={() => handleDeleteDocument(document.id)}
                          >
                            <Trash2 className="h-3.5 w-3.5" />
                          </Button>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <p className="text-xs text-slate-400 py-2">{t('No documents uploaded')}</p>
              )}
            </Panel>
          </div>
        </TabsContent>
      </Tabs>

      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={employee?.name || ''}
        entityName="employee"
      />
    </PageTemplate>
  );
}
