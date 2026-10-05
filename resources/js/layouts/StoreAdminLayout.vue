<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { ChevronDown, CircleUser, Globe, Menu, Store, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import { logout } from '@/actions/App/Http/Controllers/Auth/LoginController';
import { index as storefrontHome } from '@/actions/App/Http/Controllers/HomeController';
import { index as profileIndex } from '@/actions/App/Http/Controllers/ProfileController';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import LogoutLink from '@/components/LogoutLink.vue';
import SkipToContentLink from '@/components/SkipToContentLink.vue';
import StoreAdminNavLink from '@/components/StoreAdminNavLink.vue';
import { t } from '@/lib/i18n';
import { isActiveNavUrl } from '@/lib/navigation';
import { useStoreAccess } from '@/lib/storeAccess';
import {
    isStoreMenuGroup,
    storeAdminMenu,
    storeMenuLinks,
    visibleStoreMenu,
} from '@/lib/storeAdminMenu';
import type { StoreMenuItem } from '@/lib/storeAdminMenu';

/**
 * The store back office's shell: a sidebar built from lib/storeAdminMenu.ts,
 * gated on the shared store permissions, with the account utilities at its
 * foot. Below `lg` the sidebar folds behind a menu button.
 */
const page = usePage();
const access = useStoreAccess();

const isSidebarOpen = ref(false);

const menu = computed(() => visibleStoreMenu(storeAdminMenu, access.value));
const menuUrls = computed(() =>
    storeMenuLinks(menu.value).map((link) => link.href.url),
);

function isActive(url: string): boolean {
    return isActiveNavUrl(page.url, url, menuUrls.value);
}

function containsActive(item: StoreMenuItem): boolean {
    return storeMenuLinks([item]).some((link) => isActive(link.href.url));
}

/**
 * The groups the visitor has flipped. A group holding the current page
 * starts open and every other one closed; a click flips it from there.
 */
const toggledGroups = ref(new Set<string>());

function isOpen(item: StoreMenuItem): boolean {
    return containsActive(item) !== toggledGroups.value.has(item.labelKey);
}

function toggle(labelKey: string): void {
    const next = new Set(toggledGroups.value);

    if (!next.delete(labelKey)) {
        next.add(labelKey);
    }

    toggledGroups.value = next;
}
</script>

<template>
    <div
        class="min-h-screen bg-gray-50 text-gray-900 lg:flex dark:bg-neutral-950 dark:text-neutral-100"
    >
        <SkipToContentLink />
        <div
            class="flex items-center justify-between border-b border-gray-200 bg-white px-4 lg:hidden dark:border-neutral-800 dark:bg-neutral-900"
        >
            <span class="inline-flex items-center gap-1.5 py-3 font-semibold">
                <Store class="h-5 w-5 shrink-0" />
                {{ t('store.nav.brand') }}
            </span>
            <button
                type="button"
                class="cursor-pointer rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
                :aria-label="
                    t(isSidebarOpen ? 'nav.closeMenu' : 'nav.openMenu')
                "
                aria-controls="store-admin-sidebar"
                :aria-expanded="isSidebarOpen"
                @click="isSidebarOpen = !isSidebarOpen"
            >
                <component :is="isSidebarOpen ? X : Menu" class="h-5 w-5" />
            </button>
        </div>
        <aside
            id="store-admin-sidebar"
            class="border-gray-200 bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-64 lg:shrink-0 lg:flex-col lg:border-e dark:border-neutral-800 dark:bg-neutral-900"
            :class="isSidebarOpen ? 'flex flex-col border-b' : 'hidden'"
        >
            <span
                class="hidden items-center gap-1.5 px-5 py-4 font-semibold lg:inline-flex"
            >
                <Store class="h-5 w-5 shrink-0" />
                {{ t('store.nav.brand') }}
            </span>
            <nav
                :aria-label="t('store.nav.menuLabel')"
                class="flex-1 overflow-y-auto px-2 pb-2"
            >
                <ul class="space-y-0.5">
                    <li v-for="item in menu" :key="item.labelKey">
                        <template v-if="isStoreMenuGroup(item)">
                            <button
                                type="button"
                                class="flex w-full cursor-pointer items-center gap-2 rounded-md px-3 py-2 text-sm transition-colors hover:bg-gray-50 dark:hover:bg-neutral-800"
                                :class="
                                    containsActive(item)
                                        ? 'font-medium text-gray-900 dark:text-white'
                                        : 'text-gray-600 hover:text-gray-900 dark:text-neutral-400 dark:hover:text-white'
                                "
                                :aria-expanded="isOpen(item)"
                                @click="toggle(item.labelKey)"
                            >
                                <component
                                    :is="item.icon"
                                    v-if="item.icon"
                                    class="h-4 w-4 shrink-0"
                                />
                                <span class="flex-1 text-start">
                                    {{ t(item.labelKey) }}
                                </span>
                                <ChevronDown
                                    class="h-4 w-4 shrink-0 transition-transform"
                                    :class="{ 'rotate-180': isOpen(item) }"
                                />
                            </button>
                            <ul
                                v-if="isOpen(item)"
                                class="mt-0.5 space-y-0.5 ps-6"
                            >
                                <li
                                    v-for="child in item.children"
                                    :key="child.labelKey"
                                >
                                    <template v-if="isStoreMenuGroup(child)">
                                        <p
                                            class="px-3 pt-2 pb-1 text-xs font-semibold tracking-wide text-gray-400 uppercase dark:text-neutral-500"
                                        >
                                            {{ t(child.labelKey) }}
                                        </p>
                                        <ul class="space-y-0.5">
                                            <li
                                                v-for="link in storeMenuLinks(
                                                    child.children,
                                                )"
                                                :key="link.labelKey"
                                            >
                                                <StoreAdminNavLink
                                                    :item="link"
                                                    :active="
                                                        isActive(link.href.url)
                                                    "
                                                    @click="
                                                        isSidebarOpen = false
                                                    "
                                                />
                                            </li>
                                        </ul>
                                    </template>
                                    <StoreAdminNavLink
                                        v-else
                                        :item="child"
                                        :active="isActive(child.href.url)"
                                        @click="isSidebarOpen = false"
                                    />
                                </li>
                            </ul>
                        </template>
                        <StoreAdminNavLink
                            v-else
                            :item="item"
                            :active="isActive(item.href.url)"
                            @click="isSidebarOpen = false"
                        />
                    </li>
                </ul>
            </nav>
            <div
                class="space-y-3 border-t border-gray-200 px-5 py-4 text-sm dark:border-neutral-800"
            >
                <a
                    :href="storefrontHome.url()"
                    target="_blank"
                    rel="noopener"
                    class="flex items-center gap-1.5 text-gray-600 hover:text-gray-900 dark:text-neutral-400 dark:hover:text-white"
                >
                    <Globe class="h-4 w-4 shrink-0" />
                    {{ t('store.nav.browseWebsite') }}
                </a>
                <a
                    :href="profileIndex.url()"
                    :aria-label="t('store.nav.profile')"
                    class="flex items-center gap-1.5 text-gray-600 hover:text-gray-900 dark:text-neutral-400 dark:hover:text-white"
                >
                    <CircleUser class="h-4 w-4 shrink-0" />
                    <span class="truncate">{{
                        page.props.auth.user?.name
                    }}</span>
                </a>
                <LanguageSwitcher />
                <LogoutLink
                    :href="logout.post()"
                    class="flex cursor-pointer items-center gap-1.5 text-gray-600 hover:text-gray-900 dark:text-neutral-400 dark:hover:text-white"
                />
            </div>
        </aside>
        <main id="main" class="min-w-0 flex-1 px-4 py-8 sm:px-6">
            <div class="mx-auto max-w-6xl">
                <slot />
            </div>
        </main>
    </div>
</template>
