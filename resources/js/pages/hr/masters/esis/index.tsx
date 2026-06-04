import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, FileText } from 'lucide-react';
import { CrudTable } from '@/components/CrudTable';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

export default function Esis() {
  const { t } = useTranslation();
  const { esis, auth } = usePage().props as any;
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
    }
  };

  const handleFormSubmit = (formData: any) => {
    const routeName = formMode === 'create' ? 'hr.esi-masters.store' : 'hr.esi-masters.update';
    const method = formMode === 'create' ? 'post' : 'put';
    const routeParams = formMode === 'create' ? [] : [currentItem.id];

    router[method](route(routeName, routeParams), formData, {
      onSuccess: () => {
        setIsFormModalOpen(false);
        toast.success(t(formMode === 'create' ? 'ESI Scheme created' : 'ESI Scheme updated'));
      },
      onError: (errors) => toast.error(Object.values(errors).join(', '))
    });
  };

  const handleDeleteConfirm = () => {
    router.delete(route('hr.esi-masters.destroy', currentItem.id), {
      onSuccess: () => {
        setIsDeleteModalOpen(false);
        toast.success(t('ESI Scheme deleted'));
      }
    });
  };

  const columns = [
    { key: 'name', label: t('Scheme Name'), sortable: true },
    { key: 'percentage_employee', label: t('Employee %') },
    { key: 'percentage_employer', label: t('Employer %') },
    { key: 'created_at', label: t('Created At'), render: (v: string) => new Date(v).toLocaleDateString() }
  ];

  const actions = [
    { label: t('Edit'), icon: 'Edit', action: 'edit', className: 'text-amber-500' },
    { label: t('Delete'), icon: 'Trash2', action: 'delete', className: 'text-red-500' }
  ];

  return (
    <PageTemplate
      title={t("ESI Master Management")}
      breadcrumbs={[{ title: t('Dashboard'), href: route('dashboard') }, { title: t('ESI Masters') }]}
      actions={[
        {
          label: t('Add ESI Scheme'),
          icon: <Plus className="h-4 w-4 mr-2" />,
          onClick: () => { setCurrentItem(null); setFormMode('create'); setIsFormModalOpen(true); }
        },
        {
          label: t('Download Report'),
          icon: <FileText className="h-4 w-4 mr-2" />,
          variant: 'outline' as const,
          className: 'border-slate-300 text-slate-700 hover:bg-slate-50',
          onClick: () => {
            window.open(route('hr.reports.master_listing', { type: 'PFE', subtype: 'ESI' }), '_blank');
          }
        }
      ]}
    >
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={esis || []}
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
            { name: 'name', label: t('Scheme Name'), type: 'text', required: true },
            { name: 'percentage_employee', label: t('Employee Contribution %'), type: 'number', required: true },
            { name: 'percentage_employer', label: t('Employer Contribution %'), type: 'number', required: true },
            { name: 'description', label: t('Description'), type: 'textarea' }
          ],
          modalSize: 'md'
        }}
        initialData={currentItem}
        title={formMode === 'create' ? t('Add ESI Scheme') : t('Edit ESI Scheme')}
        mode={formMode}
      />

      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName="esi"
      />
    </PageTemplate>
  );
}
