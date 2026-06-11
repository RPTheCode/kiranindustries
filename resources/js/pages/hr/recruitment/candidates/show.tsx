import { ResumeUploadZone } from '@/components/recruitment/ResumeUploadZone';
import { StatusBadge } from '@/components/recruitment/StatusBadge';
import { PageTemplate } from '@/components/page-template';
import { toast } from '@/components/custom-toast';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { hasPermission } from '@/utils/authorization';
import { router, usePage } from '@inertiajs/react';
import { Calendar, Mail, Phone, UserCheck } from 'lucide-react';
import { useTranslation } from 'react-i18next';

export default function CandidateProfile() {
    const { t } = useTranslation();
    const { auth, candidate, resumeUrl, coverLetterUrl } = usePage().props as any;
    const permissions = auth?.permissions ?? [];

    const uploadFile = (field: 'resume' | 'cover_letter', file: File) => {
        const fd = new FormData();
        fd.append(field, file);
        router.post(route('hr.recruitment.candidates.upload-documents', candidate.id), fd, {
            forceFormData: true,
            onSuccess: () => toast.success(t('Uploaded')),
        });
    };

    const actions = [];
    if (hasPermission(permissions, 'create-employees') && !candidate.employee_id && candidate.status === 'Hired') {
        actions.push({
            label: t('Convert to Employee'),
            icon: <UserCheck className="mr-2 h-4 w-4" />,
            onClick: () => router.post(route('hr.recruitment.candidates.convert-employee', candidate.id)),
        });
    }

    return (
        <PageTemplate
            title={candidate.first_name + ' ' + candidate.last_name}
            description={candidate.job?.title}
            url={`/recruitment/candidates/${candidate.id}`}
            actions={actions}
            breadcrumbs={[
                { title: t('Dashboard'), href: route('dashboard') },
                { title: t('Recruitment'), href: route('hr.recruitment.hub') },
                { title: t('Candidates'), href: route('hr.recruitment.candidates.index') },
                { title: candidate.first_name },
            ]}
        >
            <div className="space-y-4 p-4 md:p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <StatusBadge status={candidate.status} />
                    {candidate.source?.name ? (
                        <span className="text-xs text-slate-500">{t('Source')}: {candidate.source.name}</span>
                    ) : null}
                </div>

                <Tabs defaultValue="overview">
                    <TabsList>
                        <TabsTrigger value="overview">{t('Overview')}</TabsTrigger>
                        <TabsTrigger value="resume">{t('Resume')}</TabsTrigger>
                        <TabsTrigger value="interviews">{t('Interviews')}</TabsTrigger>
                        <TabsTrigger value="offers">{t('Offers')}</TabsTrigger>
                    </TabsList>

                    <TabsContent value="overview" className="mt-4">
                        <div className="grid gap-4 md:grid-cols-2">
                            <Card>
                                <CardHeader><CardTitle className="text-sm">{t('Contact')}</CardTitle></CardHeader>
                                <CardContent className="space-y-2 text-sm text-slate-600">
                                    <p className="flex items-center gap-2"><Mail className="h-4 w-4" /> {candidate.email}</p>
                                    {candidate.phone ? <p className="flex items-center gap-2"><Phone className="h-4 w-4" /> {candidate.phone}</p> : null}
                                    <p className="flex items-center gap-2"><Calendar className="h-4 w-4" /> {t('Applied')}: {candidate.application_date}</p>
                                </CardContent>
                            </Card>
                            <Card>
                                <CardHeader><CardTitle className="text-sm">{t('Experience')}</CardTitle></CardHeader>
                                <CardContent className="space-y-1 text-sm text-slate-600">
                                    <p>{candidate.experience_years} {t('years')}</p>
                                    {candidate.current_company ? <p>{candidate.current_company} — {candidate.current_position}</p> : null}
                                    {candidate.skills ? <p className="text-xs">{candidate.skills}</p> : null}
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    <TabsContent value="resume" className="mt-4 grid gap-4 md:grid-cols-2">
                        {hasPermission(permissions, 'edit-candidates') ? (
                            <>
                                <ResumeUploadZone label={t('Resume')} currentUrl={resumeUrl} onFileSelect={(f) => uploadFile('resume', f)} />
                                <ResumeUploadZone label={t('Cover Letter')} currentUrl={coverLetterUrl} onFileSelect={(f) => uploadFile('cover_letter', f)} />
                            </>
                        ) : (
                            <p className="text-sm text-slate-500">{resumeUrl ? <a href={resumeUrl} className="text-primary">{t('View resume')}</a> : t('No resume')}</p>
                        )}
                    </TabsContent>

                    <TabsContent value="interviews" className="mt-4">
                        <div className="space-y-2">
                            {(candidate.interviews ?? []).length === 0 ? (
                                <p className="text-sm text-slate-500">{t('No interviews scheduled')}</p>
                            ) : (
                                candidate.interviews.map((iv: any) => (
                                    <Card key={iv.id}>
                                        <CardContent className="flex items-center justify-between p-4 text-sm">
                                            <div>
                                                <p className="font-medium">{iv.round?.name} — {iv.interview_type?.name}</p>
                                                <p className="text-slate-500">{iv.scheduled_date} {iv.scheduled_time}</p>
                                            </div>
                                            <StatusBadge status={iv.status} />
                                        </CardContent>
                                    </Card>
                                ))
                            )}
                        </div>
                    </TabsContent>

                    <TabsContent value="offers" className="mt-4">
                        <div className="space-y-2">
                            {(candidate.offers ?? []).length === 0 ? (
                                <p className="text-sm text-slate-500">{t('No offers yet')}</p>
                            ) : (
                                candidate.offers.map((offer: any) => (
                                    <Card key={offer.id}>
                                        <CardContent className="flex items-center justify-between p-4 text-sm">
                                            <div>
                                                <p className="font-medium">{offer.position}</p>
                                                <p className="text-slate-500">{offer.salary}</p>
                                            </div>
                                            <StatusBadge status={offer.status} />
                                        </CardContent>
                                    </Card>
                                ))
                            )}
                        </div>
                    </TabsContent>
                </Tabs>
            </div>
        </PageTemplate>
    );
}
