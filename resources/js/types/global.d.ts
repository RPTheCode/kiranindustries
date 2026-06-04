import type { route as routeFn } from 'ziggy-js';

declare global {
    const route: typeof routeFn;
    interface Window {
        appSettings: {
            formatDateTime: (date: string | Date, includeTime?: boolean) => string | null;
            formatDateForInput: (date: string | Date) => string;
            get: (key: string, defaultValue?: any) => any;
            baseUrl: string;
            imageUrl: string;
            dateFormat: string;
            timeFormat: string;
            timezone: string;
            language: string;
            emailVerification: boolean;
            formatCurrency: (amount: number | string, options?: { showSymbol?: boolean, showCode?: boolean }) => string;
            formatTime: (time: string) => string;
            formatDuration: (hoursDecimal: number | string) => string;
        };
    }
}
