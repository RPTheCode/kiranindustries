import { useState } from 'react';
import { PageTemplate } from '@/components/page-template';
import { useTranslation } from 'react-i18next';
import { router, usePage } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import { RolePermissionCheckboxGroup } from '@/components/RolePermissionCheckboxGroup';
import { toast } from '@/components/custom-toast';
import { Save, ArrowLeft, ShieldAlert } from 'lucide-react';

export default function EditRole() {
  const { t } = useTranslation();
  const { role, permissions, auth } = usePage().props as any;
  
  const [formData, setFormData] = useState({
    label: role.label || '',
    description: role.description || '',
    permissions: (role.permissions || []).map((p: any) => typeof p === 'object' ? p.name : p)
  });

  const [isSubmitting, setIsSubmitting] = useState(false);

  const breadcrumbs = [
    { title: t('Dashboard'), href: route('dashboard') },
    { title: t('User Management'), href: route('roles.index') },
    { title: t('Roles'), href: route('roles.index') },
    { title: t('Edit Role') }
  ];

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    toast.loading(t('Updating Role...'));

    router.put(route('roles.update', role.id), formData, {
      onSuccess: (page) => {
        setIsSubmitting(false);
        toast.dismiss();
        if (page.props.flash?.success) {
          toast.success(t(page.props.flash.success));
        }
      },
      onError: (errors) => {
        setIsSubmitting(false);
        toast.dismiss();
        if (typeof errors === 'string') {
          toast.error(t(errors));
        } else {
          toast.error(t('Failed to update role: {{errors}}', { errors: Object.values(errors).join(', ') }));
        }
      }
    });
  };

  const handleMakeReadOnly = () => {
    const readOnlyPerms: string[] = [];
    Object.values(permissions).flat().forEach((p: any) => {
      if (p.name.startsWith('access-') || p.name.startsWith('view-')) {
        readOnlyPerms.push(p.name);
      }
    });
    setFormData({ ...formData, permissions: readOnlyPerms });
    toast.success(t('Applied Read-Only permissions!'));
  };

  const isSystemRole = !role.is_editable;

  return (
    <PageTemplate
      title={`${t("Edit Role")}: ${role.label}`}
      breadcrumbs={breadcrumbs}
      actions={[
        {
          label: t('Back to Roles'),
          icon: <ArrowLeft className="h-4 w-4" />,
          variant: 'outline',
          onClick: () => router.get(route('roles.index'))
        }
      ]}
    >
      <form onSubmit={handleSubmit} className="space-y-8">
        
        {isSystemRole && (
          <div className="bg-amber-50 border-l-4 border-amber-500 p-4 rounded-r-xl flex items-start space-x-3">
            <ShieldAlert className="w-5 h-5 text-amber-600 mt-0.5" />
            <div>
              <h3 className="text-amber-800 font-semibold">{t("System Role")}</h3>
              <p className="text-amber-700 text-sm mt-1">
                {t("This is a system role. Its name cannot be changed, but you can update description and permissions below.")}
              </p>
            </div>
          </div>
        )}

        {/* Role Details Card */}
        <div className="bg-white rounded-xl shadow-sm border p-6">
          <h2 className="text-xl font-semibold mb-6 border-b pb-2">{t("Role Details")}</h2>
          
          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-3">
              <Label htmlFor="label" className="text-sm font-medium">{t("Role Name")} {isSystemRole ? '' : <span className="text-red-500">*</span>}</Label>
              <Input
                id="label"
                value={formData.label}
                onChange={(e) => setFormData({ ...formData, label: e.target.value })}
                placeholder={t("e.g. Senior Manager")}
                required
                disabled={isSystemRole}
                className={`max-w-md ${isSystemRole ? 'bg-gray-100 cursor-not-allowed text-gray-500 font-medium' : ''}`}
              />
            </div>

            <div className="space-y-3">
              <Label htmlFor="description" className="text-sm font-medium">{t("Description")}</Label>
              <Textarea
                id="description"
                value={formData.description}
                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                placeholder={t("Brief description of this role")}
                className="max-w-md resize-none"
                rows={3}
              />
            </div>
          </div>
        </div>

        {/* Permissions Card */}
        <div className="bg-white rounded-xl shadow-sm border p-6">
          <div className="mb-6 border-b pb-4 flex justify-between items-start">
            <div>
              <h2 className="text-xl font-semibold text-gray-800">{t("Assign Permissions")}</h2>
              <p className="text-sm text-gray-500 mt-1">
                {t("Select permissions for this role. You can select all permissions at once or manage them by module.")}
                {auth.user?.type !== 'superadmin' && (
                  <span className="block mt-1 text-amber-600 font-medium">
                    {t("Note: Only permissions for modules available to your tier are shown.")}
                  </span>
                )}
              </p>
            </div>
            <Button type="button" variant="outline" onClick={handleMakeReadOnly} className="bg-amber-50 text-amber-700 hover:bg-amber-100 border-amber-200">
              {t('Make Read-Only')}
            </Button>
          </div>

          <RolePermissionCheckboxGroup
            permissions={permissions}
            selectedPermissions={formData.permissions}
            onChange={(selected) => setFormData({ ...formData, permissions: selected })}
          />
        </div>

        {/* Submit Actions */}
        <div className="flex justify-end space-x-4 bg-gray-50 p-4 rounded-xl border items-center shadow-inner sticky bottom-4 z-50">
          <Button 
            type="button" 
            variant="outline" 
            onClick={() => router.get(route('roles.index'))}
            disabled={isSubmitting}
            className="w-32 bg-white hover:bg-gray-100"
          >
            {t("Cancel")}
          </Button>
          <Button 
            type="submit" 
            disabled={isSubmitting}
            className="w-40 bg-primary hover:bg-primary shadow-md"
          >
            <Save className="w-4 h-4 mr-2" />
            {t("Update Role")}
          </Button>
        </div>

      </form>
    </PageTemplate>
  );
}
