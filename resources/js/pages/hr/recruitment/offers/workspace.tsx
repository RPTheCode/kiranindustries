import { RecruitmentEmptyState } from '@/components/recruitment/RecruitmentEmptyState';
import { StatusBadge } from '@/components/recruitment/StatusBadge';
import { PageTemplate } from '@/components/page-template';
import { toast } from '@/components/custom-toast';
import { FormField, FormSection, RecruitmentFormSheet } from '@/components/recruitment/RecruitmentFormSheet';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { RecruitmentSelect } from '@/components/recruitment/RecruitmentSelect';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent } from '@/components/ui/card';
import { Pagination } from '@/components/ui/pagination';
import { hasPermission } from '@/utils/authorization';
import { Link, router, useForm, usePage } from '@inertiajs/react';
import {
    downloadOfferLetterPdf,
    fetchOfferLetterPreview,
    fillOfferTemplate,
    sampleOfferVariables,
    variablesFromOffer,
    type OfferTemplateRow,
} from '@/components/recruitment/offerLetterUtils';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { FileText, Download, Eye, Plus, UserCheck } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

export default function OffersWorkspace() {
    const { t } = useTranslation();
    const {
        auth,
        tab,
        offers,
        selectionCandidates,
        offerTemplates,
        candidates,
        departments,
        filters = {},
    } = usePage().props as any;
    const permissions = auth?.permissions ?? [];
    const [sheetOpen, setSheetOpen] = useState(false);
    const [letterPreview, setLetterPreview] = useState<{ title: string; html: string } | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);
    const [downloading, setDownloading] = useState(false);
    const letterIframeRef = useRef<HTMLIFrameElement>(null);

    const fitLetterIframe = useCallback(() => {
        const iframe = letterIframeRef.current;
        if (!iframe?.contentDocument) {
            return;
        }

        const doc = iframe.contentDocument;
        const height = Math.max(doc.body?.scrollHeight ?? 0, doc.documentElement?.scrollHeight ?? 0);
        iframe.style.height = `${height}px`;
    }, []);

    useEffect(() => {
        if (!letterPreview?.html || previewLoading) {
            return;
        }

        const timer = window.setTimeout(fitLetterIframe, 0);
        return () => window.clearTimeout(timer);
    }, [letterPreview?.html, previewLoading, fitLetterIframe]);

    const activeTemplate = (offerTemplates as OfferTemplateRow[] | undefined)?.find(
        (t) => t.status !== 'inactive'
    ) ?? (offerTemplates as OfferTemplateRow[] | undefined)?.[0];

    const canViewLetter = hasPermission(permissions, 'view-offer-templates');

    const openLetterPreview = async (title: string, template: OfferTemplateRow, variables: Record<string, string>) => {
        setPreviewLoading(true);
        setLetterPreview({ title, html: '' });
        try {
            const data = await fetchOfferLetterPreview(template.id, variables);
            const html = data.html?.trim()
                ? data.html
                : `<pre style="font-family:Arial,sans-serif;padding:16px;white-space:pre-wrap;">${fillOfferTemplate(template.template_content, variables)}</pre>`;
            setLetterPreview({ title, html });
        } catch {
            toast.error(t('Could not load letter preview'));
            setLetterPreview(null);
        } finally {
            setPreviewLoading(false);
        }
    };

    const downloadLetter = async (template: OfferTemplateRow, variables: Record<string, string>, filename: string) => {
        setDownloading(true);
        try {
            await downloadOfferLetterPdf(template.id, variables, filename);
            toast.success(t('Letter downloaded'));
        } catch {
            toast.error(t('Could not download letter'));
        } finally {
            setDownloading(false);
        }
    };

    const form = useForm({
        candidate_id: '',
        position: '',
        department_id: '',
        salary: '',
        start_date: '',
        expiration_date: '',
        benefits: '',
    });

    const fieldError = (key: string) => {
        const err = (form.errors as Record<string, string>)[key];
        return err ? String(err) : undefined;
    };

    const submitOffer = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(route('hr.recruitment.offers.store'), {
            onSuccess: () => {
                setSheetOpen(false);
                form.reset();
                toast.success(t('Offer created'));
            },
        });
    };

    const actions = [];
    if (hasPermission(permissions, 'create-offers')) {
        actions.push({ label: t('New Offer'), icon: <Plus className="mr-2 h-4 w-4" />, onClick: () => setSheetOpen(true) });
    }

    return (
        <PageTemplate
            title={t('Offers & Selection')}
            description={t('Manage offers and selected candidates')}
            url="/recruitment/offers"
            actions={actions}
            breadcrumbs={[
                { title: t('Dashboard'), href: route('dashboard') },
                { title: t('Recruitment'), href: route('hr.recruitment.hub') },
                { title: t('Offers') },
            ]}
        >
            <div className="p-4 md:p-6">
                <Tabs
                    value={tab}
                    onValueChange={(v) => router.get(route('hr.recruitment.offers.index'), { tab: v }, { preserveState: true })}
                >
                    <TabsList>
                        <TabsTrigger value="offers">{t('Offers')}</TabsTrigger>
                        <TabsTrigger value="templates">{t('Templates')}</TabsTrigger>
                        <TabsTrigger value="selection">{t('Employee Selection')}</TabsTrigger>
                    </TabsList>

                    <TabsContent value="offers" className="mt-4 space-y-3">
                        {offers?.data?.length ? (
                            offers.data.map((offer: any) => (
                                <Card key={offer.id}>
                                    <CardContent className="flex flex-wrap items-center justify-between gap-2 p-4">
                                        <div>
                                            <p className="font-medium text-sm">
                                                {offer.candidate?.first_name} {offer.candidate?.last_name}
                                            </p>
                                            <p className="text-xs text-slate-500">{offer.position} · {offer.salary}</p>
                                        </div>
                                        <div className="flex flex-wrap items-center justify-end gap-2">
                                            {canViewLetter && activeTemplate ? (
                                                <>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-7 text-xs"
                                                        onClick={() =>
                                                            openLetterPreview(
                                                                `${offer.candidate?.first_name} ${offer.candidate?.last_name}`,
                                                                activeTemplate,
                                                                variablesFromOffer(offer)
                                                            )
                                                        }
                                                    >
                                                        <Eye className="mr-1 h-3 w-3" />
                                                        {t('View Letter')}
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-7 text-xs"
                                                        disabled={downloading}
                                                        onClick={() =>
                                                            downloadLetter(
                                                                activeTemplate,
                                                                variablesFromOffer(offer),
                                                                `Offer_${offer.candidate?.last_name ?? offer.id}`
                                                            )
                                                        }
                                                    >
                                                        <Download className="mr-1 h-3 w-3" />
                                                        {t('PDF')}
                                                    </Button>
                                                </>
                                            ) : null}
                                            <StatusBadge status={offer.status} />
                                            {hasPermission(permissions, 'edit-offers') ? (
                                                <Select
                                                    value={offer.status}
                                                    onValueChange={(status) =>
                                                        router.put(route('hr.recruitment.offers.update-status', offer.id), { status }, {
                                                            onSuccess: () => toast.success(t('Updated')),
                                                        })
                                                    }
                                                >
                                                    <SelectTrigger className="h-8 w-[120px] text-xs"><SelectValue /></SelectTrigger>
                                                    <SelectContent>
                                                        {['Draft', 'Sent', 'Accepted', 'Declined', 'Negotiating', 'Expired'].map((s) => (
                                                            <SelectItem key={s} value={s}>{s}</SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            ) : null}
                                        </div>
                                    </CardContent>
                                </Card>
                            ))
                        ) : (
                            <RecruitmentEmptyState icon={FileText} title={t('No offers')} description={t('Create an offer for a selected candidate')} />
                        )}
                    </TabsContent>

                    <TabsContent value="templates" className="mt-4 space-y-3">
                        <p className="text-xs text-slate-500">
                            {t('Preview the appointment letter format or download a sample PDF with placeholder data.')}
                        </p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            {(offerTemplates ?? []).map((tpl: OfferTemplateRow) => (
                                <Card key={tpl.id}>
                                    <CardContent className="space-y-3 p-4">
                                        <div>
                                            <p className="font-medium text-sm">{tpl.name}</p>
                                            <p className="mt-1 line-clamp-3 whitespace-pre-wrap text-xs text-slate-500">
                                                {tpl.template_content}
                                            </p>
                                        </div>
                                        {canViewLetter ? (
                                            <div className="flex flex-wrap gap-2">
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-7 text-xs"
                                                    onClick={() => openLetterPreview(tpl.name, tpl, sampleOfferVariables())}
                                                >
                                                    <Eye className="mr-1 h-3 w-3" />
                                                    {t('Preview')}
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    className="h-7 text-xs"
                                                    disabled={downloading}
                                                    onClick={() =>
                                                        downloadLetter(tpl, sampleOfferVariables(), tpl.name.replace(/\s+/g, '_'))
                                                    }
                                                >
                                                    <Download className="mr-1 h-3 w-3" />
                                                    {t('Sample PDF')}
                                                </Button>
                                            </div>
                                        ) : null}
                                    </CardContent>
                                </Card>
                            ))}
                        </div>
                    </TabsContent>

                    <TabsContent value="selection" className="mt-4 space-y-3">
                        {(selectionCandidates ?? []).map((c: any) => (
                            <Card key={c.id}>
                                <CardContent className="flex flex-wrap items-center justify-between gap-2 p-4">
                                    <div>
                                        <Link href={route('hr.recruitment.candidates.show', c.id)} className="font-medium text-sm text-primary hover:underline">
                                            {c.first_name} {c.last_name}
                                        </Link>
                                        <p className="text-xs text-slate-500">{c.job?.title}</p>
                                    </div>
                                    <div className="flex flex-wrap gap-1">
                                        <StatusBadge status={c.status} />
                                        {hasPermission(permissions, 'edit-candidates') && c.status === 'Offer' ? (
                                            <Button
                                                size="sm"
                                                className="h-7 text-xs"
                                                onClick={() =>
                                                    router.put(route('hr.recruitment.candidates.update-status', c.id), { status: 'Hired' }, {
                                                        onSuccess: () => toast.success(t('Marked as hired')),
                                                    })
                                                }
                                            >
                                                {t('Confirm Selection')}
                                            </Button>
                                        ) : null}
                                        {hasPermission(permissions, 'create-employees') && c.status === 'Hired' && !c.employee_id ? (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="h-7 text-xs"
                                                onClick={() => router.post(route('hr.recruitment.candidates.convert-employee', c.id))}
                                            >
                                                <UserCheck className="mr-1 h-3 w-3" /> {t('Convert to Employee')}
                                            </Button>
                                        ) : null}
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </TabsContent>
                </Tabs>
            </div>

            <RecruitmentFormSheet
                open={sheetOpen}
                onOpenChange={setSheetOpen}
                title={t('New Offer')}
                description={t('Send a job offer to a selected candidate.')}
                onSubmit={submitOffer}
                processing={form.processing}
                submitLabel={t('Create Offer')}
                size="lg"
            >
                <FormSection title={t('Offer details')}>
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
                    <FormField label={t('Position title')} required error={fieldError('position')}>
                        <Input className="h-10 bg-white dark:bg-slate-950" placeholder={t('e.g. CNC Machine Operator')} value={form.data.position} onChange={(e) => form.setData('position', e.target.value)} />
                    </FormField>
                    <FormField label={t('Monthly salary (₹)')} required error={fieldError('salary')}>
                        <Input type="number" min={0} className="h-10 bg-white dark:bg-slate-950" placeholder="24000" value={form.data.salary} onChange={(e) => form.setData('salary', e.target.value)} />
                    </FormField>
                </FormSection>
                <FormSection title={t('Dates')}>
                    <div className="grid grid-cols-2 gap-4">
                        <FormField label={t('Joining date')} required error={fieldError('start_date')}>
                            <Input type="date" className="h-10 bg-white dark:bg-slate-950" value={form.data.start_date} onChange={(e) => form.setData('start_date', e.target.value)} />
                        </FormField>
                        <FormField label={t('Offer expires')} required error={fieldError('expiration_date')}>
                            <Input type="date" className="h-10 bg-white dark:bg-slate-950" value={form.data.expiration_date} onChange={(e) => form.setData('expiration_date', e.target.value)} />
                        </FormField>
                    </div>
                </FormSection>
            </RecruitmentFormSheet>

            <Dialog open={!!letterPreview} onOpenChange={(open) => !open && setLetterPreview(null)}>
                <DialogContent className="max-w-2xl overflow-visible p-4 sm:max-w-3xl sm:p-6">
                    <DialogHeader className="pb-2">
                        <DialogTitle>{letterPreview?.title ?? t('Offer Letter')}</DialogTitle>
                    </DialogHeader>
                    {previewLoading ? (
                        <p className="py-8 text-center text-sm text-slate-500">{t('Loading preview...')}</p>
                    ) : letterPreview?.html ? (
                        <iframe
                            ref={letterIframeRef}
                            title={letterPreview.title}
                            srcDoc={letterPreview.html}
                            sandbox="allow-same-origin"
                            scrolling="no"
                            onLoad={fitLetterIframe}
                            className="block w-full overflow-hidden rounded-lg border bg-white shadow-sm"
                            style={{ height: 0 }}
                        />
                    ) : (
                        <p className="py-8 text-center text-sm text-slate-500">{t('No preview available')}</p>
                    )}
                </DialogContent>
            </Dialog>
        </PageTemplate>
    );
}
