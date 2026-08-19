<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    CircleUser,
    LayoutDashboard,
    LogOut,
    Map,
    Rows3,
    Users,
    Warehouse,
} from '@lucide/vue';
import { computed } from 'vue';
import { index as cellsIndex } from '@/actions/App/Http/Controllers/Admin/CellController';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { index as dashboardIndex } from '@/actions/App/Http/Controllers/Admin/DashboardController';
import { index as rowsIndex } from '@/actions/App/Http/Controllers/Admin/RowController';
import { index as usersIndex } from '@/actions/App/Http/Controllers/Admin/UserController';
import { destroy } from '@/actions/App/Http/Controllers/LoginController';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import { t } from '@/lib/i18n';

const page = usePage();

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
    { labelKey: 'nav.users', href: usersIndex().url, icon: Users },
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
</script>

<template>
    <div
        class="min-h-screen bg-gray-50 text-gray-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <nav
            class="border-b border-gray-200 bg-white dark:border-neutral-800 dark:bg-neutral-900"
        >
            <div
                class="mx-auto flex max-w-6xl items-center justify-between px-6"
            >
                <div class="flex items-center gap-6">
                    <span
                        class="inline-flex items-center gap-1.5 py-3 font-semibold"
                    >
                        <Warehouse class="h-5 w-5 shrink-0" />
                        {{ t('nav.brand') }}
                    </span>
                    <Link
                        v-for="item in navItems"
                        :key="item.labelKey"
                        :href="item.href"
                        :aria-current="isActive(item.href) ? 'page' : undefined"
                        class="inline-flex items-center gap-1.5 border-b-2 py-3 text-sm transition-colors"
                        :class="
                            isActive(item.href)
                                ? 'border-gray-900 font-medium text-gray-900 dark:border-white dark:text-white'
                                : 'border-transparent text-gray-600 hover:text-gray-900 dark:text-neutral-400 dark:hover:text-white'
                        "
                    >
                        <component :is="item.icon" class="h-4 w-4" />
                        {{ t(item.labelKey) }}
                    </Link>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <LanguageSwitcher />
                    <span
                        class="inline-flex items-center gap-1.5 text-gray-600 dark:text-neutral-400"
                    >
                        <CircleUser class="h-4 w-4 shrink-0" />
                        {{ page.props.auth.user?.name }}
                    </span>
                    <Link
                        :href="destroy()"
                        as="button"
                        class="inline-flex items-center gap-1.5 text-gray-600 hover:text-gray-900 dark:text-neutral-400 dark:hover:text-white"
                    >
                        <LogOut class="h-4 w-4" />
                        {{ t('nav.logout') }}
                    </Link>
                </div>
            </div>
        </nav>
        <main class="mx-auto max-w-6xl px-6 py-8">
            <slot />
        </main>
    </div>
</template>
