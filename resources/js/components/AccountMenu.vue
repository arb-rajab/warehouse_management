<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, CircleUser, LogOut } from '@lucide/vue';
import { destroy } from '@/actions/App/Http/Controllers/LoginController';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { confirmLogout } from '@/lib/confirm';
import { t } from '@/lib/i18n';
import { useDismissibleListbox } from '@/lib/useDismissibleListbox';

defineProps<{ userName: string | undefined }>();

const { open, containerRef } = useDismissibleListbox(() => 0);
</script>

<template>
    <div ref="containerRef" class="relative">
        <button
            type="button"
            aria-haspopup="menu"
            :aria-expanded="open"
            :aria-label="t('nav.account')"
            class="flex cursor-pointer items-center gap-1.5 rounded-md px-2 py-1.5 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
            @click="open = !open"
        >
            <CircleUser class="h-4 w-4 shrink-0" />
            <span class="max-w-32 truncate">{{ userName }}</span>
            <ChevronDown class="h-4 w-4 shrink-0" />
        </button>
        <div
            v-if="open"
            role="menu"
            class="absolute end-0 z-10 mt-1 w-56 rounded-md border border-gray-200 bg-white p-2 shadow-lg dark:border-neutral-700 dark:bg-neutral-800"
        >
            <div
                class="border-b border-gray-200 px-1 pb-2 dark:border-neutral-700"
            >
                <LanguageSwitcher />
            </div>
            <Link
                :href="destroy()"
                as="button"
                role="menuitem"
                :on-before="confirmLogout"
                class="mt-2 flex w-full cursor-pointer items-center gap-1.5 rounded px-1 py-1.5 text-sm text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-neutral-400 dark:hover:bg-neutral-700 dark:hover:text-white"
                @click="open = false"
            >
                <LogOut class="h-4 w-4" />
                {{ t('nav.logout') }}
            </Link>
        </div>
    </div>
</template>
