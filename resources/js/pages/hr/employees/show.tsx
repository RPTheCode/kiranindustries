// pages/hr/employees/show.tsx
import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { hasPermission } from '@/utils/authorization';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useInitials } from '@/hooks/use-initials';
import { useTranslation } from 'react-i18next';
import { Edit, Trash2, Download, FileText, Calendar, Phone, Mail, MapPin, Building, Briefcase, CreditCard, User, Users, Lock, Unlock, ArrowLeft, Check, X, Eye, ShieldCheck, Landmark, Plus } from 'lucide-react';
import { getImagePath } from '@/utils/helpers';

export default function EmployeeShow() {
  const { t } = useTranslation();
  const { auth, employee, workHistory, relatedEmployments, employeeSalary, salaryComponents = [] } = usePage().props as any;
  const permissions = auth?.permissions || [];
  const getInitials = useInitials();

  // State
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [activeTab, setActiveTab] = useState('identity');

  const handleEdit = () => {
    router.get(route('hr.employees.edit', employee.id));
  };

  const handleDeleteConfirm = () => {
    toast.loading(t('Deleting employee...'));

    router.delete(route('hr.employees.destroy', employee.id), {
      onSuccess: (page: any) => {
        toast.dismiss();
        if (page.props.flash.success) {
          toast.success(t(page.props.flash.success));
        } else if (page.props.flash.error) {
          toast.error(t(page.props.flash.error));
        }
        router.get(route('hr.employees.index'));
      },
      onError: (errors) => {
        toast.dismiss();
        if (typeof errors === 'string') {
          toast.error(t(errors));
        } else {
          toast.error(t('Failed to delete employee: {{errors}}', { errors: Object.values(errors).join(', ') }));
        }
      }
    });
  };

  const handleToggleStatus = () => {
    const newStatus = employee.status === 'active' ? 'inactive' : 'active';
    toast.loading(`${newStatus === 'active' ? t('Activating') : t('Deactivating')} employee...`);

    router.put(route('hr.employees.toggle-status', employee.employee?.id || employee.id), {}, {
      onSuccess: (page) => {
        toast.dismiss();
        if (page.props.flash.success) {
          toast.success(t(page.props.flash.success));
        } else if (page.props.flash.error) {
          toast.error(t(page.props.flash.error));
        }
      },
      onError: (errors) => {
        toast.dismiss();
        if (typeof errors === 'string') {
          toast.error(t(errors));
        } else {
          toast.error(t('Failed to update employee status: {{errors}}', { errors: Object.values(errors).join(', ') }));
        }
      }
    });
  };

  const handleDeleteDocument = (documentId: number) => {
    toast.loading(t('Deleting document...'));

    router.delete(route('hr.employees.documents.destroy', [employee.id, documentId]), {
      onSuccess: (page) => {
        toast.dismiss();
        if (page.props.flash.success) {
          toast.success(t(page.props.flash.success));
        } else if (page.props.flash.error) {
          toast.error(t(page.props.flash.error));
        }
      },
      onError: (errors) => {
        toast.dismiss();
        if (typeof errors === 'string') {
          toast.error(t(errors));
        } else {
          toast.error(t('Failed to delete document: {{errors}}', { errors: Object.values(errors).join(', ') }));
        }
      }
    });
  };

  const handleDocumentVerification = (documentId: number, status: 'verified' | 'rejected') => {
    const action = status === 'verified' ? 'approve' : 'reject';
    toast.loading(t(`${status === 'verified' ? 'Approving' : 'Rejecting'} document...`));

    router.put(route(`hr.employees.documents.${action}`, [employee.id, documentId]), {}, {
      onSuccess: (page) => {
        toast.dismiss();
        if (page.props.flash?.success) {
          toast.success(t(page.props.flash.success));
        } else {
          toast.success(t(`Document ${status === 'verified' ? 'approved' : 'rejected'} successfully`));
        }
      },
      onError: (errors) => {
        toast.dismiss();
        const errorMessage = errors?.message || Object.values(errors)[0] || `Failed to ${action} document`;
        toast.error(t(errorMessage));
      }
    });
  };

  // Define page actions
  const pageActions = [
    {
      label: t('Back to Employees'),
      icon: <ArrowLeft className="h-4 w-4 mr-2" />,
      variant: 'outline',
      onClick: () => router.get(route('hr.employees.index'))
    }
  ];

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('Employees'), href: route('hr.employees.index') },
    { title: employee?.name || t('Employee Details') }
  ];

  return (
    <PageTemplate
      title={employee?.name || t("Employee Details")}
      url={`/employees/${employee?.id}`}
      actions={pageActions}
      breadcrumbs={breadcrumbs}
    >
      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        {/* Employee Profile Card */}
        <Card className="lg:col-span-1 h-fit border-none shadow-lg overflow-hidden bg-white dark:bg-gray-800 rounded-2xl">
          <div className="h-32 bg-primary relative">
            <div className="absolute -bottom-12 left-1/2 -translate-x-1/2">
              <div className="h-28 w-28 rounded-full bg-white dark:bg-gray-800 p-1 shadow-xl overflow-hidden">
                <div className="h-full w-full rounded-full bg-primary/10 text-primary flex items-center justify-center text-3xl font-bold overflow-hidden">
                  {employee.avatar ? (
                    <img src={getImagePath(employee.avatar)} alt={employee.name} className="h-full w-full object-cover" />
                  ) : (
                    getInitials(employee.name)
                  )}
                </div>
              </div>
            </div>
          </div>
          
          <CardContent className="pt-16 pb-6 px-6 text-center">
            <h2 className="text-xl font-extrabold mb-1 text-gray-900 dark:text-white">{employee.name}</h2>
            <p className="text-sm font-medium text-primary mb-4">{employee.employee?.designation?.name || '-'}</p>
            
            <button
              onClick={() => { const canToggle = hasPermission(permissions, 'toggle-status-employees') || hasPermission(permissions, 'edit-employees'); if(canToggle) handleToggleStatus(); }}
              title={hasPermission(permissions, 'toggle-status-employees') || hasPermission(permissions, 'edit-employees') ? (employee.status === 'active' ? t('Click to Deactivate') : t('Click to Activate')) : t('No permission to change status')}
              disabled={!(hasPermission(permissions, 'toggle-status-employees') || hasPermission(permissions, 'edit-employees'))}
              className={`inline-flex items-center gap-1.5 select-none border-none bg-transparent mb-6 mx-auto transition-transform ${hasPermission(permissions, 'toggle-status-employees') || hasPermission(permissions, 'edit-employees') ? 'cursor-pointer hover:scale-105' : 'cursor-not-allowed opacity-70'}`}
            >
              <span className={`relative inline-flex h-4 w-7 shrink-0 items-center rounded-full transition-colors duration-200 ease-in-out ${
                employee.status === 'active' ? 'bg-emerald-500' : 'bg-slate-300'
              }`}>
                <span className={`inline-block h-2.5 w-2.5 rounded-full bg-white shadow transition-transform duration-200 ease-in-out ${
                  employee.status === 'active' ? 'translate-x-3.5' : 'translate-x-0.5'
                }`} />
              </span>
              <span className={`text-[10px] font-bold uppercase tracking-wide ${
                employee.status === 'active' ? 'text-emerald-600' : 'text-slate-400'
              }`}>
                {employee.status === 'active' ? t('Active') : t('Inactive')}
              </span>
            </button>

            <div className="w-full space-y-4 pt-4 border-t border-gray-100 dark:border-gray-700">
              <div className="flex items-center group">
                <div className="h-8 w-8 rounded-lg bg-gray-50 dark:bg-gray-900 flex items-center justify-center mr-3 group-hover:bg-primary/10 transition-colors">
                  <User className="h-4 w-4 text-primary" />
                </div>
                <div className="text-left">
                  <p className="text-[10px] text-muted-foreground uppercase font-bold tracking-tighter">{t('Employee ID')}</p>
                  <p className="text-sm font-semibold">{employee.employee?.employee_id || '-'}</p>
                </div>
              </div>

              <div className="flex items-center group">
                <div className="h-8 w-8 rounded-lg bg-gray-50 dark:bg-gray-900 flex items-center justify-center mr-3 group-hover:bg-primary/10 transition-colors">
                  <Mail className="h-4 w-4 text-primary" />
                </div>
                <div className="text-left overflow-hidden">
                  <p className="text-[10px] text-muted-foreground uppercase font-bold tracking-tighter">{t('Email Address')}</p>
                  <p className="text-sm font-semibold truncate max-w-[180px]">{employee.email}</p>
                </div>
              </div>

              {employee.employee?.phone && (
                <div className="flex items-center group">
                  <div className="h-8 w-8 rounded-lg bg-gray-50 dark:bg-gray-900 flex items-center justify-center mr-3 group-hover:bg-primary/10 transition-colors">
                    <Phone className="h-4 w-4 text-primary" />
                  </div>
                  <div className="text-left">
                    <p className="text-[10px] text-muted-foreground uppercase font-bold tracking-tighter">{t('Phone Number')}</p>
                    <p className="text-sm font-semibold">{employee.employee.phone}</p>
                  </div>
                </div>
              )}

              <div className="flex items-center group">
                <div className="h-8 w-8 rounded-lg bg-gray-50 dark:bg-gray-900 flex items-center justify-center mr-3 group-hover:bg-primary/10 transition-colors">
                  <Briefcase className="h-4 w-4 text-primary" />
                </div>
                <div className="text-left">
                  <p className="text-[10px] text-muted-foreground uppercase font-bold tracking-tighter">{t('Department')}</p>
                  <p className="text-sm font-semibold">{employee.employee?.department?.name || '-'}</p>
                </div>
              </div>

              <div className="flex items-center group">
                <div className="h-8 w-8 rounded-lg bg-gray-50 dark:bg-gray-900 flex items-center justify-center mr-3 group-hover:bg-primary/10 transition-colors">
                  <Check className="h-4 w-4 text-primary" />
                </div>
                <div className="text-left">
                  <p className="text-[10px] text-muted-foreground uppercase font-bold tracking-tighter">{t('Joined Date')}</p>
                  <p className="text-sm font-semibold">
                    {employee.employee?.date_of_joining ? (window.appSettings?.formatDateTime(employee.employee.date_of_joining, false) || new Date(employee.employee.date_of_joining).toLocaleDateString()) : '-'}
                  </p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        {/* Employee Details Tabs */}
        <div className="lg:col-span-3">
          <Tabs value={activeTab} onValueChange={setActiveTab} className="w-full">
            <TabsList className="grid w-full grid-cols-2 md:grid-cols-3 lg:grid-cols-5 mb-8 h-auto p-1 bg-gray-100 dark:bg-gray-900/50 rounded-xl">
              <TabsTrigger value="identity" className="rounded-lg py-2.5 data-[state=active]:bg-white dark:data-[state=active]:bg-gray-800 data-[state=active]:shadow-sm transition-all duration-200">
                <User className="h-4 w-4 mr-2" />
                {t('Identity')}
              </TabsTrigger>
              <TabsTrigger value="contact" className="rounded-lg py-2.5 data-[state=active]:bg-white dark:data-[state=active]:bg-gray-800 data-[state=active]:shadow-sm transition-all duration-200">
                <MapPin className="h-4 w-4 mr-2" />
                {t('Contact')}
              </TabsTrigger>
              <TabsTrigger value="career" className="rounded-lg py-2.5 data-[state=active]:bg-white dark:data-[state=active]:bg-gray-800 data-[state=active]:shadow-sm transition-all duration-200">
                <Briefcase className="h-4 w-4 mr-2" />
                {t('Career')}
              </TabsTrigger>
              <TabsTrigger value="finance" className="rounded-lg py-2.5 data-[state=active]:bg-white dark:data-[state=active]:bg-gray-800 data-[state=active]:shadow-sm transition-all duration-200">
                <CreditCard className="h-4 w-4 mr-2" />
                {t('Finance')}
              </TabsTrigger>
              <TabsTrigger value="final" className="rounded-lg py-2.5 data-[state=active]:bg-white dark:data-[state=active]:bg-gray-800 data-[state=active]:shadow-sm transition-all duration-200">
                <ShieldCheck className="h-4 w-4 mr-2" />
                {t('Final')}
              </TabsTrigger>
            </TabsList>

            {/* IDENTITY TAB: Step 1 */}
            <TabsContent value="identity" className="animate-in fade-in slide-in-from-bottom-4 duration-500">
              <Card className="border-none shadow-md">
                <CardHeader className="bg-slate-50/50 border-b border-slate-100">
                  <CardTitle className="text-base font-bold flex items-center gap-2">
                    <User className="h-5 w-5 text-primary" />
                    {t('Identity & Profile')}
                  </CardTitle>
                </CardHeader>
                <CardContent className="p-6">
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Full Name')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.name}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Father Name')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.father_name || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Gender')}</h4>
                      <p className="text-sm font-bold text-slate-800 capitalize">{employee.employee?.gender || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Date of Birth')}</h4>
                      <p className="text-sm font-bold text-slate-800">
                        {employee.employee?.date_of_birth ? (window.appSettings?.formatDateTime(employee.employee.date_of_birth, false) || new Date(employee.employee.date_of_birth).toLocaleDateString()) : '-'}
                      </p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Marital Status')}</h4>
                      <p className="text-sm font-bold text-slate-800 capitalize">{employee.employee?.marital_status || '-'}</p>
                    </div>
                    {employee.employee?.wedding_date && (
                      <div className="space-y-1">
                        <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Wedding Date')}</h4>
                        <p className="text-sm font-bold text-slate-800">
                          {window.appSettings?.formatDateTime(employee.employee.wedding_date, false) || new Date(employee.employee.wedding_date).toLocaleDateString()}
                        </p>
                      </div>
                    )}
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Blood Group')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.blood_group || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Height')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.height ? `${employee.employee.height}` : '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Weight')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.weight ? `${employee.employee.weight} kg` : '-'}</p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            {/* CONTACT TAB: Step 2 */}
            <TabsContent value="contact" className="animate-in fade-in slide-in-from-bottom-4 duration-500">
              <Card className="border-none shadow-md">
                <CardHeader className="bg-slate-50/50 border-b border-slate-100">
                  <CardTitle className="text-base font-bold flex items-center gap-2">
                    <MapPin className="h-5 w-5 text-primary" />
                    {t('Contact & Identity')}
                  </CardTitle>
                </CardHeader>
                <CardContent className="p-6">
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div className="space-y-6">
                      <h3 className="text-xs font-black uppercase text-primary tracking-widest border-b border-slate-100 pb-2">{t('Addresses')}</h3>
                      <div className="grid grid-cols-1 gap-4">
                        <div className="space-y-1">
                          <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Permanent Address')}</h4>
                          <p className="text-sm font-medium text-slate-700">{employee.employee?.address_line_1 || employee.employee?.permanent_address || '-'}</p>
                        </div>
                        <div className="space-y-1">
                          <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Local Address')}</h4>
                          <p className="text-sm font-medium text-slate-700">{employee.employee?.address_line_2 || '-'}</p>
                        </div>
                        <div className="grid grid-cols-3 gap-4">
                          <div className="space-y-1">
                            <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('City')}</h4>
                            <p className="text-sm font-bold text-slate-800">{employee.employee?.city || '-'}</p>
                          </div>
                          <div className="space-y-1">
                            <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('State')}</h4>
                            <p className="text-sm font-bold text-slate-800">{employee.employee?.state || '-'}</p>
                          </div>
                          <div className="space-y-1">
                            <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Pincode')}</h4>
                            <p className="text-sm font-bold text-slate-800">{employee.employee?.postal_code || '-'}</p>
                          </div>
                        </div>
                      </div>
                    </div>

                    <div className="space-y-6">
                      <h3 className="text-xs font-black uppercase text-primary tracking-widest border-b border-slate-100 pb-2">{t('Identity & Reach')}</h3>
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div className="space-y-1">
                          <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Email')}</h4>
                          <p className="text-sm font-bold text-slate-800 truncate">{employee.email}</p>
                        </div>
                        <div className="space-y-1">
                          <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Phone')}</h4>
                          <p className="text-sm font-bold text-slate-800">{employee.employee?.phone || '-'}</p>
                        </div>
                        <div className="space-y-1">
                          <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Emergency Contact')}</h4>
                          <p className="text-sm font-bold text-slate-800">{employee.employee?.phone_2 || '-'}</p>
                        </div>
                        <div className="space-y-1">
                          <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('PAN Number')}</h4>
                          <p className="text-sm font-bold text-slate-800 uppercase">{employee.employee?.pan_card_number || '-'}</p>
                        </div>
                        <div className="space-y-1">
                          <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Aadhaar Number')}</h4>
                          <p className="text-sm font-bold text-slate-800">{employee.employee?.aadhar_card_number || '-'}</p>
                        </div>
                        <div className="space-y-1">
                          <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Driving License')}</h4>
                          <p className="text-sm font-bold text-slate-800">{employee.employee?.driving_license || employee.employee?.driving_licence || '-'}</p>
                        </div>
                      </div>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            {/* CAREER TAB: Step 3 */}
            <TabsContent value="career" className="animate-in fade-in slide-in-from-bottom-4 duration-500">
              <Card className="border-none shadow-md">
                <CardHeader className="bg-slate-50/50 border-b border-slate-100">
                  <CardTitle className="text-base font-bold flex items-center gap-2">
                    <Briefcase className="h-5 w-5 text-primary" />
                    {t('Career & Employment')}
                  </CardTitle>
                </CardHeader>
                <CardContent className="p-6">
                  <div className="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Branch')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.branch?.name || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Department')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.department?.name || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Designation')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.designation?.name || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Category')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.category?.name || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Section')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.section?.name || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Shift')}</h4>
                      <p className="text-sm font-bold text-slate-800">
                        {employee.employee?.shift ? `${employee.employee.shift.name}` : '-'}
                      </p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Individual Week Off')}</h4>
                      <p className="text-sm font-bold text-slate-800">
                        {(() => {
                          if (!employee.employee?.week_off) return '-';
                          if (employee.employee?.week_off_type === 'monthly' || (employee.employee?.week_off || '').startsWith('{')) {
                              try {
                                  const parsed = JSON.parse(employee.employee.week_off);
                                  const allDays = Object.values(parsed).flat() as string[];
                                  const uniqueDays = [...new Set(allDays.map(d => d.substring(0, 3)))].join(', ');
                                  return `${t('Monthly')} (${uniqueDays})`;
                              } catch(e) { return employee.employee.week_off; }
                          }
                          return employee.employee.week_off;
                        })()}
                      </p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Joining Date')}</h4>
                      <p className="text-sm font-bold text-slate-800">
                        {employee.employee?.date_of_joining ? (window.appSettings?.formatDateTime(employee.employee.date_of_joining, false) || new Date(employee.employee.date_of_joining).toLocaleDateString()) : '-'}
                      </p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Confirm Date')}</h4>
                      <p className="text-sm font-bold text-primary">
                        {employee.employee?.confirm_date ? (window.appSettings?.formatDateTime(employee.employee.confirm_date, false) || new Date(employee.employee.confirm_date).toLocaleDateString()) : '-'}
                      </p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Employment Status')}</h4>
                      <p className="text-sm font-bold text-slate-800 capitalize">{employee.employee?.employment_status || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Education')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.education || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Experience')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.experience || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Status (P/OP)')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.po_status || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Daily Option')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.daily_option ? t('Yes') : t('No')}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Working Days')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.working_days || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('HOD')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.hod_flag ? t('Yes') : t('No')}</p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            {/* FINANCE TAB: Step 4 */}
            <TabsContent value="finance" className="animate-in fade-in slide-in-from-bottom-4 duration-500">
              <Card className="border-none shadow-md">
                <CardHeader className="bg-slate-50/50 border-b border-slate-100">
                  <CardTitle className="text-base font-bold flex items-center gap-2">
                    <Landmark className="h-5 w-5 text-primary" />
                    {t('Finance & Salary')}
                  </CardTitle>
                </CardHeader>
                <CardContent className="p-6">
                  <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Bank Name')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.bank_name || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Account Number')}</h4>
                      <p className="text-sm font-bold text-slate-800 font-mono">{employee.employee?.account_number || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('IFSC Code')}</h4>
                      <p className="text-sm font-bold text-slate-800 uppercase">{employee.employee?.ifsc_code || employee.employee?.bank_identifier_code || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Account Type')}</h4>
                      <p className="text-sm font-bold text-slate-800 capitalize">{employee.employee?.account_type || '-'}</p>
                    </div>
                  </div>

                  <div className="h-px bg-slate-100 mb-8" />

                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div className="space-y-4">
                      <div className="flex items-center gap-2 text-green-600 mb-2">
                        <Plus className="h-4 w-4" />
                        <h3 className="text-xs font-black uppercase tracking-widest">{t('Increment (Earnings)')}</h3>
                      </div>
                      <div className="space-y-2">
                        {salaryComponents.filter((c: any) => c.type === 'earning').map((comp: any) => (
                          <div key={comp.id} className="flex justify-between items-center p-3 bg-green-50/50 rounded-xl border border-green-100/50">
                            <span className="text-xs font-bold text-slate-600">{comp.name}</span>
                            <span className="text-sm font-black text-green-700">₹{employeeSalary?.components?.[comp.id] || '0'}</span>
                          </div>
                        ))}
                        <div className="flex justify-between items-center p-4 bg-green-600 rounded-2xl text-white shadow-lg shadow-green-200 mt-4">
                          <span className="text-xs font-black uppercase">{t('Gross Salary')}</span>
                          <span className="text-xl font-black">₹{employee.employee?.gross_salary || '0'}</span>
                        </div>
                      </div>
                    </div>

                    <div className="space-y-4">
                      <div className="flex items-center gap-2 text-red-600 mb-2">
                        <Trash2 className="h-4 w-4" />
                        <h3 className="text-xs font-black uppercase tracking-widest">{t('Deductions')}</h3>
                      </div>
                      <div className="space-y-2">
                        {salaryComponents.filter((c: any) => c.type === 'deduction').map((comp: any) => (
                          <div key={comp.id} className="flex justify-between items-center p-3 bg-red-50/50 rounded-xl border border-red-100/50">
                            <span className="text-xs font-bold text-slate-600">{comp.name}</span>
                            <span className="text-sm font-black text-red-700">₹{employeeSalary?.components?.[comp.id] || '0'}</span>
                          </div>
                        ))}
                        <div className="flex justify-between items-center p-4 bg-slate-800 rounded-2xl text-white shadow-lg mt-4">
                          <span className="text-xs font-black uppercase">{t('Net Payable')}</span>
                          <span className="text-xl font-black">
                            ₹{(parseFloat(employee.employee?.gross_salary || '0') - Object.keys(employeeSalary?.components || {}).reduce((acc, id) => {
                              const comp = salaryComponents.find((c: any) => c.id.toString() === id.toString());
                              return comp?.type === 'deduction' ? acc + parseFloat(employeeSalary.components[id] || '0') : acc;
                            }, 0)).toFixed(2)}
                          </span>
                        </div>
                      </div>
                    </div>
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mt-12 pt-8 border-t border-slate-100">
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('PF Number')}</h4>
                      <p className="text-sm font-bold text-slate-800">{employee.employee?.pf_number || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('UAN Number')}</h4>
                      <p className="text-sm font-bold text-slate-800 font-mono">{employee.employee?.uan_number || '-'}</p>
                    </div>
                    <div className="space-y-1">
                      <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('ESIC Number')}</h4>
                      <p className="text-sm font-bold text-slate-800 font-mono">{employee.employee?.esic_number || '-'}</p>
                    </div>
                  </div>
                </CardContent>
              </Card>
            </TabsContent>

            {/* FINAL TAB: Step 5 */}
            <TabsContent value="final" className="animate-in fade-in slide-in-from-bottom-4 duration-500">
              <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {/* Documents & Nominees */}
                <div className="space-y-6">
                  <Card className="border-none shadow-md">
                    <CardHeader className="bg-slate-50/50 border-b border-slate-100">
                      <CardTitle className="text-base font-bold flex items-center gap-2">
                        <Users className="h-5 w-5 text-primary" />
                        {t('Nominee Details')}
                      </CardTitle>
                    </CardHeader>
                    <CardContent className="p-4">
                      {employee.employee?.nominees && employee.employee.nominees.length > 0 ? (
                        <div className="space-y-3">
                          {employee.employee.nominees.map((nominee: any, idx: number) => (
                            <div key={idx} className="p-4 bg-slate-50 rounded-2xl border border-slate-100">
                              <div className="grid grid-cols-2 gap-4">
                                <div className="col-span-2 space-y-1">
                                  <h4 className="text-[10px] font-bold text-slate-400 uppercase">{t('Name')}</h4>
                                  <p className="text-sm font-bold text-slate-800">{nominee.name}</p>
                                </div>
                                <div className="space-y-1">
                                  <h4 className="text-[10px] font-bold text-slate-400 uppercase">{t('Relation')}</h4>
                                  <p className="text-sm font-bold text-slate-800">{nominee.relation || '-'}</p>
                                </div>
                                <div className="space-y-1">
                                  <h4 className="text-[10px] font-bold text-slate-400 uppercase">{t('Share')}</h4>
                                  <p className="text-sm font-bold text-primary">{nominee.percentage}%</p>
                                </div>
                                <div className="col-span-2 space-y-1">
                                  <h4 className="text-[10px] font-bold text-slate-400 uppercase">{t('Aadhar No')}</h4>
                                  <p className="text-sm font-bold text-slate-800">{nominee.aadhar_number || '-'}</p>
                                </div>
                              </div>
                            </div>
                          ))}
                        </div>
                      ) : (
                        <div className="text-center py-8 text-slate-400 italic text-xs">
                          {t('No nominees added')}
                        </div>
                      )}
                    </CardContent>
                  </Card>

                  <Card className="border-none shadow-md">
                    <CardHeader className="bg-slate-50/50 border-b border-slate-100">
                      <CardTitle className="text-base font-bold flex items-center gap-2">
                        <CreditCard className="h-5 w-5 text-primary" />
                        {t('Loan Details')}
                      </CardTitle>
                    </CardHeader>
                    <CardContent className="p-6">
                      <div className="grid grid-cols-3 gap-4">
                        <div className="space-y-1">
                          <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Total Amount')}</h4>
                          <p className="text-sm font-bold text-red-600">₹{employee.employee?.loan_total_amount || '0'}</p>
                        </div>
                        <div className="space-y-1">
                          <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Installment')}</h4>
                          <p className="text-sm font-bold text-slate-800">₹{employee.employee?.loan_installment_amount || '0'}</p>
                        </div>
                        <div className="space-y-1">
                          <h4 className="text-[10px] font-black uppercase text-slate-400 tracking-wider">{t('Period')}</h4>
                          <p className="text-sm font-bold text-slate-800">{employee.employee?.loan_period ? `${employee.employee.loan_period} M` : '-'}</p>
                        </div>
                      </div>
                    </CardContent>
                  </Card>
                </div>

                {/* Documents List */}
                <Card className="border-none shadow-md h-fit">
                  <CardHeader className="bg-slate-50/50 border-b border-slate-100">
                    <CardTitle className="text-base font-bold flex items-center gap-2">
                      <FileText className="h-5 w-5 text-primary" />
                      {t('Documents')}
                    </CardTitle>
                  </CardHeader>
                  <CardContent className="p-4">
                    {employee.employee?.documents && employee.employee.documents.length > 0 ? (
                      <div className="space-y-3">
                        {employee.employee.documents.map((document: any) => (
                          <div key={document.id} className="p-4 bg-white rounded-2xl border border-slate-100 hover:shadow-md transition-all group">
                            <div className="flex items-center justify-between">
                              <div className="flex items-center gap-3">
                                <div className="h-10 w-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center">
                                  <FileText className="h-5 w-5" />
                                </div>
                                <div>
                                  <h4 className="text-sm font-bold text-slate-800">{document.document_type?.name}</h4>
                                  <p className="text-[10px] font-bold text-slate-400">{document.id_number || t('No ID')}</p>
                                </div>
                              </div>
                              <div className="flex gap-2">
                                <Button size="icon" variant="ghost" className="h-8 w-8 rounded-lg text-blue-500 hover:bg-blue-50" onClick={() => window.open(getImagePath(document.file_path), '_blank')}>
                                  <Eye className="h-4 w-4" />
                                </Button>
                                <Button size="icon" variant="ghost" className="h-8 w-8 rounded-lg text-primary hover:bg-primary/5" onClick={() => window.open(route('hr.employees.documents.download', [employee.id, document.id]), '_blank')}>
                                  <Download className="h-4 w-4" />
                                </Button>
                                {hasPermission(permissions, 'edit-employees') && (
                                  <Button size="icon" variant="ghost" className="h-8 w-8 rounded-lg text-red-500 hover:bg-red-50" onClick={() => handleDeleteDocument(document.id)}>
                                    <Trash2 className="h-4 w-4" />
                                  </Button>
                                )}
                              </div>
                            </div>
                          </div>
                        ))}
                      </div>
                    ) : (
                      <div className="text-center py-12 text-slate-400 italic text-xs">
                        {t('No documents uploaded')}
                      </div>
                    )}
                  </CardContent>
                </Card>
              </div>
            </TabsContent>
          </Tabs>
        </div>
      </div>

      {/* Delete Modal */}
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