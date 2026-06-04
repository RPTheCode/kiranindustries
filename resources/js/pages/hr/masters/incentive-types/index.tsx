import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, CheckCircle2, XCircle } from 'lucide-react';
import { CrudTable } from '@/components/CrudTable';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';

export default function IncentiveTypes() {
  const { t } = useTranslation();
  const { types, auth } = usePage().props as any;
  const permissions = auth?.permissions || [];

  const [isFormModalOpen, setIsFormModalOpen] = useState(false);
  const [isDeleteModalOpen, setIsDeleteModalOpen] = useState(false);
  const [currentItem, setCurrentItem] = useState<any>(null);
  const [formMode, setFormMode] = useState<'create' | 'edit' | 'view'>('create');

  const handleAction = (action: string, item: any) => {
    setCurrentItem(item);
    switch (action) {
      case 'view': setFormMode('view'); setIsFormModalOpen(true); break;
      case 'edit': setFormMode('edit'); setIsFormModalOpen(true); break;
      case 'delete': setIsDeleteModalOpen(true); break;
      case 'toggle-status': 
        router.put(route('hr.incentive-types.toggle-status', item.id), {}, {
          onSuccess: () => toast.success(t('Status updated')),
        });
        break;
    }
  };

  const handleFormSubmit = (formData: any) => {
    const routeName = formMode === 'create' ? 'hr.incentive-types.store' : 'hr.incentive-types.update';
    const method = formMode === 'create' ? 'post' : 'put';
    const routeParams = formMode === 'create' ? [] : [currentItem.id];

    router[method](route(routeName, routeParams), formData, {
      onSuccess: () => {
        setIsFormModalOpen(false);
        toast.success(t(formMode === 'create' ? 'Type created' : 'Type updated'));
      },
      onError: (errors) => toast.error(Object.values(errors).join(', '))
    });
  };

  const handleDeleteConfirm = () => {
    router.delete(route('hr.incentive-types.destroy', currentItem.id), {
      onSuccess: () => {
        setIsDeleteModalOpen(false);
        toast.success(t('Type deleted'));
      }
    });
  };

  const columns = [
    { key: 'name', label: t('Type Name'), sortable: true },
    { 
        key: 'type', 
        label: t('Category'), 
        render: (v: string) => (
            <Badge variant={v === 'earning' ? 'success' : 'destructive'} className="capitalize">
                {t(v)}
            </Badge>
        )
    },
    { 
        key: 'mode', 
        label: t('Entry Mode'), 
        render: (v: string) => (
            <Badge variant="outline" className="capitalize">
                {t(v)}
            </Badge>
        )
    },
    { 
        key: 'is_active', 
        label: t('Status'), 
        render: (v: boolean) => (
            <div className="flex items-center">
                {v ? (
                    <CheckCircle2 className="h-4 w-4 text-green-500 mr-1" />
                ) : (
                    <XCircle className="h-4 w-4 text-red-500 mr-1" />
                )}
                <span className={v ? 'text-green-600' : 'text-red-600'}>
                    {t(v ? 'Active' : 'Inactive')}
                </span>
            </div>
        )
    },
    { key: 'created_at', label: t('Created At'), render: (v: string) => new Date(v).toLocaleDateString() }
  ];

  const actions = [
    { label: t('Edit'), icon: 'Edit', action: 'edit', className: 'text-amber-500' },
    { label: t('Toggle Status'), icon: 'Power', action: 'toggle-status', className: 'text-blue-500' },
    { label: t('Delete'), icon: 'Trash2', action: 'delete', className: 'text-red-500' }
  ];

  return (
    <PageTemplate
      title={t("Incentive / Deduction Master")}
      breadcrumbs={[{ title: t('Dashboard'), href: route('dashboard') }, { title: t('Incentive Types') }]}
      actions={[
        {
          label: t('Add Type'),
          icon: <Plus className="h-4 w-4 mr-2" />,
          onClick: () => { setCurrentItem(null); setFormMode('create'); setIsFormModalOpen(true); }
        }
      ]}
    >
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={types || []}
          onAction={handleAction}
          permissions={permissions}
        />
      </div>

      <CrudFormModal
        isOpen={isFormModalOpen}
        onClose={() => setIsFormModalOpen(false)}
        onSubmit={handleFormSubmit}
        formConfig={{
          fields: [
            { name: 'name', label: t('Type Name'), type: 'text', required: true },
            { 
                name: 'type', 
                label: t('Category'), 
                type: 'select', 
                required: true,
                options: [
                    { label: t('Earning'), value: 'earning' },
                    { label: t('Deduction'), value: 'deduction' }
                ]
            },
            { 
                name: 'mode', 
                label: t('Entry Mode'), 
                type: 'select', 
                required: true,
                options: [
                    { label: t('Amount Wise'), value: 'amount' },
                    { label: t('Day Wise'), value: 'day' }
                ]
            },
            { name: 'is_active', label: t('Active'), type: 'checkbox' }
          ],
          modalSize: 'md'
        }}
        initialData={currentItem}
        title={formMode === 'create' ? t('Add Incentive Type') : t('Edit Incentive Type')}
        mode={formMode}
      />

      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName="type"
      />
    </PageTemplate>
  );
}
