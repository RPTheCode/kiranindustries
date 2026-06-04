import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { usePage, router } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { CrudTable } from '@/components/CrudTable';
import { CrudFormModal } from '@/components/CrudFormModal';
import { CrudDeleteModal } from '@/components/CrudDeleteModal';
import { toast } from '@/components/custom-toast';
import { useTranslation } from 'react-i18next';

export default function Overtimes() {
  const { t } = useTranslation();
  const { overtimes, auth } = usePage().props as any;
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
    const routeName = formMode === 'create' ? 'hr.overtimes.store' : 'hr.overtimes.update';
    const method = formMode === 'create' ? 'post' : 'put';
    const routeParams = formMode === 'create' ? [] : [currentItem.id];

    router[method](route(routeName, routeParams), formData, {
      onSuccess: () => {
        setIsFormModalOpen(false);
        toast.success(t(formMode === 'create' ? 'Overtime option created' : 'Overtime option updated'));
      },
      onError: (errors) => toast.error(Object.values(errors).join(', '))
    });
  };

  const handleDeleteConfirm = () => {
    router.delete(route('hr.overtimes.destroy', currentItem.id), {
      onSuccess: () => {
        setIsDeleteModalOpen(false);
        toast.success(t('Overtime option deleted'));
      }
    });
  };

  const columns = [
    { key: 'name', label: t('Overtime (Hours)'), sortable: true, render: (v: string) => `${v} ${t('hr')}` },
    { key: 'created_at', label: t('Created At'), render: (v: string) => new Date(v).toLocaleDateString() }
  ];

  const actions = [
    { label: t('Edit'), icon: 'Edit', action: 'edit', className: 'text-amber-500' },
    { label: t('Delete'), icon: 'Trash2', action: 'delete', className: 'text-red-500' }
  ];

  return (
    <PageTemplate
      title={t("Overtime Master")}
      breadcrumbs={[{ title: t('Dashboard'), href: route('dashboard') }, { title: t('Overtimes') }]}
      actions={[
        {
          label: t('Add Overtime'),
          icon: <Plus className="h-4 w-4 mr-2" />,
          onClick: () => { setCurrentItem(null); setFormMode('create'); setIsFormModalOpen(true); }
        }
      ]}
    >
      <div className="bg-white dark:bg-gray-900 rounded-lg shadow overflow-hidden">
        <CrudTable
          columns={columns}
          actions={actions}
          data={overtimes || []}
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
            { 
              name: 'name', 
              label: t('Hours'), 
              type: 'number', 
              required: true, 
              placeholder: 'e.g. 1.5',
              step: '0.5'
            }
          ],
          modalSize: 'md'
        }}
        initialData={currentItem}
        title={formMode === 'create' ? t('Add Overtime') : t('Edit Overtime')}
        mode={formMode}
      />

      <CrudDeleteModal
        isOpen={isDeleteModalOpen}
        onClose={() => setIsDeleteModalOpen(false)}
        onConfirm={handleDeleteConfirm}
        itemName={currentItem?.name || ''}
        entityName="overtime"
      />
    </PageTemplate>
  );
}
