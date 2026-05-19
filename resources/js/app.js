import '../css/app.css';

import { createInertiaApp, http } from '@inertiajs/vue3';
import { axiosAdapter } from '@inertiajs/core';
import axios from 'axios';
import { createApp, h } from 'vue';
import AppShell from './Layouts/AppShell.vue';

axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
axios.defaults.headers.common['X-CSRF-TOKEN'] = document.querySelector('meta[name="csrf-token"]')?.content || '';
http.setClient(axiosAdapter(axios));

createInertiaApp({
    title: (title) => (title ? `${title} - Buff` : 'Buff'),
    resolve: async (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const page = await pages[`./Pages/${name}.vue`]();
        page.default.layout = page.default.layout || AppShell;

        return page;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
});
