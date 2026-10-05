<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Link } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import { destroy } from '@/actions/App/Http/Controllers/LoginController';
import { confirmLogout } from '@/lib/confirm';
import { t } from '@/lib/i18n';

/**
 * `href` defaults to this app's own logout; StoreAdminLayout passes the
 * store's, which signs out of the `store` guard instead.
 */
withDefaults(defineProps<{ href?: UrlMethodPair }>(), {
    href: () => destroy(),
});
</script>

<template>
    <Link :href="href" as="button" :on-before="confirmLogout">
        <LogOut class="h-4 w-4" />
        {{ t('nav.logout') }}
    </Link>
</template>
