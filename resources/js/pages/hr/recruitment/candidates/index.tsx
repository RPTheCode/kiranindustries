import { PipelineBoard } from '@/components/recruitment/PipelineBoard';
import { RecruitmentEmptyState } from '@/components/recruitment/RecruitmentEmptyState';
import { StatusBadge } from '@/components/recruitment/StatusBadge';
import { PageTemplate } from '@/components/page-template';
import { toast } from '@/components/custom-toast';
import { FormField, FormSection, RecruitmentFormSheet } from '@/components/recruitment/RecruitmentFormSheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { RecruitmentSelect } from '@/components/recruitment/RecruitmentSelect';
import { Pagination } from '@/components/ui/pagination';
import { hasPermission } from '@/utils/authorization';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import { LayoutGrid, List, Plus, UserPlus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function RecruitmentCandidates() {
    const { t } = useTranslation();
    const { auth, view, pipeline, candidates, jobPostings, sources, employees, filters = {} } = usePage().props as any;
    const permissions = auth?.permissions ?? [];
    const [sheetOpen, setSheetOpen] = useState(false);

    const form = useForm({
        job_id: '',
        source_id: '',
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        experience_years: 0,
        expected_salary: '',
        notice_period: '',
        application_date: new Date().toISOString().split('T')[0],
    });

    const fieldError = (key: string) => {
        const err = (form.errors as Record<string, string>)[key];
        return err ? String(err) : undefined;
    };

    const switchView = (v: string) => {
        router.get(route('hr.recruitment.candidates.index'), { ...filters, view: v }, { preserveState: true });
    };

    const handleMoveStatus = (id: number, status: string) => {
        router.put(
            route('hr.recruitment.candidates.update-status', id),
            { status },
            {
                preserveScroll: true,
                onSuccess: () => toast.success(t('Status updated')),
            }
        );
    };

    const submitCandidate = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('hr.recruitment.candidates.store'), {
            onSuccess: () => {
                setSheetOpen(false);
                form.reset();
                toast.success(t('Candidate added'));
            },
        });
    };

    const actions = [];
    if (hasPermission(permissions, 'create-candidates')) {
        actions.push({
            label: t('Add Candidate'),
            icon: <Plus className="mr-2 h-4 w-4" />,
            onClick: () => setSheetOpen(true),
        });
    }

    return (
        <PageTemplate
            title={t('Candidates')}
            description={t('Track applicants through the selection process board')}
            url="/recruitment/candidates"
            actions={actions}
            breadcrumbs={[
                { title: t('Dashboard'), href: route('dashboard') },
                { title: t('Recruitment'), href: route('hr.recruitment.hub') },
                { title: t('Candidates') },
            ]}
        >
            <div className="space-y-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div className="flex rounded-lg border p-0.5">
                        <Button
                            variant={view === 'pipeline' ? 'default' : 'ghost'}
                            size="sm"
                            className="h-8"
                            onClick={() => switchView('pipeline')}
                        >
                            <LayoutGrid className="mr-1 h-4 w-4" /> {t('Selection Process Board')}
                        </Button>
                        <Button
                            variant={view === 'list' ? 'default' : 'ghost'}
                            size="sm"
                            className="h-8"
                            onClick={() => switchView('list')}
                        >
                            <List className="mr-1 h-4 w-4" /> {t('List')}
                        </Button>
                    </div>
                </div>

                {view === 'pipeline' ? (
                    pipeline && Object.values(pipeline).some((col: any) => col.length > 0) ? (
                        <PipelineBoard
                            pipeline={pipeline}
                            onMoveStatus={handleMoveStatus}
                            canEdit={hasPermission(permissions, 'edit-candidates')}
                        />
                    ) : (
                        <RecruitmentEmptyState
                            icon={UserPlus}
                            title={t('No candidates yet')}
                                description={t('Add your first candidate to start the selection board')}
                            actionLabel={hasPermission(permissions, 'create-candidates') ? t('Add Candidate') : undefined}
                            onAction={() => setSheetOpen(true)}
                        />
                    )
                ) : (
                    <>
                        <div className="overflow-hidden rounded-xl border">
                            <table className="w-full text-sm">
                                <thead className="bg-slate-50 text-left text-xs text-slate-500">
                                    <tr>
                                        <th className="px-4 py-2">{t('Name')}</th>
                                        <th className="px-4 py-2">{t('Job')}</th>
                                        <th className="px-4 py-2">{t('Status')}</th>
                                        <th className="px-4 py-2">{t('Applied')}</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {candidates?.data?.map((c: any) => (
                                        <tr key={c.id} className="hover:bg-slate-50">
                                            <td className="px-4 py-2">
                                                <Link href={route('hr.recruitment.candidates.show', c.id)} className="font-medium text-primary hover:underline">
                                                    {c.first_name} {c.last_name}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-2 text-slate-600">{c.job?.title}</td>
                                            <td className="px-4 py-2"><StatusBadge status={c.status} /></td>
                                            <td className="px-4 py-2 text-slate-500">{c.application_date}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {candidates?.last_page > 1 ? (
                            <Pagination
                                currentPage={candidates.current_page}
                                totalPages={candidates.last_page}
                                onPageChange={(page) => router.get(route('hr.recruitment.candidates.index'), { ...filters, view: 'list', page })}
                            />
                        ) : null}
                    </>
                )}
            </div>

            <RecruitmentFormSheet
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                title={t('Add Candidate')}
                description={t('Add a new applicant to the selection process. Fields marked * are required.')}
                onSubmit={submitCandidate}
                processing={form.processing}
                submitLabel={t('Add Candidate')}
                size="lg"
            >
                <FormSection title={t('Personal details')}>
                    <div className="grid grid-cols-2 gap-4">
                        <FormField label={t('First Name')} required error={fieldError('first_name')}>
                            <Input
                                className="h-10 bg-white dark:bg-slate-950"
                                placeholder={t('e.g. Ramesh')}
                                value={form.data.first_name}
                                onChange={(e) => form.setData('first_name', e.target.value)}
                            />
                        </FormField>
                        <FormField label={t('Last Name')} required error={fieldError('last_name')}>
                            <Input
                                className="h-10 bg-white dark:bg-slate-950"
                                placeholder={t('e.g. Patel')}
                                value={form.data.last_name}
                                onChange={(e) => form.setData('last_name', e.target.value)}
                            />
                        </FormField>
                    </div>
                    <FormField label={t('Email')} required error={fieldError('email')}>
                        <Input
                            type="email"
                            className="h-10 bg-white dark:bg-slate-950"
                            placeholder="name@email.com"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                        />
                    </FormField>
                    <FormField label={t('Phone')} error={fieldError('phone')} hint={t('10-digit mobile number')}>
                        <Input
                            className="h-10 bg-white dark:bg-slate-950"
                            placeholder="9876543210"
                            maxLength={10}
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value.replace(/\D/g, ''))}
                        />
                    </FormField>
                </FormSection>

                <FormSection title={t('Job application')}>
                    <FormField label={t('Job')} required error={fieldError('job_id')}>
                        <RecruitmentSelect
                            options={jobPostings.map((j: { id: number; title: string; job_code?: string }) => ({
                                value: String(j.id),
                                label: `${j.title}${j.job_code ? ` (${j.job_code})` : ''}`,
                            }))}
                            value={form.data.job_id}
                            onValueChange={(v) => form.setData('job_id', v)}
                            placeholder={t('Select job')}
                        />
                    </FormField>
                    <FormField label={t('Source')} required error={fieldError('source_id')}>
                        <RecruitmentSelect
                            options={sources.map((s: { id: number; name: string }) => ({
                                value: String(s.id),
                                label: s.name,
                            }))}
                            value={form.data.source_id}
                            onValueChange={(v) => form.setData('source_id', v)}
                            placeholder={t('Select source')}
                        />
                    </FormField>
                    <FormField label={t('Application date')} required error={fieldError('application_date')}>
                        <Input
                            type="date"
                            className="h-10 bg-white dark:bg-slate-950"
                            value={form.data.application_date}
                            onChange={(e) => form.setData('application_date', e.target.value)}
                        />
                    </FormField>
                </FormSection>

                <FormSection title={t('Experience & salary')}>
                    <div className="grid grid-cols-2 gap-4">
                        <FormField label={t('Experience (years)')} error={fieldError('experience_years')}>
                            <Input
                                type="number"
                                min={0}
                                className="h-10 bg-white dark:bg-slate-950"
                                value={form.data.experience_years}
                                onChange={(e) => form.setData('experience_years', Number(e.target.value))}
                            />
                        </FormField>
                        <FormField label={t('Expected salary (₹/month)')} error={fieldError('expected_salary')}>
                            <Input
                                type="number"
                                min={0}
                                className="h-10 bg-white dark:bg-slate-950"
                                placeholder="22000"
                                value={form.data.expected_salary}
                                onChange={(e) => form.setData('expected_salary', e.target.value)}
                            />
                        </FormField>
                    </div>
                    <FormField label={t('Notice period')} error={fieldError('notice_period')}>
                        <Input
                            className="h-10 bg-white dark:bg-slate-950"
                            placeholder={t('e.g. 15 days, Immediate')}
                            value={form.data.notice_period}
                            onChange={(e) => form.setData('notice_period', e.target.value)}
                        />
                    </FormField>
                </FormSection>
            </RecruitmentFormSheet>
        </PageTemplate>
    );
}
