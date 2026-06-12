import React from 'react';
import { PageTemplate } from '@/components/page-template';
import { DashboardSection, StatCard } from '@/components/dashboard/stat-card';
import { Users, Building2, Briefcase, Calendar, TrendingUp, BarChart3, AlertCircle, CheckCircle2, XCircle, Grid2X2, ArrowRight, Fingerprint, FileDown } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from 'react-i18next';
import { Link, router } from '@inertiajs/react';
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip, BarChart, Bar, XAxis, YAxis, CartesianGrid } from 'recharts';
import { format, subDays } from 'date-fns';
import { ChartLegend, ChartTooltip, DashboardPieChart, HIRING_BAR_COLOR } from '@/lib/dashboard-charts';
import { DashboardShortcuts } from '@/components/dashboard/DashboardShortcuts';

interface CompanyDashboardData {
  stats: {
    totalEmployees: number;
    totalBranches: number;
    totalDepartments: number;
    newEmployeesThisMonth: number;
    jobPostsThisMonth: number;
    candidatesThisMonth: number;
    attendanceRate: number;
    presentToday: number;
    absentToday: number;
    mispunchCount: number;
    mispunch24hDate?: string;
    mispunch24hLabel?: string;
    mispunchCountMonth?: number;
    mispunchMonthLabel?: string;
    mispunchMonthRangeLabel?: string;
    mispunchMonthFrom?: string;
    mispunchMonthTo?: string;
    onLeaveToday: number;
    activeJobPostings: number;
    totalCandidates: number;
    activeBranchId: number | null;
    branches: Array<{ id: number; name: string }>;
    totalCategories: number;
  };
  charts: {
    departmentStats: Array<{ name: string; value: number; color: string }>;
    categoryStats: Array<{ name: string; value: number; color: string }>;
    hiringTrend: Array<{ month: string; hires: number }>;
    candidateStatusStats: Array<{ name: string; value: number; color: string }>;
    leaveTypesStats: Array<{ name: string; value: number; color: string }>;
    mispunchList: Array<{ employee_name: string; employee_code: string; date: string; status: string }>;
  };
  recentActivities: {
    leaves: Array<any>;
    candidates: Array<any>;
    announcements: Array<any>;
    meetings: Array<any>;
    birthdays?: Array<{ id: number; name: string; department: string; date_of_birth: string; day: string; avatar: string | null }>;
    anniversaries?: Array<{ id: number; name: string; department: string; date_of_joining: string; day: string; years: number; avatar: string | null }>;
  };
  userType: string;
  esslSync?: {
    url: string;
    path: string;
    lastSyncAt: string | null;
    lastSyncDate: string | null;
    lastSyncTime: string | null;
    lastSyncLabel: string | null;
    branchName: string | null;
    hasSync: boolean;
  };
}

interface PageAction {
  label: string;
  icon: React.ReactNode;
  variant: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
  onClick: () => void;
}

