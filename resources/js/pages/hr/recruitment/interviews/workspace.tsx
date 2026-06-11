import { RecruitmentEmptyState } from '@/components/recruitment/RecruitmentEmptyState';
import { StatusBadge } from '@/components/recruitment/StatusBadge';
import { PageTemplate } from '@/components/page-template';
import { toast } from '@/components/custom-toast';
import { FormField, FormSection, RecruitmentFormSheet } from '@/components/recruitment/RecruitmentFormSheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { RecruitmentSelect, toSelectOptions } from '@/components/recruitment/RecruitmentSelect';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Pagination } from '@/components/ui/pagination';
import { hasPermission } from '@/utils/authorization';
import { router, useForm, usePage } from '@inertiajs/react';
import { Calendar, ChevronLeft, ChevronRight, Plus } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function InterviewsWorkspace() {
    const { t } = useTranslation();
    const {
        auth,
        view,
        interviews,
        calendarInterviews,
        candidates,
        rounds,
        interviewTypes,
        employees,
        calendarMonth,
        filters = {},
    } = usePage().props as any;
    const currentUserId = auth?.user?.id;
    const permissions = auth?.permissions ?? [];
    const [sheetOpen, setSheetOpen] = useState(false);

    const form = useForm({
        candidate_id: '',
        round_id: '',
        interview_type_id: '',
        scheduled_date: '',
        scheduled_time: '10:00',
        duration: 60,
        location: '',
        meeting_link: '',
        interviewers: [] as number[],
    });

    const fieldError = (key: string) => {
        const err = (form.errors as Record<string, string>)[key];
        return err ? String(err) : undefined;
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const interviewers = form.data.interviewers.length > 0 ? form.data.interviewers : currentUserId ? [currentUserId] : [];
        form.transform((data) => ({ ...data, interviewers }));
        form.post(route('hr.recruitment.interviews.store'), {
            onSuccess: () => {
                setSheetOpen(false);
                form.reset();
                toast.success(t('Interview scheduled'));
            },
        });
    };

    const changeMonth = (delta: number) => {
        const [y, m] = calendarMonth.split('-').map(Number);
        const d = new Date(y, m - 1 + delta, 1);
        const next = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
        router.get(route('hr.recruitment.interviews.index'), { ...filters, month: next, view }, { preserveState: true });
    };

    const actions = [];
    if (hasPermission(permissions, 'create-interviews')) {
        actions.push({ label: t('Schedule'), icon: <Plus className="mr-2 h-4 w-4" />, onClick: () => setSheetOpen(true) });
    }

    const groupedByDate = (calendarInterviews ?? []).reduce((acc: Record<string, any[]>, iv: any) => {
        const key = iv.scheduled_date?.split('T')[0] ?? iv.scheduled_date;
        if (!acc[key]) acc[key] = [];
        acc[key].push(iv);
        return acc;
    }, {});

    return (
        <PageTemplate
            title={t('Interviews')}
            description={t('Schedule and track interviews')}
            url="/recruitment/interviews"
            actions={actions}
            breadcrumbs={[
                { title: t('Dashboard'), href: route('dashboard') },
                { title: t('Recruitment'), href: route('hr.recruitment.hub') },
                { title: t('Interviews') },
            ]}
        >
            <div className="space-y-4 p-4 md:p-6">
                <Tabs
                    value={view}
                    onValueChange={(v) => router.get(route('hr.recruitment.interviews.index'), { ...filters, view: v }, { preserveState: true })}
                >
                    <TabsList>
                        <TabsTrigger value="list">{t('List')}</TabsTrigger>
                        <TabsTrigger value="calendar">{t('Calendar')}</TabsTrigger>
                        <TabsTrigger
                            value="mine"
                            onClick={() => router.get(route('hr.recruitment.interviews.index'), { ...filters, view: 'list', mine: 1 })}
                        >
                            {t('My Interviews')}
                        </TabsTrigger>
                    </TabsList>

                    <TabsContent value="list" className="mt-4">
                        {interviews?.data?.length ? (
                            <div className="space-y-2">
                                {interviews.data.map((iv: any) => (
                                    <div
                                        key={iv.id}
                                        className="flex flex-wrap items-center justify-between gap-2 rounded-xl border bg-white p-4 dark:bg-slate-950"
                                    >
                                        <div>
                                            <p className="font-medium text-sm">
                                                {iv.candidate?.first_name} {iv.candidate?.last_name}
                                            </p>
                                            <p className="text-xs text-slate-500">
                                                {iv.job?.title} · {iv.round?.name} · {iv.scheduled_date} {iv.scheduled_time}
                                            </p>
                                        </div>
                                        <StatusBadge status={iv.status} />
                                    </div>
                                ))}
                                {interviews.last_page > 1 ? (
                                    <Pagination
                                        currentPage={interviews.current_page}
                                        totalPages={interviews.last_page}
                                        onPageChange={(page) => router.get(route('hr.recruitment.interviews.index'), { ...filters, page })}
                                    />
                                ) : null}
                            </div>
                        ) : (
                            <RecruitmentEmptyState
                                icon={Calendar}
                                title={t('No interviews')}
                                description={t('Schedule the first interview')}
                                actionLabel={hasPermission(permissions, 'create-interviews') ? t('Schedule') : undefined}
                                onAction={() => setSheetOpen(true)}
                            />
                        )}
                    </TabsContent>

                    <TabsContent value="calendar" className="mt-4">
                        <div className="mb-4 flex items-center justify-between">
                            <Button variant="outline" size="icon" onClick={() => changeMonth(-1)}>
                                <ChevronLeft className="h-4 w-4" />
                            </Button>
                            <span className="text-sm font-semibold">{calendarMonth}</span>
                            <Button variant="outline" size="icon" onClick={() => changeMonth(1)}>
                                <ChevronRight className="h-4 w-4" />
                            </Button>
                        </div>
                        <div className="space-y-4">
                            {Object.keys(groupedByDate).length === 0 ? (
                                <p className="text-center text-sm text-slate-500">{t('No interviews this month')}</p>
                            ) : (
                                Object.entries(groupedByDate).map(([date, items]) => (
                                    <div key={date}>
                                        <p className="mb-2 text-xs font-semibold text-slate-500">{date}</p>
                                        <div className="space-y-2">
                                            {(items as any[]).map((iv) => (
                                                <div key={iv.id} className="rounded-lg border-l-4 border-primary bg-white p-3 text-sm dark:bg-slate-950">
                                                    <p className="font-medium">
                                                        {iv.scheduled_time} — {iv.candidate?.first_name} {iv.candidate?.last_name}
                                                    </p>
                                                    <p className="text-xs text-slate-500">{iv.job?.title} · {iv.round?.name}</p>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>
                    </TabsContent>
                </Tabs>
            </div>

            <RecruitmentFormSheet
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                title={t('Schedule Interview')}
                description={t('Book an interview slot for a candidate.')}
                onSubmit={submit}
                processing={form.processing}
                submitLabel={t('Schedule Interview')}
                size="lg"
            >
                <FormSection title={t('Who & which round')}>
                    <FormField label={t('Candidate')} required error={fieldError('candidate_id')}>
                        <RecruitmentSelect
                            options={candidates.map((c: { id: number; first_name: string; last_name: string }) => ({
                                value: String(c.id),
                                label: `${c.first_name} ${c.last_name}`,
                            }))}
                            value={form.data.candidate_id}
                            onValueChange={(v) => form.setData('candidate_id', v)}
                            placeholder={t('Select candidate')}
                        />
                    </FormField>
                    <div className="grid grid-cols-2 gap-4">
                        <FormField label={t('Interview round')} required error={fieldError('round_id')}>
                            <RecruitmentSelect
                                options={toSelectOptions(rounds)}
                                value={form.data.round_id}
                                onValueChange={(v) => form.setData('round_id', v)}
                                placeholder={t('Round')}
                            />
                        </FormField>
                        <FormField label={t('Interview type')} required error={fieldError('interview_type_id')}>
                            <RecruitmentSelect
                                options={toSelectOptions(interviewTypes)}
                                value={form.data.interview_type_id}
                                onValueChange={(v) => form.setData('interview_type_id', v)}
                                placeholder={t('Type')}
                            />
                        </FormField>
                    </div>
                </FormSection>
                <FormSection title={t('Schedule')}>
                    <div className="grid grid-cols-2 gap-4">
                        <FormField label={t('Date')} required error={fieldError('scheduled_date')}>
                            <Input type="date" className="h-10 bg-white dark:bg-slate-950" value={form.data.scheduled_date} onChange={(e) => form.setData('scheduled_date', e.target.value)} />
                        </FormField>
                        <FormField label={t('Time')} required error={fieldError('scheduled_time')}>
                            <Input type="time" className="h-10 bg-white dark:bg-slate-950" value={form.data.scheduled_time} onChange={(e) => form.setData('scheduled_time', e.target.value)} />
                        </FormField>
                    </div>
                    <FormField label={t('Duration (minutes)')} error={fieldError('duration')}>
                        <Input type="number" min={15} max={480} className="h-10 bg-white dark:bg-slate-950" value={form.data.duration} onChange={(e) => form.setData('duration', Number(e.target.value))} />
                    </FormField>
                    <FormField label={t('Location or meeting link')} error={fieldError('meeting_link')} hint={t('Plant office name or video call URL')}>
                        <Input className="h-10 bg-white dark:bg-slate-950" placeholder="Palsana HR Office / https://meet..." value={form.data.meeting_link} onChange={(e) => form.setData('meeting_link', e.target.value)} />
                    </FormField>
                </FormSection>
            </RecruitmentFormSheet>
        </PageTemplate>
    );
}
