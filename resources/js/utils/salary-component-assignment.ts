export function isPrimaryComponent(comp: { assign_to_all?: boolean; component_group?: string }) {
  return Boolean(comp.assign_to_all || comp.component_group === 'primary');
}

export function primaryComponents<T extends { id: number | string }>(components: T[]): T[] {
  return components.filter((c) => isPrimaryComponent(c as any));
}

export function customComponents<T extends { id: number | string }>(components: T[]): T[] {
  return components.filter((c) => !isPrimaryComponent(c as any));
}

type ComponentLike = { id: number | string; assign_to_all?: boolean; component_group?: string };

/** Custom-group IDs stored on the employee (primary is always applied from master). */
export function storedCustomComponentIds(
  assignedIds: number[] | null | undefined,
  components: ComponentLike[],
): number[] {
  return (assignedIds || [])
    .map(Number)
    .filter((id) => {
      if (id <= 0) {
        return false;
      }
      const comp = components.find((c) => Number(c.id) === id);

      return comp != null && !isPrimaryComponent(comp);
    });
}

/** True when employee has at least one custom component assigned. */
export function hasCustomAssignment(
  assignedIds: number[] | null | undefined,
  components: ComponentLike[] = [],
): boolean {
  if (components.length === 0) {
    return storedCustomComponentIds(assignedIds, []).length > 0;
  }

  return storedCustomComponentIds(assignedIds, components).length > 0;
}

/** Primary group + any stored custom IDs (for display and gross split). */
export function resolveAssignedComponentIds(
  components: ComponentLike[],
  assignedIds: number[] | null | undefined,
): number[] {
  const primaryIds = primaryComponents(components).map((c) => Number(c.id));
  const customIds = storedCustomComponentIds(assignedIds, components);

  if (customIds.length === 0 && (assignedIds || []).map(Number).filter((id) => id > 0).length === 0) {
    return primaryIds;
  }

  return [...primaryIds, ...customIds];
}

export function componentAppliesToEmployee(
  comp: { id: number | string },
  components: ComponentLike[],
  assignedIds: number[] | null | undefined,
): boolean {
  const resolved = resolveAssignedComponentIds(components, assignedIds);

  return resolved.includes(Number(comp.id));
}

export function resolveComponentsForEmployee<T extends ComponentLike>(
  all: T[],
  assignedIds: number[] | null | undefined,
): T[] {
  const resolved = new Set(resolveAssignedComponentIds(all, assignedIds));

  return all.filter((c) => resolved.has(Number(c.id)));
}
