<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    index as conversationsIndex,
    reply,
    show,
} from '@/actions/App/Http/Controllers/Store/Admin/ConversationController';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreSupportReplyForm from '@/components/StoreSupportReplyForm.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreConversation, StoreMessage } from '@/types/store';

const props = defineProps<{
    conversation: StoreConversation;
    messages: Paginated<StoreMessage>;
    filters: PerPageFilters;
    canReply: boolean;
}>();
const access = useStoreAccess();
function onPerPageChange(perPage: number): void {
    router.get(
        show.url(props.conversation),
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="conversation.title ?? t('store.support.conversations')" />
    <StoreAdminLayout>
        <PageHeader
            :title="conversation.title ?? t('store.support.conversations')"
            ><TableActionLink :href="conversationsIndex()">{{
                t('store.support.back')
            }}</TableActionLink></PageHeader
        >
        <p class="mb-4">
            {{
                t('store.support.between', {
                    sender:
                        conversation.sender?.name ??
                        t('store.support.missingUser'),
                    receiver:
                        conversation.receiver?.name ??
                        t('store.support.missingUser'),
                })
            }}
        </p>
        <ul class="space-y-4">
            <li
                v-for="message in messages.data"
                :key="message.id"
                class="rounded-md border border-gray-200 p-4 dark:border-neutral-700"
            >
                <p class="font-medium">
                    {{ message.user?.name ?? t('store.support.missingUser') }}
                </p>
                <p class="text-sm text-gray-600 dark:text-neutral-400">
                    {{
                        message.created_at
                            ? formatDateTime(message.created_at)
                            : ''
                    }}
                </p>
                <p class="whitespace-pre-wrap">{{ message.message }}</p>
            </li>
        </ul>
        <Pagination
            :links="messages.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
        <StoreSupportReplyForm
            v-if="canReply && access.can('reply_to_product_conversations')"
            class="mt-6"
            :action="reply(conversation)"
            field="message"
        />
    </StoreAdminLayout>
</template>
