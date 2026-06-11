import { StatusBadge } from '@/components/recruitment/StatusBadge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card';
import { Briefcase, MapPin, Users } from 'lucide-react';

export function JobCard({
    title,
    code,
    subtitle,
    location,
    applicantsCount,
    status,
    isPublished,
    deadline,
    actions,
}: {
    title: string;
    code?: string;
    subtitle?: string;
    location?: string;
    applicantsCount?: number;
    status: string;
    isPublished?: boolean;
    deadline?: string;
    actions?: React.ReactNode;
}) {
    return (
        <Card className="flex h-full flex-col border-slate-200/80 shadow-sm transition-shadow hover:shadow-md dark:border-slate-800">
            <CardHeader className="space-y-1 pb-2">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="font-semibold text-sm text-slate-900 dark:text-slate-100">{title}</p>
                        {code ? <p className="font-mono text-[10px] text-slate-400">{code}</p> : null}
                    </div>
                    <StatusBadge status={status} />
                </div>
                {subtitle ? <p className="text-xs text-slate-500">{subtitle}</p> : null}
            </CardHeader>
            <CardContent className="flex-1 space-y-1.5 pb-2 text-xs text-slate-500">
                {location ? (
                    <p className="flex items-center gap-1.5">
                        <MapPin className="h-3.5 w-3.5" /> {location}
                    </p>
                ) : null}
                {applicantsCount != null ? (
                    <p className="flex items-center gap-1.5">
                        <Users className="h-3.5 w-3.5" /> {applicantsCount} applicants
                    </p>
                ) : null}
                {deadline ? (
                    <p className="flex items-center gap-1.5">
                        <Briefcase className="h-3.5 w-3.5" /> Deadline: {deadline}
                    </p>
                ) : null}
                {isPublished != null ? (
                    <span
                        className={`inline-flex rounded px-1.5 py-0.5 text-[10px] font-medium ${
                            isPublished ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'
                        }`}
                    >
                        {isPublished ? 'Published' : 'Draft listing'}
                    </span>
                ) : null}
            </CardContent>
            {actions ? <CardFooter className="flex flex-wrap gap-1 border-t pt-2">{actions}</CardFooter> : null}
        </Card>
    );
}
