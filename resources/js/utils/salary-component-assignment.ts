export function isPrimaryComponent(comp: { assign_to_all?: boolean; component_group?: string }) {
  return Boolean(comp.assign_to_all || comp.component_group === 'primary');
}

export function primaryComponents<T extends { id: number | string }>(components: T[]): T[] {
  return components.filter((c) => isPrimaryComponent(c as any));
}

export function customComponents<T extends { id: number | string }>(components: T[]): T[] {
  return components.filter((c) => !isPrimaryComponent(c as any));
}

/** Empty assignment = primary group default. Non-empty = exact selection only. */
export function resolveAssignedComponentIds(
  components: { id: number | string }[],
  assignedIds: number[] | null | undefined,
): number[] {
  const ids = (assignedIds || []).map(Number).filter((id) => id > 0);
  if (ids.length === 0) {
    return primaryComponents(components).map((c) => Number(c.id));
  }
  return ids;
}

export function hasCustomAssignment(assignedIds: number[] | null | undefined): boolean {
  return (assignedIds || []).map(Number).filter((id) => id > 0).length > 0;
}

export function componentAppliesToEmployee(
  comp: { id: number | string },
  components: { id: number | string }[],
  assignedIds: number[] | null | undefined,
): boolean {
  const resolved = resolveAssignedComponentIds(components, assignedIds);
  return resolved.includes(Number(comp.id));
}

export function resolveComponentsForEmployee<T extends { id: number | string }>(
  all: T[],
  assignedIds: number[] | null | undefined,
): T[] {
  const resolved = new Set(resolveAssignedComponentIds(all, assignedIds));
  return all.filter((c) => resolved.has(Number(c.id)));
}
