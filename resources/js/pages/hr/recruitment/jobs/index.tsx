import { JobCard } from '@/components/recruitment/JobCard';
import { RecruitmentEmptyState } from '@/components/recruitment/RecruitmentEmptyState';
import { PageTemplate } from '@/components/page-template';
import { toast } from '@/components/custom-toast';
import { FormField, FormSection, RecruitmentFormSheet } from '@/components/recruitment/RecruitmentFormSheet';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { RecruitmentSelect, toSelectOptions } from '@/components/recruitment/RecruitmentSelect';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Textarea } from '@/components/ui/textarea';
import { Pagination } from '@/components/ui/pagination';
import { hasPermission } from '@/utils/authorization';
import { router, useForm, usePage } from '@inertiajs/react';
import { Briefcase, ClipboardList, Plus, Search } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function RecruitmentJobs() {
    const { t } = useTranslation();
    const {
        auth,
        tab,
        requisitions,
        jobPostings,
        jobCategories,
        departments,
        jobTypes,
        branches,
        approvedRequisitions,
        filters = {},
    } = usePage().props as any;
    const permissions = auth?.permissions ?? [];
    const [search, setSearch] = useState(filters.search ?? '');
    const [sheetOpen, setSheetOpen] = useState(false);
    const [sheetMode, setSheetMode] = useState<'requisition' | 'posting'>('posting');
    const [editingPosting, setEditingPosting] = useState<any>(null);
    const [editingRequisition, setEditingRequisition] = useState<any>(null);

    const requisitionForm = useForm({
        title: '',
        job_category_id: '',
        department_id: '',
        positions_count: 1,
        priority: 'Medium',
        description: '',
        budget_min: '',
        budget_max: '',
    });

    const postingForm = useForm({
        requisition_id: '',
        title: '',
        job_type_id: '',
        branch_id: '',
        department_id: '',
        min_experience: 0,
        max_experience: '',
        min_salary: '',
        max_salary: '',
        description: '',
        application_deadline: '',
        is_featured: false as boolean,
    });

    const applyFilters = (newTab?: string) => {
        router.get(
            route('hr.recruitment.jobs.index'),
            { tab: newTab ?? tab, search: search || undefined, per_page: filters.per_page },
            { preserveState: true }
        );
    };

    const openCreateRequisition = () => {
        setSheetMode('requisition');
        setEditingRequisition(null);
        requisitionForm.reset();
        setSheetOpen(true);
    };

    const openCreatePosting = () => {
        setSheetMode('posting');
        setEditingPosting(null);
        postingForm.reset();
        setSheetOpen(true);
    };

    const submitRequisition = (e: React.FormEvent) => {
        e.preventDefault();
        const url = editingRequisition
            ? route('hr.recruitment.job-requisitions.update', editingRequisition.id)
            : route('hr.recruitment.job-requisitions.store');
        const method = editingRequisition ? 'put' : 'post';
        requisitionForm[method](url, {
            onSuccess: () => {
                setSheetOpen(false);
                toast.success(t('Saved successfully'));
            },
        });
    };

    const submitPosting = (e: React.FormEvent) => {
        e.preventDefault();
        const url = editingPosting
            ? route('hr.recruitment.job-postings.update', editingPosting.id)
            : route('hr.recruitment.job-postings.store');
        const method = editingPosting ? 'put' : 'post';
        postingForm[method](url, {
            onSuccess: () => {
                setSheetOpen(false);
                toast.success(t('Saved successfully'));
            },
        });
    };

    const reqError = (key: string) => {
        const err = (requisitionForm.errors as Record<string, string>)[key];
        return err ? String(err) : undefined;
    };

    const postError = (key: string) => {
        const err = (postingForm.errors as Record<string, string>)[key];
        return err ? String(err) : undefined;
    };

    const sheetTitle =
        sheetMode === 'requisition'
            ? editingRequisition
                ? t('Edit Hiring Request')
                : t('New Hiring Request')
            : editingPosting
              ? t('Edit Job Posting')
              : t('New Job Posting');

    const sheetDescription =
        sheetMode === 'requisition'
            ? t('Request headcount approval before publishing a job.')
            : t('Create a job listing from an approved hiring request.');

    const pageActions = [];
    if (hasPermission(permissions, 'create-job-requisitions')) {
        pageActions.push({ label: t('New Hiring Request'), icon: <Plus className="mr-2 h-4 w-4" />, variant: 'outline' as const, onClick: openCreateRequisition });
    }
    if (hasPermission(permissions, 'create-job-postings')) {
        pageActions.push({ label: t('New Job Posting'), icon: <Plus className="mr-2 h-4 w-4" />, onClick: openCreatePosting });
    }

    return (
        <PageTemplate
            title={t('Jobs')}
            description={t('Hiring requests and job postings')}
            url="/recruitment/jobs"
            actions={pageActions}
            breadcrumbs={[
                { title: t('Dashboard'), href: route('dashboard') },
                { title: t('Recruitment'), href: route('hr.recruitment.hub') },
                { title: t('Jobs') },
            ]}
        >
            <div className="space-y-4 p-4 md:p-6">
                <div className="flex gap-2">
                    <div className="relative flex-1 max-w-md">
                        <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-slate-400" />
                        <Input
                            className="pl-8"
                            placeholder={t('Search jobs...')}
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            onKeyDown={(e) => e.key === 'Enter' && applyFilters()}
                        />
                    </div>
                    <Button variant="secondary" onClick={() => applyFilters()}>
                        {t('Search')}
                    </Button>
                </div>

                <Tabs value={tab} onValueChange={(v) => applyFilters(v)}>
                    <TabsList>
                        <TabsTrigger value="postings">{t('Job Postings')}</TabsTrigger>
                        <TabsTrigger value="requisitions">{t('Hiring Requests')}</TabsTrigger>
                    </TabsList>

                    <TabsContent value="postings" className="mt-4">
                        {jobPostings?.data?.length ? (
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {jobPostings.data.map((job: any) => (
                                    <JobCard
                                        key={job.id}
                                        title={job.title}
                                        code={job.job_code}
                                        subtitle={job.job_type?.name}
                                        location={job.location?.name}
                                        applicantsCount={job.candidates_count}
                                        status={job.status}
                                        isPublished={job.is_published}
                                        deadline={job.application_deadline}
                                        actions={
                                            <>
                                                {hasPermission(permissions, 'publish-job-postings') && !job.is_published ? (
                                                    <Button
                                                        size="sm"
                                                        variant="default"
                                                        className="h-7 text-xs"
                                                        onClick={() =>
                                                            router.put(route('hr.recruitment.job-postings.publish', job.id), {}, {
                                                                onSuccess: () => toast.success(t('Published')),
                                                            })
                                                        }
                                                    >
                                                        {t('Publish')}
                                                    </Button>
                                                ) : null}
                                                {hasPermission(permissions, 'view-candidates') ? (
                                                    <Button size="sm" variant="outline" className="h-7 text-xs" asChild>
                                                        <a href={route('hr.recruitment.candidates.index', { job_id: job.id })}>
                                                            {t('Applicants')}
                                                        </a>
                                                    </Button>
                                                ) : null}
                                                {hasPermission(permissions, 'edit-job-postings') ? (
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        className="h-7 text-xs"
                                                        onClick={() => {
                                                            setSheetMode('posting');
                                                            setEditingPosting(job);
                                                            const matchedBranch = branches.find(
                                                                (b: { id: number; name: string }) => b.name === job.location?.name
                                                            );
                                                            postingForm.setData({
                                                                requisition_id: String(job.requisition_id ?? ''),
                                                                title: job.title,
                                                                job_type_id: String(job.job_type_id ?? ''),
                                                                branch_id: String(matchedBranch?.id ?? ''),
                                                                department_id: String(job.department_id ?? ''),
                                                                min_experience: job.min_experience ?? 0,
                                                                max_experience: job.max_experience ?? '',
                                                                min_salary: job.min_salary ?? '',
                                                                max_salary: job.max_salary ?? '',
                                                                description: job.description ?? '',
                                                                application_deadline: job.application_deadline?.split('T')[0] ?? '',
                                                                is_featured: job.is_featured ?? false,
                                                            });
                                                            setSheetOpen(true);
                                                        }}
                                                    >
                                                        {t('Edit')}
                                                    </Button>
                                                ) : null}
                                            </>
                                        }
                                    />
                                ))}
                            </div>
                        ) : (
                            <RecruitmentEmptyState
                                icon={Briefcase}
                                title={t('No job postings yet')}
                                description={t('Create a posting from an approved hiring request')}
                                actionLabel={hasPermission(permissions, 'create-job-postings') ? t('New Job Posting') : undefined}
                                onAction={hasPermission(permissions, 'create-job-postings') ? openCreatePosting : undefined}
                            />
                        )}
                        {jobPostings?.last_page > 1 ? (
                            <div className="mt-4">
                                <Pagination
                                    currentPage={jobPostings.current_page}
                                    totalPages={jobPostings.last_page}
                                    onPageChange={(page) =>
                                        router.get(route('hr.recruitment.jobs.index'), { ...filters, tab: 'postings', page })
                                    }
                                />
                            </div>
                        ) : null}
                    </TabsContent>

                    <TabsContent value="requisitions" className="mt-4">
                        {requisitions?.data?.length ? (
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                {requisitions.data.map((req: any) => (
                                    <JobCard
                                        key={req.id}
                                        title={req.title}
                                        code={req.requisition_code}
                                        subtitle={[
                                            `${req.positions_count} positions · ${req.priority}`,
                                            req.status === 'Approved' && req.approver?.name
                                                ? `${t('Approved by')} ${req.approver.name}`
                                                : null,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ')}
                                        location={req.department?.name}
                                        status={req.status}
                                        actions={
                                            <>
                                                {hasPermission(permissions, 'edit-job-requisitions') &&
                                                req.status === 'Draft' ? (
                                                    <Button
                                                        size="sm"
                                                        variant="secondary"
                                                        className="h-7 text-xs"
                                                        onClick={() =>
                                                            router.put(
                                                                route('hr.recruitment.job-requisitions.update-status', req.id),
                                                                { status: 'Pending Approval' },
                                                                { onSuccess: () => toast.success(t('Submitted for approval')) }
                                                            )
                                                        }
                                                    >
                                                        {t('Submit for Approval')}
                                                    </Button>
                                                ) : null}
                                                {hasPermission(permissions, 'approve-job-requisitions') &&
                                                (req.status === 'Draft' || req.status === 'Pending Approval') ? (
                                                    <Button
                                                        size="sm"
                                                        className="h-7 text-xs"
                                                        onClick={() =>
                                                            router.put(
                                                                route('hr.recruitment.job-requisitions.update-status', req.id),
                                                                { status: 'Approved' },
                                                                { onSuccess: () => toast.success(t('Approved')) }
                                                            )
                                                        }
                                                    >
                                                        {t('Approve')}
                                                    </Button>
                                                ) : null}
                                                {hasPermission(permissions, 'edit-job-requisitions') ? (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-7 text-xs"
                                                        onClick={() => {
                                                            setSheetMode('requisition');
                                                            setEditingRequisition(req);
                                                            requisitionForm.setData({
                                                                title: req.title,
                                                                job_category_id: String(req.job_category_id ?? ''),
                                                                department_id: String(req.department_id ?? ''),
                                                                positions_count: req.positions_count,
                                                                priority: req.priority,
                                                                description: req.description ?? '',
                                                                budget_min: req.budget_min ?? '',
                                                                budget_max: req.budget_max ?? '',
                                                            });
                                                            setSheetOpen(true);
                                                        }}
                                                    >
                                                        {t('Edit')}
                                                    </Button>
                                                ) : null}
                                            </>
                                        }
                                    />
                                ))}
                            </div>
                        ) : (
                            <RecruitmentEmptyState
                                icon={ClipboardList}
                                title={t('No hiring requests yet')}
                                description={t('Start by creating a hiring request')}
                                actionLabel={hasPermission(permissions, 'create-job-requisitions') ? t('New Hiring Request') : undefined}
                                onAction={hasPermission(permissions, 'create-job-requisitions') ? openCreateRequisition : undefined}
                            />
                        )}
                    </TabsContent>
                </Tabs>
            </div>

            <RecruitmentFormSheet
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                title={sheetTitle}
                description={sheetDescription}
                onSubmit={sheetMode === 'requisition' ? submitRequisition : submitPosting}
                processing={sheetMode === 'requisition' ? requisitionForm.processing : postingForm.processing}
                submitLabel={sheetMode === 'requisition' ? t('Save Hiring Request') : t('Save Job Posting')}
                size="lg"
            >
                {sheetMode === 'requisition' ? (
                    <>
                        <FormSection title={t('Position details')}>
                            <FormField label={t('Job title')} required error={reqError('title')}>
                                <Input
                                    className="h-10 bg-white dark:bg-slate-950"
                                    placeholder={t('e.g. CNC Machine Operator')}
                                    value={requisitionForm.data.title}
                                    onChange={(e) => requisitionForm.setData('title', e.target.value)}
                                />
                            </FormField>
                            <div className="grid grid-cols-2 gap-4">
                                <FormField label={t('Category')} required error={reqError('job_category_id')}>
                                    <RecruitmentSelect
                                        options={toSelectOptions(jobCategories)}
                                        value={requisitionForm.data.job_category_id}
                                        onValueChange={(v) => requisitionForm.setData('job_category_id', v)}
                                        placeholder={t('Select category')}
                                    />
                                </FormField>
                                <FormField label={t('Department')} error={reqError('department_id')}>
                                    <RecruitmentSelect
                                        options={toSelectOptions(departments)}
                                        value={requisitionForm.data.department_id}
                                        onValueChange={(v) => requisitionForm.setData('department_id', v)}
                                        placeholder={t('Select department')}
                                    />
                                </FormField>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <FormField label={t('Positions needed')} required error={reqError('positions_count')}>
                                    <Input
                                        type="number"
                                        min={1}
                                        className="h-10 bg-white dark:bg-slate-950"
                                        value={requisitionForm.data.positions_count}
                                        onChange={(e) => requisitionForm.setData('positions_count', Number(e.target.value))}
                                    />
                                </FormField>
                                <FormField label={t('Priority')} required error={reqError('priority')}>
                                    <RecruitmentSelect
                                        options={['Low', 'Medium', 'High'].map((p) => ({ value: p, label: p }))}
                                        value={requisitionForm.data.priority}
                                        onValueChange={(v) => requisitionForm.setData('priority', v)}
                                        placeholder={t('Select priority')}
                                    />
                                </FormField>
                            </div>
                        </FormSection>
                        <FormSection title={t('Budget (optional)')}>
                            <div className="grid grid-cols-2 gap-4">
                                <FormField label={t('Min salary (₹/month)')} error={reqError('budget_min')}>
                                    <Input
                                        type="number"
                                        min={0}
                                        className="h-10 bg-white dark:bg-slate-950"
                                        placeholder="18000"
                                        value={requisitionForm.data.budget_min}
                                        onChange={(e) => requisitionForm.setData('budget_min', e.target.value)}
                                    />
                                </FormField>
                                <FormField label={t('Max salary (₹/month)')} error={reqError('budget_max')}>
                                    <Input
                                        type="number"
                                        min={0}
                                        className="h-10 bg-white dark:bg-slate-950"
                                        placeholder="28000"
                                        value={requisitionForm.data.budget_max}
                                        onChange={(e) => requisitionForm.setData('budget_max', e.target.value)}
                                    />
                                </FormField>
                            </div>
                        </FormSection>
                        <FormSection title={t('Description')}>
                            <FormField label={t('Role summary')} error={reqError('description')}>
                                <Textarea
                                    rows={4}
                                    className="resize-none bg-white dark:bg-slate-950"
                                    placeholder={t('Why is this hire needed? Key responsibilities...')}
                                    value={requisitionForm.data.description}
                                    onChange={(e) => requisitionForm.setData('description', e.target.value)}
                                />
                            </FormField>
                        </FormSection>
                    </>
                ) : (
                    <>
                        <FormSection title={t('Link to hiring request')}>
                            <FormField label={t('Approved hiring request')} required error={postError('requisition_id')}>
                                <RecruitmentSelect
                                    options={approvedRequisitions.map((r: { id: number; title: string; requisition_code: string }) => ({
                                        value: String(r.id),
                                        label: `${r.title} (${r.requisition_code})`,
                                    }))}
                                    value={postingForm.data.requisition_id}
                                    onValueChange={(v) => {
                                        postingForm.setData('requisition_id', v);
                                        const req = approvedRequisitions.find((r: { id: number }) => String(r.id) === v);
                                        if (req && !postingForm.data.title) {
                                            postingForm.setData('title', req.title);
                                        }
                                    }}
                                    placeholder={t('Select approved hiring request')}
                                />
                            </FormField>
                            <FormField label={t('Posting title')} required error={postError('title')}>
                                <Input
                                    className="h-10 bg-white dark:bg-slate-950"
                                    placeholder={t('e.g. CNC Operator — Palsana Plant')}
                                    value={postingForm.data.title}
                                    onChange={(e) => postingForm.setData('title', e.target.value)}
                                />
                            </FormField>
                        </FormSection>

                        <FormSection title={t('Job details')}>
                            <div className="grid grid-cols-2 gap-4">
                                <FormField label={t('Job type')} required error={postError('job_type_id')}>
                                    <RecruitmentSelect
                                        options={toSelectOptions(jobTypes)}
                                        value={postingForm.data.job_type_id}
                                        onValueChange={(v) => postingForm.setData('job_type_id', v)}
                                        placeholder={t('Full-time')}
                                    />
                                </FormField>
                                <FormField label={t('Branch')} required error={postError('branch_id')}>
                                    <RecruitmentSelect
                                        options={branches.map((b: { id: number; name: string; city?: string }) => ({
                                            value: String(b.id),
                                            label: `${b.name}${b.city ? ` · ${b.city}` : ''}`,
                                        }))}
                                        value={postingForm.data.branch_id}
                                        onValueChange={(v) => postingForm.setData('branch_id', v)}
                                        placeholder={t('Select branch')}
                                    />
                                </FormField>
                            </div>
                            <FormField label={t('Department')} error={postError('department_id')}>
                                <RecruitmentSelect
                                    options={toSelectOptions(departments)}
                                    value={postingForm.data.department_id}
                                    onValueChange={(v) => postingForm.setData('department_id', v)}
                                    placeholder={t('Select department')}
                                />
                            </FormField>
                            <div className="grid grid-cols-2 gap-4">
                                <FormField label={t('Min experience (years)')} required error={postError('min_experience')}>
                                    <Input
                                        type="number"
                                        min={0}
                                        className="h-10 bg-white dark:bg-slate-950"
                                        value={postingForm.data.min_experience}
                                        onChange={(e) => postingForm.setData('min_experience', Number(e.target.value))}
                                    />
                                </FormField>
                                <FormField label={t('Max experience (years)')} error={postError('max_experience')}>
                                    <Input
                                        type="number"
                                        min={0}
                                        className="h-10 bg-white dark:bg-slate-950"
                                        value={postingForm.data.max_experience}
                                        onChange={(e) => postingForm.setData('max_experience', e.target.value)}
                                    />
                                </FormField>
                            </div>
                        </FormSection>

                        <FormSection title={t('Salary & deadline')}>
                            <div className="grid grid-cols-2 gap-4">
                                <FormField label={t('Min salary (₹/month)')} error={postError('min_salary')}>
                                    <Input
                                        type="number"
                                        min={0}
                                        className="h-10 bg-white dark:bg-slate-950"
                                        placeholder="18000"
                                        value={postingForm.data.min_salary}
                                        onChange={(e) => postingForm.setData('min_salary', e.target.value)}
                                    />
                                </FormField>
                                <FormField label={t('Max salary (₹/month)')} error={postError('max_salary')}>
                                    <Input
                                        type="number"
                                        min={0}
                                        className="h-10 bg-white dark:bg-slate-950"
                                        placeholder="28000"
                                        value={postingForm.data.max_salary}
                                        onChange={(e) => postingForm.setData('max_salary', e.target.value)}
                                    />
                                </FormField>
                            </div>
                            <FormField label={t('Application deadline')} error={postError('application_deadline')}>
                                <Input
                                    type="date"
                                    className="h-10 bg-white dark:bg-slate-950"
                                    value={postingForm.data.application_deadline}
                                    onChange={(e) => postingForm.setData('application_deadline', e.target.value)}
                                />
                            </FormField>
                            <label className="flex cursor-pointer items-center gap-2.5 rounded-lg border border-slate-200 bg-white px-3 py-2.5 dark:border-slate-800 dark:bg-slate-950">
                                <Checkbox
                                    checked={postingForm.data.is_featured}
                                    onCheckedChange={(v) => postingForm.setData('is_featured', v === true)}
                                />
                                <span className="text-sm text-slate-700 dark:text-slate-300">{t('Featured job (highlight on listings)')}</span>
                            </label>
                        </FormSection>

                        <FormSection title={t('Description')}>
                            <FormField label={t('Job description')} error={postError('description')}>
                                <Textarea
                                    rows={5}
                                    className="resize-none bg-white dark:bg-slate-950"
                                    placeholder={t('Responsibilities, requirements, benefits for applicants...')}
                                    value={postingForm.data.description}
                                    onChange={(e) => postingForm.setData('description', e.target.value)}
                                />
                            </FormField>
                        </FormSection>
                    </>
                )}
            </RecruitmentFormSheet>
        </PageTemplate>
    );
}