export default function Dashboard({ dashboardData }: { dashboardData: CompanyDashboardData }) {
  const { t } = useTranslation();

  const pageActions: PageAction[] = [
    {
      label: t('Refresh'),
      iconOnly: true,
      variant: 'outline',
      onClick: () => router.reload({ only: ['dashboardData'] }),
    },
  ];

  const stats = dashboardData?.stats || {
    totalEmployees: 0,
    totalBranches: 0,
    totalDepartments: 0,
    newEmployeesThisMonth: 0,
    jobPostsThisMonth: 0,
    candidatesThisMonth: 0,
    attendanceRate: 0,
    presentToday: 0,
    absentToday: 0,
    mispunchCount: 0,
    mispunchCountMonth: 0,
    onLeaveToday: 0,
    activeJobPostings: 0,
    totalCandidates: 0,
    activeBranchId: null,
    branches: [],
    totalCategories: 0,
  };

  const activeBranchName = stats.activeBranchId
    ? stats.branches?.find((b) => b.id === stats.activeBranchId)?.name
    : t('All branches');

  const mispunch24hDate =
    stats.mispunch24hDate || format(subDays(new Date(), 1), 'yyyy-MM-dd');
  const mispunch24hLabel =
    stats.mispunch24hLabel || format(subDays(new Date(), 1), 'd MMM yyyy');

  const mispunchPageUrl = (fromDate: string, toDate: string) => {
    const params = new URLSearchParams({
      status: 'MIS',
      use_dates: '1',
      from_date: fromDate,
      to_date: toDate,
    });
    if (stats.activeBranchId) {
      params.set('branch_id', String(stats.activeBranchId));
    }
    return `/mispunch?${params.toString()}`;
  };

  const mispunchYesterdayUrl = () => mispunchPageUrl(mispunch24hDate, mispunch24hDate);

  const mispunchMonthFrom =
    stats.mispunchMonthFrom || format(new Date(new Date().getFullYear(), new Date().getMonth(), 1), 'yyyy-MM-dd');
  const mispunchMonthTo = stats.mispunchMonthTo || mispunch24hDate;
  const mispunchMonthLabel = stats.mispunchMonthLabel || format(new Date(), 'MMM yyyy');
  const mispunchMonthRangeLabel =
    stats.mispunchMonthRangeLabel ||
    `${format(new Date(mispunchMonthFrom), 'd MMM')} – ${format(new Date(mispunchMonthTo), 'd MMM yyyy')}`;
  const mispunchCountMonth = stats.mispunchCountMonth ?? 0;

  const mispunchMonthUrl = () => mispunchPageUrl(mispunchMonthFrom, mispunchMonthTo);

  const mispunchReportUrl = '/reports/mispunch-download-24h?inline=1';
  const birthdaysReportUrl = '/reports/birthdays-month-pdf?inline=1';
  const anniversariesReportUrl = '/reports/anniversaries-month-pdf?inline=1';

  const dashboardPreviewLimit = 5;

  const attendanceHeadcount = stats.presentToday + stats.absentToday;

  const esslSync = dashboardData?.esslSync ?? {
    url: '/essl-sync',
    path: '/essl-sync',
    lastSyncAt: null,
    lastSyncDate: null,
    lastSyncTime: null,
    lastSyncLabel: null,
    branchName: null,
    hasSync: false,
  };

  const charts = dashboardData?.charts || {
    departmentStats: [],
    categoryStats: [],
    hiringTrend: [],
    candidateStatusStats: [],
    leaveTypesStats: [],
    mispunchList: [],
  };



  const recentActivities = dashboardData?.recentActivities || {
    leaves: [],
    candidates: [],
    announcements: [],
    meetings: [],
    birthdays: [],
    anniversaries: []
  };

  const getStatusColor = (status: string) => {
    const colors = {
      'approved': 'bg-green-50 text-green-700 ring-green-600/20',
      'pending': 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
      'rejected': 'bg-red-50 text-red-700 ring-red-600/20',
      'New': 'bg-blue-50 text-blue-700 ring-blue-600/20',
      'Screening': 'bg-yellow-50 text-yellow-700 ring-yellow-600/20',
      'Interview': 'bg-purple-50 text-purple-700 ring-purple-600/20',
      'Hired': 'bg-green-50 text-green-700 ring-green-600/20',
      'Rejected': 'bg-red-50 text-red-700 ring-red-600/20'
    };
    return colors[status] || 'bg-gray-50 text-gray-700 ring-gray-600/20';
  };

  return (
    <PageTemplate
      title={t('Dashboard')}
      url="/dashboard"
      actions={pageActions}
      noPadding
    >
      <div className="space-y-2.5 px-4 pb-4 pt-1 sm:px-6">
        <DashboardShortcuts />

        <div className="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
          <div className="grid divide-y divide-slate-100 sm:grid-cols-2 sm:divide-x sm:divide-y-0 dark:divide-slate-800">
            {stats.mispunchCount > 0 ? (
              <Link
                href={mispunchYesterdayUrl()}
                className="group flex items-center gap-2.5 border-l-[3px] border-l-orange-500 bg-orange-50/50 px-3 py-2 transition-colors hover:bg-orange-50 dark:bg-orange-950/20 dark:hover:bg-orange-950/35"
                role="alert"
              >
                <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-orange-100 dark:bg-orange-900/40">
                  <AlertCircle className="h-4 w-4 text-orange-600" aria-hidden />
                </span>
                <span className="min-w-0 flex-1">
                  <span className="block text-xs font-semibold text-orange-900 dark:text-orange-100">
                    {stats.mispunchCount} {t('missing punch yesterday')}
                  </span>
                  <span className="block text-[10px] text-orange-700/85 dark:text-orange-300/80">
                    {mispunch24hLabel} · {t('Review & fix')}
                  </span>
                </span>
                <ArrowRight className="h-3.5 w-3.5 shrink-0 text-orange-500/70 transition-colors group-hover:text-orange-700" aria-hidden />
              </Link>
            ) : (
              <div className="flex items-center gap-2.5 border-l-[3px] border-l-emerald-500 bg-emerald-50/40 px-3 py-2 dark:bg-emerald-950/20">
                <CheckCircle2 className="h-4 w-4 shrink-0 text-emerald-600" aria-hidden />
                <span className="text-xs font-medium text-emerald-800 dark:text-emerald-200">
                  {t('No missing punch yesterday')} · {mispunch24hLabel}
                </span>
              </div>
            )}

            <Link
              href={esslSync.url}
              title={esslSync.path}
              className="group flex items-center gap-2.5 px-3 py-2 transition-colors hover:bg-slate-50/90 dark:hover:bg-slate-900/50"
            >
              <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10">
                <Fingerprint className="h-4 w-4 text-primary" aria-hidden />
              </span>
              <span className="min-w-0 flex-1">
                <span className="block text-xs font-semibold text-slate-800 dark:text-slate-100">{t('Essl sync')}</span>
                <span className="block truncate text-[10px] text-slate-500 dark:text-slate-400">
                  {esslSync.hasSync ? esslSync.lastSyncLabel : t('Not synced')}
                  {esslSync.branchName ? ` · ${esslSync.branchName}` : ''}
                </span>
              </span>
              <ArrowRight className="h-3.5 w-3.5 shrink-0 text-slate-400 transition-colors group-hover:text-primary" aria-hidden />
            </Link>
          </div>
        </div>

        <DashboardSection title={t("Today's attendance")}>
          <div className="grid grid-cols-2 gap-2 lg:grid-cols-4">
            <StatCard label={t('Present')} value={stats.presentToday} hint={t('on duty')} icon={CheckCircle2} tone="emerald" />
            <StatCard label={t('Absent')} value={stats.absentToday} hint={t('not reported')} icon={XCircle} tone="rose" />
            <StatCard label={t('On leave')} value={stats.onLeaveToday} hint={t('approved')} icon={Calendar} tone="blue" />
            <StatCard
              label={t("Today's rate")}
              value={`${stats.attendanceRate}%`}
              hint={
                attendanceHeadcount > 0
                  ? `${stats.presentToday} ${t('of')} ${attendanceHeadcount} ${t('marked today')}`
                  : t('No attendance marked yet')
              }
              icon={TrendingUp}
              tone="purple"
            />
          </div>
        </DashboardSection>

        <DashboardSection title={t('Workforce overview')}>
          <div className="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
            <StatCard label={t('Total Employees')} value={stats.totalEmployees} hint={t('active workforce')} icon={Users} tone="blue" href={route('hr.employees.index')} />
            <StatCard
              label={stats.activeBranchId ? t('Active branch') : t('Branches')}
              value={activeBranchName ?? stats.totalBranches}
              hint={stats.activeBranchId ? t('current filter') : `${stats.totalDepartments} ${t('departments')}`}
              icon={Building2}
              tone="emerald"
            />
            <StatCard label={t('Categories')} value={stats.totalCategories} hint={`${stats.totalDepartments} ${t('departments')}`} icon={Grid2X2} tone="slate" />
            <StatCard
              label={t('MisPunch this month')}
              value={mispunchCountMonth}
              hint={`${t('IN/OUT pending')} · ${mispunchMonthRangeLabel}`}
              icon={AlertCircle}
              tone={mispunchCountMonth > 0 ? 'orange' : 'emerald'}
              highlight={mispunchCountMonth > 0}
              href={mispunchMonthUrl()}
            />
          </div>
        </DashboardSection>

        <DashboardSection title={t('Analytics')}>
        <div className="grid gap-3 lg:grid-cols-2">
          <Card className="border-slate-200/90 shadow-sm">
            <CardHeader className="space-y-0 py-2.5 px-4">
              <CardTitle className="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <BarChart3 className="h-4 w-4 text-slate-500" />
                {t('Department Distribution')}
              </CardTitle>
            </CardHeader>
            <CardContent className="px-4 pb-3 pt-0">
              {charts.departmentStats.length > 0 ? (
                <>
                  <DashboardPieChart data={charts.departmentStats} />
                  <ChartLegend items={charts.departmentStats} max={6} />
                </>
              ) : (
                <div className="py-8 text-center text-sm text-muted-foreground">
                  {t('No department data available')}
                </div>
              )}
            </CardContent>
          </Card>

          <Card className="border-slate-200/90 shadow-sm">
            <CardHeader className="space-y-0 py-2.5 px-4">
              <CardTitle className="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <Users className="h-4 w-4 text-slate-500" />
                {t('Category Distribution')}
              </CardTitle>
            </CardHeader>
            <CardContent className="px-4 pb-3 pt-0">
              {charts.categoryStats.length > 0 ? (
                <>
                  <DashboardPieChart data={charts.categoryStats} />
                  <ChartLegend items={charts.categoryStats} />
                </>
              ) : (
                <div className="py-8 text-center text-sm text-muted-foreground">
                  {t('No category data available')}
                </div>
              )}
            </CardContent>
          </Card>

          <Card className="border-slate-200/90 shadow-sm">
            <CardHeader className="space-y-0 py-2.5 px-4">
              <CardTitle className="flex items-center gap-2 text-sm font-semibold text-slate-800">
                <TrendingUp className="h-4 w-4 text-slate-500" />
                {t('Hiring Trend (6 Months)')}
              </CardTitle>
            </CardHeader>
            <CardContent className="px-4 pb-3 pt-0">
              {charts.hiringTrend.length > 0 ? (
                <ResponsiveContainer width="100%" height={140}>
                  <BarChart data={charts.hiringTrend} margin={{ top: 4, right: 4, left: -16, bottom: 0 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="#e2e8f0" vertical={false} />
                    <XAxis dataKey="month" tick={{ fontSize: 10, fill: '#64748b' }} axisLine={false} tickLine={false} />
                    <YAxis
                      allowDecimals={false}
                      tick={{ fontSize: 10, fill: '#64748b' }}
                      axisLine={false}
                      tickLine={false}
                      domain={[0, (max: number) => Math.max(4, Math.ceil(max * 1.15))]}
                    />
                    <Tooltip content={<ChartTooltip />} />
                    <Bar dataKey="hires" name={t('Hires')} fill={HIRING_BAR_COLOR} radius={[4, 4, 0, 0]} />
                  </BarChart>
                </ResponsiveContainer>
              ) : (
                <div className="py-8 text-center text-sm text-muted-foreground">
                  {t('No hiring data available')}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Candidate Status Chart */}
          {/* 
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center gap-2 text-lg font-semibold">
                <UserPlus className="h-5 w-5" />
                {t('Candidate Status')}
              </CardTitle>
            </CardHeader>
            <CardContent>
              {charts.candidateStatusStats.length > 0 ? (
                <ResponsiveContainer width="100%" height={200}>
                  <PieChart>
                    <Pie
                      data={charts.candidateStatusStats}
                      cx="50%"
                      cy="50%"
                      outerRadius={80}
                      dataKey="value"
                    >
                      {charts.candidateStatusStats.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={entry.color} />
                      ))}
                    </Pie>
                    <Tooltip />
                    <Legend />
                  </PieChart>
                </ResponsiveContainer>
              ) : (
                <div className="text-center py-8 text-muted-foreground">
                  {t('No candidate data available')}
                </div>
              )}
            </CardContent>
          </Card>
          */}

          <Card className="overflow-hidden border-slate-200/90 shadow-sm">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 border-b border-slate-100 bg-slate-50/80 px-3 py-2.5">
              <CardTitle className="flex items-center gap-2 text-xs font-semibold text-slate-800">
                <AlertCircle className="h-3.5 w-3.5 text-orange-600" />
                {t('Yesterday missing punch')} · {mispunch24hLabel}
              </CardTitle>
              <div className="flex items-center gap-1.5">
                {charts.mispunchList.length > 0 && (
                  <a
                    href={mispunchReportUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-slate-600 shadow-sm transition-colors hover:bg-slate-50 hover:text-primary"
                    title={t('Open MisPunch report in new tab')}
                    aria-label={t('Open MisPunch report in new tab')}
                  >
                    <FileDown className="h-3.5 w-3.5" />
                  </a>
                )}
                <Link
                  href={mispunchYesterdayUrl()}
                  className="text-[11px] font-medium text-primary hover:underline"
                >
                  {t('See all')}
                </Link>
              </div>
            </CardHeader>
            <CardContent className="p-0">
              {charts.mispunchList.length > 0 ? (
                <div className="divide-y divide-slate-100">
                  {charts.mispunchList.slice(0, dashboardPreviewLimit).map((item, index) => (
                    <div key={index} className="flex items-center justify-between gap-2 px-3 py-2.5 transition-colors hover:bg-slate-50/80">
                      <div className="flex min-w-0 items-center gap-2.5">
                        <div className="flex h-9 min-w-[36px] flex-col items-center justify-center rounded-lg border border-slate-200 bg-slate-50">
                          <span className="text-[8px] font-medium leading-none text-slate-400">
                            {item.date.split(' ')[1]?.substring(0, 3) ?? ''}
                          </span>
                          <span className="mt-0.5 text-xs font-semibold leading-none text-slate-700">
                            {item.date.split(' ')[0]}
                          </span>
                        </div>
                        <div className="min-w-0 flex-col">
                          <span className="block truncate text-xs font-medium text-slate-800">
                            {item.employee_name}
                          </span>
                          <span className="text-[10px] text-slate-500">{item.employee_code}</span>
                        </div>
                      </div>
                      <Badge variant="outline" className="h-4 shrink-0 rounded-full border-orange-300 bg-orange-100 py-0 text-[9px] font-semibold text-orange-800">
                        {t('Missing punch')}
                      </Badge>
                    </div>
                  ))}
                  {charts.mispunchList.length > dashboardPreviewLimit && (
                    <p className="border-t border-slate-100 bg-slate-50/50 px-3 py-2 text-center text-[11px] text-slate-600">
                      +{charts.mispunchList.length - dashboardPreviewLimit} {t('more')} —{' '}
                      <Link href={mispunchYesterdayUrl()} className="font-medium text-primary hover:underline">
                        {t('See all')}
                      </Link>
                    </p>
                  )}
                </div>
              ) : (
                <div className="flex flex-col items-center py-8 text-center">
                  <div className="mb-2 flex h-9 w-9 items-center justify-center rounded-full bg-emerald-50">
                    <CheckCircle2 className="h-4 w-4 text-emerald-600" />
                  </div>
                  <p className="text-xs text-muted-foreground">
                    {t('All clear — no missing punches for yesterday')} ({mispunch24hLabel})
                  </p>
                </div>
              )}
            </CardContent>
          </Card>
        </div>
        </DashboardSection>

        <DashboardSection title={t('People & celebrations')}>
        {/* Recent Activities */}
        <div className="grid gap-6 lg:grid-cols-2">
          {/* Recent Leave Applications */}
          {/* 
          <Card>
            <CardHeader className="py-3 px-4">
              <CardTitle className="flex items-center justify-between text-sm font-bold">
                <div className="flex items-center gap-2">
                  <Calendar className="h-4 w-4" />
                  {t('Recent Leaves')}
                </div>
                <div className="flex items-center gap-2">
                  <Badge variant="secondary" className="h-5 text-[10px]">{recentActivities.leaves.length}</Badge>
                  <button
                    onClick={() => window.location.href = route('hr.leave-applications.index')}
                    className="px-2 py-0.5 text-[10px] bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-md font-bold transition-colors"
                  >
                    {t('View All')}
                  </button>
                </div>
              </CardTitle>
            </CardHeader>
            <CardContent className="p-4">
              {recentActivities.leaves.length > 0 ? (
                <div className="space-y-3 max-h-80 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100">
                  {recentActivities.leaves.map((leave, index) => (
                    <div key={index} className="flex items-center justify-between p-2 border rounded-lg">
                      <div className="flex-1">
                        <div className="flex items-center gap-2 mb-0.5">
                          <p className="text-xs font-medium">{leave.employee?.name || 'Employee'}</p>
                          <Badge variant="outline" className={`text-[9px] h-4 ring-1 ring-inset ${getStatusColor(leave.status)}`}>
                            {leave.status}
                          </Badge>
                        </div>
                        <p className="text-[10px] text-muted-foreground">
                          {leave.leave_type?.name || 'Leave'} • {(() => {
                            try {
                              return leave.start_date ? format(new Date(leave.start_date), 'MMM dd') : 'N/A';
                            } catch {
                              return 'Invalid date';
                            }
                          })()} - {(() => {
                            try {
                              return leave.end_date ? format(new Date(leave.end_date), 'MMM dd') : 'N/A';
                            } catch {
                              return 'Invalid date';
                            }
                          })()}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-8 text-muted-foreground">
                  {t('No recent leave applications')}
                </div>
              )}
            </CardContent>
          </Card>
          */}

          {/* Recent Candidates */}
          {/* 
          <Card>
            <CardHeader>
              <CardTitle className="flex items-center justify-between text-lg font-semibold">
                <div className="flex items-center gap-2">
                  <UserPlus className="h-5 w-5" />
                  {t('Recent Candidates')}
                </div>
                <div className="flex items-center gap-2">
                  <Badge variant="secondary">{recentActivities.candidates.length}</Badge>
                  <button 
                    onClick={() => window.location.href = route('hr.recruitment.candidates.index')}
                    className="px-2 py-1 text-xs bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-md font-medium transition-colors"
                  >
                    {t('View All')}
                  </button>
                </div>
              </CardTitle>
            </CardHeader>
            <CardContent>
              {recentActivities.candidates.length > 0 ? (
                <div className="space-y-3 max-h-80 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100">
                  {recentActivities.candidates.map((candidate, index) => (
                    <div key={index} className="flex items-center justify-between p-3 border rounded-lg">
                      <div className="flex-1">
                        <div className="flex items-center gap-2 mb-1">
                          <p className="font-medium">{candidate.first_name} {candidate.last_name}</p>
                          <Badge variant="outline" className={`text-xs ring-1 ring-inset ${getStatusColor(candidate.status)}`}>
                            {candidate.status}
                          </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                          {candidate.job?.title || 'Job'} • {(() => {
                            try {
                              return candidate.created_at ? format(new Date(candidate.created_at), 'MMM dd, yyyy') : 'N/A';
                            } catch {
                              return 'Invalid date';
                            }
                          })()}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-8 text-muted-foreground">
                  {t('No recent candidates')}
                </div>
              )}
            </CardContent>
          </Card>
          */}

          {/* Recent Announcements */}
          {/* 
          <Card>
            <CardHeader className="py-3 px-4">
              <CardTitle className="flex items-center justify-between text-sm font-bold">
                <div className="flex items-center gap-2">
                  <Bell className="h-4 w-4" />
                  {t('Announcements')}
                </div>
                <div className="flex items-center gap-2">
                  <Badge variant="secondary" className="h-5 text-[10px]">{recentActivities.announcements.length}</Badge>
                  <button
                    onClick={() => window.location.href = route('hr.announcements.index')}
                    className="px-2 py-0.5 text-[10px] bg-blue-100 text-blue-700 hover:bg-blue-200 rounded-md font-bold transition-colors"
                  >
                    {t('View All')}
                  </button>
                </div>
              </CardTitle>
            </CardHeader>
            <CardContent className="p-4">
              {recentActivities.announcements.length > 0 ? (
                <div className="space-y-3 max-h-80 overflow-y-auto scrollbar-thin scrollbar-thumb-gray-300 scrollbar-track-gray-100">
                  {recentActivities.announcements.map((announcement, index) => (
                    <div key={index} className="flex items-center justify-between p-2 border rounded-lg">
                      <div className="flex-1">
                        <div className="flex items-center gap-2 mb-0.5">
                          <p className="text-xs font-medium">{announcement.title}</p>
                          {announcement.is_high_priority && (
                            <Badge variant="outline" className="text-[9px] h-4 ring-1 ring-inset bg-red-50 text-red-700 ring-red-600/20">
                              High Priority
                            </Badge>
                          )}
                        </div>
                        <p className="text-[10px] text-muted-foreground">
                          {announcement.category} • {(() => {
                            try {
                              return announcement.created_at ? format(new Date(announcement.created_at), 'MMM dd, yyyy') : 'N/A';
                            } catch {
                              return 'Invalid date';
                            }
                          })()}
                        </p>
                      </div>
                    </div>
                  ))}
                </div>
              ) : (
                <div className="text-center py-8 text-muted-foreground">
                  {t('No recent announcements')}
                </div>
              )}
            </CardContent>
          </Card>
          */}

          {/* Recent Meetings */}
          {/* 
          <Card>
            <CardHeader>
          {/* Birthdays This Month */}
          <Card>
            <CardHeader className="py-3 px-4">
              <CardTitle className="flex items-center justify-between text-sm font-bold">
                <div className="flex items-center gap-2">
                  <Calendar className="h-4 w-4 text-pink-500" />
                  {t('Birthdays This Month')}
                </div>
                <div className="flex items-center gap-1.5">
                  {(recentActivities.birthdays?.length || 0) > 0 && (
                    <a
                      href={birthdaysReportUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-pink-200 bg-white text-pink-700 shadow-sm hover:bg-pink-50"
                      title={t('Full list PDF — birthdays this month')}
                      aria-label={t('Full list PDF — birthdays this month')}
                    >
                      <FileDown className="h-3.5 w-3.5" />
                    </a>
                  )}
                  <Badge variant="secondary" className="h-5 text-[10px] bg-pink-100 text-pink-700 hover:bg-pink-200 border-none">{recentActivities.birthdays?.length || 0}</Badge>
                </div>
              </CardTitle>
            </CardHeader>
            <CardContent className="p-4">
              {recentActivities.birthdays && recentActivities.birthdays.length > 0 ? (
                <div className="space-y-2">
                  {recentActivities.birthdays.slice(0, dashboardPreviewLimit).map((birthday, index) => (
                    <div key={index} className="flex items-center gap-3 p-2 border border-pink-100 bg-pink-50/30 rounded-lg hover:bg-pink-50/80 transition-colors">
                      <div className="flex flex-col items-center justify-center min-w-[40px] h-10 rounded-lg bg-pink-100 border border-pink-200">
                        <span className="text-[10px] font-bold text-pink-500 leading-none">
                          {birthday.date_of_birth ? birthday.date_of_birth.split(' ')[1] : ''}
                        </span>
                        <span className="text-sm font-black text-pink-700 leading-none mt-0.5">
                          {birthday.day}
                        </span>
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-xs font-bold text-gray-800 truncate">{birthday.name}</p>
                        <div className="flex items-center gap-1.5 mt-0.5">
                          <Building2 className="h-3 w-3 text-muted-foreground" />
                          <p className="text-[10px] text-muted-foreground truncate">{birthday.department}</p>
                        </div>
                      </div>
                      <div className="text-right flex flex-col items-end">
                        <Badge variant="outline" className="bg-white text-pink-600 border-pink-200 text-[9px] py-0 h-4 font-bold rounded-full">
                          {t('B-Day')}
                        </Badge>
                      </div>
                    </div>
                  ))}
                  {(recentActivities.birthdays?.length || 0) > dashboardPreviewLimit && (
                    <a
                      href={birthdaysReportUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="block border-t border-pink-100 pt-2 text-center text-[11px] font-medium text-pink-800 hover:text-pink-950 hover:underline"
                    >
                      +{(recentActivities.birthdays?.length || 0) - dashboardPreviewLimit} {t('more this month')} — {t('Open report')}
                    </a>
                  )}
                </div>
              ) : (
                <div className="text-center py-8 text-muted-foreground">
                  {t('No birthdays this month')}
                </div>
              )}
            </CardContent>
          </Card>

          {/* Work Anniversaries This Month */}
          <Card>
            <CardHeader className="py-3 px-4">
              <CardTitle className="flex items-center justify-between text-sm font-bold">
                <div className="flex items-center gap-2">
                  <Briefcase className="h-4 w-4 text-purple-500" />
                  {t('Work Anniversaries')}
                </div>
                <div className="flex items-center gap-1.5">
                  {(recentActivities.anniversaries?.length || 0) > 0 && (
                    <a
                      href={anniversariesReportUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex h-7 w-7 items-center justify-center rounded-md border border-purple-200 bg-white text-purple-700 shadow-sm hover:bg-purple-50"
                      title={t('Full list PDF — work anniversaries this month')}
                      aria-label={t('Full list PDF — work anniversaries this month')}
                    >
                      <FileDown className="h-3.5 w-3.5" />
                    </a>
                  )}
                  <Badge variant="secondary" className="h-5 text-[10px] bg-purple-100 text-purple-700 hover:bg-purple-200 border-none">{recentActivities.anniversaries?.length || 0}</Badge>
                </div>
              </CardTitle>
            </CardHeader>
            <CardContent className="p-4">
              {recentActivities.anniversaries && recentActivities.anniversaries.length > 0 ? (
                <div className="space-y-2">
                  {recentActivities.anniversaries.slice(0, dashboardPreviewLimit).map((anniversary, index) => (
                    <div key={index} className="flex items-center gap-3 p-2 border border-purple-100 bg-purple-50/30 rounded-lg hover:bg-purple-50/80 transition-colors">
                      <div className="flex flex-col items-center justify-center min-w-[40px] h-10 rounded-lg bg-purple-100 border border-purple-200">
                        <span className="text-[10px] font-bold text-purple-500 leading-none">
                          {anniversary.date_of_joining ? anniversary.date_of_joining.split(' ')[1] : ''}
                        </span>
                        <span className="text-sm font-black text-purple-700 leading-none mt-0.5">
                          {anniversary.day}
                        </span>
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-xs font-bold text-gray-800 truncate">{anniversary.name}</p>
                        <div className="flex items-center gap-1.5 mt-0.5">
                          <Building2 className="h-3 w-3 text-muted-foreground" />
                          <p className="text-[10px] text-muted-foreground truncate">{anniversary.department}</p>
                        </div>
                      </div>
                      <div className="text-right flex flex-col items-end">
                        <Badge variant="outline" className="bg-white text-purple-600 border-purple-200 text-[9px] py-0 h-4 font-bold rounded-full">
                          {anniversary.years} {anniversary.years === 1 ? t('Year') : t('Years')}
                        </Badge>
                      </div>
                    </div>
                  ))}
                  {(recentActivities.anniversaries?.length || 0) > dashboardPreviewLimit && (
                    <a
                      href={anniversariesReportUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="block border-t border-purple-100 pt-2 text-center text-[11px] font-medium text-purple-800 hover:text-purple-950 hover:underline"
                    >
                      +{(recentActivities.anniversaries?.length || 0) - dashboardPreviewLimit} {t('more this month')} — {t('Open report')}
                    </a>
                  )}
                </div>
              ) : (
                <div className="text-center py-8 text-muted-foreground">
                  {t('No work anniversaries this month')}
                </div>
              )}
            </CardContent>
          </Card>
        </div>
        </DashboardSection>
      </div>
      </PageTemplate>
  );
}