import '../css/app.css';

import { axiosAdapter } from '@inertiajs/core';
import { createInertiaApp, http, router } from '@inertiajs/vue3';
import axios from 'axios';
import { createApp, h } from 'vue';
import AppShell from './Layouts/AppShell.vue';
import { applyAppearance, applyReducedMotion, watchSystemAppearance, watchSystemReducedMotion } from './appearance';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
http.setClient(axiosAdapter(axios));
applyAppearance();
applyReducedMotion();
watchSystemAppearance();
watchSystemReducedMotion();
(window as typeof window & { router?: typeof router }).router = router;

createInertiaApp({
    dev: import.meta.env.DEV,
    progress: { color: 'var(--brand-violet)' },
    title: (title) => (title ? `${title} - Buff` : 'Buff'),
    // @ts-ignore
    resolve: async (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const page = await pages[`./Pages/${name}.vue`]() as {default: {layout?: unknown}};

        if (!Object.prototype.hasOwnProperty.call(page.default, 'layout')) {
            page.default.layout = AppShell;
        }

        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
