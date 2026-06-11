import type { TFunction } from 'i18next';
import type { NavItem } from '@/types';

export interface NavBuildContext {
    permissions: string[];
    t: TFunction;
    canViewActivityLogs: boolean;
    userRole?: string;
}

export type NavSectionBuilder = (ctx: NavBuildContext) => NavItem[];
