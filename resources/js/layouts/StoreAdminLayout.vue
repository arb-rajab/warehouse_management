<script setup lang="ts">
import { Form, Link, usePage } from '@inertiajs/vue3';
import {
    Bell,
    ChevronDown,
    CircleUser,
    Globe,
    Search,
    Trash2,
} from '@lucide/vue';
import { computed, ref, watch } from 'vue';
import { logout } from '@/actions/App/Http/Controllers/Auth/LoginController';
import { index as storefrontHome } from '@/actions/App/Http/Controllers/HomeController';
import { clearCache } from '@/actions/App/Http/Controllers/Store/Admin/DashboardController';
import { index as notificationsIndex } from '@/actions/App/Http/Controllers/Store/Admin/NotificationController';
import { index as profileIndex } from '@/actions/App/Http/Controllers/Store/Admin/ProfileController';
import BackofficeShell from '@/components/BackofficeShell.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import LogoutLink from '@/components/LogoutLink.vue';
import SidebarUser from '@/components/SidebarUser.vue';
import StoreAdminNavLink from '@/components/StoreAdminNavLink.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import {
    findActiveStoreMenuLink,
    storeMenuItemContains,
} from '@/lib/storeAdminActiveMenu';
import {
    isStoreMenuGroup,
    storeAdminMenu,
    storeAdminPageParents,
    storeMenuLinks,
    visibleStoreMenu,
} from '@/lib/storeAdminMenu';
import type { StoreMenuItem, StoreMenuLink } from '@/lib/storeAdminMenu';
import { useDismissibleListbox } from '@/lib/useDismissibleListbox';

/**
 * The store control panel, drawn by the shared BackofficeShell
 * (.ai/rules/layouts.md): a sidebar built from lib/storeAdminMenu.ts, gated
 * on the shared store permissions, with Notifications above and Clear
 * dashboard cache, the user, profile and logout in its footer; the header
 * carries the language switcher and "Browse website". The menu and its
 * active state stay here (.ai/rules/store-admin-menu-highlighting.md).
 */
const page = usePage();
const access = useStoreAccess();

const menuQuery = ref('');
const clearCacheOpen = ref(false);
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
/**
 * The active link comes from the whole menu, never the filtered copy shown,
 * so who is looking and what they typed in the search box cannot move it
 * (.ai/rules/store-admin-menu-highlighting.md).
 */
const activeLink = computed(() =>
    findActiveStoreMenuLink(page.url, storeAdminMenu, storeAdminPageParents),
);

function isActive(link: StoreMenuLink): boolean {
    return link === activeLink.value;
}

const section = computed(() =>
    activeLink.value ? t(activeLink.value.labelKey) : undefined,
);

function containsActive(item: StoreMenuItem): boolean {
    return storeMenuItemContains(item, activeLink.value);
}

/**
 * Groups the visitor has opened or closed by hand. A group holding the
 * current page is open and every other one closed until a click says
 * otherwise; going to another page forgets the clicks, so the group holding
 * the new page is open again.
 */
const manualGroups = ref(new Map<string, boolean>());

watch(
    () => page.url,
    () => {
        manualGroups.value = new Map();
    },
);

function isOpen(item: StoreMenuItem): boolean {
    return (
        !!menuQuery.value ||
        (manualGroups.value.get(item.labelKey) ?? containsActive(item))
    );
}

function toggle(item: StoreMenuItem): void {
    const next = new Map(manualGroups.value);

    next.set(item.labelKey, !isOpen(item));
    manualGroups.value = next;
}

/**
 * On the folded icon rail a group shows only its icon: a click unfolds the
 * sidebar with that group open.
 */
function openGroup(
    item: StoreMenuItem,
    collapsed: boolean,
    expand: () => void,
): void {
    if (!collapsed) {
        toggle(item);

        return;
    }

    const next = new Map(manualGroups.value);

    next.set(item.labelKey, true);
    manualGroups.value = next;
    expand();
}
</script>

