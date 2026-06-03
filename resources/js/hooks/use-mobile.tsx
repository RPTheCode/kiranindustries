import { useEffect, useState } from 'react';

/** Viewports below this width use slide-out drawer menu (phones + tablets). */
export const SIDEBAR_DRAWER_BREAKPOINT = 1024;

/** @deprecated Use SIDEBAR_DRAWER_BREAKPOINT — drawer mode for mobile & tablet */
export const MOBILE_BREAKPOINT = SIDEBAR_DRAWER_BREAKPOINT;

export function useIsMobile() {
    const [isMobile, setIsMobile] = useState<boolean>();

    useEffect(() => {
        const mql = window.matchMedia(`(max-width: ${SIDEBAR_DRAWER_BREAKPOINT - 1}px)`);

        const onChange = () => {
            setIsMobile(window.innerWidth < SIDEBAR_DRAWER_BREAKPOINT);
        };

        mql.addEventListener('change', onChange);
        setIsMobile(window.innerWidth < SIDEBAR_DRAWER_BREAKPOINT);

        return () => mql.removeEventListener('change', onChange);
    }, []);

    return !!isMobile;
}

export function useIsDesktopSidebar() {
    return !useIsMobile();
}
