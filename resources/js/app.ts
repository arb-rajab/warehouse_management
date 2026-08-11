import { createInertiaApp } from '@inertiajs/vue3';
import { i18n, isSupportedLocale } from '@/lib/i18n';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
    withApp(app, { page }) {
        app.use(i18n);

        if (isSupportedLocale(page.props.locale)) {
            i18n.global.locale.value = page.props.locale;
        }
    },
});
