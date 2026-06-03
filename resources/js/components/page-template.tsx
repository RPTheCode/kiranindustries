import { Head, usePage } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Button } from '@/components/ui/button';
import { ReactNode } from 'react';
import { FloatingChatGpt } from '@/components/FloatingChatGpt';
import { cn } from '@/lib/utils';

export interface PageAction {
  label: string;
  icon?: ReactNode;
  variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
  onClick?: () => void;
}

export interface PageTemplateProps {
  title: string;
  description?: string;
  url: string;
  actions?: PageAction[];
  headerExtra?: ReactNode;
  children: ReactNode;
  noPadding?: boolean;
  breadcrumbs?: BreadcrumbItem[];
}

function PageToolbarActions({
  actions,
  headerExtra,
}: {
  actions?: PageAction[];
  headerExtra?: ReactNode;
}) {
  if (!headerExtra && (!actions || actions.length === 0)) {
    return null;
  }

  return (
    <div className="flex flex-wrap items-center justify-end gap-2">
      {headerExtra}
      {actions?.map((action, index) => (
        <Button
          key={index}
          variant={action.variant || 'outline'}
          size="sm"
          onClick={action.onClick}
          className="h-9 shrink-0 cursor-pointer"
        >
          {action.icon && <span className="mr-1.5">{action.icon}</span>}
          {action.label}
        </Button>
      ))}
    </div>
  );
}

export function PageTemplate({
  title,
  description,
  url,
  actions,
  headerExtra,
  children,
  noPadding = false,
  breadcrumbs,
}: PageTemplateProps) {
  const pageBreadcrumbs: BreadcrumbItem[] = breadcrumbs || [
    {
      title,
      href: url,
    },
  ];

  const isNestedPage = pageBreadcrumbs.length > 1;
  const hasToolbar = Boolean(headerExtra || (actions && actions.length > 0));

  const headerActions =
    !isNestedPage && hasToolbar ? (
      <PageToolbarActions actions={actions} headerExtra={headerExtra} />
    ) : undefined;

  return (
    <AppLayout breadcrumbs={pageBreadcrumbs} headerActions={headerActions}>
      <Head title={`${title} - ${(usePage().props as any).globalSettings?.titleText || 'HRM'}`} />

      <div className="flex w-full min-w-0 flex-1 flex-col gap-4">
        {isNestedPage && (
          <div className="flex flex-col gap-3 border-b border-slate-100 pb-4 dark:border-slate-800 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <h1 className="text-lg font-semibold text-slate-900 dark:text-slate-50">{title}</h1>
              {description?.trim() ? (
                <p className="mt-1 text-sm text-slate-500 dark:text-slate-400">{description}</p>
              ) : null}
            </div>
            {hasToolbar && <PageToolbarActions actions={actions} headerExtra={headerExtra} />}
          </div>
        )}

        {!isNestedPage && description?.trim() ? (
          <p className="text-sm text-slate-500 dark:text-slate-400">{description}</p>
        ) : null}

        <div
          className={cn(
            'w-full min-w-0',
            !noPadding &&
              'rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-800 dark:bg-slate-950 sm:p-5'
          )}
        >
          {children}
        </div>
      </div>
      <FloatingChatGpt />
    </AppLayout>
  );
}
