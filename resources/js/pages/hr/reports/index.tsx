import { PageTemplate } from '@/components/page-template';
import { Link, router } from '@inertiajs/react';
import { 
  Printer, 
  Users, 
  FileText, 
  Calendar, 
  Database, 
  Search, 
  TrendingUp, 
  CreditCard, 
  Clock, 
  Briefcase,
  ChevronRight,
  ClipboardList,
  UserCheck,
  Percent,
  History,
  Cake,
  FileBadge,
  ShieldCheck,
  UserPlus,
  IndianRupee,
  MoreHorizontal,
  Download,
  Filter,
  Eye,
  FileSpreadsheet,
  ChevronDown,
  Layers,
  Settings,
  AlertCircle,
  ArrowRight,
  Barcode,
  X,
  FileDown,
  User
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Button } from '@/components/ui/button';
import { 
  Dialog, 
  DialogContent, 
  DialogDescription, 
  DialogFooter, 
  DialogHeader, 
  DialogTitle 
} from '@/components/ui/dialog';
import { Badge } from '@/components/ui/badge';
import { 
  DropdownMenu, 
  DropdownMenuContent, 
  DropdownMenuItem, 
  DropdownMenuTrigger,
  DropdownMenuSub,
  DropdownMenuSubTrigger,
  DropdownMenuSubContent,
  DropdownMenuPortal
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { DatePicker } from '@/components/ui/date-picker';
import { Badge } from '@/components/ui/badge';

interface ReportsIndexProps {
  departments: Array<{ id: number; name: string }>;
  sections: Array<{ id: number; name: string }>;
  categories: Array<{ id: number; name: string }>;
  branches: Array<{ id: number; name: string }>;
  employees: Array<{ id: number; code: string; name: string }>;
  userBranchId?: number;
}

export default function ReportsIndex({ 
  departments = [], 
  sections = [], 
  categories = [],
  branches = [],
  employees = [],
  userBranchId 
}: ReportsIndexProps) {
  const { t } = useTranslation();
  const [searchQuery, setSearchQuery] = useState('');
  const [isDialogOpen, setIsDialogOpen] = useState(false);
  const [selectedReport, setSelectedReport] = useState<any>(null);
  const [biometricType, setBiometricType] = useState('codewise');
  
  // Filter States
  const [fromDate, setFromDate] = useState<Date | undefined>(new Date());
  const [toDate, setToDate] = useState<Date | undefined>(new Date());
  const [selectedSection, setSelectedSection] = useState('all');
  const [selectedDept, setSelectedDept] = useState('all');
  const [selectedCategory, setSelectedCategory] = useState('all');
  const [selectedBranch, setSelectedBranch] = useState(userBranchId?.toString() || (branches[0]?.id.toString() || '1'));
  const [selectedPoStatus, setSelectedPoStatus] = useState('all');
  const [statusType, setStatusType] = useState('P');
  const [hourlyType, setHourlyType] = useState('N'); // For Workerwise Attendance
  const [cardType, setCardType] = useState('N'); // For Workerwise Attendance
  const [selectedMasterType, setSelectedMasterType] = useState('CNT');
  const [pfEsicType, setPfEsicType] = useState('PF');
  const [selectedStaffId, setSelectedStaffId] = useState('all');
  const [selectedEmployeeId, setSelectedEmployeeId] = useState('all');
  const [employeeSearchQuery, setEmployeeSearchQuery] = useState('');
  const [isEmployeeListOpen, setIsEmployeeListOpen] = useState(false);
  const [staffListType, setStaffListType] = useState('Alphabetic');
  const [staffSearchQuery, setStaffSearchQuery] = useState('');
  const [isStaffListOpen, setIsStaffListOpen] = useState(false);
  const [selectedMonth, setSelectedMonth] = useState((new Date().getMonth() + 1).toString().padStart(2, '0'));
  const [selectedYear, setSelectedYear] = useState(new Date().getFullYear().toString());
  const [deductionReportType, setDeductionReportType] = useState('summary');
  // Handle Branch Change to update departments/sections
  const handleBranchChange = (branchId: string) => {
    setSelectedBranch(branchId);
    setSelectedDept('all');
    setSelectedSection('all');
    setSelectedCategory('all');

    // Use Inertia to reload the page with new props for the selected branch
    router.visit(window.location.pathname, {
      data: { branch_id: branchId },
      preserveState: true,
      only: ['departments', 'sections', 'categories', 'employees']
    });
  };

  const openReportDialog = (report: any) => {
    setSelectedReport(report);
    
    // For Single Date Summary, we only need one date
    if (report.id === 'att_summary') {
      const now = new Date();
      setFromDate(now);
      setToDate(now);
    }
    
    // For Matrix/Attendance reports, default to the full current month
    else if (['att_worker', 'att_dept', 'att_shift', 'emp_monthly', 'production'].includes(report.id)) {
      const now = new Date();
      setFromDate(new Date(now.getFullYear(), now.getMonth(), 1));
      setToDate(new Date(now.getFullYear(), now.getMonth() + 1, 0));
      
      if (report.id === 'production') {
        setBiometricType('summary');
      }
    }

    setIsDialogOpen(true);
  };

  const handleGenerateReport = () => {
    const formatDate = (date: Date | undefined) => {
      if (!date) return '';
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    };

    if (selectedReport?.id === 'common_master') {
      const url = `/reports/master-listing?type=${selectedMasterType}`;
      const link = document.createElement('a');
      link.href = url;
      link.target = '_blank';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      setIsDialogOpen(false);
      return;
    }

    const masterIdMap: Record<string, string> = {
      'designation': 'DSG',
      'shift': 'SHT',
      'bank': 'BNK',
      'skill': 'SKL',
      'pf_esic': 'PFE',
      'material': 'MAT'
    };

    const commonParams = `?from_date=${formatDate(fromDate)}&to_date=${formatDate(toDate)}&section=${selectedSection}&department=${selectedDept}&category=${selectedCategory}&branch_id=${selectedBranch}&po_status=${selectedPoStatus}&status=${statusType}&report_type=${biometricType}&report_id=${selectedReport?.id}`;

    if (selectedReport?.id === 'biometric_dedicated') {
      const url = `/reports/biometric-dedicated${commonParams}`;
      const link = document.createElement('a');
      link.href = url;
      link.target = '_blank';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      setIsDialogOpen(false);
      return;
    }

    if (selectedReport?.id === 'pf_esic') {
      const url = `/reports/master-listing?type=PFE&subtype=${pfEsicType}`;
      const link = document.createElement('a');
      link.href = url;
      link.target = '_blank';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      setIsDialogOpen(false);
      return;
    }

    if (selectedReport?.id === 'staff') {
      const url = `/reports/master-listing?type=STF&employee_id=${selectedStaffId}&list_type=${staffListType}`;
      const link = document.createElement('a');
      link.href = url;
      link.target = '_blank';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      setIsDialogOpen(false);
      return;
    }

    if (selectedReport?.id && masterIdMap[selectedReport.id]) {
      const url = `/reports/master-listing?type=${masterIdMap[selectedReport.id]}`;
      const link = document.createElement('a');
      link.href = url;
      link.target = '_blank';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      setIsDialogOpen(false);
      return;
    }

    const filters = {
      report_id: selectedReport?.id,
      from_date: formatDate(fromDate),
      to_date: formatDate(toDate),
      section: selectedSection,
      department: selectedDept,
      category: selectedCategory,
      po_status: selectedPoStatus,
      report_type: biometricType,
      status: statusType,
      hourly_type: hourlyType,
      card_type: cardType,
      branch_id: selectedBranch,
      employee_id: selectedEmployeeId,
      month: selectedMonth,
      year: selectedYear,
      deduction_type: deductionReportType
    };
    
    const params = new URLSearchParams(filters as any).toString();
    const url = `/reports/generate?${params}`;
    
    const link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    setIsDialogOpen(false);
  };

  const masterReports = [
    {
      category: t('1. Master'),
      description: t('Core system configuration masters'),
      icon: Database,
      color: 'bg-gradient-to-br from-[#1e293b] via-[#334155] to-[#1e293b]',
      items: [
        { title: '1. CNT/DPT/PLC/RLV/SEC Master Printing', id: 'common_master', icon: Printer },
        { title: '2. Designation Master Printing', id: 'designation', icon: Briefcase },
        { title: '3. Shift Master Printing', id: 'shift', icon: Clock },
        { title: '4. Bank Master Printing', id: 'bank', icon: IndianRupee },
        { title: '5. Skill Master Printing', id: 'skill', icon: TrendingUp },
        { title: '6. PF / ESIC Master Printing', id: 'pf_esic', icon: Percent },
        { title: '7. Staff Master Printing', id: 'staff', icon: Users },
        { title: '8. Material Item Master', id: 'material', icon: Layers },
        { 
          title: '9. Staff Management Report', 
          icon: Users,
          hasSubmenu: true,
          items: [
            { title: '1. Letter - Relieving / Socase / Declaration Form', id: 'letters', icon: FileBadge },
            { title: '2. Staff Birthday / Anniversary / Year Complete', id: 'birthday', icon: Cake },
            { title: '3. Staff I-Card Printing', id: 'icard', icon: Printer },
            { title: '4. Staff Police Report', id: 'police', icon: ShieldCheck },
            { title: '5. Staff History Report', id: 'history', icon: History },
            { title: '6. Staff Socase Letter', id: 'socase', icon: AlertCircle },
            { title: '7. Staff Retirement Letter', id: 'retirement', icon: History },
            { title: '8. Staff Employee Appraisal / Increment', id: 'appraisal', icon: TrendingUp },
            { title: '9. Staff Offer / Appointment Letter', id: 'offer', icon: UserPlus },
          ]
        },
      ]
    }
  ];

  const transactionReports = [
    {
      category: t('2. Transaction'),
      description: t('Daily operations and transaction reports'),
      icon: TrendingUp,
      color: 'bg-gradient-to-br from-[#1a365d] via-[#2c5282] to-[#1a365d]',
      items: [
        { 
          title: '1. Daily Report', 
          icon: Clock,
          hasSubmenu: true,
          items: [
            { 
              title: '1. Biometric Report Menu', 
              id: 'bio_menu', 
              icon: Barcode,
              hasSubmenu: true,
              items: [
                { title: '1. Biometric Report Module', id: 'biometric_dedicated', icon: UserCheck, isBiometric: true },
                { title: '2. Biometric Report', id: 'biometric', icon: Barcode, isBiometric: true },
                { title: '3. Biometric Daily Present', id: 'biometric_single', icon: Eye, isBiometric: true },
                { title: '4. All Punch Report', id: 'all_punch', icon: FileSpreadsheet, isBiometric: true },
              ]
            },
            { 
              title: '2. Attendant Report Menu', 
              id: 'att_menu', 
              icon: Calendar,
              hasSubmenu: true,
              items: [
                { title: '1. Workerwise Attn. Report', id: 'att_worker', icon: Users, isBiometric: true },
                { title: '2. Departmentwise Attn. Report', id: 'att_dept', icon: Briefcase, isBiometric: true },
                { title: '3. Shiftwise Attn. Report', id: 'att_shift', icon: Clock, isBiometric: true },
                { title: '4. Single Datewise Worker Summary', id: 'att_summary', icon: ClipboardList, isBiometric: true },
              ]
            },
            { 
              title: '3. Incentive / Deduction Report', 
              id: 'inc_menu', 
              icon: IndianRupee,
              hasSubmenu: true,
              items: [
                { title: '1. Incentive Report', id: 'incentive', icon: TrendingUp },
                { title: '2. Monthly Deduction List', id: 'deduction', icon: Percent },
              ]
            },
            { title: '4. Production Printing', id: 'production', icon: Printer },
          ]
        },
        { 
          title: '2. Single Employee Menu', 
          icon: UserCheck,
          hasSubmenu: true,
          items: [
            { title: '1. Single Employee Attendent', id: 'emp_monthly', icon: Calendar },
            { title: '2. Single Employee Deduction List', id: 'emp_salary', icon: IndianRupee },
            { title: '3. Single Employee Month PaySlip', id: 'emp_leave', icon: ClipboardList },
            { title: '4. Single Employee Yearly Pay Register', id: 'emp_detail', icon: FileText },
            { title: '5. Single Departmentwise Employee Reg', id: 'emp_docs', icon: Printer },
          ]
        },
        { 
          title: '3. Salary Report', 
          icon: FileSpreadsheet,
          hasSubmenu: true,
          items: [
            { title: '1. Employee Salary Register', id: 'salary_reg', icon: FileSpreadsheet },
            { title: '2. Employee Payslip-All', id: 'payslip_all', icon: FileText },
            { title: '3. Professional Tax Register', id: 'pt_reg', icon: Percent },
            { title: '4. Provident Fund Register', id: 'pf_reg', icon: ShieldCheck },
            { title: '5. ESIC Reports', id: 'esic_reports', icon: ClipboardList },
            { title: '6. Bank RTGS Report', id: 'bank_rtgs', icon: IndianRupee },
            { title: '7. Gross Salary List', id: 'gross_salary', icon: TrendingUp },
            { title: '8. Departmentwise Salary Register', id: 'dept_salary', icon: Briefcase },
            { title: '9. Non Bank Report', id: 'non_bank', icon: AlertCircle },
          ]
        },
        { 
          title: '4. Loan Report Menu', 
          icon: IndianRupee,
          hasSubmenu: true,
          items: [
            { title: '1. Loan Application Report', id: 'loan_app', icon: FileText },
            { title: '2. Loan Register', id: 'loan_reg', icon: ClipboardList },
          ]
        },
        { 
          title: '5. Leave Report Menu', 
          icon: Calendar,
          hasSubmenu: true,
          items: [
            { title: '1. Leave Application Report', id: 'leave_app', icon: FileText },
            { title: '2. Leave Register', id: 'leave_reg', icon: ClipboardList },
          ]
        },
        { 
          title: '6. Management Report', 
          icon: Settings,
          hasSubmenu: true,
          items: [
            { title: '1. Blank Format', id: 'blank_format', icon: FileText },
            { title: '2. PF/ESI CSV File Convertion.', id: 'pf_esi_conv', icon: FileSpreadsheet },
            { title: '3. Monthly Bill Printing', id: 'monthly_bill', icon: Printer },
            { title: '4. Salary/Experience Certificate Report', id: 'cert_report', icon: FileBadge },
            { title: '5. Employee Exit Date Report', id: 'exit_report', icon: History },
          ]
        },
      ]
    }
  ];

  const allReports = useMemo(() => {
    const flat: any[] = [];
    const extractItems = (items: any[], cat: string) => {
      items.forEach(item => {
        if (item.hasSubmenu) {
          extractItems(item.items, cat);
        } else {
          flat.push({ ...item, category: cat });
        }
      });
    };
    masterReports.forEach(c => extractItems(c.items, c.category));
    transactionReports.forEach(c => extractItems(c.items, c.category));
    return flat;
  }, [masterReports, transactionReports]);

  const filteredSearchResults = useMemo(() => {
    if (!searchQuery) return [];
    return allReports.filter(report => 
      report.title.toLowerCase().includes(searchQuery.toLowerCase())
    );
  }, [searchQuery, allReports]);

  const ReportItem = ({ item }: { item: any }) => {
    const Icon = item.icon || FileText;

    if (item.hasSubmenu) {
      return (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <button className="flex items-center justify-between w-full p-3 rounded-xl hover:bg-slate-50 group transition-all text-left border border-transparent hover:border-slate-100 hover:shadow-sm">
              <div className="flex items-center gap-3.5">
                <div className="p-2.5 rounded-lg bg-slate-50 group-hover:bg-primary/5 text-slate-400 group-hover:text-primary transition-all duration-300">
                   <Icon className="h-4.5 w-4.5" />
                </div>
                <span className="text-[13px] font-bold text-slate-600 group-hover:text-slate-900 block leading-tight tracking-tight">{item.title}</span>
              </div>
              <ChevronRight className="h-4 w-4 text-slate-300 group-hover:text-primary group-hover:translate-x-0.5 transition-all" />
            </button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="start" className="w-72 rounded-xl p-1.5 shadow-xl border-slate-200">
            {item.items.map((sub: any, i: number) => {
              const SubIcon = sub.icon || FileText;
              
              if (sub.hasSubmenu) {
                return (
                  <DropdownMenuSub key={i}>
                    <DropdownMenuSubTrigger className="rounded-lg p-2.5 cursor-pointer hover:bg-slate-100 focus:bg-slate-100 mb-1">
                      <div className="flex items-center gap-2.5">
                        <SubIcon className="h-4 w-4 text-slate-500" />
                        <span className="text-sm font-medium text-slate-700">{sub.title}</span>
                      </div>
                    </DropdownMenuSubTrigger>
                    <DropdownMenuPortal>
                      <DropdownMenuSubContent className="rounded-xl p-1.5 shadow-xl border-slate-200 w-64">
                        {sub.items.map((nested: any, ni: number) => (
                          <DropdownMenuItem 
                            key={ni} 
                            className="rounded-lg p-2.5 cursor-pointer hover:bg-slate-100 focus:bg-slate-100 mb-1"
                            onSelect={() => openReportDialog(nested)}
                          >
                            <div className="flex items-center gap-2.5">
                              <FileText className="h-4 w-4 text-slate-400" />
                              <span className="text-sm font-medium text-slate-700">{nested.title}</span>
                            </div>
                          </DropdownMenuItem>
                        ))}
                      </DropdownMenuSubContent>
                    </DropdownMenuPortal>
                  </DropdownMenuSub>
                );
              }

              return (
                <DropdownMenuItem 
                  key={i} 
                  className="rounded-lg p-2.5 cursor-pointer hover:bg-slate-100 focus:bg-slate-100 mb-1"
                  onSelect={() => openReportDialog(sub)}
                >
                  <div className="flex items-center gap-2.5">
                    <SubIcon className="h-4 w-4 text-slate-500" />
                    <span className="text-sm font-medium text-slate-700">{sub.title}</span>
                  </div>
                </DropdownMenuItem>
              );
            })}
          </DropdownMenuContent>
        </DropdownMenu>
      );
    }

    return (
      <button 
        onClick={() => openReportDialog(item)}
        className="flex items-center justify-between w-full p-3 rounded-xl hover:bg-slate-50 group transition-all text-left border border-transparent hover:border-slate-100 hover:shadow-sm"
      >
        <div className="flex items-center gap-3.5">
          <div className="p-2.5 rounded-lg bg-slate-50 group-hover:bg-primary/5 text-slate-400 group-hover:text-primary transition-all duration-300">
             <Icon className="h-4.5 w-4.5" />
          </div>
          <span className="text-[13px] font-bold text-slate-600 group-hover:text-slate-900 leading-tight tracking-tight">{item.title}</span>
        </div>
        <ArrowRight className="h-4 w-4 text-slate-300 opacity-0 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all" />
      </button>
    );
  };
  const isMasterReport = useMemo(() => {
    return ['common_master', 'designation', 'shift', 'bank', 'skill', 'pf_esic', 'staff', 'material'].includes(selectedReport?.id);
  }, [selectedReport]);

  return (
    <PageTemplate
      title={t('Reports Center')}
      url="/reports"
    >
      <div className="space-y-6 max-w-7xl mx-auto p-2">
        {/* Compact Search Header */}
        <div className="bg-white p-6 rounded-2xl shadow-sm border border-slate-200 flex flex-col md:flex-row items-center justify-between gap-6">
          <div className="space-y-0.5 text-center md:text-left">
            <h1 className="text-xl font-bold text-slate-900">{t('Reports Center')}</h1>
            <p className="text-xs text-slate-500 font-medium">{t('Numbered hierarchical menu system')}</p>
          </div>
          
          <div className="relative w-full md:w-80">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400" />
            <Input 
              placeholder={t('Search by name or number...')} 
              className="pl-9 h-10 border-slate-200 bg-slate-50/50 focus:bg-white rounded-lg text-sm"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
            />
          </div>
        </div>

        {searchQuery ? (
           <div className="space-y-4">
             <div className="flex items-center gap-2 text-xs text-slate-500 font-bold uppercase tracking-wider px-2">
               <Search className="h-3 w-3" />
               <span>{t('Results for')} "{searchQuery}" ({filteredSearchResults.length})</span>
             </div>
             {filteredSearchResults.length > 0 ? (
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3">
                  {filteredSearchResults.map((report, i) => (
                    <button 
                      key={i}
                      onClick={() => openReportDialog(report)}
                      className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-all flex items-center justify-between group"
                    >
                      <div className="flex items-center gap-3">
                        <div className="p-2 rounded-lg bg-slate-50 text-slate-500 group-hover:text-primary group-hover:bg-primary/10">
                          {report.icon ? <report.icon className="h-4 w-4" /> : <FileText className="h-4 w-4" />}
                        </div>
                        <div className="text-left">
                          <span className="block text-sm font-bold text-slate-800 leading-tight">{report.title}</span>
                          <span className="text-[10px] text-slate-400 font-bold uppercase tracking-widest">{report.category}</span>
                        </div>
                      </div>
                    </button>
                  ))}
                </div>
             ) : (
                <div className="bg-slate-50 rounded-xl p-10 text-center border-2 border-dashed border-slate-200">
                  <h3 className="text-sm font-bold text-slate-800 mb-1">{t('No reports found')}</h3>
                  <Button variant="link" size="sm" onClick={() => setSearchQuery('')}>{t('Clear Search')}</Button>
                </div>
             )}
           </div>
        ) : (
          <Tabs defaultValue="master" className="w-full">
            <div className="flex justify-center w-full">
              <TabsList className="bg-slate-100 p-1 rounded-xl mb-8 inline-flex border border-slate-200">
                <TabsTrigger value="master" className="rounded-lg px-8 py-2 data-[state=active]:bg-white data-[state=active]:shadow-sm data-[state=active]:text-primary font-bold text-sm transition-all gap-2">
                  <Database className="h-4 w-4" />
                  {t('1. Master')}
                </TabsTrigger>
                <TabsTrigger value="transaction" className="rounded-lg px-8 py-2 data-[state=active]:bg-white data-[state=active]:shadow-sm data-[state=active]:text-primary font-bold text-sm transition-all gap-2">
                  <TrendingUp className="h-4 w-4" />
                  {t('2. Transaction')}
                </TabsTrigger>
              </TabsList>
            </div>

            <TabsContent value="master" className="space-y-6">
              <div className="grid grid-cols-1 gap-6">
                {masterReports.map((cat, idx) => (
                  <Card key={idx} className="border-slate-200 shadow-sm rounded-2xl overflow-hidden">
                    <CardHeader className={`${cat.color} text-white p-4`}>
                      <div className="flex items-center gap-3">
                        <Database className="h-5 w-5" />
                        <CardTitle className="text-lg font-bold">{cat.category}</CardTitle>
                      </div>
                    </CardHeader>
                    <CardContent className="p-4">
                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-1.5">
                        {cat.items.map((item, i) => (
                          <ReportItem key={i} item={item} />
                        ))}
                      </div>
                    </CardContent>
                  </Card>
                ))}
              </div>
            </TabsContent>

            <TabsContent value="transaction" className="space-y-6">
              <div className="grid grid-cols-1 gap-6">
                {transactionReports.map((cat, idx) => (
                  <Card key={idx} className="border-slate-200 shadow-sm rounded-2xl overflow-hidden">
                    <CardHeader className={`${cat.color} text-white p-4`}>
                      <div className="flex items-center gap-3">
                        <TrendingUp className="h-5 w-5" />
                        <CardTitle className="text-lg font-bold">{cat.category}</CardTitle>
                      </div>
                    </CardHeader>
                    <CardContent className="p-4">
                      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-1.5">
                        {cat.items.map((item, i) => (
                          <ReportItem key={i} item={item} />
                        ))}
                      </div>
                    </CardContent>
                  </Card>
                ))}
              </div>
            </TabsContent>
          </Tabs>
        )}
      </div>

      {/* Report Generation Popup - Refined Design with NATIVE SELECTS for 100% Reliability */}
      <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
        <DialogContent 
          className="sm:max-w-[550px] rounded-3xl border-none shadow-2xl p-0 bg-slate-50 overflow-hidden outline-none flex flex-col [&>button:last-child]:text-white/70 [&>button:last-child]:hover:text-white [&>button:last-child]:top-6 [&>button:last-child]:right-6 [&>button:last-child]:transition-all"
        >
          {/* Header Section with Gradient - Fixed at top */}
          <div className="bg-gradient-to-r from-[#1a365d] to-[#2c5282] text-white px-8 py-6 shrink-0">
             <div className="flex items-center justify-between">
               <div className="flex items-center gap-4">
                 <div className="p-3 bg-white/10 backdrop-blur-md rounded-2xl border border-white/10">
                   <FileText className="h-6 w-6 text-primary-foreground" />
                 </div>
                 <div>
                   <h2 className="text-xl font-bold tracking-tight leading-none">
                     {selectedReport?.id === 'common_master' ? 'Master Listing Report' : (selectedReport?.isBiometric ? 'Attendance Report' : selectedReport?.title.replace(' Printing', ''))}
                   </h2>
                   <p className="text-[11px] text-blue-200 font-semibold uppercase mt-1 tracking-widest opacity-80">
                     Configure Report Parameters
                   </p>
                 </div>
               </div>
             </div>
          </div>

          {/* Scrollable Content Area */}
          <div className="p-8 py-6 space-y-6 overflow-y-auto max-h-[65vh] flex-1 scrollbar-thin scrollbar-thumb-slate-200">
            {selectedReport?.id === 'common_master' ? (
              <div className="space-y-4">
                <div className="flex items-center gap-2 px-1">
                  <Layers className="h-4 w-4 text-primary" />
                  <span className="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Master Selection</span>
                </div>
                <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                  <div className="flex flex-col gap-2">
                    <Label className="text-sm font-bold text-slate-700 ml-1">Report for</Label>
                    <select 
                      value={selectedMasterType} 
                      onChange={(e) => setSelectedMasterType(e.target.value)}
                      className="w-full h-12 px-4 rounded-xl border-2 border-slate-200 bg-slate-50/30 focus:bg-white focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all text-sm font-bold outline-none appearance-none cursor-pointer"
                      style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 16px center', backgroundSize: '16px' }}
                    >
                      <option value="CNT">Contract</option>
                      <option value="DPT">Department</option>
                      <option value="PLC">Place / Unit</option>
                      <option value="RLV">Leave Reason</option>
                      <option value="SEC">Section</option>
                    </select>
                  </div>
                </div>
              </div>
            ) : selectedReport?.id === 'staff' ? (
              <div className="space-y-6">
                <div className="flex items-center gap-2 px-1">
                  <UserCheck className="h-4 w-4 text-primary" />
                  <span className="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Staff Configuration</span>
                </div>
                
                <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-5">
                  <div className="space-y-2">
                    <Label className="text-xs font-bold text-slate-500 uppercase ml-1">Staff / Worker Selection</Label>
                    <div className="relative group">
                      <div className="relative">
                        <Search className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-slate-400 group-focus-within:text-primary transition-colors" />
                        <input 
                          type="text"
                          placeholder="Search name or code (Leave blank for all)"
                          value={staffSearchQuery}
                          onChange={(e) => {
                            setStaffSearchQuery(e.target.value);
                            if (e.target.value === '') setSelectedStaffId('all');
                          }}
                          onFocus={() => setIsStaffListOpen(true)}
                          className="w-full h-12 pl-11 pr-4 rounded-xl border-2 border-slate-200 bg-slate-50/30 focus:bg-white focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all text-sm font-bold outline-none"
                        />
                      </div>

                      {/* Live Dropdown Results */}
                      {isStaffListOpen && staffSearchQuery.length > 0 && (
                        <div className="absolute z-50 w-full mt-2 bg-white rounded-2xl border border-slate-200 shadow-2xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
                          <div className="max-h-[250px] overflow-y-auto scrollbar-thin scrollbar-thumb-slate-200">
                            {employees
                              .filter(emp => 
                                emp.name.toLowerCase().includes(staffSearchQuery.toLowerCase()) || 
                                emp.code.toLowerCase().includes(staffSearchQuery.toLowerCase())
                              )
                              .slice(0, 50) // Limit for performance
                              .map(emp => (
                                <button
                                  key={emp.id}
                                  onClick={() => {
                                    setSelectedStaffId(emp.id.toString());
                                    setStaffSearchQuery(`${emp.code} - ${emp.name}`);
                                    setIsStaffListOpen(false);
                                  }}
                                  className="w-full px-5 py-3 text-left hover:bg-slate-50 flex items-center justify-between transition-colors border-b border-slate-50 last:border-0"
                                >
                                  <div className="flex flex-col">
                                    <span className="text-sm font-bold text-slate-700">{emp.name}</span>
                                    <span className="text-[10px] font-bold text-primary uppercase tracking-wider">{emp.code}</span>
                                  </div>
                                  <User className="h-4 w-4 text-slate-300" />
                                </button>
                              ))
                            }
                            {employees.filter(emp => 
                              emp.name.toLowerCase().includes(staffSearchQuery.toLowerCase()) || 
                              emp.code.toLowerCase().includes(staffSearchQuery.toLowerCase())
                            ).length === 0 && (
                              <div className="px-5 py-8 text-center">
                                <p className="text-sm font-bold text-slate-400 italic">No matching staff found</p>
                              </div>
                            )}
                          </div>
                        </div>
                      )}

                      {/* Click outside to close - invisible overlay when open */}
                      {isStaffListOpen && (
                        <div 
                          className="fixed inset-0 z-40 bg-transparent" 
                          onClick={() => setIsStaffListOpen(false)}
                        />
                      )}
                    </div>
                    {selectedStaffId !== 'all' && (
                       <div className="flex items-center gap-2 px-3 py-2 bg-primary/5 rounded-lg border border-primary/10">
                          <Badge variant="outline" className="bg-primary/10 text-primary border-primary/20 font-bold">SELECTED</Badge>
                          <span className="text-[11px] font-bold text-slate-600 truncate max-w-[300px]">
                             {employees.find(e => e.id.toString() === selectedStaffId)?.name}
                          </span>
                          <button 
                            onClick={() => {
                              setSelectedStaffId('all');
                              setStaffSearchQuery('');
                            }}
                            className="ml-auto p-1 hover:bg-primary/10 rounded-full transition-colors"
                          >
                            <X className="h-3 w-3 text-primary" />
                          </button>
                       </div>
                    )}
                  </div>

                  <div className="space-y-2">
                    <Label className="text-xs font-bold text-slate-500 uppercase ml-1">Report Listing Type</Label>
                    <select 
                      value={staffListType} 
                      onChange={(e) => setStaffListType(e.target.value)}
                      className="w-full h-12 px-4 rounded-xl border-2 border-slate-200 bg-white focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all text-sm font-bold outline-none appearance-none cursor-pointer"
                      style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 16px center', backgroundSize: '16px' }}
                    >
                      <option value="Alphabetic">ALPHABETICAL ORDER</option>
                      <option value="Department Wise">DEPARTMENT WISE ORDER</option>
                    </select>
                  </div>
                </div>
              </div>
            ) : selectedReport?.id === 'pf_esic' ? (
              <div className="space-y-4">
                <div className="flex items-center gap-2 px-1">
                  <Layers className="h-4 w-4 text-primary" />
                  <span className="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Master Selection</span>
                </div>
                <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                  <div className="flex flex-col gap-2">
                    <Label className="text-sm font-bold text-slate-700 ml-1">Report for</Label>
                    <select 
                      value={pfEsicType} 
                      onChange={(e) => setPfEsicType(e.target.value)}
                      className="w-full h-12 px-4 rounded-xl border-2 border-slate-200 bg-slate-50/30 focus:bg-white focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all text-sm font-bold outline-none appearance-none cursor-pointer"
                      style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 16px center', backgroundSize: '16px' }}
                    >
                      <option value="PF">PF</option>
                      <option value="ESIC">ESIC</option>
                    </select>
                  </div>
                </div>
              </div>
            ) : isMasterReport ? (
              <div className="space-y-4">
                <div className="flex items-center justify-center py-12">
                   <div className="text-center space-y-3">
                     <div className="p-4 bg-primary/5 rounded-full inline-block border border-primary/10">
                        <FileDown className="h-10 w-10 text-primary" />
                     </div>
                     <p className="text-sm font-bold text-slate-600">Download the full {selectedReport?.title.replace(' Printing', '')} in PDF format.</p>
                   </div>
                </div>
              </div>
            ) : (
              <>
                {/* 1. Single Date Summary Popup (4th Menu) */}
                {selectedReport?.id === 'att_summary' ? (
                  <div className="space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <div className="flex items-center gap-2 px-1">
                      <Layers className="h-4 w-4 text-primary" />
                      <span className="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Summary Configuration</span>
                    </div>
                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-6">
                       {/* Section Filter */}
                       <div className="space-y-2 group">
                        <Label className="text-xs font-bold text-slate-700 ml-1">Section Selection</Label>
                        <select 
                          value={selectedSection} 
                          onChange={(e) => setSelectedSection(e.target.value)}
                          className="w-full h-12 px-4 rounded-xl border border-slate-200 bg-slate-50/30 focus:bg-white focus:border-primary focus:ring-4 focus:ring-primary/10 transition-all text-sm font-bold outline-none appearance-none cursor-pointer"
                          style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 16px center', backgroundSize: '16px' }}
                        >
                          <option value="all">All Sections</option>
                          {sections.map(sec => (
                            <option key={sec.id} value={sec.id.toString()}>{sec.name}</option>
                          ))}
                        </select>
                        <p className="text-[10px] text-red-500 italic font-medium ml-1">Leave Blank for All</p>
                      </div>

                      {/* Single Date Selection */}
                      <div className="space-y-2 group">
                        <Label className="text-xs font-bold text-slate-700 ml-1">Select Report Date</Label>
                        <div className="relative">
                          <Input
                            type="date"
                            value={fromDate ? fromDate.toISOString().split('T')[0] : ''}
                            onChange={(e) => {
                              const d = new Date(e.target.value);
                              setFromDate(d);
                              setToDate(d); // Keep both same for single date
                            }}
                            className="h-12 border-slate-200 focus:ring-primary/20 rounded-xl pl-10 pr-4 transition-all hover:border-primary/50 bg-slate-50/30 font-bold text-sm"
                          />
                          <Calendar className="absolute left-3.5 top-3.5 h-5 w-5 text-slate-400 group-hover:text-primary transition-colors" />
                        </div>
                      </div>
                    </div>
                  </div>
                ) : (
                  <>
                {selectedReport?.id === 'deduction' && (
                  <div className="space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-500">
                    <div className="flex items-center gap-2 px-1">
                      <Settings className="h-4 w-4 text-primary" />
                      <span className="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Deduction List Configuration</span>
                    </div>
                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-6">
                      <div className="grid grid-cols-2 gap-4">
                        {/* Month Selection */}
                        <div className="space-y-2 group">
                          <Label className="text-xs font-bold text-slate-700 ml-1">Select Month</Label>
                          <select 
                            value={selectedMonth} 
                            onChange={(e) => setSelectedMonth(e.target.value)}
                            className="w-full h-11 px-4 rounded-xl border border-slate-200 bg-slate-50/30 focus:bg-white transition-all text-sm font-bold outline-none cursor-pointer"
                          >
                            {['01','02','03','04','05','06','07','08','09','10','11','12'].map(m => (
                              <option key={m} value={m}>{new Date(2000, parseInt(m)-1).toLocaleString('default', { month: 'long' })}</option>
                            ))}
                          </select>
                        </div>
                        {/* Year Selection */}
                        <div className="space-y-2 group">
                          <Label className="text-xs font-bold text-slate-700 ml-1">Select Year</Label>
                          <select 
                            value={selectedYear} 
                            onChange={(e) => setSelectedYear(e.target.value)}
                            className="w-full h-11 px-4 rounded-xl border border-slate-200 bg-slate-50/30 focus:bg-white transition-all text-sm font-bold outline-none cursor-pointer"
                          >
                            {[2024, 2025, 2026, 2027].map(y => (
                              <option key={y} value={y.toString()}>{y}</option>
                            ))}
                          </select>
                        </div>
                      </div>
                      
                      {/* Report Type */}
                      <div className="space-y-2 group">
                        <Label className="text-xs font-bold text-slate-700 ml-1">Report Type</Label>
                        <select 
                          value={deductionReportType} 
                          onChange={(e) => setDeductionReportType(e.target.value)}
                          className="w-full h-11 px-4 rounded-xl border border-slate-200 bg-slate-50/30 focus:bg-white transition-all text-sm font-bold outline-none cursor-pointer"
                        >
                          <option value="summary">Summary</option>
                          <option value="detail">Detail</option>
                        </select>
                      </div>
                    </div>
                  </div>
                )}

                {/* Time Selection */}
                {selectedReport?.id !== 'deduction' && (
                  <div className="space-y-3">
                    <div className="flex items-center gap-2 px-1">
                      <Calendar className="h-4 w-4 text-primary" />
                      <span className="text-[11px] font-bold text-slate-500 uppercase tracking-widest">{t('Time Period Selection')}</span>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                      <div className="flex flex-col gap-1.5">
                        <Label className="text-xs font-bold text-slate-700 ml-1">{t('From Date')}</Label>
                        <DatePicker 
                          className="w-full h-11 rounded-xl border-slate-200 bg-white hover:border-primary/30 transition-all shadow-sm" 
                          selected={fromDate}
                          onSelect={setFromDate}
                          max={new Date().toISOString().split('T')[0]}
                        />
                      </div>
                      <div className="flex flex-col gap-1.5">
                        <Label className="text-xs font-bold text-slate-700 ml-1">{t('To Date')}</Label>
                        <DatePicker 
                          className="w-full h-11 rounded-xl border-slate-200 bg-white hover:border-primary/30 transition-all shadow-sm" 
                          selected={toDate}
                          onSelect={setToDate}
                          max={new Date().toISOString().split('T')[0]}
                        />
                      </div>
                    </div>
                  </div>
                )}
              </>
              )}
                
                {/* Organizational Filters - Shown for Biometric and Workerwise */}
                {selectedReport?.id === 'emp_monthly' && (
                  <div className="space-y-3 mb-6 animate-in fade-in slide-in-from-top-4 duration-500">
                    <div className="flex items-center gap-2 px-1">
                      <User className="h-4 w-4 text-primary" />
                      <span className="text-[11px] font-bold text-slate-500 uppercase tracking-widest">Single Employee Selection</span>
                    </div>
                    <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-3 relative">
                      <div className="relative group">
                        <Search className="absolute left-3 top-3.5 h-4 w-4 text-slate-400 group-hover:text-primary transition-colors" />
                        <Input 
                          placeholder="Type Name or Employee Code..." 
                          value={employeeSearchQuery}
                          onFocus={() => setIsEmployeeListOpen(true)}
                          onChange={(e) => {
                            setEmployeeSearchQuery(e.target.value);
                            setIsEmployeeListOpen(true);
                          }}
                          className="pl-10 h-11 bg-slate-50/50 border-slate-200 focus:ring-primary/20 rounded-xl font-bold text-sm"
                        />
                        {employeeSearchQuery && (
                          <button 
                            onClick={() => {
                              setEmployeeSearchQuery('');
                              setSelectedEmployeeId('all');
                            }}
                            className="absolute right-3 top-3 h-5 w-5 text-slate-400 hover:text-red-500 transition-colors"
                          >
                            <X className="h-4 w-4" />
                          </button>
                        )}
                      </div>

                      {isEmployeeListOpen && employeeSearchQuery.length > 0 && (
                        <div className="absolute left-4 right-4 top-16 z-50 bg-white border border-slate-200 rounded-xl shadow-2xl max-h-60 overflow-y-auto animate-in zoom-in-95 duration-200">
                          {employees.filter(emp => 
                            emp.name.toLowerCase().includes(employeeSearchQuery.toLowerCase()) || 
                            emp.code.toLowerCase().includes(employeeSearchQuery.toLowerCase())
                          ).length > 0 ? (
                            employees.filter(emp => 
                              emp.name.toLowerCase().includes(employeeSearchQuery.toLowerCase()) || 
                              emp.code.toLowerCase().includes(employeeSearchQuery.toLowerCase())
                            ).slice(0, 50).map(emp => (
                              <button
                                key={emp.id}
                                onClick={() => {
                                  setSelectedEmployeeId(emp.id.toString());
                                  setEmployeeSearchQuery(`${emp.code} - ${emp.name}`);
                                  setIsEmployeeListOpen(false);
                                }}
                                className="w-full text-left px-4 py-3 hover:bg-slate-50 border-b border-slate-100 last:border-0 transition-colors flex flex-col gap-0.5 group"
                              >
                                <span className="text-sm font-bold text-slate-800 group-hover:text-primary">{emp.name}</span>
                                <span className="text-[10px] font-medium text-slate-500 uppercase">Code: {emp.code}</span>
                              </button>
                            ))
                          ) : (
                            <div className="p-4 text-center text-slate-500 text-xs font-medium">No employees found matching "{employeeSearchQuery}"</div>
                          )}
                        </div>
                      )}

                      {selectedEmployeeId !== 'all' && !isEmployeeListOpen && (
                        <div className="px-4 py-2 bg-primary/5 rounded-xl border border-primary/10 flex items-center justify-between">
                          <div className="flex flex-col">
                            <span className="text-[10px] text-primary/60 font-bold uppercase tracking-wider leading-none mb-1">Selected Employee</span>
                            <span className="text-sm font-bold text-primary">{employeeSearchQuery}</span>
                          </div>
                          <Badge className="bg-primary text-white text-[10px]">ID: {selectedEmployeeId}</Badge>
                        </div>
                      )}
                    </div>
                  </div>
                )}

                {['biometric_dedicated', 'biometric', 'all_punch', 'biometric_single', 'att_worker', 'att_dept', 'att_shift', 'incentive'].includes(selectedReport?.id) && (
                  <div className="space-y-3">
                    <div className="flex items-center gap-2 px-1">
                      <Layers className="h-4 w-4 text-primary" />
                      <span className="text-[11px] font-bold text-slate-500 uppercase tracking-widest">{t('Organizational Filters')}</span>
                    </div>
                    <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                      {/* Category Selection */}
                      <div className="flex flex-col gap-1.5">
                        <Label className="text-xs font-bold text-slate-700 ml-1">{t('Employee Category')}</Label>
                        <select 
                          value={selectedCategory} 
                          onChange={(e) => setSelectedCategory(e.target.value)}
                          className="w-full h-11 px-4 rounded-xl border border-slate-200 bg-slate-50/30 focus:bg-white transition-all text-sm font-bold outline-none appearance-none cursor-pointer"
                          style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 16px center', backgroundSize: '16px' }}
                        >
                          <option value="all">{t('All Categories')}</option>
                          {categories.map(cat => (
                            <option key={cat.id} value={cat.id.toString()}>{cat.name}</option>
                          ))}
                        </select>
                      </div>

                      <div className="grid grid-cols-2 gap-4">
                        <div className="flex flex-col gap-1.5">
                          <Label className="text-xs font-bold text-slate-700 ml-1">{t('Section')}</Label>
                          <select 
                            value={selectedSection} 
                            onChange={(e) => setSelectedSection(e.target.value)}
                            className="w-full h-11 px-3 rounded-xl border border-slate-200 bg-slate-50/50 focus:bg-white transition-all text-sm font-medium outline-none appearance-none cursor-pointer"
                            style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center', backgroundSize: '16px' }}
                          >
                            <option value="all">{t('All Sections')}</option>
                            {sections.map(section => (
                              <option key={section.id} value={section.id.toString()}>{section.name}</option>
                            ))}
                          </select>
                        </div>

                        <div className="flex flex-col gap-1.5">
                          <Label className="text-xs font-bold text-slate-700 ml-1">{t('Department')}</Label>
                          <select 
                            value={selectedDept} 
                            onChange={(e) => setSelectedDept(e.target.value)}
                            className="w-full h-11 px-3 rounded-xl border border-slate-200 bg-slate-50/50 focus:bg-white transition-all text-sm font-medium outline-none appearance-none cursor-pointer"
                            style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center', backgroundSize: '16px' }}
                          >
                            <option value="all">{t('All Departments')}</option>
                            {departments.map(dept => (
                              <option key={dept.id} value={dept.id.toString()}>{dept.name}</option>
                            ))}
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>
                )}

                {selectedReport?.id === 'production' && (
                  <div className="space-y-3">
                    <div className="flex items-center gap-2 px-1">
                      <Layers className="h-4 w-4 text-primary" />
                      <span className="text-[11px] font-bold text-slate-500 uppercase tracking-widest">{t('Organizational Filters')}</span>
                    </div>
                    <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                      <div className="grid grid-cols-2 gap-4">
                        <div className="flex flex-col gap-1.5">
                          <Label className="text-xs font-bold text-slate-700 ml-1">{t('Section')}</Label>
                          <select 
                            value={selectedSection} 
                            onChange={(e) => setSelectedSection(e.target.value)}
                            className="w-full h-11 px-3 rounded-xl border border-slate-200 bg-slate-50/50 focus:bg-white transition-all text-sm font-medium outline-none appearance-none cursor-pointer"
                            style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center', backgroundSize: '16px' }}
                          >
                            <option value="all">{t('All Sections')}</option>
                            {sections.map(section => (
                              <option key={section.id} value={section.id.toString()}>{section.name}</option>
                            ))}
                          </select>
                        </div>

                        <div className="flex flex-col gap-1.5">
                          <Label className="text-xs font-bold text-slate-700 ml-1">{t('Department')}</Label>
                          <select 
                            value={selectedDept} 
                            onChange={(e) => setSelectedDept(e.target.value)}
                            className="w-full h-11 px-3 rounded-xl border border-slate-200 bg-slate-50/50 focus:bg-white transition-all text-sm font-medium outline-none appearance-none cursor-pointer"
                            style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center', backgroundSize: '16px' }}
                          >
                            <option value="all">{t('All Departments')}</option>
                            {departments.map(dept => (
                              <option key={dept.id} value={dept.id.toString()}>{dept.name}</option>
                            ))}
                          </select>
                        </div>
                      </div>
                    </div>
                  </div>
                )}

                {/* Specialized Parameters */}
                <div className="space-y-4">
                  {['biometric_dedicated', 'biometric', 'all_punch', 'biometric_single'].includes(selectedReport?.id) && (
                    <>
                      <div className="grid grid-cols-2 gap-4">
                        <div className="flex flex-col gap-1.5">
                          <Label className="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">{selectedReport?.id === 'biometric_single' ? t('Shift Selection') : t('Report Format')}</Label>
                          <select 
                            value={biometricType} 
                            onChange={(e) => setBiometricType(e.target.value)}
                            className="w-full h-11 px-4 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold text-primary outline-none appearance-none cursor-pointer"
                            style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center', backgroundSize: '16px' }}
                          >
                            {selectedReport?.id === 'biometric_dedicated' ? (
                              <>
                                <option value="codewise">Codewise Report</option>
                                <option value="namewise">Namewise Report</option>
                                <option value="department">Departmentwise Report</option>
                                <option value="intime">InTime Report</option>
                                <option value="outime">OutTime Report</option>
                                <option value="dayshift">Day Shift Report</option>
                                <option value="nightshift">Night Shift Report</option>
                                <option value="overtime">Overtime Report</option>
                                <option value="mispunch">MisPunch Report</option>
                                <option value="latecomming">Late Coming Report</option>
                                <option value="earlyout">Early Out Report</option>
                                <option value="1daysummary">1 Day Summary Report</option>
                              </>
                            ) : selectedReport?.id === 'biometric_single' ? (
                              <>
                                <option value="dayshift">Day Shift Report</option>
                                <option value="nightshift">Night Shift Report</option>
                              </>
                            ) : (
                              <>
                                <option value="codewise">Codewise Report</option>
                                <option value="namewise">Namewise Report</option>
                              </>
                            )}
                          </select>
                        </div>

                        <div className="flex flex-col gap-1.5">
                          <Label className="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">{t('P/O Status')}</Label>
                          <select 
                            value={selectedPoStatus} 
                            onChange={(e) => setSelectedPoStatus(e.target.value)}
                            className="w-full h-11 px-4 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold text-primary outline-none appearance-none cursor-pointer"
                            style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center', backgroundSize: '16px' }}
                          >
                            <option value="all">P/O All</option>
                            <option value="P">Permanent Only</option>
                            <option value="O">Other Only</option>
                          </select>
                        </div>
                      </div>

                      <div className="grid grid-cols-1 gap-4">
                        <div className="flex flex-col gap-1.5">
                          <Label className="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">{t('Attendance Status')}</Label>
                          <select 
                            value={statusType} 
                            onChange={(e) => setStatusType(e.target.value)}
                            className="w-full h-11 px-4 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold text-primary outline-none appearance-none cursor-pointer"
                            style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center', backgroundSize: '16px' }}
                          >
                            <option value="all">All Status</option>
                            <option value="P">Present</option>
                            <option value="A">Absent</option>
                            <option value="MIS">Mispunch</option>
                            <option value="overtime">Overtime</option>
                            <option value="latein">Late In</option>
                            <option value="earlyout">Early Out</option>
                          </select>
                        </div>
                      </div>
                    </>
                  )}

                  {['incentive', 'production'].includes(selectedReport?.id) && (
                    <div className="grid grid-cols-2 gap-4">
                      {selectedReport?.id === 'production' ? (
                        <div className="flex flex-col gap-1.5">
                          <Label className="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">{t('Report Type')}</Label>
                          <select 
                            value={biometricType} 
                            onChange={(e) => setBiometricType(e.target.value)}
                            className="w-full h-11 px-4 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold text-primary outline-none appearance-none cursor-pointer"
                            style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center', backgroundSize: '16px' }}
                          >
                            <option value="summary">{t('Summary')}</option>
                            <option value="details">{t('Details')}</option>
                          </select>
                        </div>
                      ) : (
                        <div className="flex flex-col gap-1.5">
                          <Label className="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">{t('P/O Status')}</Label>
                          <select 
                            value={selectedPoStatus} 
                            onChange={(e) => setSelectedPoStatus(e.target.value)}
                            className="w-full h-11 px-4 rounded-xl border border-slate-200 bg-white focus:ring-2 focus:ring-primary/20 transition-all text-sm font-bold text-primary outline-none appearance-none cursor-pointer"
                            style={{ backgroundImage: 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' fill=\'none\' viewBox=\'0 0 24 24\' stroke=\'%2364748b\'%3E%3Cpath stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M19 9l-7 7-7-7\'%3E%3C/path%3E%3C/svg%3E")', backgroundRepeat: 'no-repeat', backgroundPosition: 'right 12px center', backgroundSize: '16px' }}
                          >
                            <option value="all">P/O All</option>
                            <option value="P">Permanent Only</option>
                            <option value="O">Other Only</option>
                          </select>
                        </div>
                      )}
                    </div>
                  )}

                  {['att_worker', 'att_dept', 'att_shift'].includes(selectedReport?.id) && (
                    <div className="space-y-4">
                      {['att_worker', 'att_dept', 'att_shift'].includes(selectedReport?.id) && (
                        <div className="flex flex-col gap-1.5">
                          <Label className="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">{t('Hourly Format (Y/N/T)')}</Label>
                          <div className="flex items-center gap-4 bg-white p-3 rounded-xl border border-slate-200">
                            {['N', 'Y', 'T'].map((type) => (
                              <label key={type} className="flex items-center gap-2 cursor-pointer">
                                <input 
                                  type="radio" 
                                  name="hourlyType" 
                                  value={type} 
                                  checked={hourlyType === type} 
                                  onChange={(e) => setHourlyType(e.target.value)}
                                  className="h-4 w-4 accent-[#1a365d]"
                                />
                                <span className="text-xs font-bold text-slate-700">
                                  {type === 'N' ? 'N - Numeric' : type === 'Y' ? 'Y - Hourly' : 'T - Time'}
                                </span>
                              </label>
                            ))}
                          </div>
                        </div>
                      )}

                      <div className="flex flex-col gap-1.5">
                        <Label className="text-[11px] font-bold text-slate-500 uppercase tracking-widest ml-1">{t('Card (N/Y)')}</Label>
                        <div className="flex items-center gap-4 bg-white p-3 rounded-xl border border-slate-200">
                          {['N', 'Y', 'A'].map((type) => (
                            <label key={type} className="flex items-center gap-2 cursor-pointer">
                              <input 
                                type="radio" 
                                name="cardType" 
                                value={type} 
                                checked={cardType === type} 
                                onChange={(e) => setCardType(e.target.value)}
                                className="h-4 w-4 accent-[#1a365d]"
                              />
                              <span className="text-xs font-bold text-slate-700">
                                {type === 'N' ? 'N - No' : type === 'Y' ? 'Y - Yes' : 'A - P/A Status'}
                              </span>
                            </label>
                          ))}
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              </>
            )}
          </div>

          {/* Footer Section with Buttons - Fixed at bottom */}
          <div className="p-8 py-5 flex gap-4 bg-white border-t border-slate-100 shrink-0">
            <Button 
              onClick={handleGenerateReport}
              className="flex-[2] h-12 bg-[#1a365d] hover:bg-[#2c5282] text-white font-bold rounded-2xl shadow-xl hover:shadow-2xl hover:scale-[1.02] active:scale-[0.98] transition-all gap-3"
            >
              <FileDown className="h-5 w-5 text-blue-300" />
              <span className="text-sm">{t('GENERATE REPORT')}</span>
            </Button>
            
            <Button 
              variant="outline"
              onClick={() => setIsDialogOpen(false)}
              className="flex-1 h-12 border-slate-200 hover:bg-white text-slate-600 font-bold rounded-2xl gap-2 transition-all"
            >
              <X className="h-4 w-4" />
              {t('CANCEL')}
            </Button>
          </div>
        </DialogContent>
      </Dialog>
    </PageTemplate>
  );
}
