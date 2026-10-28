import { createInertiaApp, router } from '@inertiajs/vue3';
import { i18n, syncPageLocale, t } from '@/lib/i18n';
import { installImageUploadForms } from '@/lib/imageUploadForms';

if (typeof document !== 'undefined') {
    installImageUploadForms(
        () => ({
            preparing: t('common.imageUpload.preparing'),
            animated: t('common.imageUpload.animated'),
            tooLarge: t('common.imageUpload.tooLarge'),
        }),
        'input[type=file][name=trade_license]',
    );
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    progress: {
        color: '#4B5563',
    },
    withApp(app, { page }) {
        app.use(i18n);

        syncPageLocale(page.props);
        router.on('navigate', (event) =>
            syncPageLocale(event.detail.page.props),
        );
    },
});
