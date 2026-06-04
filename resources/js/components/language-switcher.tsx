import { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import ReactCountryFlag from 'react-country-flag';
import { Button } from '@/components/ui/button';
import { CreateLanguageModal } from './create-language-modal';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuTrigger,
    DropdownMenuSeparator,
} from '@/components/ui/dropdown-menu';
import { Globe, Plus, Settings } from 'lucide-react';
import { usePage, router } from '@inertiajs/react';
import { hasRole } from '@/utils/authorization';


interface Language {
    code: string;
    name: string;
    countryCode: string;
    enabled?: boolean;
}

export function LanguageSwitcher() {
    const { i18n, t } = useTranslation();
    const { auth, globalSettings } = usePage().props as any;
    const [currentLanguage, setCurrentLanguage] = useState<Language | null>(null);
    const [showCreateModal, setShowCreateModal] = useState(false);

    const availableLanguages = globalSettings?.availableLanguages || [];
    const isSaas = globalSettings?.is_saas || false;
    
    
    const isAuthenticated = !!auth?.user;
    const userRoles = auth?.user?.roles?.map((role: any) => role.name) || [];
    const isSuperAdmin = isAuthenticated && hasRole('superadmin', userRoles);
    const isCompany = isAuthenticated && hasRole('company', userRoles);
    

    // Determine if user can manage languages based on SaaS mode
    const canManageLanguages = isSaas ? isSuperAdmin : isCompany;



    useEffect(() => {
        const languages = (availableLanguages || []).filter((l: any) => l.enabled !== false);
        const lang = languages.find((l: Language) => l.code === i18n.language) || languages[0];
        setCurrentLanguage(lang);
    }, [i18n.language, availableLanguages]);

    const handleLanguageChange = (languageCode: string) => {
        if (languageCode === 'manage_languages') {
            router.get(route('manage-language'), { lang: i18n.language });
            return;
        }
        if (languageCode === 'create_language') {
            setShowCreateModal(true);
            return;
        }

        const lang = availableLanguages.find((l: Language) => l.code === languageCode);
        if (lang) {
            setCurrentLanguage(lang);
            i18n.changeLanguage(languageCode);
        }
    };

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" className="flex items-center gap-2 h-8 rounded-md">
                        <Globe className="h-4 w-4" />
                        {currentLanguage && (
                            <>
                                <span className="text-sm font-medium hidden md:inline-block">
                                    {currentLanguage.name}
                                </span>
                                <ReactCountryFlag
                                    countryCode={currentLanguage.countryCode}
                                    svg
                                    style={{ width: '1.2em', height: '1.2em' }}
                                />
                            </>
                        )}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent className="w-56" align="end" forceMount>
                    <DropdownMenuGroup>
                        <div className="max-h-48 overflow-y-auto">
                            {(availableLanguages || []).filter((language: any) => language.enabled !== false).map((language: Language) => (
                                <DropdownMenuItem
                                    key={language.code}
                                    onClick={() => handleLanguageChange(language.code)}
                                    className={`flex items-center gap-2 ${currentLanguage?.code === language.code ? 'bg-accent' : ''}`}
                                >
                                    <ReactCountryFlag
                                        countryCode={language.countryCode}
                                        svg
                                        style={{ width: '1.2em', height: '1.2em' }}
                                    />
                                    <span>{language.name}</span>
                                </DropdownMenuItem>
                            ))}
                        </div>
                    </DropdownMenuGroup>
                    {canManageLanguages && (
                        <>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onClick={() => handleLanguageChange('create_language')}
                                className="justify-center text-primary font-semibold cursor-pointer"
                            >
                                <Plus className="h-4 w-4 mr-2" />
                                {t('Create Language')}
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild className="justify-center text-primary font-semibold cursor-pointer">
                                <a href={route('manage-language')} rel="noopener noreferrer">
                                    <Settings className="h-4 w-4 mr-2" />
                                    {t('Manage Language')}
                                </a>
                            </DropdownMenuItem>
                        </>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            <CreateLanguageModal
                open={showCreateModal}
                onOpenChange={setShowCreateModal}
                onSuccess={() => setShowCreateModal(false)}
            />
        </>
    );
} 