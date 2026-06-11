import { useState, useEffect, useMemo } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { IndeterminateCheckbox } from '@/components/ui/indeterminate-checkbox';
import { Label } from '@/components/ui/label';
import { useTranslation } from 'react-i18next';
import { Search, ChevronDown, ChevronUp, Shield, Layers } from 'lucide-react';

interface Permission {
  id: string | number;
  name: string;
  label: string;
}

interface RolePermissionCheckboxGroupProps {
  permissions: Record<string, any[]>;
  selectedPermissions: any;
  onChange: (permissions: string[]) => void;
}

export function RolePermissionCheckboxGroup({
  permissions,
  selectedPermissions,
  onChange
}: RolePermissionCheckboxGroupProps) {
  const { t } = useTranslation();
  const [selected, setSelected] = useState<string[]>([]);
  const [searchQuery, setSearchQuery] = useState('');
  const [expandedModules, setExpandedModules] = useState<string[]>([]);

  // Filter permissions based on search query
  const legacyPayrollModules = [
    'Payroll Management',
    'payroll_runs',
    'payslips',
    'payroll_adjustments',
    'employee_salaries',
    'employee_advances',
  ];

  const filteredPermissions = useMemo(() => {
    return Object.entries(permissions).reduce((acc, [module, modulePermissions]) => {
      if (legacyPayrollModules.includes(module)) {
        return acc;
      }

      const filtered = modulePermissions.filter(p =>
        p.label.toLowerCase().includes(searchQuery.toLowerCase()) ||
        p.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        module.toLowerCase().includes(searchQuery.toLowerCase())
      );

      if (filtered.length > 0) {
        acc[module] = filtered;
      }
      return acc;
    }, {} as Record<string, any[]>);
  }, [permissions, searchQuery]);

  // Open first module by default if none open, or all if searching
  useEffect(() => {
    const keys = Object.keys(filteredPermissions);
    if (searchQuery && keys.length > 0) {
      setExpandedModules(keys);
    } else if (keys.length > 0 && expandedModules.length === 0) {
      setExpandedModules([keys[0]]);
    }
  }, [filteredPermissions, searchQuery]);

  const toggleModule = (module: string) => {
    if (expandedModules.includes(module)) {
      setExpandedModules(expandedModules.filter(m => m !== module));
    } else {
      setExpandedModules([...expandedModules, module]);
    }
  };

  // Get all permission IDs
  const getAllPermissionIds = (): string[] => {
    const allIds: string[] = [];
    Object.values(filteredPermissions).forEach(group => {
      group.forEach(permission => {
        allIds.push(permission.id.toString());
      });
    });
    return allIds;
  };

  const getModulePermissionIds = (module: string): string[] => {
    return filteredPermissions[module]?.map(permission => permission.id.toString()) || [];
  };

  // Initialize selected permissions
  useEffect(() => {
    if (!selectedPermissions || Object.keys(permissions).length === 0) {
      setSelected([]);
      return;
    }

    try {
      const nameMap: Record<string, string> = {};

      Object.values(permissions).forEach(group => {
        group.forEach(permission => {
          nameMap[permission.name as string] = permission.id.toString();
        });
      });

      let processedPermissions: string[] = [];

      if (Array.isArray(selectedPermissions)) {
        processedPermissions = selectedPermissions.map((p: any) => {
          if (typeof p === 'object' && p !== null) {
            if ('id' in p) return p.id.toString();
            if ('name' in p) return nameMap[p.name] || p.name;
          }
          return nameMap[String(p)] || String(p);
        }).filter(Boolean);
      } else if (typeof selectedPermissions === 'object' && selectedPermissions !== null) {
        if ('permissions' in selectedPermissions && Array.isArray(selectedPermissions.permissions)) {
          processedPermissions = selectedPermissions.permissions.map((p: any) => {
            if (typeof p === 'object' && p !== null) {
              if ('id' in p) return p.id.toString();
              if ('name' in p) return nameMap[p.name] || p.name;
            }
            return nameMap[String(p)] || String(p);
          }).filter(Boolean);
        }
      }

      setSelected(processedPermissions);
    } catch (error) {
      console.error('Error processing permissions:', error);
      setSelected([]);
    }
  }, [selectedPermissions, permissions]);

  const handlePermissionChange = (permissionId: string, checked: boolean) => {
    let newSelected = checked
      ? [...selected, permissionId]
      : selected.filter(id => id !== permissionId);

    if (checked) {
      // Find the permission name to handle mutual exclusivity
      let permName = '';
      for (const group of Object.values(permissions as Record<string, any[]>)) {
        const found = group.find(p => p.id.toString() === permissionId);
        if (found) {
          permName = found.name;
          break;
        }
      }

      if (permName) {
        // Handle mutual exclusivity for Access, Manage Any, Manage Own
        const isManageAny = permName.startsWith('manage-any-');
        const isManageOwn = permName.startsWith('manage-own-');
        const isStandardAccess = permName.startsWith('access-');
        // Handle special cases where 'manage-xxx' acts as 'access-xxx-module'
        const isSpecialAccess = permName === 'manage-attendance-records' || permName === 'manage-attendance-regularizations';
        const isAccess = isStandardAccess || isSpecialAccess;

        if (isAccess || isManageAny || isManageOwn) {
          let baseName = '';
          if (isStandardAccess) baseName = permName.replace('access-', '').replace('-module', '');
          else if (permName === 'manage-attendance-records') baseName = 'attendance-records';
          else if (permName === 'manage-attendance-regularizations') baseName = 'attendance-regularizations';
          else if (isManageAny) baseName = permName.replace('manage-any-', '');
          else if (isManageOwn) baseName = permName.replace('manage-own-', '');

          const toRemove: string[] = [];
          
          if (isManageAny) {
            toRemove.push(`access-${baseName}-module`);
            toRemove.push(`manage-${baseName}`); // Catch the special ones
            toRemove.push(`manage-own-${baseName}`);
          } else if (isManageOwn) {
            toRemove.push(`access-${baseName}-module`);
            toRemove.push(`manage-${baseName}`);
            toRemove.push(`manage-any-${baseName}`);
          } else if (isAccess) {
            toRemove.push(`manage-own-${baseName}`);
            toRemove.push(`manage-any-${baseName}`);
          }

          if (toRemove.length > 0) {
             const idsToRemove: string[] = [];
             for (const group of Object.values(permissions as Record<string, any[]>)) {
               group.forEach(p => {
                 if (toRemove.includes(p.name)) {
                   idsToRemove.push(p.id.toString());
                 }
               });
             }
             newSelected = newSelected.filter(id => !idsToRemove.includes(id));
          }
        }
      }
    }

    setSelected(newSelected);
    updateParent(newSelected);
  };

  const handleMultiplePermissionChange = (permissionIds: string[], checked: boolean) => {
    let newSelected: string[];
    
    if (checked) {
      const permissionsToAdd = permissionIds.filter(id => !selected.includes(id));
      newSelected = [...selected, ...permissionsToAdd];
    } else {
      newSelected = selected.filter(id => !permissionIds.includes(id));
    }
    
    setSelected(newSelected);
    updateParent(newSelected);
  };

  const handleModuleChange = (module: string, checked: boolean) => {
    handleMultiplePermissionChange(getModulePermissionIds(module), checked);
  };

  const handleSelectAll = (checked: boolean) => {
    const newSelected = checked ? getAllPermissionIds() : [];
    setSelected(newSelected);
    updateParent(newSelected);
  };

  const updateParent = (newSelected: string[]) => {
    const idToNameMap: Record<string, string> = {};

    Object.values(permissions).forEach(group => {
      group.forEach(permission => {
        idToNameMap[permission.id.toString()] = permission.name as string;
      });
    });

    const permissionNames = newSelected.map(id => {
      return idToNameMap[id] || id;
    }).filter(name => !!name);

    onChange(permissionNames);
  };

  const isAllSelected = selected.length === getAllPermissionIds().length && getAllPermissionIds().length > 0;

  const isModuleSelected = (module: string): boolean => {
    const modulePermissionIds = getModulePermissionIds(module);
    return modulePermissionIds.every(id => selected.includes(id)) && modulePermissionIds.length > 0;
  };

  const isModuleIndeterminate = (module: string): boolean => {
    const modulePermissionIds = getModulePermissionIds(module);
    const selectedCount = modulePermissionIds.filter(id => selected.includes(id)).length;
    return selectedCount > 0 && selectedCount < modulePermissionIds.length;
  };

  // Helper to categorize permission into matrix buckets
  const categorizePermission = (permissionName: string) => {
    const name = permissionName.toLowerCase();
    if (name.startsWith('view-')) return 'view';
    if (name.startsWith('create-') || name.startsWith('add-')) return 'add';
    if (name.startsWith('edit-') || name.startsWith('update-')) return 'edit';
    if (name.startsWith('delete-') || name.startsWith('remove-')) return 'delete';
    return 'other';
  };

  return (
    <div className="space-y-6 max-w-full">
      {/* Top Bar: Search and Select All */}
      <div className="flex flex-col sm:flex-row justify-between items-center gap-4 bg-white p-4 rounded-xl border shadow-sm sticky top-0 z-10">
        <div className="relative w-full sm:w-1/2 md:w-2/3">
          <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
            <Search className="h-4 w-4 text-gray-400" />
          </div>
          <input
            type="text"
            className="flex h-10 w-full rounded-md border border-gray-300 bg-white pl-9 pr-3 py-2 text-sm shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/20"
            placeholder={t("Search permissions or modules...")}
            value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
          />
        </div>
        
        <div className="flex flex-shrink-0 items-center space-x-3 px-4 py-2 bg-primary/5 text-primary rounded-md border border-primary/20 shadow-sm w-full sm:w-auto justify-center transition-colors hover:bg-primary/10">
          <IndeterminateCheckbox
            id="select-all-permissions"
            checked={isAllSelected}
            onCheckedChange={(checked) => handleSelectAll(checked === true)}
            className="w-5 h-5 text-primary border-primary/20 data-[state=checked]:bg-primary data-[state=checked]:border-primary"
          />
          <Label htmlFor="select-all-permissions" className="font-bold text-sm cursor-pointer select-none">
            {t("Select All")} <span className="opacity-75 font-normal">({selected.length}/{getAllPermissionIds().length})</span>
          </Label>
        </div>
      </div>

      {/* Matrix Layout for Modules */}
      <div className="space-y-4">
        {Object.keys(filteredPermissions).sort((a, b) => {
          const order = [
            'Dashboard',
            'Masters',
            'Employees',
            'Attendance & Bio-Sync',
            'Essl sync',
            'Salary Payroll',
            'Reports',
            'Leave Management',
            'Staff & Security',
            'Mobile App',
            'Media Library',
            'Settings'
          ];
          const indexA = order.indexOf(a);
          const indexB = order.indexOf(b);
          if (indexA === -1 && indexB === -1) return a.localeCompare(b);
          if (indexA === -1) return 1;
          if (indexB === -1) return -1;
          return indexA - indexB;
        }).map(module => {
          const isExpanded = expandedModules.includes(module);
          const modulePermissions = filteredPermissions[module];
          const totalPerms = modulePermissions.length;
          const selectedCount = modulePermissions.filter(p => selected.includes(p.id.toString())).length;

          return (
            <div key={module} className={`border rounded-xl shadow-sm transition-all duration-200 bg-white overflow-hidden ${isExpanded ? 'ring-1 ring-primary/20 border-primary/20' : 'hover:border-gray-300 hover:shadow-md'}`}>
              
              {/* Accordion Header */}
              <div 
                className={`flex flex-col sm:flex-row items-start sm:items-center justify-between p-4 cursor-pointer select-none transition-colors ${isExpanded ? 'bg-primary/5' : 'hover:bg-gray-50'}`}
                onClick={() => toggleModule(module)}
              >
                <div className="flex items-center space-x-3">
                  <div className={`p-2 rounded-lg transition-colors ${isExpanded ? 'bg-primary text-white shadow-sm' : 'bg-primary/10 text-primary'}`}>
                    <Layers className="w-5 h-5" />
                  </div>
                  <div>
                    <h3 className="font-bold text-gray-800 text-lg">{module}</h3>
                    <p className="text-xs text-gray-500 mt-0.5">{selectedCount} {t("of")} {totalPerms} {t("permissions selected")}</p>
                  </div>
                </div>

                <div className="flex items-center space-x-6 mt-3 sm:mt-0 w-full sm:w-auto justify-between sm:justify-end">
                  <div className="flex items-center space-x-2 bg-white px-3 py-1.5 rounded border border-gray-200 hover:border-primary/20 hover:bg-primary/5 transition-colors" onClick={(e) => e.stopPropagation()}>
                    <IndeterminateCheckbox
                      id={`module-cb-${module}`}
                      checked={isModuleSelected(module)}
                      indeterminate={isModuleIndeterminate(module)}
                      onCheckedChange={(checked) => handleModuleChange(module, checked === true)}
                      className="w-4 h-4 text-primary"
                    />
                    <Label htmlFor={`module-cb-${module}`} className="font-semibold text-sm cursor-pointer text-gray-700">
                      {t("Select Entire Module")}
                    </Label>
                  </div>
                  
                  <div className={`p-1.5 rounded-full transition-colors ${isExpanded ? 'bg-primary/10 text-primary' : 'text-gray-400 hover:bg-gray-100'}`}>
                    {isExpanded ? <ChevronUp className="w-5 h-5" /> : <ChevronDown className="w-5 h-5" />}
                  </div>
                </div>
              </div>

              {/* Matrix Content */}
              {isExpanded && (
                <div className="border-t border-gray-200 bg-white">
                  <div className="overflow-x-auto">
                    <table className="w-full text-left text-sm border-collapse min-w-[700px]">
                      <thead>
                        <tr className="bg-gray-50/80 text-gray-600 text-xs uppercase tracking-wider border-b">
                          <th className="py-3 px-6 font-semibold w-1/4 min-w-[180px]">{t("Feature")}</th>
                          <th className="py-3 px-4 font-semibold text-center w-[80px] border-l border-gray-200">{t("All")}</th>
                          <th className="py-3 px-4 font-semibold text-center w-[80px] border-l border-gray-200">{t("View")}</th>
                          <th className="py-3 px-4 font-semibold text-center w-[80px] border-l border-gray-200">{t("Add")}</th>
                          <th className="py-3 px-4 font-semibold text-center w-[80px] border-l border-gray-200">{t("Edit")}</th>
                          <th className="py-3 px-4 font-semibold text-center w-[80px] border-l border-gray-200">{t("Delete")}</th>
                          <th className="py-3 px-6 font-semibold border-l border-gray-200">{t("Other Permissions")}</th>
                        </tr>
                      </thead>
                      <tbody>
                        {(() => {
                          const groupedByEntity: Record<string, any[]> = {};
                          const entityDisplayNames: Record<string, string> = {
                            'deduction-types': 'Deduction Master',
                            'salary-components': 'Salary Component Master',
                            'salary-payroll-employee-salary': 'Employee Salary',
                            'salary-payroll-increment': 'Bulk Salary Increment',
                            'salary-payroll-runs': 'Generate Payroll',
                            'earning-deduction-entry': 'Earning / Deduction',
                            'payroll-settings': 'Payroll Settings',
                          };
                          
                          modulePermissions.forEach(permission => {
                            let entityName = (permission.name as string);
                            
                            const prefixes = [
                                'manage-any-', 'manage-own-', 'manage-', 
                                'view-', 'create-', 'edit-', 'update-', 'delete-', 'remove-',
                                'approve-', 'reject-', 'finalize-', 'toggle-status-', 'download-', 
                                'process-', 'publish-', 'record-', 'request-', 'resolve-',
                                'send-', 'subscribe-', 'trial-', 'upgrade-', 'acknowledge-'
                            ];
                            
                            for (const prefix of prefixes) {
                                if (entityName.startsWith(prefix)) {
                                    entityName = entityName.substring(prefix.length);
                                    break;
                                }
                            }
                            
                            if (['attendance', 'clock-in-out'].includes(entityName)) {
                                entityName = 'attendance-records';
                            }
                            if (entityName.includes('settings')) entityName = 'settings';

                            // Custom display names for Attendance
                            if (entityName === 'attendance-records') {
                                entityName = 'manually-entry';
                            } else if (entityName === 'attendance-regularizations') {
                                entityName = 'mispunch';
                            } else if (entityName === 'bulk-attendance-add' || entityName === 'bulk-attendance') {
                                entityName = 'bulk-attendance';
                            }
                            
                            if (!groupedByEntity[entityName]) {
                                groupedByEntity[entityName] = [];
                            }
                            groupedByEntity[entityName].push(permission);
                          });
                          
                          return Object.entries(groupedByEntity).map(([entity, perms], index, arr) => {
                            // Bucket permissions
                            const buckets: Record<string, any[]> = { view: [], add: [], edit: [], delete: [], other: [] };
                            perms.forEach(p => buckets[categorizePermission(p.name)].push(p));
                            
                            const allIds = perms.map(p => p.id.toString());
                            const isRowAllSelected = allIds.every(id => selected.includes(id)) && allIds.length > 0;
                            const isRowIndeterminate = allIds.some(id => selected.includes(id)) && !isRowAllSelected;

                            const isLast = index === arr.length - 1;

                            // Reusable checkbox renderer
                            const renderCheckboxCell = (permList: any[], isDanger: boolean = false) => {
                              if (permList.length === 0) return <span className="text-gray-300">-</span>;
                              if (permList.length === 1) {
                                const p = permList[0];
                                const danger = isDanger || p.name.includes('manage-all');
                                return (
                                  <div className="flex justify-center" title={p.label}>
                                    <Checkbox
                                      id={`perm-${p.id}`}
                                      checked={selected.includes(p.id.toString()) || selected.includes(p.name)}
                                      onCheckedChange={(checked) => handlePermissionChange(p.id.toString(), checked === true)}
                                      className={`w-4 h-4 ${danger ? 'data-[state=checked]:bg-red-500 data-[state=checked]:border-red-500 border-red-300/50 hover:border-red-500' : ''}`}
                                    />
                                  </div>
                                );
                              }
                              
                              return (
                                <div className="flex flex-col items-center gap-1.5">
                                  {permList.map(p => {
                                    const danger = isDanger || p.name.includes('manage-all');
                                    return (
                                    <div key={p.id} title={p.label}>
                                      <Checkbox
                                        id={`perm-${p.id}`}
                                        checked={selected.includes(p.id.toString()) || selected.includes(p.name)}
                                        onCheckedChange={(checked) => handlePermissionChange(p.id.toString(), checked === true)}
                                        className={`w-4 h-4 ${danger ? 'data-[state=checked]:bg-red-500 data-[state=checked]:border-red-500 border-red-300/50 hover:border-red-500' : ''}`}
                                      />
                                    </div>
                                  )})}
                                </div>
                              );
                            };

                            const displayName = entityDisplayNames[entity] || entity.replace(/-/g, ' ');

                            return (
                              <tr key={entity} className={`hover:bg-gray-50/70 transition-colors ${!isLast ? 'border-b border-gray-100' : ''}`}>
                                {/* Entity Name */}
                                <td className="py-3.5 px-6 font-medium text-gray-800 capitalize flex items-center">
                                  <div className="w-1.5 h-1.5 rounded-full bg-primary-400 mr-2.5"></div>
                                  {displayName}
                                </td>
                                
                                {/* Select All for Row */}
                                <td className="py-3.5 px-4 text-center border-l border-gray-100 bg-gray-50/30">
                                  <IndeterminateCheckbox
                                    id={`row-cb-${entity}`}
                                    checked={isRowAllSelected}
                                    indeterminate={isRowIndeterminate}
                                    onCheckedChange={(checked) => handleMultiplePermissionChange(allIds, checked === true)}
                                    className="w-4.5 h-4.5 mx-auto text-primary border-primary/20"
                                  />
                                </td>

                                {/* View */}
                                <td className="py-3.5 px-4 text-center border-l border-gray-100">
                                  {renderCheckboxCell(buckets.view)}
                                </td>

                                {/* Add */}
                                <td className="py-3.5 px-4 text-center border-l border-gray-100">
                                  {renderCheckboxCell(buckets.add)}
                                </td>

                                {/* Edit */}
                                <td className="py-3.5 px-4 text-center border-l border-gray-100">
                                  {renderCheckboxCell(buckets.edit)}
                                </td>

                                {/* Delete */}
                                <td className="py-3.5 px-4 text-center border-l border-red-50 bg-red-50/10">
                                  {renderCheckboxCell(buckets.delete, true)}
                                </td>

                                {/* Other */}
                                <td className="py-3.5 px-6 border-l border-gray-100">
                                  {buckets.other.length > 0 ? (
                                    <div className="flex flex-wrap gap-2">
                                      {buckets.other.map(p => (
                                        <Label 
                                          key={p.id} 
                                          htmlFor={`perm-${p.id}`}
                                          className="flex items-center gap-1.5 whitespace-nowrap bg-white border border-gray-200 px-2.5 py-1.5 rounded text-xs cursor-pointer hover:border-primary/20 hover:bg-primary/5 transition-colors"
                                        >
                                          <Checkbox 
                                            id={`perm-${p.id}`}
                                            checked={selected.includes(p.id.toString()) || selected.includes(p.name)}
                                            onCheckedChange={(checked) => handlePermissionChange(p.id.toString(), checked === true)}
                                            className="w-3.5 h-3.5 data-[state=checked]:bg-primary data-[state=checked]:border-primary"
                                          />
                                          <span className="font-medium text-gray-700">
                                            {(() => {
                                              const entityDisplayName = entity.replace(/-/g, ' ');
                                              let short = (p.label as string).replace(new RegExp(entityDisplayName, 'i'), '').trim();
                                              if (short === (p.label as string)) {
                                                const singular = entityDisplayName.replace(/s$/, '');
                                                short = short.replace(new RegExp(singular, 'i'), '').trim();
                                              }
                                              if (!short || short.toLowerCase() === 'manage') return 'Access';
                                              return short;
                                            })()}
                                          </span>
                                        </Label>
                                      ))}
                                    </div>
                                  ) : (
                                    <span className="text-gray-300 text-xs italic pl-2">{t("None")}</span>
                                  )}
                                </td>
                              </tr>
                            );
                          });
                        })()}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}
            </div>
          );
        })}
        
        {Object.keys(filteredPermissions).length === 0 && (
          <div className="p-12 text-center border-2 rounded-xl bg-gray-50 border-dashed border-gray-200">
            <Shield className="w-16 h-16 mx-auto mb-4 text-gray-300" />
            <p className="text-xl font-bold text-gray-700 mb-1">{t("No permissions found")}</p>
            <p className="text-gray-500">{t("Try adjusting your search query.")}</p>
          </div>
        )}
      </div>
    </div>
  );
}