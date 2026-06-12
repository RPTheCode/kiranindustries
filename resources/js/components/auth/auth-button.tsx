import { LoaderCircle } from 'lucide-react';
import { ButtonHTMLAttributes } from 'react';
import { useTranslation } from 'react-i18next';

import { useBrand } from '@/contexts/BrandContext';
import { THEME_COLORS } from '@/hooks/use-appearance';

interface AuthButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    processing?: boolean;
    tabIndex?: number;
    children: React.ReactNode;
}

export default function AuthButton({
    processing = false,
    tabIndex,
    children,
    className = '',
    disabled,
    ...props
}: AuthButtonProps) {
    const { t } = useTranslation();
    const { themeColor, customColor } = useBrand();
    const primaryColor = themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS];

    return (
        <button
            {...props}
            type={props.type || 'submit'}
            className={`inline-flex h-11 w-full items-center justify-center rounded-lg text-sm font-semibold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:shadow-lg active:translate-y-0 disabled:translate-y-0 disabled:cursor-not-allowed disabled:opacity-70 ${className}`}
            tabIndex={tabIndex}
            disabled={processing || disabled}
            aria-busy={processing}
            style={{ backgroundColor: primaryColor }}
        >
            {processing && <LoaderCircle className="mr-2 h-4 w-4 animate-spin" aria-hidden />}
            {processing ? t('Signing in...') : children}
        </button>
    );
}
