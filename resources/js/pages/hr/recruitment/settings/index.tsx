import { SettingsBranchPanel, SettingsMasterPanel } from '@/components/recruitment/RecruitmentSettingsPanel';
import { PageTemplate } from '@/components/page-template';
import { hasPermission } from '@/utils/authorization';
import { router, usePage } from '@inertiajs/react';
import { Briefcase, FolderTree, MapPin, MessageSquare } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

const TAB_CONFIG = [
    { value: 'categories', labelKey: 'Categories', icon: FolderTree },
    { value: 'types', labelKey: 'Job Types', icon: Briefcase },
    { value: 'branches', labelKey: 'Branch', icon: MapPin },
    { value: 'interview-types', labelKey: 'Interview Types', icon: MessageSquare },
] as const;

export default function RecruitmentSettings() {
    const { t } = useTranslation();
    const { auth, tab, jobCategories, jobTypes, branches, interviewTypes } = usePage().props as any;
    const permissions = auth?.permissions ?? [];
    const activeTab = tab === 'locations' || tab === 'sources' ? 'categories' : tab;

    const switchTab = (value: string) => {
        router.get(route('hr.recruitment.settings.index'), { tab: value }, { preserveState: false, preserveScroll: true });
    };

    return (
        <PageTemplate
            title={t('Recruitment Settings')}
            description={t('Master data and configuration')}
            url="/recruitment/settings"
            breadcrumbs={[
                { title: t('Dashboard'), href: route('dashboard') },
                { title: t('Recruitment'), href: route('hr.recruitment.hub') },
                { title: t('Settings') },
            ]}
        >
            <div className="p-4 md:p-6">
                <div className="overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-950">
                    <div className="border-b border-slate-200/80 bg-slate-50/50 px-4 py-3 dark:border-slate-800 dark:bg-slate-900/30 md:px-6">
                        <div className="flex flex-wrap gap-1">
                            {TAB_CONFIG.map(({ value, labelKey, icon: Icon }) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => switchTab(value)}
                                    className={cn(
                                        'inline-flex items-center gap-2 rounded-lg px-3 py-2 text-xs font-medium transition-colors',
                                        activeTab === value
                                            ? 'bg-white text-primary shadow-sm ring-1 ring-slate-200/80 dark:bg-slate-950 dark:ring-slate-700'
                                            : 'text-slate-600 hover:bg-white/60 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-900/50 dark:hover:text-slate-200'
                                    )}
                                >
                                    <Icon className="h-3.5 w-3.5" />
                                    {t(labelKey)}
                                </button>
                            ))}
                        </div>
                    </div>

                    <div className="p-4 md:p-6">
                        {activeTab === 'categories' ? (
                            <SettingsMasterPanel
                                title={t('Job Categories')}
                                description={t('Group hiring requests by department type — Production, QC, Maintenance, etc.')}
                                icon={FolderTree}
                                items={jobCategories ?? []}
                                reloadKey="jobCategories"
                                placeholder={t('e.g. Production, Quality Control')}
                                storeRoute={route('hr.recruitment.job-categories.store')}
                                updateRoute={(id) => route('hr.recruitment.job-categories.update', id)}
                                destroyRoute={(id) => route('hr.recruitment.job-categories.destroy', id)}
                                canCreate={hasPermission(permissions, 'create-job-categories')}
                                canEdit={hasPermission(permissions, 'edit-job-categories')}
                                canDelete={hasPermission(permissions, 'delete-job-categories')}
                            />
                        ) : null}

                        {activeTab === 'types' ? (
                            <SettingsMasterPanel
                                title={t('Job Types')}
                                description={t('Employment types used when publishing job postings.')}
                                icon={Briefcase}
                                items={jobTypes ?? []}
                                reloadKey="jobTypes"
                                placeholder={t('e.g. Full-time, Contract')}
                                storeRoute={route('hr.recruitment.job-types.store')}
                                updateRoute={(id) => route('hr.recruitment.job-types.update', id)}
                                destroyRoute={(id) => route('hr.recruitment.job-types.destroy', id)}
                                canCreate={hasPermission(permissions, 'create-job-types')}
                                canEdit={hasPermission(permissions, 'edit-job-types')}
                                canDelete={hasPermission(permissions, 'delete-job-types')}
                            />
                        ) : null}

                        {activeTab === 'branches' ? (
                            <SettingsBranchPanel
                                branches={branches ?? []}
                                manageUrl={route('hr.branches.index')}
                                canManage={hasPermission(permissions, 'manage-branches') || hasPermission(permissions, 'view-branches')}
                            />
                        ) : null}

                        {activeTab === 'interview-types' ? (
                            <SettingsMasterPanel
                                title={t('Interview Types')}
                                description={t('Types of interviews — Technical, HR, Plant visit, etc.')}
                                icon={MessageSquare}
                                items={interviewTypes ?? []}
                                reloadKey="interviewTypes"
                                placeholder={t('e.g. Technical Round, HR Round')}
                                storeRoute={route('hr.recruitment.interview-types.store')}
                                updateRoute={(id) => route('hr.recruitment.interview-types.update', id)}
                                destroyRoute={(id) => route('hr.recruitment.interview-types.destroy', id)}
                                canCreate={hasPermission(permissions, 'create-interview-types')}
                                canEdit={hasPermission(permissions, 'edit-interview-types')}
                                canDelete={hasPermission(permissions, 'delete-interview-types')}
                            />
                        ) : null}
                    </div>
                </div>
            </div>
        </PageTemplate>
    );
}
