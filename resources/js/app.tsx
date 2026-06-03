import '../css/app.css';
import '../css/dark-mode.css';
import '../css/route-progress.css';

import { createInertiaApp, router } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { AppProviders } from './components/app-providers';
import { initializeTheme } from './hooks/use-appearance';
import { initializeGlobalSettings } from './utils/globalSettings';
import { initPerformanceMonitoring, lazyLoadImages } from './utils/performance';
import './i18n'; // Import i18n configuration
import './utils/axios-config'; // Import axios configuration

// Initialize performance monitoring
initPerformanceMonitoring();

// Initialize lazy loading of images when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    lazyLoadImages();
});

// Add event listener for theme changes
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    // Re-apply theme when system preference changes
    const savedTheme = localStorage.getItem('themeSettings');
    if (savedTheme) {
        const themeSettings = JSON.parse(savedTheme);
        if (themeSettings.appearance === 'system') {
            initializeTheme();
        }
    }
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        const syncPageGlobals = (page: { props?: Record<string, unknown> }) => {
            try {
                (window as any).page = page;
                (window as any).isDemo = page.props?.is_demo || false;
                const globalSettings = (page.props?.globalSettings as Record<string, unknown>) || {};
                if (Object.keys(globalSettings).length > 0) {
                    initializeGlobalSettings(globalSettings);
                }
            } catch (e) {
                console.warn('Could not sync page globals:', e);
            }
        };

        syncPageGlobals(props.initialPage);

        // Let Inertia swap pages internally — avoid full root re-render (was very slow).
        root.render(
            <App {...props}>
                {({ Component, props: pageProps, key }) => (
                    <AppProviders
                        globalSettings={(pageProps as { globalSettings?: Record<string, unknown> }).globalSettings}
                        user={(pageProps as { auth?: { user?: Record<string, unknown> } }).auth?.user}
                    >
                        <Component key={key} {...pageProps} />
                    </AppProviders>
                )}
            </App>
        );

        router.on('navigate', (event) => {
            syncPageGlobals(event.detail.page);
        });
    },
    progress: {
        delay: 200,
        color: '#1e2978',
        includeCSS: true,
        showSpinner: false,
    },
});

// This will set light / dark mode on load...
initializeTheme();

// Direction initialization is now handled by LayoutProvider and landing page
