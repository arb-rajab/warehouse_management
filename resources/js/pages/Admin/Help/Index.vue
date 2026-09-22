<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import {
    ArrowLeftRight,
    ClipboardCheck,
    LayoutDashboard,
    Map,
    Package,
    Rows3,
    Settings,
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
    { key: 'cells', topic: 'cells', icon: Map },
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

        <p class="mb-6 text-sm text-gray-600 dark:text-neutral-400">
            {{ t('help.index.intro') }}
        </p>

        <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <li v-for="topic in topics" :key="topic.key">
                <Link
                    :href="showHelp(topic.topic)"
                    class="flex h-full flex-col gap-2 rounded-lg border border-gray-200 bg-white p-4 hover:border-gray-300 dark:border-neutral-800 dark:bg-neutral-900 dark:hover:border-neutral-700"
                >
                    <span
                        class="inline-flex items-center gap-2 font-medium text-gray-900 dark:text-neutral-100"
                    >
                        <component :is="topic.icon" class="h-4 w-4 shrink-0" />
                        {{ t(`help.index.topics.${topic.key}.title`) }}
                    </span>
                    <span class="text-sm text-gray-600 dark:text-neutral-400">
                        {{ t(`help.index.topics.${topic.key}.description`) }}
                    </span>
                </Link>
            </li>
        </ul>
    </AdminLayout>
</template>
