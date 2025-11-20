import '../css/app.css';
import 'vue-sonner/style.css';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';
import { configureEcho } from '@laravel/echo-vue';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Configure Echo
configureEcho({
    broadcaster: 'reverb',
});

// Create a global Echo instance for direct access to private() method
// This is needed because useEcho composable doesn't expose the underlying Echo instance directly
if (typeof window !== 'undefined') {
    // Get values from environment or use defaults
    // Note: Environment variables need VITE_ prefix to be available in frontend
    // If not set, use defaults matching your .env REVERB_* variables
    const key = import.meta.env.VITE_REVERB_APP_KEY || 'p4tstxziotnfxnnrz7xy';
    const host = import.meta.env.VITE_REVERB_HOST || 'localhost';
    const port = import.meta.env.VITE_REVERB_PORT || '8080';
    const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';
    
    (window as any).Echo = new Echo({
        broadcaster: 'reverb',
        key: key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });
    console.log('[app.ts] Global Echo instance created with config:', { host, port, scheme });
}

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on page load...
initializeTheme();
