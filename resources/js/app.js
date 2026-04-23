import '../css/app.css';
import 'primeicons/primeicons.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import PrimeVue from 'primevue/config';
import ToastService from 'primevue/toastservice';
import Aura from '@primeuix/themes/aura';
import { primeVueLocaleForLang } from './i18n/locales';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

function getInitialLang() {
    const stored = window?.localStorage?.getItem('app_language');
    if (stored === 'en' || stored === 'es') {
        return stored;
    }
    const nav = window?.navigator?.language?.toLowerCase() ?? '';
    return nav.startsWith('es') ? 'es' : 'en';
}

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const initialLang = getInitialLang();

        if (typeof document !== 'undefined') {
            document.documentElement.setAttribute('lang', initialLang);
        }

        const app = createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(PrimeVue, {
                theme: {
                    preset: Aura,
                    options: {
                        darkModeSelector: '.dark',
                    },
                },
                locale: primeVueLocaleForLang(initialLang),
            })
            .use(ToastService)
            .mount(el);

        window?.addEventListener?.('app:language-changed', (ev) => {
            const lang = ev?.detail?.lang;
            if (lang !== 'en' && lang !== 'es') {
                return;
            }
            document.documentElement.setAttribute('lang', lang);
            app.config.globalProperties.$primevue.config.locale =
                primeVueLocaleForLang(lang);
        });

        return app;
    },
    progress: {
        color: '#4B5563',
    },
});
