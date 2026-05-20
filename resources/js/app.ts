import '../css/app.css';

import { axiosAdapter } from '@inertiajs/core';
import { createInertiaApp, http } from '@inertiajs/vue3';
import axios from 'axios';
import { createApp, h } from 'vue';
import AppShell from './Layouts/AppShell.vue';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
http.setClient(axiosAdapter(axios));

createInertiaApp({
    title: (title) => (title ? `${title} - Buff` : 'Buff'),
    // @ts-ignore
    resolve: async (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const page = await pages[`./Pages/${name}.vue`]();

        // @ts-ignore
        page.default.layout = page.default.layout || AppShell;

        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
