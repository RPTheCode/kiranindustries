import { useEffect, useMemo, useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { router, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Combobox } from '@/components/ui/combobox';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Loader2 } from 'lucide-react';
import { toast } from '@/components/custom-toast';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

function formatCurrency(value: number) {
  return `₹${Number(value).toLocaleString('en-IN', { minimumFractionDigits: 2 })}`;
}

type GuarantorRow = {
  name: string;
  employee_code: string;
  department: string;
  guarantor_employee_id: string;
  manual_entry?: boolean;
};

type GuarantorEmployeeOption = {
  value: string;
  label: string;
  name: string;
  employee_code: string;
  department: string;
};

const emptyGuarantor = (): GuarantorRow => ({
  name: '',
  employee_code: '',
  department: '',
  guarantor_employee_id: '',
  manual_entry: false,
});

export default function SalaryLoanForm() {
  const { t } = useTranslation();
  const { loan, employees = [] } = usePage().props as any;
  const isEdit = Boolean(loan?.id);

  const [eligibility, setEligibility] = useState<any>(null);
  const [loadingEligibility, setLoadingEligibility] = useState(false);

  const initialGuarantors: GuarantorRow[] = loan?.guarantors?.length === 3
    ? loan.guarantors.map((g: any) => ({
        name: g.name || '',
        employee_code: g.employee_code || '',
        department: g.department || '',
        guarantor_employee_id: g.guarantor_employee_id?.toString() || '',
        manual_entry: !g.guarantor_employee_id,
      }))
    : [emptyGuarantor(), emptyGuarantor(), emptyGuarantor()];

  const { data, setData, processing, errors } = useForm({
    employee_id: loan?.employee_id?.toString() || '',
    application_date: loan?.application_date || new Date().toISOString().split('T')[0],
    requested_amount: loan?.requested_amount?.toString() || '',
    installment_count: String(loan?.installment_count || 3),
    purpose: loan?.purpose || '',
    remarks: loan?.remarks || '',
    submit: false,
    guarantors: initialGuarantors,
  });

  const pageErrors = (usePage().props as { errors?: Record<string, string> }).errors ?? {};
  const fieldError = (field: string) => errors[field as keyof typeof errors] || pageErrors[field];

  const employeeOptions = useMemo(
    () => employees.map((e: any) => ({
      value: e.id.toString(),
      label: e.label || e.name,
    })),
    [employees]
  );

  const applicantEmployee = useMemo(
    () => employees.find((e: { id?: number }) => e.id?.toString() === data.employee_id),
    [employees, data.employee_id]
  );

  const applicantEmployeeRecordId = applicantEmployee?.employee_record_id?.toString() || '';
  const applicantEmployeeCode = (applicantEmployee?.employee_code || '').trim().toLowerCase();

  const guarantorEmployeeOptions = useMemo<GuarantorEmployeeOption[]>(
    () => employees
      .filter((e: { employee_record_id?: number }) => e.employee_record_id)
      .filter((e: { employee_record_id?: number }) => e.employee_record_id?.toString() !== applicantEmployeeRecordId)
      .map((e: {
        employee_record_id: number;
        label?: string;
        name: string;
        employee_code?: string;
        department?: string;
      }) => ({
        value: e.employee_record_id.toString(),
        label: e.label || `${e.name}${e.employee_code ? ` (${e.employee_code})` : ''}`,
        name: e.name,
        employee_code: e.employee_code || '',
        department: e.department || '',
      })),
    [employees, applicantEmployeeRecordId]
  );

  const guarantorEmployeeMap = useMemo(
    () => new Map<string, GuarantorEmployeeOption>(guarantorEmployeeOptions.map((e) => [e.value, e])),
    [guarantorEmployeeOptions]
  );

  const guarantorOptionsForIndex = (index: number) => {
    const usedElsewhere = data.guarantors
      .map((g, i) => (i !== index && g.guarantor_employee_id ? g.guarantor_employee_id : ''))
      .filter(Boolean);

    return guarantorEmployeeOptions.filter((opt) => !usedElsewhere.includes(opt.value));
  };

  const isApplicantGuarantor = (g: GuarantorRow) => {
    if (applicantEmployeeRecordId && g.guarantor_employee_id === applicantEmployeeRecordId) {
      return true;
    }
    if (applicantEmployeeCode && g.employee_code?.trim().toLowerCase() === applicantEmployeeCode) {
      return true;
    }
    return false;
  };

  const loadEligibility = async (employeeId: string, date: string) => {
    if (!employeeId || !date) return;
    setLoadingEligibility(true);
    try {
      const response = await axios.get(route('hr.salary-loans.eligibility', employeeId), { params: { date } });
      setEligibility(response.data);
    } catch {
      setEligibility(null);
    } finally {
      setLoadingEligibility(false);
    }
  };

  useEffect(() => {
    if (data.employee_id) loadEligibility(data.employee_id, data.application_date);
  }, [data.employee_id, data.application_date]);

  useEffect(() => {
    if (!applicantEmployeeRecordId && !applicantEmployeeCode) return;

    const hasApplicantAsGuarantor = data.guarantors.some(isApplicantGuarantor);
    if (!hasApplicantAsGuarantor) return;

    const next = data.guarantors.map((g) => (isApplicantGuarantor(g) ? emptyGuarantor() : g));
    setData('guarantors', next);
    toast.error(t('Loan applicant cannot be selected as their own guarantor.'));
  }, [data.employee_id, applicantEmployeeRecordId, applicantEmployeeCode]);

  const updateGuarantor = (index: number, field: keyof GuarantorRow, value: string | boolean) => {
    const next = [...data.guarantors];
    next[index] = { ...next[index], [field]: value };
    setData('guarantors', next);
  };

  const selectGuarantorEmployee = (index: number, employeeRecordId: string) => {
    const next = [...data.guarantors];
    if (!employeeRecordId) {
      next[index] = { ...emptyGuarantor(), manual_entry: false };
      setData('guarantors', next);
      return;
    }

    const selected = guarantorEmployeeMap.get(employeeRecordId);
    if (!selected) return;

    next[index] = {
      name: selected.name,
      employee_code: selected.employee_code,
      department: selected.department,
      guarantor_employee_id: employeeRecordId,
      manual_entry: false,
    };
    setData('guarantors', next);
  };

  const enableManualGuarantor = (index: number) => {
    const next = [...data.guarantors];
    next[index] = {
      name: '',
      employee_code: '',
      department: '',
      guarantor_employee_id: '',
      manual_entry: true,
    };
    setData('guarantors', next);
  };

  const suggestedEmi = useMemo(() => {
    const amount = parseFloat(data.requested_amount);
    const count = parseInt(data.installment_count, 10);
    if (!amount || !count) return 0;
    return Math.round((amount / count) * 100) / 100;
  }, [data.requested_amount, data.installment_count]);

  const submitForm = (submit: boolean) => {
    const amount = parseFloat(data.requested_amount);
    if (eligibility && amount > eligibility.max_loan_amount) {
      toast.error(t('Loan amount cannot exceed one month gross of {{amount}}', {
        amount: formatCurrency(eligibility.max_loan_amount),
      }));
      return;
    }
    if (eligibility && !eligibility.can_apply) {
      toast.error(t('Maximum active loans per year reached.'));
      return;
    }
    if (!data.employee_id) {
      toast.error(t('Please select an employee.'));
      return;
    }
    if (data.guarantors.some((g) => !g.name?.trim())) {
      toast.error(t('All 3 guarantor names are required.'));
      return;
    }
    if (data.guarantors.some(isApplicantGuarantor)) {
      toast.error(t('Loan applicant cannot be their own guarantor.'));
      return;
    }

    const guarantorIds = data.guarantors.map((g) => g.guarantor_employee_id).filter(Boolean);
    if (new Set(guarantorIds).size !== guarantorIds.length) {
      toast.error(t('Each guarantor must be a different employee.'));
      return;
    }

    const guarantorCodes = data.guarantors
      .filter((g) => g.manual_entry && g.employee_code?.trim())
      .map((g) => g.employee_code.trim().toLowerCase());
    if (new Set(guarantorCodes).size !== guarantorCodes.length) {
      toast.error(t('Each guarantor must be a different person.'));
      return;
    }

    const url = isEdit ? route('hr.salary-loans.update', loan.id) : route('hr.salary-loans.store');
    router[isEdit ? 'put' : 'post'](url, { ...data, submit }, {
      preserveScroll: true,
      onError: (errs: Record<string, string>) => {
        const first = errs.requested_amount || errs.purpose || Object.values(errs)[0];
        toast.error(typeof first === 'string' ? first : t('Failed to save loan request'));
      },
    });
  };

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Salary Loan'), href: route('hr.salary-loans.index') },
    { title: isEdit ? t('Edit Loan') : t('New Loan') },
  ];

  return (
    <PageTemplate title={isEdit ? t('Edit Salary Loan') : t('New Salary Loan')} breadcrumbs={breadcrumbs} noPadding>
      <div className="max-w-5xl mx-auto p-4 space-y-4">
        <Card>
          <CardHeader><CardTitle>{t('Employee Information')}</CardTitle></CardHeader>
          <CardContent className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {!isEdit && (
              <div className="md:col-span-2">
                <Label>{t('Employee')}</Label>
                <Combobox
                  options={employeeOptions}
                  value={data.employee_id}
                  onChange={(value) => setData('employee_id', value)}
                  placeholder={t('Select employee...')}
                />
                {fieldError('employee_id') && <p className="text-sm text-red-500 mt-1">{fieldError('employee_id')}</p>}
              </div>
            )}
            <div>
              <Label>{t('Application Date')}</Label>
              <Input type="date" value={data.application_date} onChange={(e) => setData('application_date', e.target.value)} />
            </div>
            {loadingEligibility ? (
              <div className="flex items-center gap-2 text-muted-foreground"><Loader2 className="h-4 w-4 animate-spin" /> {t('Loading eligibility...')}</div>
            ) : eligibility ? (
              <div className="md:col-span-2 grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                <div><span className="text-muted-foreground">{t('Month Gross')}</span><div className="font-semibold">{formatCurrency(eligibility.present_salary)}</div></div>
                <div><span className="text-muted-foreground">{t('Max Loan')}</span><div className="font-semibold text-green-700">{formatCurrency(eligibility.max_loan_amount)}</div></div>
                <div><span className="text-muted-foreground">{t('Loans This Year')}</span><div className="font-semibold">{eligibility.active_loans_this_year} / {eligibility.max_loans_per_year}</div></div>
                <div><span className="text-muted-foreground">{t('Suggested EMI')}</span><div className="font-semibold">{formatCurrency(eligibility.suggested_emi)}</div></div>
              </div>
            ) : null}
          </CardContent>
        </Card>

        <Card>
          <CardHeader><CardTitle>{t('Loan Details')}</CardTitle></CardHeader>
          <CardContent className="space-y-4">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <Label>{t('Loan Amount')}</Label>
                <Input
                  type="number"
                  min="1"
                  step="0.01"
                  value={data.requested_amount}
                  onChange={(e) => setData('requested_amount', e.target.value)}
                />
                {eligibility && (
                  <p className="text-xs text-muted-foreground mt-1">{t('Maximum')}: {formatCurrency(eligibility.max_loan_amount)}</p>
                )}
                {fieldError('requested_amount') && <p className="text-sm text-red-500 mt-1">{fieldError('requested_amount')}</p>}
              </div>
              <div>
                <Label>{t('Installments (months)')}</Label>
                <Select value={data.installment_count} onValueChange={(v) => setData('installment_count', v)}>
                  <SelectTrigger><SelectValue /></SelectTrigger>
                  <SelectContent>
                    {[1, 2, 3, 4, 5, 6].map((n) => (
                      <SelectItem key={n} value={String(n)}>{n} {t('months')}</SelectItem>
                    ))}
                  </SelectContent>
                </Select>
                {suggestedEmi > 0 && (
                  <p className="text-xs text-muted-foreground mt-1">{t('EMI approx')}: {formatCurrency(suggestedEmi)}</p>
                )}
              </div>
            </div>
            <div>
              <Label>{t('Purpose')}</Label>
              <Textarea value={data.purpose} onChange={(e) => setData('purpose', e.target.value)} rows={3} />
              {fieldError('purpose') && <p className="text-sm text-red-500 mt-1">{fieldError('purpose')}</p>}
            </div>
            <div>
              <Label>{t('Remarks')}</Label>
              <Textarea value={data.remarks} onChange={(e) => setData('remarks', e.target.value)} rows={2} />
            </div>
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('Guarantors (Surety)')}</CardTitle>
            {data.employee_id && (
              <p className="text-xs text-muted-foreground font-normal">
                {t('The loan applicant cannot be selected as a guarantor. Choose 3 different employees.')}
              </p>
            )}
          </CardHeader>
          <CardContent className="space-y-4">
            {data.guarantors.map((g, index) => {
              const isEmployeeSelected = Boolean(g.guarantor_employee_id) && !g.manual_entry;

              return (
                <div key={index} className="grid grid-cols-1 md:grid-cols-3 gap-3 rounded-md border p-3">
                  <div className="md:col-span-3 flex flex-wrap items-center justify-between gap-2">
                    <span className="text-xs font-bold text-slate-600">{t('Guarantor')} {index + 1}</span>
                    {!g.manual_entry ? (
                      <button
                        type="button"
                        className="text-xs text-blue-700 hover:underline"
                        onClick={() => enableManualGuarantor(index)}
                      >
                        {t('Enter manually instead')}
                      </button>
                    ) : (
                      <button
                        type="button"
                        className="text-xs text-blue-700 hover:underline"
                        onClick={() => {
                          const next = [...data.guarantors];
                          next[index] = emptyGuarantor();
                          setData('guarantors', next);
                        }}
                      >
                        {t('Search employee instead')}
                      </button>
                    )}
                  </div>

                  {g.manual_entry ? (
                    <>
                      <div>
                        <Label>{t('Name')}</Label>
                        <Input value={g.name} onChange={(e) => updateGuarantor(index, 'name', e.target.value)} />
                      </div>
                      <div>
                        <Label>{t('Employee Code')}</Label>
                        <Input value={g.employee_code} onChange={(e) => updateGuarantor(index, 'employee_code', e.target.value)} />
                      </div>
                      <div>
                        <Label>{t('Department')}</Label>
                        <Input value={g.department} onChange={(e) => updateGuarantor(index, 'department', e.target.value)} />
                      </div>
                    </>
                  ) : (
                    <>
                      <div className="md:col-span-3">
                        <Label>{t('Employee')}</Label>
                        <Combobox
                          options={guarantorOptionsForIndex(index)}
                          value={g.guarantor_employee_id}
                          onChange={(value) => selectGuarantorEmployee(index, value)}
                          placeholder={t('Search by name or employee code...')}
                          searchPlaceholder={t('Type name or code...')}
                          emptyText={t('No employee found.')}
                        />
                      </div>
                      <div>
                        <Label>{t('Name')}</Label>
                        <Input value={g.name} readOnly className="bg-muted/50" />
                      </div>
                      <div>
                        <Label>{t('Employee Code')}</Label>
                        <Input value={g.employee_code} readOnly className="bg-muted/50" />
                      </div>
                      <div>
                        <Label>{t('Department')}</Label>
                        <Input value={g.department} readOnly className="bg-muted/50" />
                      </div>
                    </>
                  )}

                  {fieldError(`guarantors.${index}.name`) && (
                    <p className="md:col-span-3 text-sm text-red-500">{fieldError(`guarantors.${index}.name`)}</p>
                  )}
                  {!g.manual_entry && !isEmployeeSelected && (
                    <p className="md:col-span-3 text-xs text-muted-foreground">
                      {t('Select an employee — name, code and department will fill automatically.')}
                    </p>
                  )}
                </div>
              );
            })}
          </CardContent>
        </Card>

        <div className="flex flex-wrap gap-2 justify-end pb-8">
          <Button variant="outline" onClick={() => router.visit(route('hr.salary-loans.index'))}>{t('Cancel')}</Button>
          <Button variant="secondary" disabled={processing} onClick={() => submitForm(false)}>{t('Save Draft')}</Button>
          <Button disabled={processing} onClick={() => submitForm(true)}>{t('Submit')}</Button>
        </div>
      </div>
    </PageTemplate>
  );
}
