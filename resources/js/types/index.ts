import { LucideIcon } from 'lucide-react';

export interface SharedData {
    auth: {
        user: {
            id: number;
            name: string;
            email: string;
            avatar?: string;
            type: string;
        } | null;
        active_branch_id?: number;
        branches?: { id: number; name: string }[];
    };
    flash: {
        success: string | null;
        error: string | null;
    };
    [key: string]: any;
}

export type NavGroupId =
    | 'overview'
    | 'setup'
    | 'workforce'
    | 'attendance'
    | 'payroll'
    | 'salary-payroll'
    | 'leave'
    | 'reports'
    | 'admin'
    | 'other';

export interface NavItem {
    title: string;
    href?: string;
    icon?: any;
    permission?: string;
    children?: NavItem[];
    target?: string;
    external?: boolean;
    defaultOpen?: boolean;
    /** Sidebar section label grouping */
    group?: NavGroupId;
    /** Ziggy route pattern e.g. hr.reports.* */
    routePattern?: string;
    /** Short hint shown in sidebar tooltip */
    description?: string;
    /** Alternate search terms for sidebar filter */
    keywords?: string[];
    badge?: {
        label: string;
        variant?: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost';
    };
}

export interface BreadcrumbItem {
    title: string;
    href?: string;
}

export interface PageAction {
    label: string;
    icon: React.ReactNode;
    variant: 'default' | 'destructive' | 'outline' | 'secondary' | 'ghost' | 'link';
    onClick: () => void;
}