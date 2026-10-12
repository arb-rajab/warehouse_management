import type { Auth } from '@/types/auth';
import type { StoreSharedProps } from '@/types/store';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            locale: string;
            auth: Auth;
            /** Present on the store back office's pages only. */
            store?: StoreSharedProps;
            [key: string]: unknown;
        };
    }
}
