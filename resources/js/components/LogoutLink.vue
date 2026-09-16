<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { LogOut } from '@lucide/vue';
import { destroy } from '@/actions/App/Http/Controllers/LoginController';
import { confirmLogout } from '@/lib/confirm';
import { t } from '@/lib/i18n';
</script>

<template>
    <Link :href="destroy()" as="button" :on-before="confirmLogout">
        <LogOut class="h-4 w-4" />
        {{ t('nav.logout') }}
    </Link>
</template>
