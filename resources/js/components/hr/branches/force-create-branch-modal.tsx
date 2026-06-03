import { useState } from 'react';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/react';
import { useTranslation } from 'react-i18next';

export function ForceCreateBranchModal({ isOpen }: { isOpen: boolean }) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors } = useForm({
        name: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('hr.branches.store'), {
            onSuccess: () => {
                // The modal will close automatically because the page will reload 
                // and the 'must_create_branch' prop will become false.
            },
        });
    };

    return (
        <Dialog open={isOpen} onOpenChange={() => { }} modal={true}>
            <DialogContent
                className="sm:max-w-md"
                onInteractOutside={(e) => e.preventDefault()}
                onEscapeKeyDown={(e) => e.preventDefault()}
                hideClose={true}
            >
                <DialogHeader>
                    <DialogTitle>{t('Create Your First Branch')}</DialogTitle>
                    <DialogDescription>
                        {t('To get started, you must create at least one branch for your company. This will be your primary branch.')}
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="name">{t('Branch Name')} <span className="text-red-500">*</span></Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                            className={errors.name ? 'border-red-500' : ''}
                            placeholder={t('e.g. Head Office')}
                            autoFocus
                        />
                        {errors.name && <p className="text-xs text-red-500">{errors.name}</p>}
                    </div>

                    <DialogFooter>
                        <Button type="submit" disabled={processing} className="w-full">
                            {processing ? t('Creating...') : t('Create Branch')}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
