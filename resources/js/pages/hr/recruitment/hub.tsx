import { PageTemplate } from '@/components/page-template';
import { DashboardSection, StatCard } from '@/components/dashboard/stat-card';
import { StatusBadge } from '@/components/recruitment/StatusBadge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { hasPermission } from '@/utils/authorization';
import { Link, usePage } from '@inertiajs/react';
import { Briefcase, Calendar, FileText, Plus, UserPlus, Users } from 'lucide-react';
import { useTranslation } from 'react-i18next';

type HubProps = {
    stats: {
        open_jobs: number;
        active_candidates: number;
        interviews_this_week: number;
        offers_pending: number;
    };
    pipeline: { status: string; count: number }[];
    upcoming_interviews: {
        id: number;
        candidate_name: string;
        job_title: string;
        round_name: string;
        scheduled_date: string;
        scheduled_time: string;
        status: string;
    }[];
    recent_candidates: {
        id: number;
        name: string;
        job_title: string;
        source: string;
        status: string;
        application_date: string;
        has_resume: boolean;
    }[];
};

export default function RecruitmentHub() {
    const { t } = useTranslation();
    const { auth, stats, pipeline, upcoming_interviews, recent_candidates } = usePage().props as HubProps & {
        auth: { permissions: string[] };
    };
    const permissions = auth?.permissions ?? [];

    const pipelineMax = Math.max(...pipeline.map((p) => p.count), 1);

    const actions = [];
    if (hasPermission(permissions, 'create-job-postings')) {
        actions.push({
            label: t('New Job'),
            icon: <Plus className="mr-2 h-4 w-4" />,
            onClick: () => (window.location.href = route('hr.recruitment.jobs.index', { tab: 'postings' })),
        });
    }
    if (hasPermission(permissions, 'create-candidates')) {
        actions.push({
            label: t('Add Candidate'),
            icon: <UserPlus className="mr-2 h-4 w-4" />,
            variant: 'outline' as const,
            onClick: () => (window.location.href = route('hr.recruitment.candidates.index')),
        });
    }

    return (
        <PageTemplate
            title={t('Recruitment')}
            description={t('Selection process overview')}
            url="/recruitment"
            actions={actions}
            breadcrumbs={[
                { title: t('Dashboard'), href: route('dashboard') },
                { title: t('Recruitment') },
            ]}
        >
            <div className="space-y-4 p-4 md:p-6">
                <DashboardSection title={t('Overview')}>
                    <div className="grid grid-cols-2 gap-2.5 lg:grid-cols-4">
                        <StatCard
                            label={t('Open Jobs')}
                            value={stats.open_jobs}
                            icon={Briefcase}
                            tone="blue"
                            href={route('hr.recruitment.jobs.index')}
                        />
                        <StatCard
                            label={t('Active Candidates')}
                            value={stats.active_candidates}
                            icon={Users}
                            tone="purple"
                            href={route('hr.recruitment.candidates.index')}
                        />
                        <StatCard
                            label={t('Interviews This Week')}
                            value={stats.interviews_this_week}
                            icon={Calendar}
                            tone="emerald"
                            href={route('hr.recruitment.interviews.index')}
                        />
                        <StatCard
                            label={t('Offers Pending')}
                            value={stats.offers_pending}
                            icon={FileText}
                            tone="orange"
                            href={route('hr.recruitment.offers.index')}
                        />
                    </div>
                </DashboardSection>

                <Card className="border-slate-200/90 shadow-sm">
                    <CardHeader className="pb-2">
                        <CardTitle className="text-sm font-semibold">{t('Selection Process Board')}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-2">
                            {pipeline.map((stage) => (
                                <Link
                                    key={stage.status}
                                    href={route('hr.recruitment.candidates.index', { status: stage.status })}
                                    className="min-w-[100px] flex-1 rounded-lg border border-slate-200 bg-white p-3 transition-colors hover:border-primary/30 dark:border-slate-800 dark:bg-slate-950"
                                >
                                    <StatusBadge status={stage.status} />
                                    <p className="mt-2 text-xl font-bold tabular-nums text-slate-800">{stage.count}</p>
                                    <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100">
                                        <div
                                            className="h-full rounded-full bg-primary/70"
                                            style={{ width: `${(stage.count / pipelineMax) * 100}%` }}
                                        />
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                <div className="grid gap-4 lg:grid-cols-2">
                    <Card className="border-slate-200/90 shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-semibold">{t('Upcoming Interviews')}</CardTitle>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={route('hr.recruitment.interviews.index')}>{t('View all')}</Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="divide-y divide-slate-100 p-0">
                            {upcoming_interviews.length === 0 ? (
                                <p className="px-4 py-8 text-center text-xs text-slate-500">{t('No upcoming interviews')}</p>
                            ) : (
                                upcoming_interviews.map((iv) => (
                                    <div key={iv.id} className="flex items-center justify-between gap-2 px-4 py-3">
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">{iv.candidate_name}</p>
                                            <p className="truncate text-[11px] text-slate-500">
                                                {iv.job_title} · {iv.round_name}
                                            </p>
                                        </div>
                                        <div className="shrink-0 text-right text-[11px] text-slate-500">
                                            <p>{iv.scheduled_date}</p>
                                            <p>{iv.scheduled_time}</p>
                                        </div>
                                    </div>
                                ))
                            )}
                        </CardContent>
                    </Card>

                    <Card className="border-slate-200/90 shadow-sm">
                        <CardHeader className="flex flex-row items-center justify-between pb-2">
                            <CardTitle className="text-sm font-semibold">{t('Recent Candidates')}</CardTitle>
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={route('hr.recruitment.candidates.index')}>{t('View selection board')}</Link>
                            </Button>
                        </CardHeader>
                        <CardContent className="divide-y divide-slate-100 p-0">
                            {recent_candidates.length === 0 ? (
                                <p className="px-4 py-8 text-center text-xs text-slate-500">{t('No candidates yet')}</p>
                            ) : (
                                recent_candidates.map((c) => (
                                    <Link
                                        key={c.id}
                                        href={route('hr.recruitment.candidates.show', c.id)}
                                        className="flex items-center justify-between gap-2 px-4 py-3 transition-colors hover:bg-slate-50"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">{c.name}</p>
                                            <p className="truncate text-[11px] text-slate-500">
                                                {c.job_title} · {c.source}
                                            </p>
                                        </div>
                                        <StatusBadge status={c.status} />
                                    </Link>
                                ))
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </PageTemplate>
    );
}
