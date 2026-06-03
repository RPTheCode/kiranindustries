import { useForm, router, usePage } from '@inertiajs/react';
import { Mail, Lock, Eye, EyeOff, AlertCircle } from 'lucide-react';
import { FormEventHandler, useState, useEffect, useMemo } from 'react';

import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from 'react-i18next';
import AuthLayout from '@/layouts/auth-layout';
import AuthButton from '@/components/auth/auth-button';
import Recaptcha from '@/components/recaptcha';
import { useBrand } from '@/contexts/BrandContext';
import { THEME_COLORS } from '@/hooks/use-appearance';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type LoginForm = {
    email: string;
    password: string;
    remember: boolean;
    recaptcha_token?: string;
};

interface Business {
    id: number;
    name: string;
    slug: string;
    business_type: string;
}

interface LoginProps {
    status?: string;
    canResetPassword: boolean;
    demoBusinesses?: Business[];
}

export default function Login({ status, canResetPassword, demoBusinesses = [] }: LoginProps) {
    const { t } = useTranslation();
    const { themeColor, customColor } = useBrand();
    const primaryColor = themeColor === 'custom' ? customColor : THEME_COLORS[themeColor as keyof typeof THEME_COLORS];
    const [showPassword, setShowPassword] = useState(false);
    const [clientError, setClientError] = useState<string | null>(null);
    const { props } = usePage();
    const globalSettings = (props as { globalSettings?: Record<string, unknown> }).globalSettings;
    const isSaas = globalSettings?.is_saas;
    const isDemo = globalSettings?.is_demo;
    const recaptchaEnabled =
        globalSettings?.recaptchaEnabled === true ||
        globalSettings?.recaptchaEnabled === 'true' ||
        globalSettings?.recaptchaEnabled === 1 ||
        globalSettings?.recaptchaEnabled === '1';

    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
        recaptcha_token: '',
    });

    useEffect(() => {
        if (isDemo) {
            setData({
                email: 'company@example.com',
                password: 'password',
                remember: false,
                recaptcha_token: data.recaptcha_token ?? '',
            });
        }
    }, [isDemo]);

    const bannerError = useMemo(() => {
        if (clientError) {
            return clientError;
        }
        if (errors.login) {
            return errors.login;
        }
        if (errors.email && !errors.password) {
            return errors.email;
        }
        return null;
    }, [clientError, errors.login, errors.email, errors.password]);

    const emailFieldError = errors.email && errors.password ? undefined : errors.email;
    const passwordFieldError = errors.password;
    const hasCredentialError = Boolean(errors.login || (errors.email && errors.password));

    const inputErrorClass = (hasError: boolean) =>
        cn(
            'pl-10 w-full border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-lg transition-all duration-200',
            hasError && 'border-red-500 focus-visible:ring-red-500/30 dark:border-red-500'
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        setClientError(null);
        clearErrors();

        const email = data.email.trim();
        const password = data.password;

        if (!email) {
            setClientError(t('Please enter your email address.'));
            return;
        }

        if (!password) {
            setClientError(t('Please enter your password.'));
            return;
        }

        if (recaptchaEnabled && !data.recaptcha_token) {
            setClientError(t('Please complete the security verification (reCAPTCHA).'));
            return;
        }

        setData({
            email,
            password,
            remember: data.remember,
            recaptcha_token: data.recaptcha_token,
        });

        post(route('login'), {
            preserveScroll: true,
            onFinish: () => reset('password'),
            onError: () => reset('password'),
        });
    };

    const demoLogin = (email: string) => {
        setClientError(null);
        clearErrors();
        router.post(route('login'), {
            email,
            password: 'password',
            remember: false,
            recaptcha_token: data.recaptcha_token || '',
        }, {
            preserveScroll: true,
            onError: () => reset('password'),
        });
    };

    return (
        <AuthLayout
            title={t('Log in to your account')}
            description={t('Enter your credentials to access your account')}
            status={status}
        >
            <form className="space-y-5" onSubmit={submit} noValidate>
                {bannerError && (
                    <div
                        role="alert"
                        className="flex items-start gap-2 rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300"
                    >
                        <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{bannerError}</span>
                    </div>
                )}

                <div className="space-y-4">
                    <div className="relative">
                        <Label htmlFor="email" className="text-gray-700 dark:text-gray-300 font-medium mb-1 block">
                            {t('Email address')}
                        </Label>
                        <div className="relative">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Mail className={cn('h-5 w-5', emailFieldError || hasCredentialError ? 'text-red-400' : 'text-gray-400')} />
                            </div>
                            <Input
                                id="email"
                                type="email"
                                autoFocus
                                tabIndex={1}
                                autoComplete="email"
                                value={data.email}
                                onChange={(e) => {
                                    setData('email', e.target.value);
                                    if (clientError) setClientError(null);
                                }}
                                placeholder="email@example.com"
                                className={inputErrorClass(Boolean(emailFieldError || hasCredentialError))}
                                style={{ '--tw-ring-color': primaryColor } as React.CSSProperties}
                                aria-invalid={Boolean(emailFieldError || hasCredentialError)}
                            />
                        </div>
                        <InputError message={emailFieldError} className="mt-1.5" />
                    </div>

                    <div>
                        <div className="flex items-center justify-between mb-1">
                            <Label htmlFor="password" className="text-gray-700 dark:text-gray-300 font-medium">
                                {t('Password')}
                            </Label>
                            {canResetPassword && (
                                <TextLink
                                    href={route('password.request')}
                                    className="text-xs font-medium transition-colors duration-200"
                                    style={{ color: primaryColor }}
                                    tabIndex={5}
                                >
                                    {t('Forgot password?')}
                                </TextLink>
                            )}
                        </div>
                        <div className="relative">
                            <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <Lock className={cn('h-5 w-5', passwordFieldError || hasCredentialError ? 'text-red-400' : 'text-gray-400')} />
                            </div>
                            <Input
                                id="password"
                                type={showPassword ? 'text' : 'password'}
                                tabIndex={2}
                                autoComplete="current-password"
                                value={data.password}
                                onChange={(e) => {
                                    setData('password', e.target.value);
                                    if (clientError) setClientError(null);
                                }}
                                placeholder="••••••••"
                                className={cn(inputErrorClass(Boolean(passwordFieldError || hasCredentialError)), 'pr-10')}
                                style={{ '--tw-ring-color': primaryColor } as React.CSSProperties}
                                aria-invalid={Boolean(passwordFieldError || hasCredentialError)}
                            />
                            <button
                                type="button"
                                onClick={() => setShowPassword(!showPassword)}
                                className="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 focus:outline-none"
                                tabIndex={-1}
                                aria-label={showPassword ? t('Hide password') : t('Show password')}
                            >
                                {showPassword ? <EyeOff className="h-5 w-5" /> : <Eye className="h-5 w-5" />}
                            </button>
                        </div>
                        <InputError message={passwordFieldError} className="mt-1.5" />
                    </div>

                    <div className="flex items-center">
                        <Checkbox
                            id="remember"
                            name="remember"
                            checked={data.remember}
                            onCheckedChange={(checked) => setData('remember', checked === true)}
                            tabIndex={3}
                            className="border-gray-300 rounded"
                            style={{ '--tw-ring-color': primaryColor, color: primaryColor } as React.CSSProperties}
                        />
                        <Label htmlFor="remember" className="ml-2 text-gray-600 dark:text-gray-400 cursor-pointer">
                            {t('Remember me')}
                        </Label>
                    </div>
                </div>

                <Recaptcha
                    onVerify={(token) => {
                        setData('recaptcha_token', token);
                        if (clientError?.includes('reCAPTCHA') || clientError?.includes('security')) {
                            setClientError(null);
                        }
                    }}
                    onExpired={() => setData('recaptcha_token', '')}
                    onError={() => {
                        setData('recaptcha_token', '');
                        setClientError(t('Security verification failed. Please refresh and try again.'));
                    }}
                />
                <InputError message={errors.recaptcha_token} className="mt-1" />

                <AuthButton tabIndex={4} processing={processing}>
                    {t('Log in')}
                </AuthButton>

                {isDemo && (
                    <div className="mt-6">
                        <div className="border-t border-gray-200 dark:border-gray-700 pt-5">
                            <h3 className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-4 text-center">
                                Demo Quick Access
                            </h3>

                            {isSaas ? (
                                <div className="flex flex-col space-y-3">
                                    <div className="flex flex-col sm:flex-row gap-2 sm:gap-3">
                                        <Button
                                            type="button"
                                            onClick={() => demoLogin('superadmin@example.com')}
                                            className="w-full sm:flex-1 text-white px-3 py-2 rounded-md text-xs sm:text-sm font-medium transition-all duration-200"
                                            style={{ backgroundColor: primaryColor }}
                                        >
                                            Login as Super Admin
                                        </Button>
                                        <Button
                                            type="button"
                                            onClick={() => demoLogin('company@example.com')}
                                            className="w-full sm:flex-1 text-white px-3 py-2 rounded-md text-xs sm:text-sm font-medium transition-all duration-200"
                                            style={{ backgroundColor: primaryColor }}
                                        >
                                            Login as Company
                                        </Button>
                                    </div>
                                    <div className="flex flex-col sm:flex-row gap-2 sm:gap-3">
                                        <Button
                                            type="button"
                                            onClick={() => demoLogin('maggie93@example.com')}
                                            className="w-full sm:flex-1 text-white px-3 py-2 rounded-md text-xs sm:text-sm font-medium transition-all duration-200"
                                            style={{ backgroundColor: primaryColor }}
                                        >
                                            Login As HR
                                        </Button>
                                        <Button
                                            type="button"
                                            onClick={() => demoLogin('qwaters@example.com')}
                                            className="w-full sm:flex-1 text-white px-3 py-2 rounded-md text-xs sm:text-sm font-medium transition-all duration-200"
                                            style={{ backgroundColor: primaryColor }}
                                        >
                                            Login As Employee
                                        </Button>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex flex-col space-y-3">
                                    <div className="flex flex-col sm:flex-row gap-2 sm:gap-3">
                                        <Button
                                            type="button"
                                            onClick={() => demoLogin('company@example.com')}
                                            className="w-full sm:flex-1 text-white px-3 py-2 rounded-md text-xs sm:text-sm font-medium transition-all duration-200"
                                            style={{ backgroundColor: primaryColor }}
                                        >
                                            Login as Company
                                        </Button>
                                        <Button
                                            type="button"
                                            onClick={() => demoLogin('hr@example.com')}
                                            className="w-full sm:flex-1 text-white px-3 py-2 rounded-md text-xs sm:text-sm font-medium transition-all duration-200"
                                            style={{ backgroundColor: primaryColor }}
                                        >
                                            Login As HR
                                        </Button>
                                    </div>
                                    <div className="flex justify-center">
                                        <Button
                                            type="button"
                                            onClick={() => demoLogin('employee@example.com')}
                                            className="w-full sm:flex-1 text-white px-3 py-2 rounded-md text-xs sm:text-sm font-medium transition-all duration-200"
                                            style={{ backgroundColor: primaryColor }}
                                        >
                                            Login As Employee
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                )}

                {isSaas && (
                    <div className="text-center text-sm text-gray-600 dark:text-gray-400 mt-6">
                        {t("Don't have an account?")}{' '}
                        <TextLink
                            href={route('register')}
                            className="font-medium transition-colors duration-200"
                            style={{ color: primaryColor }}
                            tabIndex={6}
                        >
                            {t('Sign up')}
                        </TextLink>
                    </div>
                )}
            </form>
        </AuthLayout>
    );
}