<template>
    <BackofficeShell
        sidebar-id="store-admin-sidebar"
        :area="t('store.nav.brand')"
        :section="section"
    >
        <template #nav="{ close, collapsed, expand }">
            <div class="px-3.5 pb-2" :class="{ 'lg:hidden': collapsed }">
                <label
                    for="store-menu-search"
                    class="flex h-9 items-center gap-2 rounded-md bg-sidebar-well px-2.5 text-sm text-sidebar-muted focus-within:ring-2 focus-within:ring-focus-ring"
                >
                    <Search class="h-4 w-4 shrink-0" aria-hidden="true" />
                    <span class="sr-only">{{ t('store.nav.searchMenu') }}</span>
                    <input
                        id="store-menu-search"
                        type="search"
                        maxlength="100"
                        :placeholder="t('store.nav.searchMenu')"
                        class="min-w-0 flex-1 bg-transparent text-sidebar-strong outline-none placeholder:text-sidebar-muted"
                        @input="
                            menuQuery = ($event.target as HTMLInputElement)
                                .value
                        "
                    />
                </label>
            </div>
            <nav
                :aria-label="t('store.nav.menuLabel')"
                class="flex-1 overflow-y-auto px-2.5 pb-2"
            >
                <ul class="space-y-0.5">
                    <li v-for="item in menu" :key="item.labelKey">
                        <template v-if="isStoreMenuGroup(item)">
                            <button
                                type="button"
                                class="flex w-full cursor-pointer items-center gap-2.5 rounded-md px-2.5 py-2 text-sm transition-colors hover:bg-sidebar-hover"
                                :class="[
                                    containsActive(item)
                                        ? 'font-semibold text-sidebar-strong'
                                        : 'font-medium text-sidebar-ink hover:text-sidebar-strong',
                                    { 'lg:justify-center': collapsed },
                                ]"
                                :aria-expanded="
                                    collapsed ? undefined : isOpen(item)
                                "
                                :title="
                                    collapsed ? t(item.labelKey) : undefined
                                "
                                :data-active="containsActive(item)"
                                @click="openGroup(item, collapsed, expand)"
                            >
                                <component
                                    :is="item.icon"
                                    v-if="item.icon"
                                    class="h-[18px] w-[18px] shrink-0"
                                    aria-hidden="true"
                                />
                                <span
                                    class="flex-1 text-start"
                                    :class="{ 'lg:sr-only': collapsed }"
                                >
                                    {{ t(item.labelKey) }}
                                </span>
                                <ChevronDown
                                    class="h-4 w-4 shrink-0 text-sidebar-muted transition-transform"
                                    :class="{
                                        'rotate-180': isOpen(item),
                                        'lg:hidden': collapsed,
                                    }"
                                    aria-hidden="true"
                                />
                            </button>
                            <ul
                                v-if="isOpen(item)"
                                class="mt-0.5 space-y-0.5 ps-7"
                                :class="{ 'lg:hidden': collapsed }"
                            >
                                <li
                                    v-for="child in item.children"
                                    :key="child.labelKey"
                                >
                                    <template v-if="isStoreMenuGroup(child)">
                                        <p
                                            class="px-2.5 pt-2 pb-1 text-[11px] font-bold tracking-[0.08em] uppercase rtl:text-xs rtl:tracking-normal"
                                            :class="
                                                containsActive(child)
                                                    ? 'text-sidebar-strong'
                                                    : 'text-sidebar-muted'
                                            "
                                            :data-active="containsActive(child)"
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
                                                    :active="isActive(link)"
                                                    @click="close()"
                                                />
                                            </li>
                                        </ul>
                                    </template>
                                    <StoreAdminNavLink
                                        v-else
                                        :item="child"
                                        :count="badge(child)"
                                        :active="isActive(child)"
                                        @click="close()"
                                    />
                                </li>
                            </ul>
                        </template>
                        <StoreAdminNavLink
                            v-else
                            :item="item"
                            :count="badge(item)"
                            :active="isActive(item)"
                            :collapsed="collapsed"
                            @click="close()"
                        />
                    </li>
                </ul>
            </nav>
            <div
                ref="notificationsRef"
                class="relative border-t border-sidebar-line px-2.5 py-2"
            >
                <button
                    type="button"
                    class="relative flex w-full cursor-pointer items-center gap-2.5 rounded-md px-2.5 py-2 text-sm font-medium text-sidebar-ink transition-colors hover:bg-sidebar-hover hover:text-sidebar-strong"
                    :class="{ 'lg:justify-center': collapsed }"
                    :title="
                        collapsed ? t('store.nav.notifications') : undefined
                    "
                    :aria-expanded="notificationsOpen"
                    aria-controls="store-notifications"
                    @click="notificationsOpen = !notificationsOpen"
                >
                    <Bell
                        class="h-[18px] w-[18px] shrink-0"
                        aria-hidden="true"
                    />
                    <span
                        class="flex-1 text-start"
                        :class="{ 'lg:sr-only': collapsed }"
                        >{{ t('store.nav.notifications') }}</span
                    >
                    <template v-if="notifications.unread_count">
                        <span
                            aria-hidden="true"
                            class="ms-auto rounded-full bg-brand px-2 text-[11px] font-bold text-on-brand"
                            :class="{
                                'lg:absolute lg:end-0 lg:-top-0.5 lg:ms-0 lg:px-1 lg:text-[10px]':
                                    collapsed,
                            }"
                            >{{ notifications.unread_count }}</span
                        >
                        <span class="sr-only">{{
                            t('store.nav.notificationsUnread', {
                                count: notifications.unread_count,
                            })
                        }}</span>
                    </template>
                </button>
                <div
                    v-if="notificationsOpen"
                    id="store-notifications"
                    class="z-30 mt-2 max-h-96 overflow-y-auto rounded-lg border border-line bg-surface p-4 text-sm text-ink shadow-pop lg:absolute lg:start-full lg:bottom-0 lg:ms-2 lg:mt-0 lg:w-80"
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
                            <time class="text-xs text-muted">{{
                                formatDateTime(notification.created_at ?? '')
                            }}</time>
                        </li>
                    </ul>
                    <a
                        :href="notificationsIndex.url()"
                        class="mt-3 block font-medium text-link"
                        >{{ t('store.nav.allNotifications') }}</a
                    >
                </div>
            </div>
        </template>
        <template #footer="{ collapsed }">
            <div
                v-if="access.can('file_system_&_cache_configuration')"
                class="px-2.5 pt-2"
            >
                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        class="flex flex-1 cursor-pointer items-center gap-2.5 rounded-md px-2.5 py-2 text-sm font-medium text-sidebar-ink hover:bg-sidebar-hover hover:text-sidebar-strong"
                        :class="{ 'lg:justify-center': collapsed }"
                        :title="
                            collapsed ? t('store.nav.clearCache') : undefined
                        "
                        @click="clearCacheOpen = true"
                    >
                        <Trash2
                            class="h-[18px] w-[18px] shrink-0"
                            aria-hidden="true"
                        />
                        <span :class="{ 'lg:sr-only': collapsed }">{{
                            t('store.nav.clearCache')
                        }}</span>
                    </button>
                    <HelpTooltip
                        :class="{ 'lg:hidden': collapsed }"
                        :label="t('store.nav.clearCacheHelpLabel')"
                        :text="t('store.nav.clearCacheHelp')"
                    />
                </div>
                <FilterDialog
                    v-model:open="clearCacheOpen"
                    :title="t('store.nav.clearCacheConfirmTitle')"
                    :close-label="t('common.close')"
                >
                    <Form
                        :action="clearCache()"
                        class="pb-6"
                        #default="{ processing }"
                    >
                        <p class="text-sm text-ink">
                            {{ t('store.nav.clearCacheHelp') }}
                        </p>
                        <div class="mt-5 flex justify-end gap-2">
                            <button
                                type="button"
                                class="cursor-pointer rounded-md border border-line-strong bg-surface px-3 py-2 text-sm font-semibold hover:bg-subtle"
                                @click="clearCacheOpen = false"
                            >
                                {{ t('common.cancel') }}
                            </button>
                            <SubmitButton
                                class="!w-auto"
                                :label="t('store.nav.clearCacheConfirm')"
                                :processing-label="t('store.orders.saving')"
                                :processing="processing"
                            />
                        </div>
                    </Form>
                </FilterDialog>
            </div>
            <div class="px-4 pt-3 sm:hidden">
                <LanguageSwitcher />
            </div>
            <SidebarUser
                :name="page.props.auth.user?.name"
                :detail="page.props.auth.user?.email"
                :collapsed="collapsed"
            >
                <Link
                    :href="profileIndex()"
                    :aria-label="t('store.nav.profile')"
                    :title="t('store.nav.profile')"
                    class="shrink-0 rounded-md p-1.5 text-sidebar-muted hover:bg-sidebar-hover hover:text-sidebar-strong"
                >
                    <CircleUser class="h-4 w-4" aria-hidden="true" />
                </Link>
                <LogoutLink
                    :href="logout.post()"
                    label-class="sr-only"
                    :title="t('nav.logout')"
                    class="flex shrink-0 cursor-pointer items-center rounded-md p-1.5 text-sidebar-muted hover:bg-sidebar-hover hover:text-sidebar-strong"
                />
            </SidebarUser>
        </template>
        <template #actions>
            <div
                class="hidden h-9 items-center rounded-md border border-line bg-surface px-2 sm:flex"
            >
                <LanguageSwitcher />
            </div>
            <a
                :href="storefrontHome.url()"
                target="_blank"
                rel="noopener"
                :aria-label="t('store.nav.browseWebsite')"
                :title="t('store.nav.browseWebsite')"
                class="grid h-9 w-9 place-items-center rounded-md border border-line bg-surface text-muted hover:bg-subtle hover:text-ink"
            >
                <Globe class="h-[18px] w-[18px]" aria-hidden="true" />
            </a>
        </template>
        <slot />
    </BackofficeShell>
</template>
