/**
 * CopyToBranchesModal — Reusable modal for copying any entity to branches.
 * Uses var(--theme-color) for consistent theming across all modules.
 *
 * Usage:
 *   <CopyToBranchesModal
 *     open={isCopyModalOpen}
 *     onClose={() => setIsCopyModalOpen(false)}
 *     onConfirm={(branchIds) => handleCopy(branchIds)}
 *     branches={branches}
 *     excludeBranchId={currentItem?.branch_id}   // optional: hide source branch
 *     title="Copy 'General Shift' to Branches"   // optional custom title
 *     isLoading={isCopying}
 *   />
 */

import { useState } from 'react';
import { Copy } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

interface Branch {
  id: number;
  name: string;
}

interface CopyToBranchesModalProps {
  open: boolean;
  onClose: () => void;
  onConfirm: (branchIds: number[]) => void;
  branches: Branch[];
  excludeBranchId?: number | null;
  title?: string;
  description?: string;
  isLoading?: boolean;
}

export function CopyToBranchesModal({
  open,
  onClose,
  onConfirm,
  branches,
  excludeBranchId,
  title,
  description,
  isLoading = false,
}: CopyToBranchesModalProps) {
  const { t } = useTranslation();
  const [selectedBranchIds, setSelectedBranchIds] = useState<number[]>([]);

  const available = excludeBranchId
    ? branches.filter((b) => b.id !== excludeBranchId)
    : branches;

  const allSelected = available.length > 0 && selectedBranchIds.length === available.length;

  const handleToggleAll = () => {
    allSelected
      ? setSelectedBranchIds([])
      : setSelectedBranchIds(available.map((b) => b.id));
  };

  const handleToggle = (id: number) => {
    setSelectedBranchIds((prev) =>
      prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]
    );
  };

  const handleClose = () => {
    if (!isLoading) {
      setSelectedBranchIds([]);
      onClose();
    }
  };

  const handleConfirm = () => {
    if (selectedBranchIds.length > 0) {
      onConfirm(selectedBranchIds);
    }
  };

  return (
    <Dialog open={open} onOpenChange={(o) => !o && handleClose()}>
      <DialogContent className="sm:max-w-[440px] p-0 overflow-hidden rounded-xl border-0 shadow-xl">

        {/* Header — theme color strip */}
        <DialogHeader className="px-5 pt-5 pb-4 border-b border-slate-100">
          <DialogTitle className="flex items-center gap-2 text-base font-semibold" style={{ color: 'var(--theme-color)' }}>
            <span
              className="flex items-center justify-center h-7 w-7 rounded-lg"
              style={{ backgroundColor: 'color-mix(in srgb, var(--theme-color) 12%, transparent)' }}
            >
              <Copy className="h-3.5 w-3.5" style={{ color: 'var(--theme-color)' }} />
            </span>
            {title || t('Copy to Branches')}
          </DialogTitle>
          {description !== undefined ? (
            <p className="text-xs text-slate-500 mt-1 ml-9">{description}</p>
          ) : (
            <p className="text-xs text-slate-500 mt-1 ml-9">
              {t('Select branches to copy to. Existing codes will be skipped.')}
            </p>
          )}
        </DialogHeader>

        {/* Branch list */}
        <div className="px-5 py-4 max-h-72 overflow-y-auto space-y-1.5
          [&::-webkit-scrollbar]:w-1.5
          [&::-webkit-scrollbar-track]:bg-slate-100
          [&::-webkit-scrollbar-thumb]:bg-slate-300
          [&::-webkit-scrollbar-thumb]:rounded-full">

          {available.length === 0 ? (
            <p className="text-sm text-slate-400 text-center py-6 italic">
              {t('No other branches available.')}
            </p>
          ) : (
            <>
              {/* Select All row */}
              <label
                className="flex items-center gap-3 px-3 py-2 rounded-lg cursor-pointer border transition-all duration-150 select-none"
                style={{
                  backgroundColor: allSelected
                    ? 'color-mix(in srgb, var(--theme-color) 8%, transparent)'
                    : 'color-mix(in srgb, var(--theme-color) 4%, transparent)',
                  borderColor: allSelected
                    ? 'color-mix(in srgb, var(--theme-color) 35%, transparent)'
                    : 'color-mix(in srgb, var(--theme-color) 20%, transparent)',
                }}
              >
                <input
                  type="checkbox"
                  className="h-4 w-4 rounded cursor-pointer accent-[var(--theme-color)]"
                  checked={allSelected}
                  onChange={handleToggleAll}
                />
                <span className="text-sm font-semibold" style={{ color: 'var(--theme-color)' }}>
                  {allSelected ? t('Deselect All') : t('Select All')}
                </span>
                {selectedBranchIds.length > 0 && (
                  <span
                    className="ml-auto text-[11px] font-bold px-2 py-0.5 rounded-full"
                    style={{
                      backgroundColor: 'color-mix(in srgb, var(--theme-color) 15%, transparent)',
                      color: 'var(--theme-color)',
                    }}
                  >
                    {selectedBranchIds.length} {t('selected')}
                  </span>
                )}
              </label>

              {/* Branch rows */}
              {available.map((branch) => {
                const checked = selectedBranchIds.includes(branch.id);
                return (
                  <label
                    key={branch.id}
                    className="flex items-center gap-3 px-3 py-2 rounded-lg cursor-pointer border transition-all duration-150 select-none"
                    style={{
                      backgroundColor: checked
                        ? 'color-mix(in srgb, var(--theme-color) 6%, transparent)'
                        : 'white',
                      borderColor: checked
                        ? 'color-mix(in srgb, var(--theme-color) 30%, transparent)'
                        : '#e2e8f0',
                    }}
                  >
                    <input
                      type="checkbox"
                      className="h-4 w-4 rounded cursor-pointer accent-[var(--theme-color)]"
                      checked={checked}
                      onChange={() => handleToggle(branch.id)}
                    />
                    <span className={cn('text-sm font-medium', checked ? 'text-slate-800' : 'text-slate-600')}>
                      {branch.name}
                    </span>
                    {checked && (
                      <span className="ml-auto">
                        <svg className="h-4 w-4" style={{ color: 'var(--theme-color)' }} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2.5}>
                          <path strokeLinecap="round" strokeLinejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                      </span>
                    )}
                  </label>
                );
              })}
            </>
          )}
        </div>

        {/* Footer */}
        <DialogFooter className="px-5 py-4 border-t border-slate-100 flex gap-2 sm:justify-end">
          <Button
            variant="outline"
            onClick={handleClose}
            disabled={isLoading}
            className="text-slate-600 border-slate-300 hover:bg-slate-50"
          >
            {t('Cancel')}
          </Button>
          <Button
            onClick={handleConfirm}
            disabled={selectedBranchIds.length === 0 || isLoading}
            className="text-white border-0 gap-1.5 transition-opacity"
            style={{ backgroundColor: 'var(--theme-color)' }}
          >
            <Copy className="h-3.5 w-3.5" />
            {isLoading
              ? t('Copying...')
              : t('Copy to {{count}} Branch(es)', { count: selectedBranchIds.length || 0 })}
          </Button>
        </DialogFooter>

      </DialogContent>
    </Dialog>
  );
}
