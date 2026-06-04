import { PageCrudWrapper } from '@/components/PageCrudWrapper';
import { rolesConfig } from '@/config/crud/roles';
import { PermissionBadges } from '@/components/PermissionBadges';
import { useEffect, useState } from 'react';
import { usePage, router } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';
import { PlusIcon } from 'lucide-react';

export default function RolesPage() {
  const { t } = useTranslation();
  const { permissions, flash, auth } = usePage().props as any;
  const [config, setConfig] = useState(rolesConfig);
  
  useEffect(() => {
    if (permissions) {
      setConfig({
        ...rolesConfig,
        table: {
          ...rolesConfig.table,
          columns: [
            ...rolesConfig.table.columns,
            {
              key: 'permissions',
              label: t('Permissions'),
              render: (value, row) => <PermissionBadges permissions={value || []} />
            }
          ]
        }
      });
    }
  }, [permissions, t]);

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('User Management'), href: route('roles.index') },
    { title: t('Roles') }
  ];

  const handleCustomAction = (action: string, item: any) => {
    if (action === 'edit') {
      router.get(route('roles.edit', item.id));
      return true; // handled
    }
    return false; // let PageCrudWrapper handle others (like delete)
  };

  const buttons = [
    {
      label: t('Add New Role'),
      icon: <PlusIcon className="h-4 w-4" />,
      onClick: () => router.get(route('roles.create')),
      showAddButton: false // Hide the default one
    }
  ];

  return (
    <PageCrudWrapper 
      config={config} 
      url="/roles" 
      breadcrumbs={breadcrumbs}
      onCustomAction={handleCustomAction}
      buttons={buttons}
    />
  );
}