import {
    Building2,
    CreditCard,
    DollarSign,
    Gift,
    Images,
    LayoutDashboard,
    Palette,
    Settings2,
    Ticket,
} from 'lucide-react';
import type { TFunction } from 'i18next';
import type { NavItem } from '@/types';

export function buildSuperAdminNavItems(t: TFunction): NavItem[] {
    return [
        {
            title: t('Dashboard'),
            href: route('dashboard'),
            icon: LayoutDashboard,
        },
        {
            title: t('Companies'),
            href: route('companies.index'),
            icon: Building2,
        },
        {
            title: t('Media Library'),
            href: route('media-library'),
            icon: Images,
        },
        {
            title: t('Plans'),
            icon: CreditCard,
            children: [
                {
                    title: t('Plan'),
                    href: route('plans.index'),
                },
                {
                    title: t('Plan Request'),
                    href: route('plan-requests.index'),
                },
                {
                    title: t('Plan Orders'),
                    href: route('plan-orders.index'),
                },
            ],
        },
        {
            title: t('Coupons'),
            href: route('coupons.index'),
            icon: Ticket,
        },
        {
            title: t('Currencies'),
            href: route('currencies.index'),
            icon: DollarSign,
        },
        {
            title: t('Referral Program'),
            href: route('referral.index'),
            icon: Gift,
        },
        {
            title: t('Landing Page'),
            icon: Palette,
            children: [
                {
                    title: t('Landing Page'),
                    href: route('landing-page'),
                },
                {
                    title: t('Custom Pages'),
                    href: route('landing-page.custom-pages.index'),
                },
            ],
        },
        {
            title: t('Settings'),
            href: route('settings'),
            icon: Settings2,
        },
    ];
}
