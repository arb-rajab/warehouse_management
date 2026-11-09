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
        /**
         * One-time messages a controller sends with Inertia::flash(); the
         * layouts' SuccessBanner shows `success` above the page content.
         */
        flashDataType: {
            success?: string;
        };
        sharedPageProps: {
            name: string;
            /**
             * The owner-editable placeholder brand name (`messages.brand_name`,
             * the storefront header's source) in the current language; the
             * back-office sidebar shows it next to the logo.
             */
            brandName: string;
            locale: string;
            auth: Auth;
            /** Present on the store back office's pages only. */
            store?: StoreSharedProps;
            [key: string]: unknown;
        };
    }
}
