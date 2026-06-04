import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus, FileText } from 'lucide-react';
import { CrudTable } from '@/components/CrudTable';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

export default function Banks() {
  const { t } = useTranslation();
  const { banks, auth } = usePage().props as any;
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
    const routeName = formMode === 'create' ? 'hr.bank-masters.store' : 'hr.bank-masters.update';
    const method = formMode === 'create' ? 'post' : 'put';
    const routeParams = formMode === 'create' ? [] : [currentItem.id];

    router[method](route(routeName, routeParams), formData, {
      onSuccess: () => {
        setIsFormModalOpen(false);
        toast.success(t(formMode === 'create' ? 'Bank created' : 'Bank updated'));
      },
      onError: (errors) => toast.error(Object.values(errors).join(', '))
    });
  };

  const handleDeleteConfirm = () => {
    router.delete(route('hr.bank-masters.destroy', currentItem.id), {
      onSuccess: () => {
        setIsDeleteModalOpen(false);
        toast.success(t('Bank deleted'));
      }
    });
  };

  const columns = [
    { key: 'code', label: t('Bank Code'), sortable: true },
    { key: 'bank_name', label: t('Bank Name'), sortable: true },
    { key: 'branch_name', label: t('Branch Name') },
    { key: 'ifsc_code', label: t('IFSC Code') },
    { key: 'created_at', label: t('Created At'), render: (v: string) => new Date(v).toLocaleDateString() }
  ];

  const actions = [
    { label: t('Edit'), icon: 'Edit', action: 'edit', className: 'text-amber-500' },
    { label: t('Delete'), icon: 'Trash2', action: 'delete', className: 'text-red-500' }
  ];

  return (
    <PageTemplate
      title={t("Bank Master Management")}
      breadcrumbs={[{ title: t('Dashboard'), href: route('dashboard') }, { title: t('Banks') }]}
      actions={[
        {
          label: t('Add Bank'),
          icon: <Plus className="h-4 w-4 mr-2" />,
          onClick: () => { setCurrentItem(null); setFormMode('create'); setIsFormModalOpen(true); }
        },
        {
          label: t('Download Report'),
          icon: <FileText className="h-4 w-4 mr-2" />,
          variant: 'outline' as const,
          className: 'border-slate-300 text-slate-700 hover:bg-slate-50',
          onClick: () => {
            window.open(route('hr.reports.master_listing', { type: 'BNK' }), '_blank');
          }
        }
      ]}
    >
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={banks || []}
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
            { name: 'code', label: t('Bank Code'), type: 'text', required: true },
            { name: 'bank_name', label: t('Bank Name'), type: 'text', required: true },
            { name: 'branch_name', label: t('Branch Name'), type: 'text', required: true },
            { name: 'ifsc_code', label: t('IFSC Code'), type: 'text', required: true }
          ],
          modalSize: 'md'
        }}
        initialData={currentItem}
        title={formMode === 'create' ? t('Add Bank Master') : t('Edit Bank Master')}
        mode={formMode}
      />

      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.bank_name || ''}
        entityName="bank"
      />
    </PageTemplate>
  );
}
