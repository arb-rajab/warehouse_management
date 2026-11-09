<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    ClipboardCheck,
    LayoutDashboard,
    Boxes,
    Building2,
    LayoutGrid,
    Package,
    Rows3,
    Settings,
    Truck,
    Users,
} from '@lucide/vue';
import type { Component } from 'vue';
import { show as showHelp } from '@/actions/App/Http/Controllers/Admin/HelpController';
import PageHeader from '@/components/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';

const topics: { key: string; topic: string; icon: Component }[] = [
    { key: 'dashboard', topic: 'dashboard', icon: LayoutDashboard },
    { key: 'rows', topic: 'rows', icon: Rows3 },
    { key: 'warehouses', topic: 'warehouses', icon: Building2 },
    { key: 'cells', topic: 'cells', icon: LayoutGrid },
    { key: 'receipts', topic: 'receipts', icon: Truck },
    { key: 'warehouseMap', topic: 'warehouse-map', icon: Boxes },
    { key: 'cellLogs', topic: 'cell-logs', icon: ArrowLeftRight },
    {
        key: 'cellVerificationRounds',
        topic: 'cell-verification-rounds',
        icon: ClipboardCheck,
    },
    { key: 'products', topic: 'products', icon: Package },
    { key: 'users', topic: 'users', icon: Users },
    { key: 'settings', topic: 'settings', icon: Settings },
];
</script>

<template>
    <Head :title="t('help.index.title')" />

    <AdminLayout>
        <PageHeader :title="t('help.index.title')" />

        <p class="mb-6 text-sm text-muted">
            {{ t('help.index.intro') }}
        </p>

        <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <li v-for="topic in topics" :key="topic.key">
                <Link
                    :href="showHelp(topic.topic)"
                    class="flex h-full flex-col gap-2 rounded-lg border border-line bg-surface p-4 hover:border-line-strong"
                >
                    <span
                        class="inline-flex items-center gap-2 font-medium text-ink"
                    >
                        <component :is="topic.icon" class="h-4 w-4 shrink-0" />
                        {{ t(`help.index.topics.${topic.key}.title`) }}
                    </span>
                    <span class="text-sm text-muted">
                        {{ t(`help.index.topics.${topic.key}.description`) }}
                    </span>
                </Link>
            </li>
        </ul>
    </AdminLayout>
</template>
