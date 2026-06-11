import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuGroup,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { Link, router, usePage } from '@inertiajs/react';
import { ChevronDown, LogOut, User } from 'lucide-react';
import { useTranslation } from 'react-i18next';

type ProfileMenuProps = {
    variant?: 'default' | 'header';
    embedded?: boolean;
};

function headerDisplayName(fullName?: string): string {
    if (!fullName?.trim()) {
        return '';
    }

    const parts = fullName.trim().split(/\s+/);
    return parts[0] ?? fullName;
}

export function ProfileMenu({ variant = 'default', embedded = false }: ProfileMenuProps) {
    const { t } = useTranslation();
    const { auth } = usePage().props as { auth?: { user?: { name?: string; email?: string; avatar?: string } } };
    const user = auth?.user;
    const isHeader = variant === 'header';

    const getAvatarUrl = () => {
        if (auth?.user?.avatar) {
            return window.storage(auth.user.avatar);
        }
        return window.asset('images/avatar/avatar.png');
    };

    const handleLogout = () => {
        router.post(route('logout'));
    };

    const initials = user?.name
        ? user.name
              .split(' ')
              .map((n: string) => n[0])
              .join('')
              .toUpperCase()
              .slice(0, 2)
        : 'U';

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    type="button"
                    variant={isHeader && !embedded ? 'outline' : 'ghost'}
                    className={cn(
                        'gap-2',
                        isHeader
                            ? cn(
                                  'header-control h-8 w-auto max-w-none shrink-0 px-1.5 shadow-none sm:pl-1.5 sm:pr-2',
                                  embedded
                                      ? 'border-0 bg-transparent hover:bg-white data-[state=open]:bg-white dark:hover:bg-slate-800 dark:data-[state=open]:bg-slate-800'
                                      : 'border-slate-200 bg-white hover:bg-slate-50 data-[state=open]:border-primary/40 dark:border-slate-700 dark:bg-slate-900 dark:hover:bg-slate-800'
                              )
                            : 'h-9 rounded-lg px-1.5 hover:bg-slate-100 dark:hover:bg-slate-800'
                    )}
                >
                    <Avatar className="h-7 w-7 shrink-0">
                        <AvatarImage src={getAvatarUrl()} alt={user?.name} />
                        <AvatarFallback className="bg-slate-200 text-[10px] font-semibold text-slate-600 dark:bg-slate-700 dark:text-slate-200">
                            {initials}
                        </AvatarFallback>
                    </Avatar>
                    {isHeader ? (
                        <>
                            <span
                                className="hidden max-w-[7rem] truncate whitespace-nowrap text-sm font-medium text-slate-800 sm:inline dark:text-slate-100"
                                title={user?.name}
                            >
                                {headerDisplayName(user?.name)}
                            </span>
                            <ChevronDown className="hidden h-4 w-4 shrink-0 text-slate-400 sm:block" />
                        </>
                    ) : (
                        <span className="hidden text-sm font-medium md:inline-block">{user?.name}</span>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent className="z-[20001] w-56" align="end">
                <DropdownMenuLabel className="px-3 py-2 font-normal">
                    <p className="truncate text-sm font-semibold text-slate-900 dark:text-slate-50">{user?.name}</p>
                    <p className="truncate text-xs text-slate-500">{user?.email}</p>
                </DropdownMenuLabel>
                <DropdownMenuSeparator />
                <DropdownMenuGroup>
                    <DropdownMenuItem asChild className="cursor-pointer px-3 py-2">
                        <Link href={route('profile')} className="flex items-center">
                            <User className="mr-2 h-4 w-4" />
                            <span>{t('Profile')}</span>
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuGroup>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    onClick={handleLogout}
                    className="cursor-pointer px-3 py-2 text-red-600 focus:bg-red-50 focus:text-red-600"
                >
                    <LogOut className="mr-2 h-4 w-4" />
                    <span>{t('Log out')}</span>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
