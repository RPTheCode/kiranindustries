import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    AlertTriangle,
    Award,
    Banknote,
    BarChart3,
    Briefcase,
    Building2,
    Calendar,
    CalendarClock,
    CalendarDays,
    CalendarOff,
    CircleDot,
    ClipboardList,
    Clock,
    Coins,
    CreditCard,
    Factory,
    FileBarChart,
    FileCheck,
    FileSpreadsheet,
    FileText,
    FolderTree,
    Gift,
    GraduationCap,
    Image,
    KeyRound,
    Landmark,
    Layers,
    LayoutGrid,
    LogIn,
    Package,
    Receipt,
    RefreshCw,
    Settings,
    Shield,
    ShieldCheck,
    SlidersHorizontal,
    Tag,
    Timer,
    TrendingUp,
    UserCog,
    Users,
    Wallet,
    Wrench,
} from 'lucide-react';
import type { NavItem } from '@/types';
import { normalizeNavPath } from '@/lib/nav-utils';

/** Match sidebar child links to icons by URL path segment. */
const PATH_ICON_RULES: { test: (path: string) => boolean; icon: LucideIcon }[] = [
    { test: (p) => p === '/dashboard' || p.endsWith('/dashboard'), icon: LayoutGrid },
    { test: (p) => p.includes('/branches'), icon: Building2 },
    { test: (p) => p.includes('week-off'), icon: CalendarOff },
    { test: (p) => p.includes('/shifts'), icon: Clock },
    { test: (p) => p.includes('/departments'), icon: FolderTree },
    { test: (p) => p.includes('/designations'), icon: Briefcase },
    { test: (p) => p.includes('/skills'), icon: Wrench },
    { test: (p) => p.includes('/sections'), icon: Layers },
    { test: (p) => p.includes('/categories'), icon: Tag },
    { test: (p) => p.includes('bank-master'), icon: Landmark },
    { test: (p) => p.includes('resign-reason'), icon: LogIn },
    { test: (p) => p.includes('/overtimes'), icon: Timer },
    { test: (p) => p.includes('material-item'), icon: Package },
    { test: (p) => p.includes('document-type'), icon: FileText },
    { test: (p) => p.includes('salary-component'), icon: Coins },
    { test: (p) => p.includes('/employees') && !p.includes('employee-'), icon: Users },
    { test: (p) => p.includes('attendance-module'), icon: ClipboardList },
    { test: (p) => p.includes('/mispunch'), icon: AlertTriangle },
    { test: (p) => p.includes('production-attendance'), icon: Factory },
    { test: (p) => p.includes('essl-sync'), icon: RefreshCw },
    { test: (p) => p.includes('employee-salar') || p.includes('employee-salary'), icon: Wallet },
    { test: (p) => p.includes('salary-increment'), icon: TrendingUp },
    { test: (p) => p.includes('earning-deduction'), icon: Banknote },
    { test: (p) => p.includes('monthly-incentive'), icon: Banknote },
    { test: (p) => p.includes('deduction-type'), icon: Receipt },
    { test: (p) => p.includes('payroll/generate') || p.includes('payroll-run'), icon: Receipt },
    { test: (p) => p.includes('/payslips'), icon: FileCheck },
    { test: (p) => p === '/recruitment' || p.endsWith('/recruitment'), icon: Briefcase },
    { test: (p) => p.includes('employee-advance'), icon: CreditCard },
    { test: (p) => p.includes('payroll-setting'), icon: SlidersHorizontal },
    { test: (p) => p.includes('/reports/'), icon: FileBarChart },
    { test: (p) => p.includes('leave-type'), icon: CalendarDays },
    { test: (p) => p.includes('leave-polic'), icon: FileSpreadsheet },
    { test: (p) => p.includes('leave-application'), icon: CalendarClock },
    { test: (p) => p.includes('leave-balance'), icon: Calendar },
    { test: (p) => p.includes('/roles'), icon: Shield },
    { test: (p) => p.includes('/permissions'), icon: KeyRound },
    { test: (p) => p.includes('/users') && !p.includes('employee'), icon: UserCog },
    { test: (p) => p.includes('media-library'), icon: Image },
    { test: (p) => p.includes('activity-log'), icon: Activity },
    { test: (p) => p.includes('/settings'), icon: Settings },
    { test: (p) => p.includes('/companies'), icon: Building2 },
    { test: (p) => p.includes('/plans'), icon: CreditCard },
    { test: (p) => p.includes('plan-request'), icon: ClipboardList },
    { test: (p) => p.includes('plan-order'), icon: Receipt },
    { test: (p) => p.includes('/coupons'), icon: Gift },
    { test: (p) => p.includes('/currencies'), icon: Coins },
    { test: (p) => p.includes('/referral'), icon: Gift },
    { test: (p) => p.includes('landing-page'), icon: LayoutGrid },
    { test: (p) => p.includes('/awards'), icon: Award },
    { test: (p) => p.includes('/promotions'), icon: GraduationCap },
    { test: (p) => p.includes('performance'), icon: BarChart3 },
    { test: (p) => p.includes('/holidays'), icon: CalendarDays },
    { test: (p) => p.includes('/training'), icon: GraduationCap },
    { test: (p) => p.includes('/assets'), icon: Package },
];

export function resolveNavIcon(item: NavItem): LucideIcon {
    if (item.icon) {
        return item.icon;
    }

    if (item.href) {
        const path = normalizeNavPath(item.href);
        const rule = PATH_ICON_RULES.find((r) => r.test(path));
        if (rule) {
            return rule.icon;
        }
    }

    return CircleDot;
}
