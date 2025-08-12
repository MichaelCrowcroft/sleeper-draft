import '../css/app.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { ZiggyVue } from 'ziggy-js';
import hljs from 'highlight.js/lib/core';
import json from 'highlight.js/lib/languages/json';
import VueHighlightJS from '@highlightjs/vue-plugin';
import 'highlight.js/styles/vs2015.css';

hljs.registerLanguage('json', json);

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.vue`, import.meta.glob<DefineComponent>('./pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(VueHighlightJS, { hljs })
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
