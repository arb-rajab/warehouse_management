<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Link, usePage } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    Boxes,
    Building2,
    CircleHelp,
    ClipboardCheck,
    Layers,
    LayoutDashboard,
    Package,
    Rows3,
    Settings,
    Truck,
    Users,
} from '@lucide/vue';
import type { Component } from 'vue';
import { computed } from 'vue';
import { index as cellLogsIndex } from '@/actions/App/Http/Controllers/Admin/CellStatusLogController';
import { index as cellVerificationRoundsIndex } from '@/actions/App/Http/Controllers/Admin/CellVerificationRoundController';
import { index as dashboardIndex } from '@/actions/App/Http/Controllers/Admin/DashboardController';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import { index as palletsIndex } from '@/actions/App/Http/Controllers/Admin/PalletController';
import { index as productsIndex } from '@/actions/App/Http/Controllers/Admin/ProductController';
import { index as receiptsIndex } from '@/actions/App/Http/Controllers/Admin/ReceiptController';
import { index as rowsIndex } from '@/actions/App/Http/Controllers/Admin/RowController';
import { edit as settingsEdit } from '@/actions/App/Http/Controllers/Admin/SettingController';
import { index as usersIndex } from '@/actions/App/Http/Controllers/Admin/UserController';
import { index as warehousesIndex } from '@/actions/App/Http/Controllers/Admin/WarehouseController';
import { index as warehouseMapIndex } from '@/actions/App/Http/Controllers/Admin/WarehouseMapController';
import BackofficeShell from '@/components/BackofficeShell.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import LogoutLink from '@/components/LogoutLink.vue';
import SidebarNavLink from '@/components/SidebarNavLink.vue';
import SidebarSectionHeading from '@/components/SidebarSectionHeading.vue';
import SidebarUser from '@/components/SidebarUser.vue';
import { t } from '@/lib/i18n';
import { isActiveNavUrl } from '@/lib/navigation';

/**
 * The warehouse (WMS) back office, drawn by the shared BackofficeShell
 * (.ai/rules/layouts.md). Its menu, groups and active-state logic live here.
 * `fullBleed` hands the whole area beside the sidebar and under the header
 * to the page (the 2D / 3D warehouse map): no width limit, no padding, and
 * the sidebar starts folded to its icon rail (remembered separately).
 */
defineProps<{
    fullBleed?: boolean;
}>();

const page = usePage();

interface NavItem {
    labelKey: string;
    href: UrlMethodPair;
    icon: Component;
}

interface NavGroup {
    labelKey?: string;
    items: NavItem[];
}

const navGroups = computed<NavGroup[]>(() => [
    {
        items: [
            {
                labelKey: 'nav.dashboard',
                href: dashboardIndex(),
                icon: LayoutDashboard,
            },
        ],
    },
    {
        labelKey: 'nav.groupInventory',
        items: [
            {
                labelKey: 'nav.warehouses',
                href: warehousesIndex(),
                icon: Building2,
            },
            { labelKey: 'nav.rows', href: rowsIndex(), icon: Rows3 },
            { labelKey: 'nav.pallets', href: palletsIndex(), icon: Layers },
            { labelKey: 'nav.receipts', href: receiptsIndex(), icon: Truck },
            {
                labelKey: 'nav.warehouseMap',
                href: warehouseMapIndex(),
                icon: Boxes,
            },
            { labelKey: 'nav.products', href: productsIndex(), icon: Package },
        ],
    },
    {
        labelKey: 'nav.groupActivity',
        items: [
            {
                labelKey: 'nav.cellLog',
                href: cellLogsIndex(),
                icon: ArrowLeftRight,
            },
            {
                labelKey: 'nav.cellVerificationRounds',
                href: cellVerificationRoundsIndex(),
                icon: ClipboardCheck,
            },
        ],
    },
    {
        labelKey: 'nav.groupAdmin',
        items: [
            { labelKey: 'nav.users', href: usersIndex(), icon: Users },
            { labelKey: 'nav.settings', href: settingsEdit(), icon: Settings },
            { labelKey: 'nav.help', href: helpIndex(), icon: CircleHelp },
        ],
    },
]);

const navItems = computed(() =>
    navGroups.value.flatMap((group) => group.items),
);

/**
 * The dashboard's href ('/admin') is itself a prefix of every other nav
 * item's href, so only the longest matching href wins (lib/navigation.ts).
 */
function isActive({ url }: UrlMethodPair): boolean {
    return isActiveNavUrl(
        page.url,
        url,
        navItems.value.map((item) => item.href.url),
    );
}

const section = computed(() => {
    const item = navItems.value.find((candidate) => isActive(candidate.href));

    return item ? t(item.labelKey) : undefined;
});
</script>

<template>
    <BackofficeShell
        sidebar-id="admin-sidebar"
        :area="t('nav.brand')"
        :section="section"
        :full-bleed="fullBleed"
        :collapse-scope="fullBleed ? 'map' : 'page'"
        :default-collapsed="fullBleed"
    >
        <template #nav="{ close, collapsed }">
            <nav
                :aria-label="t('nav.menuLabel')"
                class="flex-1 overflow-y-auto px-2.5 pb-3"
            >
                <div v-for="group in navGroups" :key="group.labelKey ?? ''">
                    <SidebarSectionHeading
                        v-if="group.labelKey"
                        :label="t(group.labelKey)"
                        :collapsed="collapsed"
                    />
                    <ul class="space-y-0.5">
                        <li v-for="item in group.items" :key="item.labelKey">
                            <SidebarNavLink
                                :href="item.href"
                                :label="t(item.labelKey)"
                                :icon="item.icon"
                                :active="isActive(item.href)"
                                :collapsed="collapsed"
                                @click="close()"
                            />
                        </li>
                    </ul>
                </div>
            </nav>
        </template>
        <template #footer="{ collapsed }">
            <div class="px-4 pt-3 sm:hidden">
                <LanguageSwitcher />
            </div>
            <SidebarUser
                :name="page.props.auth.user?.name"
                :detail="page.props.auth.user?.email"
                :collapsed="collapsed"
            >
                <LogoutLink
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
            <Link
                :href="helpIndex()"
                :aria-label="t('nav.help')"
                :title="t('nav.help')"
                class="grid h-9 w-9 place-items-center rounded-md border border-line bg-surface text-muted hover:bg-subtle hover:text-ink"
            >
                <CircleHelp class="h-[18px] w-[18px]" aria-hidden="true" />
            </Link>
        </template>
        <slot />
    </BackofficeShell>
</template>
