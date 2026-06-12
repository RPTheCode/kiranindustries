import { useState, useEffect, useCallback } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import {
  ChevronRight, ChevronLeft, Save, User, MapPin,
  Briefcase, CreditCard, ShieldCheck, Plus, Trash2,
  Phone, Mail, FileText, Landmark, Users, Camera,
  Info, Calendar, Search
} from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';
import MediaPicker from '@/components/MediaPicker';
import { Combobox } from '@/components/ui/combobox';
import { toast } from '@/components/custom-toast';
import { MultiSelect } from '@/components/ui/multi-select';
import { useTranslation } from 'react-i18next';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Badge } from '@/components/ui/badge';
import {
  customComponents,
  hasCustomAssignment,
  isPrimaryComponent,
  primaryComponents,
  resolveAssignedComponentIds,
  resolveComponentsForEmployee,
  storedCustomComponentIds,
} from '@/utils/salary-component-assignment';
import { EmployeeGrossSalarySection } from '@/pages/hr/employees/components/EmployeeGrossSalarySection';
import type { GrossInputMode } from '@/utils/salary-gross-split';

export default function CreateEmployee() {
  const { t } = useTranslation();
  const {
    departments = [],
    designations = [],
    sections = [],
    categories = [],
    shifts = [],
    branches = [],
    documentTypes = [],
    attendancePolicies = [],
    skills = [],
    banks = [],
    salaryComponents = [],
    branchPayrollSettings = null,
    auth,
    nextEmployeeId,
    resignReasons = [],
    overtimeOptions = []
  } = usePage().props as any;

  const [currentStep, setCurrentStep] = useState(1);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [customizeSalaryComponents, setCustomizeSalaryComponents] = useState(false);

  const [formData, setFormData] = useState<any>({
    emy_code: nextEmployeeId || '',
    avatar: '',
    name: '',
    father_name: '',
    gender: 'male',
    marital_status: 'single',
    wedding_date: '',
    date_of_birth: '',
    blood_group: '',
    height: '',
    weight: '',
    address_line_1: '',
    address_line_2: '',
    city: '',
    postal_code: '',
    state: '',
    email: '',
    phone: '',
    phone_2: '',
    pan_card_number: '',
    aadhar_card_number: '',
    election_card_number: '',
    driving_license: '',
    branch_id: branches?.[0]?.id?.toString() || '',
    department_id: '',
    designation_id: '',
    category_id: '',
    section_id: '',
    shift_id: '',
    date_of_joining: new Date().toISOString().split('T')[0],
    confirm_date: '',
    employment_status: 'active',
    daily_option: false,
    gross_input_mode: 'month' as GrossInputMode,
    gross_amount: '',
    hod_flag: false,
    education: '',
    experience: '',
    skill_id: [],
    bank_id: '',
    bank_name: '',
    account_type: 'saving',
    ifsc_code: '',
    account_number: '',
    pf_number: '',
    uan_number: '',
    esic_number: '',
    pf_flag: false,
    esic_flag: false,
    ptax_flag: false,
    bonus_flag: false,
    pt_flag: false,
    pt_hours: '',
    pt_type: '',
    basic_salary: '',
    lta_allowance: '',
    hra_allowance: '',
    pf_basic_salary: '',
    other_allowance: '',
    medical_allowance: '',
    gross_salary: '',
    education_allowance: '',
    it_amount: '',
    nominee_name: '',
    nominee_relation: '',
    documents: [],
    salary_components: {},
    extra_salary_component_ids: [] as number[],
    resign_date: '',
    resign_reason_id: '',
    nominees: [{ name: '', aadhar_number: '', percentage: '', relation: '' }],
    lunch_time: '13:00',
    week_off: ['Sunday'],
    week_off_type: 'weekly',
    monthly_week_offs: {},
    po_status: 'Permanent',
    probation_period: '',
  });

  const [errors, setErrors] = useState<Record<string, string>>({});

  const [branchMasters, setBranchMasters] = useState({
    categories: categories || [],
    departments: departments || [],
    sections: sections || [],
    designations: designations || [],
    shifts: shifts || [],
    skills: skills || [],
  });

  const [branchPayroll, setBranchPayroll] = useState(branchPayrollSettings);

  const loadBranchMasters = async (branchId: string, resetDependents = false) => {
    if (!branchId) return;
    try {
      const res = await fetch(route('hr.employees.branch-masters', { branch_id: branchId }));
      const data = await res.json();
      setBranchMasters({
        categories: data.categories || [],
        departments: data.departments || [],
        sections: data.sections || [],
        designations: data.designations || [],
        shifts: data.shifts || [],
        skills: data.skills || [],
      });
      if (data.payroll_working_days) {
        setBranchPayroll((prev: any) => ({
          ...(prev || {}),
          working_days: data.payroll_working_days,
          working_days_source: data.payroll_working_days_source ?? prev?.working_days_source,
        }));
      }
      if (resetDependents) {
        setFormData((prev: any) => ({
          ...prev,
          category_id: '',
          department_id: '',
          section_id: '',
          designation_id: '',
          shift_id: '',
          skill_id: [],
        }));
      }
    } catch {
      toast.error(t('Failed to load branch options'));
    }
  };

  useEffect(() => {
    if (formData.branch_id) {
      loadBranchMasters(formData.branch_id);
    }
  }, []);

  useEffect(() => {
    if (formData.category_id) {
      // Fetch next ID for category via AJAX
      fetch(route('hr.employees.get-next-id', { category_id: formData.category_id }))
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            setFormData(prev => ({ ...prev, emy_code: data.next_id }));
          }
        })
        .catch(err => console.error('Error fetching next employee ID:', err));
    }
  }, [formData.category_id]);

  useEffect(() => {
    const savedData = localStorage.getItem('employee_create_form');
    if (savedData) {
      try {
        setFormData(prev => ({ ...prev, ...JSON.parse(savedData) }));
      } catch (e) {
        console.error("Error loading saved data", e);
      }
    }
  }, []);

  useEffect(() => {
    localStorage.setItem('employee_create_form', JSON.stringify(formData));
  }, [formData]);

  const handleChange = (name: string, value: any) => {
    setFormData((prev: any) => {
      const newData = { ...prev, [name]: value };

      // Auto-select department if designation is selected
      if (name === 'designation_id' && value) {
        const selectedDesig = (branchMasters.designations || []).find((d: any) => d.id.toString() === value.toString());
        if (selectedDesig && selectedDesig.department_id) {
          const targetDeptId = selectedDesig.department_id.toString();
          const deptExists = (branchMasters.departments || []).some((dept: any) => dept.id.toString() === targetDeptId);
          if (deptExists) {
            newData.department_id = targetDeptId;
          }
        }
      }

      return newData;
    });

    if (errors[name]) {
      const newErrors = { ...errors };
      delete newErrors[name];
      setErrors(newErrors);
    }
  };

  // Filter designations based on selected department for better UX
  const filteredDesignations = formData.department_id
    ? (branchMasters.designations || []).filter((d: any) =>
      d.department_id?.toString() === formData.department_id.toString() ||
      d.id.toString() === formData.designation_id?.toString()
    )
    : (branchMasters.designations || []);

  const primarySalaryComponents = primaryComponents(salaryComponents);
  const customSalaryComponents = customComponents(salaryComponents);
  const extraComponentIds = (formData.extra_salary_component_ids || []).map(Number);
  const applicableSalaryComponents = resolveComponentsForEmployee(salaryComponents, extraComponentIds);

  const recalcGrossFromComponents = (componentsMap: Record<string, string>, assignedIds: number[]) => {
    let earnings = 0;
    resolveComponentsForEmployee(salaryComponents, assignedIds).forEach((comp: any) => {
      if (comp.type !== 'earning') return;
      earnings += parseFloat(componentsMap[comp.id] || '0');
    });
    return earnings.toString();
  };

  const handleCustomizeToggle = (on: boolean) => {
    setCustomizeSalaryComponents(on);
    setFormData((prev: any) => {
      if (on) {
        const nextIds = storedCustomComponentIds(prev.extra_salary_component_ids, salaryComponents);
        return {
          ...prev,
          extra_salary_component_ids: nextIds,
          gross_salary: recalcGrossFromComponents(prev.salary_components, nextIds),
        };
      }
      const newSalaryComponents = { ...prev.salary_components };
      const applicable = resolveComponentsForEmployee(salaryComponents, []);
      const applicableIds = new Set(applicable.map((c: any) => Number(c.id)));
      Object.keys(newSalaryComponents).forEach((id) => {
        if (!applicableIds.has(Number(id))) delete newSalaryComponents[id];
      });
      return {
        ...prev,
        extra_salary_component_ids: [],
        salary_components: newSalaryComponents,
        gross_salary: recalcGrossFromComponents(newSalaryComponents, []),
      };
    });
  };

  const toggleExtraComponent = (id: number, checked: boolean) => {
    const comp = salaryComponents.find((c: any) => Number(c.id) === id);
    if (comp && isPrimaryComponent(comp)) {
      return;
    }
    setFormData((prev: any) => {
      const ids = storedCustomComponentIds(prev.extra_salary_component_ids, salaryComponents);
      const nextIds = checked ? [...ids, id] : ids.filter((x) => x !== id);
      const newSalaryComponents = { ...prev.salary_components };
      if (!checked) delete newSalaryComponents[id];

      return {
        ...prev,
        extra_salary_component_ids: nextIds,
        salary_components: newSalaryComponents,
        gross_salary: recalcGrossFromComponents(newSalaryComponents, nextIds),
      };
    });
  };

  const handleSalaryComponentChange = (id: string, value: string) => {
    setFormData((prev: any) => {
      const newSalaryComponents = {
        ...prev.salary_components,
        [id]: value
      };

      const assignedIds = customizeSalaryComponents
        ? (prev.extra_salary_component_ids || []).map(Number)
        : [];

      return {
        ...prev,
        salary_components: newSalaryComponents,
        gross_salary: recalcGrossFromComponents(newSalaryComponents, assignedIds),
      };
    });
  };

  const handleSalaryComputed = useCallback((payload: {
    gross_salary: string;
    basic_salary: string;
    salary_components: Record<string, string>;
    gross_input_mode: GrossInputMode;
  }) => {
    setFormData((prev: any) => ({
      ...prev,
      gross_salary: payload.gross_salary,
      basic_salary: payload.basic_salary,
      salary_components: payload.salary_components,
      gross_input_mode: payload.gross_input_mode,
    }));
  }, []);

  const standardWorkingDays = Number(branchPayroll?.working_days ?? 26) || 26;

  // Helper to calculate totals
  const calculateTotals = () => {
    let earnings = 0;
    let deductions = 0;

    salaryComponents.forEach((comp: any) => {
      if (!applicableSalaryComponents.some((c: any) => Number(c.id) === Number(comp.id))) return;
      const val = parseFloat(formData.salary_components[comp.id] || '0');
      if (comp.type === 'earning') earnings += val;
      else deductions += val;
    });

    return {
      gross: earnings,
      net: earnings - deductions
    };
  };

  const totals = calculateTotals();

  const handleDocumentChange = (index: number, field: string, value: any) => {
    const newDocs = [...formData.documents];
    newDocs[index] = { ...newDocs[index], [field]: value };
    setFormData((prev: any) => ({ ...prev, documents: newDocs }));
  };

  const addDocument = () => {
    setFormData((prev: any) => ({
      ...prev,
      documents: [...prev.documents, { document_type_id: '', file_path: '', expiry_date: '', id_number: '' }]
    }));
  };

  const removeDocument = (index: number) => {
    const newDocs = formData.documents.filter((_: any, i: number) => i !== index);
    setFormData((prev: any) => ({ ...prev, documents: newDocs }));
  };

  const validateStep = (step: number) => {
    const newErrors: Record<string, string> = {};

    if (step === 1) {
      if (!formData.name) newErrors.name = t('Name is required');
      if (!formData.gender) newErrors.gender = t('Gender is required');
      if (!formData.date_of_birth) newErrors.date_of_birth = t('Birth date is required');

      if (formData.weight && parseFloat(formData.weight) < 0) {
        newErrors.weight = t('Weight cannot be negative');
      }

      // For height, check if it's a number and negative, or if it starts with a minus
      if (formData.height && (formData.height.toString().startsWith('-') || (parseFloat(formData.height) < 0))) {
        newErrors.height = t('Height cannot be negative');
      }
    } else if (step === 2) {
      if (formData.phone && !/^\d{10}$/.test(formData.phone)) {
        newErrors.phone = t('Phone number must be exactly 10 digits');
      }
      if (formData.phone_2 && !/^\d{10}$/.test(formData.phone_2)) {
        newErrors.phone_2 = t('Emergency contact number must be exactly 10 digits');
      }
    } else if (step === 3) {
      if (!formData.department_id) newErrors.department_id = t('Department is required');
      if (!formData.designation_id) newErrors.designation_id = t('Designation is required');
      if (!formData.category_id) newErrors.category_id = t('Category is required');
      if (!formData.section_id) newErrors.section_id = t('Section is required');
      if (!formData.date_of_joining) newErrors.date_of_joining = t('Joining date is required');
    } else if (step === 5) {
      const invalidDocs = formData.documents.some((doc: any) =>
        !doc.file_path || !doc.document_type_id
      );
      if (invalidDocs) {
        toast.error(t('Please select both document type and file for all added documents'));
        return false;
      }
    }

    setErrors(newErrors);
    return Object.keys(newErrors).length === 0;
  };

  const goToStep = (stepId: number) => {
    if (stepId <= currentStep) {
      setCurrentStep(stepId);
      return;
    }

    // If going forward, must validate all steps in between
    for (let i = currentStep; i < stepId; i++) {
      if (!validateStep(i)) {
        toast.error(t('Please complete Step :step before moving forward', { step: i }));
        setCurrentStep(i);
        return;
      }
    }

    setCurrentStep(stepId);
  };

  const nextStep = () => {
    if (validateStep(currentStep)) {
      setCurrentStep(prev => Math.min(prev + 1, 5));
    } else {
      toast.error(t('Please fill all required fields in this step'));
    }
  };
  const prevStep = () => setCurrentStep(prev => Math.max(prev - 1, 1));

  const addNominee = () => {
    setFormData((prev: any) => ({
      ...prev,
      nominees: [...(prev.nominees || []), { name: '', aadhar_number: '', percentage: '', relation: '' }]
    }));
  };

  const removeNominee = (index: number) => {
    const newNominees = formData.nominees.filter((_: any, i: number) => i !== index);
    setFormData((prev: any) => ({ ...prev, nominees: newNominees }));
  };

  const handleNomineeChange = (index: number, field: string, value: any) => {
    const newNominees = [...formData.nominees];
    newNominees[index] = { ...newNominees[index], [field]: value };
    setFormData((prev: any) => ({ ...prev, nominees: newNominees }));
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (currentStep < 5) {
      nextStep();
      return;
    }

    if (!validateStep(1) || !validateStep(2) || !validateStep(3) || !validateStep(4) || !validateStep(5)) {
      toast.error(t('Some required fields are missing or invalid'));
      return;
    }

    setIsSubmitting(true);
    const submissionData = { ...formData };
    if (formData.week_off_type === 'monthly') {
      submissionData.week_off = JSON.stringify(formData.monthly_week_offs);
    }

    router.post((window as any).route('hr.employees.store'), submissionData, {
      onSuccess: () => {
        toast.success(t('Employee created successfully'));
        localStorage.removeItem('employee_create_form');
        setIsSubmitting(false);
      },
      onError: (err) => {
        console.error('Validation Errors:', err);
        setErrors(err);

        // Map fields to steps to navigate to the first step with errors
        const fieldStepMap: Record<string, number> = {
          // Step 1
          name: 1, gender: 1, marital_status: 1, date_of_birth: 1, father_name: 1,
          // Step 2
          address_line_1: 2, address_line_2: 2, city: 2, state: 2, postal_code: 2, phone: 2,
          pan_card_number: 2, aadhar_card_number: 2, election_card_number: 2, driving_licence: 2,
          // Step 3
          department_id: 3, designation_id: 3, category_id: 3, section_id: 3,
          date_of_joining: 3, po_status: 3,
          // Step 4
          bank_id: 4, account_number: 4,
        };

        const firstErrorField = Object.keys(err)[0];
        if (firstErrorField && fieldStepMap[firstErrorField]) {
          setCurrentStep(fieldStepMap[firstErrorField]);
        } else if (firstErrorField?.startsWith('documents')) {
          setCurrentStep(5);
        } else if (firstErrorField?.startsWith('nominees')) {
          setCurrentStep(5);
        }

        const errorCount = Object.keys(err).length;
        toast.error(t('Please check the form for {{count}} errors', { count: errorCount }));
        setIsSubmitting(false);
      }
    });
  };

  const steps = [
    { id: 1, title: t('Identity'), icon: <User className="h-4 w-4" />, description: t('Profile & Bio') },
    { id: 2, title: t('Contact'), icon: <MapPin className="h-4 w-4" />, description: t('Address & ID') },
    { id: 3, title: t('Career'), icon: <Briefcase className="h-4 w-4" />, description: t('Position & Shift') },
    { id: 4, title: t('Finance'), icon: <Landmark className="h-4 w-4" />, description: t('Bank & Salary') },
    { id: 5, title: t('Final'), icon: <Plus className="h-4 w-4" />, description: t('Docs & Family') },
  ];

  // Helper for Combobox options
  const toOptions = (items: any[], labelKey = 'name') => (items || []).map(item => ({ label: item[labelKey], value: item.id.toString() }));

  return (
    <PageTemplate title={t('Register Employee')}>
      <div className="max-w-6xl mx-auto px-4">

        {/* Compact Stepper */}
        <div className="mb-4">
          <div className="flex items-center justify-between relative max-w-2xl mx-auto">
            <div className="absolute top-[14px] left-0 w-full h-[2px] bg-slate-100 z-0" />
            <div
              className="absolute top-[14px] left-0 h-[2px] bg-primary transition-all duration-700 z-0 shadow-[0_0_8px_rgba(var(--primary),0.4)]"
              style={{ width: `${((currentStep - 1) / (steps.length - 1)) * 100}%` }}
            />

            {steps.map((step) => (
              <div key={step.id} className="relative z-10 flex flex-col items-center">
                <button
                  type="button"
                  onClick={() => goToStep(step.id)}
                  className={cn(
                    "w-8 h-8 rounded-full flex items-center justify-center transition-all duration-500 border-2 text-xs",
                    currentStep === step.id
                      ? "bg-white border-primary text-primary shadow-[0_0_15px_rgba(var(--primary),0.3)] scale-110"
                      : currentStep > step.id
                        ? "bg-primary border-primary text-white"
                        : "bg-white border-slate-200 text-slate-400"
                  )}
                >
                  {currentStep > step.id ? <Save className="h-3 w-3" /> : step.id}
                </button>
                <span className={cn(
                  "text-[10px] font-bold mt-1 transition-colors duration-300",
                  currentStep === step.id ? "text-primary" : "text-slate-500"
                )}>
                  {step.title}
                </span>
              </div>
            ))}
          </div>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4 pt-4 pb-16">
          <Card className="shadow-md border-slate-200 overflow-hidden bg-white">
            <CardHeader className="bg-slate-50 border-b border-slate-100 py-3">
              <div className="flex items-center gap-3">
                <div className="p-1.5 bg-primary/10 text-primary rounded-lg">
                  {steps[currentStep - 1].icon}
                </div>
                <CardTitle className="text-base font-bold text-slate-800">
                  {steps[currentStep - 1].title} {t('Information')}
                </CardTitle>
              </div>
            </CardHeader>
            <CardContent className="p-5">

              {/* STEP 1: IDENTITY & PROFILE */}
              {currentStep === 1 && (
                <div className="animate-in fade-in slide-in-from-bottom-4 duration-500">
                  <div className="flex flex-col lg:flex-row gap-6 items-start">
                    <div className="w-full lg:w-1/4 flex flex-col items-center">
                      <MediaPicker
                        label={t('Profile Image')}
                        value={formData.avatar}
                        onChange={(v) => handleChange('avatar', v)}
                      />
                      <div className="mt-4 p-3 bg-primary/5 rounded-xl border border-primary/10 w-full text-center">
                        <span className="text-[10px] font-bold text-primary uppercase block mb-1">{t('Employee ID')}</span>
                        <div className="text-xl font-black text-slate-800">{formData.emy_code || '---'}</div>
                      </div>
                    </div>

                    <div className="w-full lg:w-3/4 grid grid-cols-1 md:grid-cols-3 gap-4">
                      <div className="space-y-1">
                        <Label className="text-slate-700 font-semibold text-xs">{t('Employee Name')} <span className="text-red-500">*</span></Label>
                        <Input
                          placeholder={t('Full Name')}
                          className="h-9 border-slate-200"
                          value={formData.name}
                          onChange={(e) => handleChange('name', e.target.value)}
                        />
                        {errors.name && <p className="text-[10px] text-red-500">{errors.name}</p>}
                      </div>

                      <div className="space-y-1">
                        <Label className="text-slate-700 font-semibold text-xs">{t('Father Name')}</Label>
                        <Input
                          placeholder={t("Father's Name")}
                          className="h-9 border-slate-200"
                          value={formData.father_name}
                          onChange={(e) => handleChange('father_name', e.target.value)}
                        />
                      </div>

                      <div className="space-y-1">
                        <Label className="text-slate-700 font-semibold text-xs">{t('Gender')} <span className="text-red-500">*</span></Label>
                        <Combobox
                          options={[
                            { label: t('Male'), value: 'male' },
                            { label: t('Female'), value: 'female' },
                            { label: t('Other'), value: 'other' }
                          ]}
                          value={formData.gender}
                          onChange={(v) => handleChange('gender', v)}
                          placeholder={t('Select Gender')}
                        />
                        {errors.gender && <p className="text-[10px] text-red-500">{errors.gender}</p>}
                      </div>

                      <div className="space-y-2">
                        <Label className="text-slate-700 font-semibold">{t('Marital Status')}</Label>
                        <Combobox
                          options={[
                            { label: t('Single'), value: 'single' },
                            { label: t('Married'), value: 'married' },
                            { label: t('Divorced'), value: 'divorced' }
                          ]}
                          value={formData.marital_status}
                          onChange={(v) => handleChange('marital_status', v)}
                          placeholder={t('Select Status')}
                        />
                        {errors.marital_status && <p className="text-[10px] text-red-500">{errors.marital_status}</p>}
                      </div>

                      <div className="space-y-2">
                        <Label className="text-slate-700 font-semibold">{t('Birth Date')} <span className="text-red-500">*</span></Label>
                        <div className="relative">
                          <Input
                            type="date"
                            className="h-11 border-slate-200 focus:ring-primary/20 pl-10"
                            value={formData.date_of_birth}
                            onChange={(e) => handleChange('date_of_birth', e.target.value)}
                            max={(() => {
                              const today = new Date();
                              today.setDate(today.getDate() - 1);
                              return today.toISOString().split('T')[0];
                            })()}
                          />
                          <Calendar className="absolute left-3 top-3 h-5 w-5 text-slate-400" />
                        </div>
                        {errors.date_of_birth && <p className="text-[10px] text-red-500">{errors.date_of_birth}</p>}
                      </div>

                      <div className="space-y-2">
                        <Label className="text-slate-700 font-semibold">{t('Wedding Date')}</Label>
                        <div className="relative">
                          <Input
                            type="date"
                            className="h-11 border-slate-200 focus:ring-primary/20 pl-10"
                            value={formData.wedding_date}
                            onChange={(e) => handleChange('wedding_date', e.target.value)}
                          />
                          <Calendar className="absolute left-3 top-3 h-5 w-5 text-slate-400" />
                        </div>
                      </div>

                      <div className="grid grid-cols-3 gap-4 md:col-span-2">
                        <div className="space-y-2">
                          <Label className="text-slate-700 font-semibold">{t('Blood Group')}</Label>
                          <Combobox
                            options={['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'].map(g => ({ label: g, value: g }))}
                            value={formData.blood_group}
                            onChange={(v) => handleChange('blood_group', v)}
                            placeholder={t('O+')}
                          />
                        </div>
                        <div className="space-y-2">
                          <Label className="text-slate-700 font-semibold">{t('Height')}</Label>
                          <Input
                            placeholder={t("5'8\"")}
                            className="h-11 border-slate-200"
                            value={formData.height}
                            onChange={(e) => handleChange('height', e.target.value)}
                          />
                          {errors.height && <p className="text-[10px] text-red-500">{errors.height}</p>}
                        </div>
                        <div className="space-y-2">
                          <Label className="text-slate-700 font-semibold">{t('Weight')}</Label>
                          <Input
                            type="number"
                            min="0"
                            placeholder="70"
                            className="h-11 border-slate-200"
                            value={formData.weight}
                            onChange={(e) => handleChange('weight', e.target.value)}
                          />
                          {errors.weight && <p className="text-[10px] text-red-500">{errors.weight}</p>}
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              )}

              {/* STEP 2: CONTACT & IDENTITY */}
              {currentStep === 2 && (
                <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="md:col-span-2 space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Permanent Address')}</Label>
                    <Input
                      placeholder={t('Street, Area, Building...')}
                      className="h-9 border-slate-200"
                      value={formData.address_line_1}
                      onChange={(e) => handleChange('address_line_1', e.target.value)}
                    />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Local Address')}</Label>
                    <Input
                      placeholder={t('Current residence...')}
                      className="h-9 border-slate-200"
                      value={formData.address_line_2}
                      onChange={(e) => handleChange('address_line_2', e.target.value)}
                    />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('City')}</Label>
                    <Input className="h-9 border-slate-200" value={formData.city} onChange={(e) => handleChange('city', e.target.value)} />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Pincode')}</Label>
                    <Input
                      type="number"
                      className="h-9 border-slate-200"
                      value={formData.postal_code}
                      onChange={(e) => {
                        const val = e.target.value.slice(0, 6);
                        handleChange('postal_code', val);
                      }}
                    />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('State')}</Label>
                    <Input className="h-9 border-slate-200" value={formData.state} onChange={(e) => handleChange('state', e.target.value)} />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Email Address')}</Label>
                    <Input type="email" className="h-9 border-slate-200" value={formData.email} onChange={(e) => handleChange('email', e.target.value)} />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Primary Phone')}</Label>
                    <Input type="number" className="h-9 border-slate-200" value={formData.phone} onChange={(e) => handleChange('phone', e.target.value)} />
                    {errors.phone && <p className="text-[10px] text-red-500">{errors.phone}</p>}
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Emergency Contact')}</Label>
                    <Input type="number" className="h-9 border-slate-200" value={formData.phone_2} onChange={(e) => handleChange('phone_2', e.target.value)} />
                    {errors.phone_2 && <p className="text-[10px] text-red-500">{errors.phone_2}</p>}
                  </div>

                  <div className="md:col-span-3 h-px bg-slate-100 my-2" />

                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('PAN Number')}</Label>
                    <Input className="h-9 border-slate-200 uppercase" value={formData.pan_card_number} onChange={(e) => handleChange('pan_card_number', e.target.value)} />
                    {errors.pan_card_number && <p className="text-[10px] text-red-500">{errors.pan_card_number}</p>}
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Aadhaar Number')}</Label>
                    <Input type="number" className="h-9 border-slate-200" value={formData.aadhar_card_number} onChange={(e) => handleChange('aadhar_card_number', e.target.value)} />
                    {errors.aadhar_card_number && <p className="text-[10px] text-red-500">{errors.aadhar_card_number}</p>}
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Driving License')}</Label>
                    <Input className="h-9 border-slate-200" value={formData.driving_license} onChange={(e) => handleChange('driving_license', e.target.value)} />
                    {errors.driving_license && <p className="text-[10px] text-red-500">{errors.driving_license}</p>}
                  </div>
                </div>
              )}

              {/* STEP 3: WORK & CAREER */}
              {currentStep === 3 && (
                <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 grid grid-cols-1 md:grid-cols-4 gap-3">
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Branch')}</Label>
                    <Combobox options={toOptions(branches)} value={formData.branch_id} onChange={(v) => { handleChange('branch_id', v); loadBranchMasters(v, true); }} placeholder={t('Select Branch')} />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Department')} <span className="text-red-500">*</span></Label>
                    <Combobox options={toOptions(branchMasters.departments)} value={formData.department_id} onChange={(v) => handleChange('department_id', v)} placeholder={t('Select Dept')} />
                    {errors.department_id && <p className="text-[10px] text-red-500">{errors.department_id}</p>}
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Designation')} <span className="text-red-500">*</span></Label>
                    <Combobox options={toOptions(filteredDesignations)} value={formData.designation_id} onChange={(v) => handleChange('designation_id', v)} placeholder={t('Select Desig')} />
                    {errors.designation_id && <p className="text-[10px] text-red-500">{errors.designation_id}</p>}
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Category')} <span className="text-red-500">*</span></Label>
                    <Combobox options={toOptions(branchMasters.categories)} value={formData.category_id} onChange={(v) => handleChange('category_id', v)} placeholder={t('Select Category')} />
                    {errors.category_id && <p className="text-[10px] text-red-500">{errors.category_id}</p>}
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Section')} <span className="text-red-500">*</span></Label>
                    <Combobox options={toOptions(branchMasters.sections)} value={formData.section_id} onChange={(v) => handleChange('section_id', v)} placeholder={t('Select Section')} />
                    {errors.section_id && <p className="text-[10px] text-red-500">{errors.section_id}</p>}
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Shift')} <span className="text-red-500">*</span></Label>
                    <Combobox options={toOptions(branchMasters.shifts)} value={formData.shift_id} onChange={(v) => handleChange('shift_id', v)} placeholder={t('Select Shift')} />
                    {errors.shift_id && <p className="text-[10px] text-red-500">{errors.shift_id}</p>}
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Skill Level')}</Label>
                    <Combobox options={toOptions(branchMasters.skills)} value={formData.skill_id?.[0]?.toString() ?? ''} onChange={(v) => handleChange('skill_id', v ? [v] : [])} placeholder={t('Select Skill')} />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Joining Date')} <span className="text-red-500">*</span></Label>
                    <Input type="date" className="h-9 border-slate-200" value={formData.date_of_joining} onChange={(e) => handleChange('date_of_joining', e.target.value)} />
                    {errors.date_of_joining && <p className="text-[10px] text-red-500">{errors.date_of_joining}</p>}
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Confirm Date')}</Label>
                    <Input type="date" className="h-9 border-slate-200" value={formData.confirm_date} onChange={(e) => handleChange('confirm_date', e.target.value)} />
                  </div>

                  <div className="md:col-span-4 h-px bg-slate-100 my-1" />

                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Education')}</Label>
                    <Input className="h-9 border-slate-200" value={formData.education} onChange={(e) => handleChange('education', e.target.value)} />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Experience')}</Label>
                    <Input className="h-9 border-slate-200" value={formData.experience} onChange={(e) => handleChange('experience', e.target.value)} />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Resign Date')}</Label>
                    <Input type="date" className="h-9 border-slate-200" value={formData.resign_date} onChange={(e) => handleChange('resign_date', e.target.value)} />
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Resign Reason')}</Label>
                    <Combobox options={toOptions(resignReasons)} value={formData.resign_reason_id} onChange={(v) => handleChange('resign_reason_id', v)} placeholder={t('Select Reason')} />
                  </div>

                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Lunch Time')} (Min)</Label>
                    <Input type="number" placeholder="30" className="h-9 border-slate-200" value={formData.lunch_time} onChange={(e) => handleChange('lunch_time', e.target.value)} />
                  </div>
                  <div className="space-y-3">
                    <div className="flex items-center justify-between">
                      <Label className="text-slate-700 font-semibold text-xs tracking-wide">{t('Individual Week Off')}</Label>
                      <div className="flex bg-slate-100 p-0.5 rounded-lg border border-slate-200 shadow-sm">
                        <button
                          type="button"
                          onClick={() => handleChange('week_off_type', 'weekly')}
                          className={cn(
                            "px-3 py-1 text-[9px] font-black rounded-md transition-all uppercase tracking-tight",
                            formData.week_off_type === 'weekly' ? "bg-white text-primary shadow-sm" : "text-slate-500 hover:text-slate-700"
                          )}
                        >
                          {t('Weekly')}
                        </button>
                        <button
                          type="button"
                          onClick={() => handleChange('week_off_type', 'monthly')}
                          className={cn(
                            "px-3 py-1 text-[9px] font-black rounded-md transition-all uppercase tracking-tight",
                            formData.week_off_type === 'monthly' ? "bg-white text-primary shadow-sm" : "text-slate-500 hover:text-slate-700"
                          )}
                        >
                          {t('Monthly')}
                        </button>
                      </div>
                    </div>

                    {formData.week_off_type === 'weekly' ? (
                      <div className="flex items-center gap-1.5">
                        {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => {
                          const fullDay = { 'Sun': 'Sunday', 'Mon': 'Monday', 'Tue': 'Tuesday', 'Wed': 'Wednesday', 'Thu': 'Thursday', 'Fri': 'Friday', 'Sat': 'Saturday' }[day];
                          const isSelected = (formData.week_off || []).includes(fullDay);
                          const isWeekend = day === 'Sun' || day === 'Sat';

                          return (
                            <button
                              key={day}
                              type="button"
                              onClick={() => {
                                const current = formData.week_off || [];
                                const next = isSelected ? current.filter((d: string) => d !== fullDay) : [...current, fullDay];
                                handleChange('week_off', next);
                              }}
                              className={cn(
                                "w-8 h-8 rounded-full flex items-center justify-center text-[10px] font-black transition-all duration-200 border-2",
                                isSelected
                                  ? "bg-primary border-primary text-white shadow-lg shadow-primary/20 scale-110"
                                  : "bg-white border-slate-100 text-slate-400 hover:border-primary/30 hover:text-primary",
                                !isSelected && isWeekend && "bg-slate-50 border-slate-200 text-slate-400"
                              )}
                              title={t(fullDay!)}
                            >
                              {day[0]}
                            </button>
                          );
                        })}
                      </div>
                    ) : (
                      <div className="space-y-1.5 bg-slate-50 p-2 rounded-xl border border-slate-200/50">
                        {[1, 2, 3, 4, 5].map((week) => (
                          <div key={week} className="flex items-center gap-2">
                            <span className="text-[8px] font-black text-slate-400 uppercase w-8">W{week}</span>
                            <div className="flex gap-1">
                              {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((day) => {
                                const fullDay = { 'Sun': 'Sunday', 'Mon': 'Monday', 'Tue': 'Tuesday', 'Wed': 'Wednesday', 'Thu': 'Thursday', 'Fri': 'Friday', 'Sat': 'Saturday' }[day];
                                const weekDays = formData.monthly_week_offs[week] || [];
                                const isSelected = weekDays.includes(fullDay);

                                return (
                                  <button
                                    key={day}
                                    type="button"
                                    onClick={() => {
                                      const current = [...weekDays];
                                      const next = isSelected ? current.filter(d => d !== fullDay) : [...current, fullDay];
                                      setFormData({
                                        ...formData,
                                        monthly_week_offs: { ...formData.monthly_week_offs, [week]: next }
                                      });
                                    }}
                                    className={cn(
                                      "w-6 h-6 rounded-lg flex items-center justify-center text-[8px] font-black transition-all border",
                                      isSelected
                                        ? "bg-primary border-primary text-white shadow-sm"
                                        : "bg-white border-slate-200 text-slate-400 hover:border-primary/30"
                                    )}
                                  >
                                    {day[0]}
                                  </button>
                                );
                              })}
                            </div>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                  <div className="space-y-1">
                    <Label className="text-slate-700 font-semibold text-xs">{t('Status (P/OP)')}</Label>
                    <div className="flex gap-4 items-center h-9">
                      <label className="flex items-center gap-1 cursor-pointer">
                        <input type="radio" value="Permanent" checked={formData.po_status === 'Permanent'} onChange={(e) => handleChange('po_status', e.target.value)} className="w-3 h-3 accent-primary" />
                        <span className="text-[10px] font-bold">P</span>
                      </label>
                      <label className="flex items-center gap-1 cursor-pointer">
                        <input type="radio" value="Other" checked={formData.po_status === 'Other'} onChange={(e) => handleChange('po_status', e.target.value)} className="w-3 h-3 accent-primary" />
                        <span className="text-[10px] font-bold">O/P</span>
                      </label>
                    </div>
                    {errors.po_status && <p className="text-[10px] text-red-500">{errors.po_status}</p>}
                  </div>
                </div>
              )}

              {/* STEP 4: FINANCE & SALARY */}
              {currentStep === 4 && (
                <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 space-y-10">
                  <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8 p-6 bg-slate-50/50 rounded-3xl border border-slate-100 shadow-inner">
                    <div className="md:col-span-2 space-y-2">
                      <Label className="text-slate-700 font-semibold">{t('Select Bank')} <span className="text-red-500">*</span></Label>
                      <Combobox
                        options={banks.map((b: any) => ({ label: b.branch_name ? `${b.bank_name} (${b.branch_name})` : b.bank_name, value: b.id.toString() }))}
                        value={formData.bank_id}
                        onChange={(v) => {
                          const bank = banks.find((b: any) => b.id.toString() === v);
                          handleChange('bank_id', v);
                          handleChange('bank_name', bank?.bank_name || '');
                          handleChange('ifsc_code', bank?.ifsc_code || '');
                        }}
                        placeholder={t('Search & Select Bank')}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label className="text-slate-700 font-semibold">{t('Account Type')}</Label>
                      <Combobox
                        options={[
                          { label: t('Saving'), value: 'saving' },
                          { label: t('Current'), value: 'current' }
                        ]}
                        value={formData.account_type}
                        onChange={(v) => handleChange('account_type', v)}
                      />
                    </div>
                    <div className="space-y-2">
                      <Label className="text-slate-700 font-semibold">{t('IFSC Code')}</Label>
                      <Input className="h-11 border-slate-200 bg-white" value={formData.ifsc_code} onChange={(e) => handleChange('ifsc_code', e.target.value)} />
                    </div>
                    <div className="md:col-span-2 space-y-2">
                      <Label className="text-slate-700 font-semibold">{t('Account Number')}</Label>
                      <Input className="h-11 border-slate-200 bg-white font-mono" value={formData.account_number} onChange={(e) => handleChange('account_number', e.target.value)} />
                    </div>
                  </div>


                  {/* Statutory & Policy Flags Section */}
                  <div className="p-6 bg-blue-50/20 rounded-3xl border border-blue-100/50 shadow-sm">
                    <div className="flex items-center gap-3 text-blue-700 mb-6 border-b border-blue-100 pb-3">
                      <div className="p-2 bg-blue-100 rounded-xl">
                        <ShieldCheck className="h-5 w-5" />
                      </div>
                      <div>
                        <h3 className="font-bold text-sm tracking-tight">{t('Statutory & Policies')}</h3>
                        <p className="text-[10px] text-blue-600/70 font-medium">{t('Manage PF, ESIC, Bonus & OT')}</p>
                      </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                      {/* PF Column */}
                      <div className={cn("space-y-3 p-4 rounded-2xl transition-all", formData.pf_flag ? "bg-white shadow-sm border border-blue-100" : "bg-slate-50/50 border border-transparent")}>
                        <div className="flex items-center justify-between">
                          <Label className="text-xs font-bold text-slate-700">{t('Provident Fund (P.F.)')}</Label>
                          <Switch checked={formData.pf_flag} onCheckedChange={(v) => handleChange('pf_flag', v)} />
                        </div>
                        {formData.pf_flag && (
                          <div className="grid grid-cols-2 gap-3 animate-in fade-in slide-in-from-top-2 duration-300">
                            <div className="space-y-1">
                              <Label className="text-[10px] font-bold text-slate-400">{t('PF Number')}</Label>
                              <Input className="h-9 text-xs" placeholder="PF-0000" value={formData.pf_number} onChange={(e) => handleChange('pf_number', e.target.value)} />
                            </div>
                            <div className="space-y-1">
                              <Label className="text-[10px] font-bold text-slate-400">{t('UAN Number')}</Label>
                              <Input className="h-9 text-xs" placeholder="UAN-0000" value={formData.uan_number} onChange={(e) => handleChange('uan_number', e.target.value)} />
                            </div>
                          </div>
                        )}
                      </div>

                      {/* ESIC Column */}
                      <div className={cn("space-y-3 p-4 rounded-2xl transition-all", formData.esic_flag ? "bg-white shadow-sm border border-blue-100" : "bg-slate-50/50 border border-transparent")}>
                        <div className="flex items-center justify-between">
                          <Label className="text-xs font-bold text-slate-700">{t('ESIC')}</Label>
                          <Switch checked={formData.esic_flag} onCheckedChange={(v) => handleChange('esic_flag', v)} />
                        </div>
                        {formData.esic_flag && (
                          <div className="space-y-1 animate-in fade-in slide-in-from-top-2 duration-300">
                            <Label className="text-[10px] font-bold text-slate-400">{t('ESIC Number')}</Label>
                            <Input className="h-9 text-xs" placeholder="ESIC-0000" value={formData.esic_number} onChange={(e) => handleChange('esic_number', e.target.value)} />
                          </div>
                        )}
                      </div>

                      {/* Overtime (P.I.) Column */}
                      <div className={cn("space-y-3 p-4 rounded-2xl transition-all", formData.ot_flag ? "bg-white shadow-sm border border-blue-100" : "bg-slate-50/50 border border-transparent")}>
                        <div className="flex items-center justify-between">
                          <Label className="text-xs font-bold text-slate-700">{t('Overtime (P.I.)')}</Label>
                          <Switch checked={formData.ot_flag} onCheckedChange={(v) => handleChange('ot_flag', v)} />
                        </div>
                        {formData.ot_flag && (
                          <div className="animate-in fade-in slide-in-from-top-2 duration-300">
                            <div className="space-y-1">
                              <Label className="text-[10px] font-bold text-slate-400">{t('OT Hours')}</Label>
                              <Combobox
                                options={overtimeOptions.map((o: any) => ({ label: o.name, value: o.name }))}
                                value={formData.ot_hours}
                                onChange={(v) => handleChange('ot_hours', v)}
                                placeholder={t('Hours')}
                              />
                            </div>
                          </div>
                        )}
                      </div>

                      {/* Other Flags */}
                      <div className="col-span-full flex items-center gap-8 bg-white p-4 rounded-2xl border border-blue-50">
                        <div className="flex items-center gap-3">
                          <Switch checked={formData.bonus_flag} onCheckedChange={(v) => handleChange('bonus_flag', v)} />
                          <Label className="text-xs font-bold text-slate-700">{t('Bonus Eligible')}</Label>
                        </div>
                        <div className="h-8 w-px bg-slate-100" />
                        <div className="flex items-center gap-3">
                          <Switch checked={formData.ptax_flag} onCheckedChange={(v) => handleChange('ptax_flag', v)} />
                          <Label className="text-xs font-bold text-slate-700">{t('Professional Tax (P.Tax)')}</Label>
                        </div>
                        <div className="h-8 w-px bg-slate-100" />
                        <div className="flex items-center gap-3">
                          <Switch checked={formData.hod_flag} onCheckedChange={(v) => handleChange('hod_flag', v)} />
                          <Label className="text-xs font-bold text-slate-700">{t('HOD')}</Label>
                        </div>
                      </div>
                    </div>
                  </div>

                  {(primarySalaryComponents.length > 0 || customSalaryComponents.length > 0) && (
                    <div className="p-6 bg-amber-50/20 rounded-3xl border border-amber-100/50 shadow-sm">
                      <div className="mb-4 flex flex-wrap items-start justify-between gap-3 border-b border-amber-100 pb-3">
                        <div className="flex items-center gap-3 text-amber-800">
                          <div className="p-2 bg-amber-100 rounded-xl">
                            <Briefcase className="h-5 w-5" />
                          </div>
                          <div>
                            <h3 className="font-bold text-sm tracking-tight">{t('Salary Components')}</h3>
                            <p className="text-[10px] text-amber-700/70 font-medium">
                              {t('Primary group is always applied. Customize to add custom components.')}
                            </p>
                          </div>
                        </div>
                        <div className="flex items-center gap-2 rounded-xl border border-amber-100 bg-white/80 px-3 py-2">
                          <Switch checked={customizeSalaryComponents} onCheckedChange={handleCustomizeToggle} />
                          <Label className="text-xs font-semibold text-slate-700">{t('Customize for this employee')}</Label>
                        </div>
                      </div>

                      {!customizeSalaryComponents ? (
                        <div className="rounded-xl border border-amber-100 bg-white/80 p-4">
                          <p className="text-[10px] font-bold uppercase tracking-wide text-slate-500">{t('Default — Primary group')}</p>
                          <div className="mt-2 flex flex-wrap gap-1.5">
                            {primarySalaryComponents.map((comp: any) => (
                              <Badge key={comp.id} variant="secondary" className="text-[11px]">
                                {comp.name}
                              </Badge>
                            ))}
                          </div>
                        </div>
                      ) : (
                        <div className="space-y-4">
                          {primarySalaryComponents.length > 0 && (
                            <div className="space-y-2">
                              <p className="text-[10px] font-bold uppercase tracking-wide text-slate-500">{t('Primary group')}</p>
                              <p className="text-[10px] text-slate-500">{t('Always applied — cannot be changed per employee.')}</p>
                              <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                {primarySalaryComponents.map((comp: any) => (
                                  <label
                                    key={comp.id}
                                    className="flex cursor-not-allowed items-center gap-2 rounded-xl border border-amber-100 bg-muted/30 px-3 py-2 opacity-80"
                                  >
                                    <Checkbox checked disabled />
                                    <span className="text-sm font-medium text-slate-700">{comp.name}</span>
                                    <span className="ml-auto text-[10px] text-muted-foreground">
                                      {comp.calculation_type === 'percentage_of_gross'
                                        ? `${comp.percentage_of_gross_pay}%`
                                        : `${comp.percentage_of_basic}%`}
                                    </span>
                                  </label>
                                ))}
                              </div>
                            </div>
                          )}
                          {customSalaryComponents.length > 0 && (
                            <div className="space-y-2">
                              <p className="text-[10px] font-bold uppercase tracking-wide text-slate-500">{t('Custom group')}</p>
                              <p className="text-[10px] text-slate-500">{t('Select additional components for this employee.')}</p>
                              <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                {customSalaryComponents.map((comp: any) => {
                                  const checked = extraComponentIds.includes(Number(comp.id));
                                  return (
                                    <label
                                      key={comp.id}
                                      className="flex cursor-pointer items-center gap-2 rounded-xl border border-amber-100 bg-white/80 px-3 py-2 hover:bg-amber-50/50"
                                    >
                                      <Checkbox
                                        checked={checked}
                                        onCheckedChange={(v) => toggleExtraComponent(Number(comp.id), Boolean(v))}
                                      />
                                      <span className="text-sm font-medium text-slate-700">{comp.name}</span>
                                      <span className="ml-auto text-[10px] text-muted-foreground">
                                        {comp.calculation_type === 'percentage_of_gross'
                                          ? `${comp.percentage_of_gross_pay}%`
                                          : `${comp.percentage_of_basic}%`}
                                      </span>
                                    </label>
                                  );
                                })}
                              </div>
                            </div>
                          )}
                          {customSalaryComponents.length === 0 && (
                            <p className="rounded-xl border border-dashed border-amber-100 px-3 py-4 text-center text-xs text-muted-foreground">
                              {t('No custom components in master. Add custom components in Salary Component Master first.')}
                            </p>
                          )}
                        </div>
                      )}
                    </div>
                  )}

                  <EmployeeGrossSalarySection
                    grossAmount={formData.gross_amount ?? ''}
                    grossInputMode={(formData.gross_input_mode as GrossInputMode) || 'month'}
                    workingDays={standardWorkingDays}
                    workingDaysSource={branchPayroll?.working_days_source}
                    salaryComponents={salaryComponents}
                    extraComponentIds={extraComponentIds}
                    pfApplicable={Boolean(formData.pf_flag)}
                    esiApplicable={Boolean(formData.esic_flag)}
                    onGrossAmountChange={(v) => handleChange('gross_amount', v)}
                    onGrossInputModeChange={(mode) => handleChange('gross_input_mode', mode)}
                    onComputedChange={handleSalaryComputed}
                  />
                </div>
              )}

              {/* STEP 5: FINAL & DOCUMENTS */}
              {currentStep === 5 && (
                <div className="animate-in fade-in slide-in-from-bottom-4 duration-500 space-y-6">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div className="space-y-4">
                      <div className="flex items-center justify-between border-b border-slate-100 pb-2 mb-4">
                        <div className="flex items-center gap-2 text-slate-800">
                          <Users className="h-4 w-4 text-primary" />
                          <h3 className="text-sm font-bold">{t('Nominee Details')}</h3>
                        </div>
                        <Button type="button" variant="outline" size="sm" onClick={addNominee} className="h-7 px-2 text-[10px] rounded-lg border-primary/20 text-primary hover:bg-primary/5">
                          <Plus className="h-3 w-3 mr-1" /> {t('Add Nominee')}
                        </Button>
                      </div>

                      <div className="space-y-4 max-h-[300px] overflow-y-auto pr-2 custom-scrollbar">
                        {formData.nominees?.map((nominee: any, idx: number) => (
                          <div key={idx} className="p-3 bg-slate-50/50 rounded-xl border border-slate-200/60 relative group">
                            {formData.nominees.length > 1 && (
                              <button type="button" onClick={() => removeNominee(idx)} className="absolute top-2 right-2 text-red-400 hover:text-red-600 transition-colors opacity-0 group-hover:opacity-100">
                                <Trash2 className="h-3 w-3" />
                              </button>
                            )}
                            <div className="grid grid-cols-2 gap-3">
                              <div className="col-span-2 space-y-1">
                                <Label className="text-[10px] font-bold text-slate-500">{t('Nominee Name')}</Label>
                                <Input placeholder={t('Full Name')} className="h-8 text-xs border-slate-200" value={nominee.name} onChange={(e) => handleNomineeChange(idx, 'name', e.target.value)} />
                              </div>
                              <div className="space-y-1">
                                <Label className="text-[10px] font-bold text-slate-500">{t('Relation')}</Label>
                                <Input placeholder={t('Relation')} className="h-8 text-xs border-slate-200" value={nominee.relation} onChange={(e) => handleNomineeChange(idx, 'relation', e.target.value)} />
                              </div>
                              <div className="space-y-1">
                                <Label className="text-[10px] font-bold text-slate-500">{t('Aadhar No')}</Label>
                                <Input type="number" placeholder={t('Aadhar Number')} className="h-8 text-xs border-slate-200" value={nominee.aadhar_number} onChange={(e) => handleNomineeChange(idx, 'aadhar_number', e.target.value)} />
                              </div>
                              <div className="space-y-1">
                                <Label className="text-[10px] font-bold text-slate-500">{t('Share %')}</Label>
                                <Input type="number" placeholder="0" className="h-8 text-xs border-slate-200" value={nominee.percentage} onChange={(e) => handleNomineeChange(idx, 'percentage', e.target.value)} />
                              </div>
                            </div>
                          </div>
                        ))}
                      </div>

                    </div>

                    <div className="space-y-6">
                      <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div className="flex items-center gap-3 text-slate-800">
                          <FileText className="h-6 w-6 text-primary" />
                          <h3 className="text-lg font-bold">{t('Upload Documents')}</h3>
                        </div>
                        <Button type="button" variant="outline" size="sm" onClick={addDocument} className="rounded-xl border-primary/20 text-primary hover:bg-primary/5">
                          <Plus className="h-4 w-4 mr-2" /> {t('Add More')}
                        </Button>
                      </div>
                      <div className="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
                        {formData.documents.map((doc: any, idx: number) => (
                          <div key={idx} className="p-5 bg-slate-50/80 rounded-2xl border border-slate-200/60 grid grid-cols-2 gap-4 relative group hover:bg-white hover:shadow-md transition-all">
                            <div className="col-span-2 flex justify-between items-start mb-1">
                              <span className="text-[10px] font-black uppercase text-slate-400">Doc #{idx + 1}</span>
                              <button type="button" onClick={() => removeDocument(idx)} className="text-red-400 hover:text-red-600 transition-colors">
                                <Trash2 className="h-4 w-4" />
                              </button>
                            </div>
                            <div className="space-y-2">
                              <Label className="text-xs font-bold text-slate-600">{t('Type')}</Label>
                              <Combobox
                                options={toOptions(documentTypes)}
                                value={doc.document_type_id}
                                onChange={(v) => handleDocumentChange(idx, 'document_type_id', v)}
                                placeholder={t('Select')}
                                className="h-9 text-xs"
                              />
                            </div>
                            <div className="space-y-2">
                              <Label className="text-xs font-bold text-slate-600">{t('ID Number')}</Label>
                              <Input className="h-9 text-xs bg-white" value={doc.id_number} onChange={(e) => handleDocumentChange(idx, 'id_number', e.target.value)} />
                            </div>
                            <div className="col-span-2">
                              <MediaPicker value={doc.file_path} onChange={(v) => handleDocumentChange(idx, 'file_path', v)} showPreview={false} />
                              <InputError message={errors[`documents.${idx}.file_path`]} className="mt-1" />
                              <InputError message={errors[`documents.${idx}.document_type_id`]} className="mt-1" />
                            </div>
                          </div>
                        ))}
                        {formData.documents.length === 0 && (
                          <div className="text-center py-12 bg-slate-50 rounded-3xl border-2 border-dashed border-slate-200">
                            <FileText className="h-10 w-10 text-slate-300 mx-auto mb-3" />
                            <p className="text-slate-500 text-sm font-medium">{t('No documents added yet.')}</p>
                            <Button type="button" variant="link" onClick={addDocument} className="text-primary font-bold">{t('Click to add first')}</Button>
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                </div>
              )}

            </CardContent>
          </Card>

          {/* Sticky Actions Bar */}
          <div className="fixed bottom-0 left-0 right-0 p-3 bg-white/90 backdrop-blur-md border-t border-slate-200 flex justify-between items-center z-50 lg:left-64 shadow-2xl">
            <Button
              type="button"
              variant="ghost"
              onClick={() => router.get((window as any).route('hr.employees.index'))}
              className="text-slate-500 font-bold text-xs"
            >
              {t('Discard')}
            </Button>
            <div className="flex gap-2">
              {currentStep > 1 && (
                <Button
                  type="button"
                  variant="outline"
                  onClick={prevStep}
                  className="rounded-lg px-4 h-9 border-slate-200 font-bold text-slate-600 text-xs"
                >
                  <ChevronLeft className="h-4 w-4 mr-1" />
                  {t('Back')}
                </Button>
              )}
              <Button
                type="submit"
                className={cn(
                  "rounded-lg px-6 h-9 font-black text-xs transition-all",
                  currentStep === 5
                    ? "bg-green-600 hover:bg-green-700 shadow-md"
                    : "bg-primary hover:bg-primary shadow-md"
                )}
                disabled={isSubmitting}
              >
                {currentStep < 5 ? (
                  <>
                    {t('Continue')}
                    <ChevronRight className="h-4 w-4 ml-2" />
                  </>
                ) : (
                  <>
                    <Save className="h-4 w-4 mr-2" />
                    {isSubmitting ? t('Processing...') : t('Save Employee')}
                  </>
                )}
              </Button>
            </div>
          </div>
        </form>
      </div>
    </PageTemplate>
  );
}