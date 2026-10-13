<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { index as notificationsIndex } from '@/actions/App/Http/Controllers/Store/Admin/NotificationController';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDateTime } from '@/lib/date';
import { i18n, t } from '@/lib/i18n';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { AdminNotification } from '@/types/storeNotifications';

defineProps<{
    notifications: Paginated<AdminNotification>;
    filters: PerPageFilters;
}>();

function statusLabel(status: string | null): string {
    if (status === null) {
        return '';
    }

    const key = `store.notifications.statuses.${status}`;

    return i18n.global.te(key) ? t(key) : status.replaceAll('_', ' ');
}

function onPerPageChange(perPage: number): void {
    router.get(
        notificationsIndex().url,
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="t('store.notifications.personal.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.notifications.personal.title')" />
        <ul class="divide-y divide-gray-200 dark:divide-neutral-700">
            <li
                v-for="notification in notifications.data"
                :key="notification.id"
                class="py-3"
            >
                <p v-if="notification.kind === 'order'">
                    {{ t('store.notifications.personal.orderCode') }}
                    <a
                        v-if="notification.url"
                        :href="notification.url"
                        class="text-blue-600 underline dark:text-blue-400"
                        >{{ notification.order_code }}</a
                    >
                    <span v-else>{{ notification.order_code }}</span>
                    {{
                        t('store.notifications.personal.status', {
                            status: statusLabel(notification.status),
                        })
                    }}
                </p>
                <a
                    v-else-if="notification.kind === 'cart' && notification.url"
                    :href="notification.url"
                    class="text-blue-600 underline dark:text-blue-400"
                    >{{ notification.customer_name }}
                    {{ notification.message }}</a
                >
                <p v-else>{{ t('store.notifications.personal.other') }}</p>
                <time
                    v-if="notification.created_at"
                    :datetime="notification.created_at"
                    class="text-sm text-gray-500 dark:text-neutral-400"
                    >{{ formatDateTime(notification.created_at) }}</time
                >
            </li>
            <li v-if="notifications.data.length === 0" class="py-6 text-center">
                {{ t('store.notifications.index.empty') }}
            </li>
        </ul>
        <Pagination
            :links="notifications.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
