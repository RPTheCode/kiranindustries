import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import { Filter, Search, List, LayoutGrid, X } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { Combobox } from '@/components/ui/combobox';

interface FilterOption {
  name: string;
  label: string;
  type: 'select' | 'date' | 'combobox';
  options?: { value: string; label: string }[];
  value: string | Date | undefined;
  onChange: (value: any) => void;
}

interface SearchAndFilterBarProps {
  searchTerm: string;
  onSearchChange: (value: string) => void;
  onSearch: (e: React.FormEvent) => void;
  onSearchClear?: () => void;
  filters?: FilterOption[];
  showFilters: boolean;
  setShowFilters: (show: boolean) => void;
  hasActiveFilters: () => boolean;
  activeFilterCount: () => number;
  onResetFilters: () => void;
  onApplyFilters?: () => void;
  perPageOptions?: number[];
  currentPerPage: string;
  onPerPageChange: (value: string) => void;
  extraActions?: React.ReactNode;
  // View toggle props
  showViewToggle?: boolean;
  activeView?: 'list' | 'grid';
  onViewChange?: (view: 'list' | 'grid') => void;
}

export function SearchAndFilterBar({
  searchTerm,
  onSearchChange,
  onSearch,
  onSearchClear,
  filters = [],
  showFilters,
  setShowFilters,
  hasActiveFilters,
  activeFilterCount,
  onResetFilters,
  onApplyFilters,
  perPageOptions = [10, 25, 50, 100],
  currentPerPage,
  onPerPageChange,
  extraActions,
  // View toggle props
  showViewToggle = false,
  activeView = 'list',
  onViewChange,
}: SearchAndFilterBarProps) {
  const { t } = useTranslation();

  return (
    <div className="w-full">
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-2">
          <form onSubmit={onSearch} className="flex gap-2">
            <div className="relative w-64">
              <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
              <Input
                placeholder={t("Search...")}
                value={searchTerm}
                onChange={(e) => onSearchChange(e.target.value)}
                className="w-full pl-9 pr-8"
              />
              {searchTerm && (
                <button
                  type="button"
                  onClick={() => {
                    onSearchChange('');
                    if (onSearchClear) onSearchClear();
                  }}
                  className="absolute right-2.5 top-2.5 text-muted-foreground hover:text-foreground"
                >
                  <X className="h-4 w-4" />
                </button>
              )}
            </div>
            <Button type="submit" size="sm">
              <Search className="h-4 w-4 mr-1.5" />
              {t("Search")}
            </Button>
          </form>

          {filters.length > 0 && (
            <div className="ml-2 flex items-center gap-2">
              <Button
                variant={hasActiveFilters() ? "default" : "outline"}
                size="sm"
                className="h-8 px-2 py-1"
                onClick={() => setShowFilters(!showFilters)}
              >
                <Filter className="h-3.5 w-3.5 mr-1.5" />
                {showFilters ? t('Hide Filters') : t('Filters')}
                {hasActiveFilters() && (
                  <span className="ml-1 bg-primary-foreground text-primary rounded-full w-5 h-5 flex items-center justify-center text-xs">
                    {activeFilterCount()}
                  </span>
                )}
              </Button>
              {extraActions}
            </div>
          )}
        </div>

        <div className="flex items-center gap-2">
          {showViewToggle && onViewChange && (
            <div className="border rounded-md p-0.5 mr-2">
              <Button
                size="sm"
                variant={activeView === 'list' ? "default" : "ghost"}
                className="h-7 px-2"
                onClick={() => onViewChange('list')}
              >
                <List className="h-4 w-4" />
              </Button>
              <Button
                size="sm"
                variant={activeView === 'grid' ? "default" : "ghost"}
                className="h-7 px-2"
                onClick={() => onViewChange('grid')}
              >
                <LayoutGrid className="h-4 w-4" />
              </Button>
            </div>
          )}

          <Label className="text-xs text-muted-foreground">{t("Per Page:")}</Label>
          <Select
            value={currentPerPage}
            onValueChange={onPerPageChange}
          >
            <SelectTrigger className="w-16 h-8">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {perPageOptions.map(option => (
                <SelectItem key={option} value={option.toString()}>{option}</SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {showFilters && filters.length > 0 && (
        <div className="w-full mt-3 p-4 bg-gray-50 dark:bg-gray-800 border dark:border-gray-700 rounded-md">
          <div className="flex flex-wrap gap-4 items-end">
            {filters.map((filter) => (
              <div key={filter.name} className="flex flex-col space-y-2">
                <Label>{filter.label}</Label>
                {filter.type === 'select' && filter.options && (
                  <Combobox
                    options={filter.options}
                    value={filter.value as string}
                    onChange={filter.onChange}
                    placeholder={t(`All ${filter.label}`)}
                    className="w-40"
                  />
                )}
                {filter.type === 'date' && (
                  <DatePicker
                    selected={filter.value as Date | undefined}
                    onSelect={filter.onChange}
                    onChange={filter.onChange}
                  />
                )}
                {filter.type === 'combobox' && filter.options && (
                  <Combobox
                    options={filter.options}
                    value={filter.value as string}
                    onChange={filter.onChange}
                    placeholder={t(`All ${filter.label}`)}
                    className="w-40"
                  />
                )}
              </div>
            ))}

            <div className="flex gap-2">
              {onApplyFilters && (
                <Button
                  variant="default"
                  size="sm"
                  className="h-9"
                  onClick={onApplyFilters}
                >
                  {t("Apply Filters")}
                </Button>
              )}

              <Button
                variant="outline"
                size="sm"
                className="h-9"
                onClick={onResetFilters}
                disabled={!hasActiveFilters()}
              >
                {t("Reset Filters")}
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}