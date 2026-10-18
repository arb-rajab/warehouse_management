<script setup lang="ts">
import { Form, Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    ChevronDown,
    CircleUser,
    Globe,
    Menu,
    Store,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { logout } from '@/actions/App/Http/Controllers/Auth/LoginController';
import { index as storefrontHome } from '@/actions/App/Http/Controllers/HomeController';
import { clearCache } from '@/actions/App/Http/Controllers/Store/Admin/DashboardController';
import { index as notificationsIndex } from '@/actions/App/Http/Controllers/Store/Admin/NotificationController';
import { index as profileIndex } from '@/actions/App/Http/Controllers/Store/Admin/ProfileController';
import FormField from '@/components/FormField.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import LogoutLink from '@/components/LogoutLink.vue';
import SkipToContentLink from '@/components/SkipToContentLink.vue';
import StoreAdminNavLink from '@/components/StoreAdminNavLink.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { formatDateTime } from '@/lib/date';
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
import { useDismissibleListbox } from '@/lib/useDismissibleListbox';

/**
 * The store back office's shell: a sidebar built from lib/storeAdminMenu.ts,
 * gated on the shared store permissions, with the account utilities at its
 * foot. Below `lg` the sidebar folds behind a menu button.
 */
const page = usePage();
const access = useStoreAccess();

const isSidebarOpen = ref(false);

const menuQuery = ref('');
const { open: notificationsOpen, containerRef: notificationsRef } =
    useDismissibleListbox(() => 0);
const notifications = computed(
    () => page.props.store?.notifications ?? { unread_count: 0, items: [] },
);
const menu = computed(() => {
    const visible = visibleStoreMenu(storeAdminMenu, access.value);
    const query = menuQuery.value.trim().toLocaleLowerCase();

    if (!query) {
        return visible;
    }

    function filter(items: StoreMenuItem[]): StoreMenuItem[] {
        return items.flatMap((item) => {
            if (t(item.labelKey).toLocaleLowerCase().includes(query)) {
                return [item];
            }

            if (!isStoreMenuGroup(item)) {
                return [];
            }

            const children = filter(item.children);

            return children.length ? [{ ...item, children }] : [];
        });
    }

    return filter(visible);
});
function badge(item: StoreMenuItem): number {
    return !isStoreMenuGroup(item) && item.badgeKey
        ? (page.props.store?.counts?.[item.badgeKey] ?? 0)
        : 0;
}
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
    return (
        !!menuQuery.value ||
        containsActive(item) !== toggledGroups.value.has(item.labelKey)
    );
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
            class="flex items-center justify-between border-b border-gray-200 bg-white px-4 lg:hidden dark:border-neutral-800 dark:bg-neutral-900 print:hidden"
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
            class="border-gray-200 bg-white lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-64 lg:shrink-0 lg:flex-col lg:border-e dark:border-neutral-800 dark:bg-neutral-900 print:hidden"
            :class="isSidebarOpen ? 'flex flex-col border-b' : 'hidden'"
        >
            <span
                class="hidden items-center gap-1.5 px-5 py-4 font-semibold lg:inline-flex"
            >
                <Store class="h-5 w-5 shrink-0" />
                {{ t('store.nav.brand') }}
            </span>
            <div class="px-3 pb-3">
                <FormField
                    id="store-menu-search"
                    type="search"
                    :label="t('store.nav.searchMenu')"
                    maxlength="100"
                    @input="
                        menuQuery = ($event.target as HTMLInputElement).value
                    "
                />
            </div>
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
                                                    :count="badge(link)"
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
                                        :count="badge(child)"
                                        :active="isActive(child.href.url)"
                                        @click="isSidebarOpen = false"
                                    />
                                </li>
                            </ul>
                        </template>
                        <StoreAdminNavLink
                            v-else
                            :item="item"
                            :count="badge(item)"
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
                <Link
                    :href="profileIndex()"
                    :aria-label="t('store.nav.profile')"
                    class="flex items-center gap-1.5 text-gray-600 hover:text-gray-900 dark:text-neutral-400 dark:hover:text-white"
                >
                    <CircleUser class="h-4 w-4 shrink-0" />
                    <span class="truncate">{{
                        page.props.auth.user?.name
                    }}</span>
                </Link>
                <LanguageSwitcher />
                <LogoutLink
                    :href="logout.post()"
                    class="flex cursor-pointer items-center gap-1.5 text-gray-600 hover:text-gray-900 dark:text-neutral-400 dark:hover:text-white"
                />
            </div>
        </aside>
        <main id="main" class="min-w-0 flex-1 px-4 py-8 sm:px-6">
            <div class="mx-auto max-w-6xl">
                <div class="mb-5 flex items-center justify-end gap-3">
                    <Form
                        v-if="access.isRaizerAdmin"
                        :action="clearCache()"
                        #default="{ processing }"
                    >
                        <SubmitButton
                            :label="t('store.nav.clearCache')"
                            :processing-label="t('store.orders.saving')"
                            :processing="processing"
                        />
                    </Form>
                    <div ref="notificationsRef" class="relative">
                        <button
                            type="button"
                            class="flex cursor-pointer items-center gap-2 rounded-md border border-gray-300 px-3 py-2 dark:border-neutral-700"
                            :aria-expanded="notificationsOpen"
                            aria-controls="store-notifications"
                            @click="notificationsOpen = !notificationsOpen"
                        >
                            <Bell class="h-4 w-4" />{{
                                t('store.nav.notifications')
                            }}
                            <span v-if="notifications.unread_count">{{
                                notifications.unread_count
                            }}</span>
                        </button>
                        <div
                            v-if="notificationsOpen"
                            id="store-notifications"
                            class="absolute end-0 z-30 mt-2 max-h-96 w-80 overflow-y-auto rounded-md border border-gray-200 bg-white p-4 shadow-lg dark:border-neutral-700 dark:bg-neutral-900"
                        >
                            <p v-if="!notifications.items.length">
                                {{ t('store.nav.noNotifications') }}
                            </p>
                            <ul v-else class="space-y-3">
                                <li
                                    v-for="notification in notifications.items"
                                    :key="notification.id"
                                >
                                    <p v-if="notification.kind === 'order'">
                                        {{
                                            t('store.nav.orderNotification', {
                                                code: notification.order_code,
                                                status: t(
                                                    `store.orders.status.${notification.status}`,
                                                ),
                                            })
                                        }}
                                    </p>
                                    <p v-else>
                                        {{ notification.user_name }}
                                        {{ notification.message }}
                                    </p>
                                    <time
                                        class="text-xs text-gray-500 dark:text-neutral-400"
                                        >{{
                                            formatDateTime(
                                                notification.created_at ?? '',
                                            )
                                        }}</time
                                    >
                                </li>
                            </ul>
                            <a
                                :href="notificationsIndex.url()"
                                class="mt-3 block text-blue-700 dark:text-blue-400"
                                >{{ t('store.nav.allNotifications') }}</a
                            >
                        </div>
                    </div>
                </div>
                <slot />
            </div>
        </main>
    </div>
</template>
