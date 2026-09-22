<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    CircleUser,
    ClipboardCheck,
    LayoutDashboard,
    Map,
    Menu,
    Package,
    Rows3,
    Settings,
    Users,
    Warehouse,
    X,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { index as cellVerificationRoundsIndex } from '@/actions/App/Http/Controllers/Admin/CellVerificationRoundController';
import { index as dashboardIndex } from '@/actions/App/Http/Controllers/Admin/DashboardController';
import { index as productsIndex } from '@/actions/App/Http/Controllers/Admin/ProductController';
import { index as rowsIndex } from '@/actions/App/Http/Controllers/Admin/RowController';
import { edit as settingsEdit } from '@/actions/App/Http/Controllers/Admin/SettingController';
import { index as usersIndex } from '@/actions/App/Http/Controllers/Admin/UserController';
import AccountMenu from '@/components/AccountMenu.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import LogoutLink from '@/components/LogoutLink.vue';
import { t } from '@/lib/i18n';

const page = usePage();

const isMobileMenuOpen = ref(false);

const navItems = computed(() => [
    {
        labelKey: 'nav.dashboard',
        href: dashboardIndex().url,
        icon: LayoutDashboard,
    },
    { labelKey: 'nav.rows', href: rowsIndex().url, icon: Rows3 },
    { labelKey: 'nav.map', href: cellsIndex().url, icon: Map },
    {
        labelKey: 'nav.cellLog',
        href: cellLogsIndex().url,
        icon: ArrowLeftRight,
    },
    {
        labelKey: 'nav.cellVerificationRounds',
        href: cellVerificationRoundsIndex().url,
        icon: ClipboardCheck,
    },
    { labelKey: 'nav.products', href: productsIndex().url, icon: Package },
    { labelKey: 'nav.users', href: usersIndex().url, icon: Users },
    {
        labelKey: 'nav.settings',
        href: settingsEdit().url,
        icon: Settings,
    },
]);

/**
 * A nav item matches on exact URL or as a path prefix, but the dashboard's
 * href ('/admin') is itself a prefix of every other nav item's href, so a
 * plain prefix check would mark it active alongside whichever section is
 * actually open. Only the longest matching href wins.
 */
function isActive(href: string): boolean {
    const currentPath = page.url.split('?')[0];
    const matches = (candidate: string) =>
        currentPath === candidate || currentPath.startsWith(`${candidate}/`);

    if (!matches(href)) {
        return false;
    }

    return !navItems.value.some(
        (item) => item.href.length > href.length && matches(item.href),
    );
}

function navLinkStateClass(href: string): string[] {
    return isActive(href)
        ? [
              'border-gray-900',
              'font-medium',
              'text-gray-900',
              'dark:border-white',
              'dark:text-white',
          ]
        : [
              'border-transparent',
              'text-gray-600',
              'hover:text-gray-900',
              'dark:text-neutral-400',
              'dark:hover:text-white',
          ];
}
</script>

<template>
    <div
        class="min-h-screen bg-gray-50 text-gray-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <nav
            class="border-b border-gray-200 bg-white dark:border-neutral-800 dark:bg-neutral-900"
        >
            <div
                class="mx-auto flex max-w-6xl items-center justify-between px-4 sm:px-6"
            >
                <div class="flex items-center gap-6">
                    <span
                        class="inline-flex items-center gap-1.5 py-3 font-semibold"
                    >
                        <Warehouse class="h-5 w-5 shrink-0" />
                        {{ t('nav.brand') }}
                    </span>
                    <div class="hidden items-center gap-6 xl:flex">
                        <Link
                            v-for="item in navItems"
                            :key="item.labelKey"
                            :href="item.href"
                            :aria-current="
                                isActive(item.href) ? 'page' : undefined
                            "
                            class="inline-flex items-center gap-1.5 border-b-2 py-3 text-sm transition-colors"
                            :class="navLinkStateClass(item.href)"
                        >
                            <component :is="item.icon" class="h-4 w-4" />
                            {{ t(item.labelKey) }}
                        </Link>
                    </div>
                </div>
                <div class="hidden items-center xl:flex">
                    <AccountMenu :user-name="page.props.auth.user?.name" />
                </div>
                <button
                    type="button"
                    class="cursor-pointer rounded-md p-2 text-gray-600 hover:bg-gray-100 hover:text-gray-900 xl:hidden dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-white"
                    :aria-label="
                        t(isMobileMenuOpen ? 'nav.closeMenu' : 'nav.openMenu')
                    "
                    aria-controls="admin-mobile-menu"
                    :aria-expanded="isMobileMenuOpen"
                    @click="isMobileMenuOpen = !isMobileMenuOpen"
                >
                    <component
                        :is="isMobileMenuOpen ? X : Menu"
                        class="h-5 w-5"
                    />
                </button>
            </div>
            <div
                v-if="isMobileMenuOpen"
                id="admin-mobile-menu"
                class="border-t border-gray-200 xl:hidden dark:border-neutral-800"
            >
                <div class="flex flex-col gap-1 px-4 py-3">
                    <Link
                        v-for="item in navItems"
                        :key="item.labelKey"
                        :href="item.href"
                        :aria-current="isActive(item.href) ? 'page' : undefined"
                        class="flex items-center gap-2 rounded-md border-s-4 px-3 py-2 text-sm transition-colors"
                        :class="navLinkStateClass(item.href)"
                        @click="isMobileMenuOpen = false"
                    >
                        <component :is="item.icon" class="h-4 w-4" />
                        {{ t(item.labelKey) }}
                    </Link>
                </div>
                <div
                    class="flex items-center justify-between border-t border-gray-200 px-4 py-3 text-sm dark:border-neutral-800"
                >
                    <LanguageSwitcher />
                </div>
                <div
                    class="flex items-center justify-between border-t border-gray-200 px-4 py-3 text-sm dark:border-neutral-800"
                >
                    <span
                        class="inline-flex items-center gap-1.5 text-gray-600 dark:text-neutral-400"
                    >
                        <CircleUser class="h-4 w-4 shrink-0" />
                        {{ page.props.auth.user?.name }}
                    </span>
                    <LogoutLink
                        class="inline-flex items-center gap-1.5 text-gray-600 hover:text-gray-900 dark:text-neutral-400 dark:hover:text-white"
                        @click="isMobileMenuOpen = false"
                    />
                </div>
            </div>
        </nav>
        <main class="mx-auto max-w-6xl px-4 py-8 sm:px-6">
            <slot />
        </main>
    </div>
</template>
