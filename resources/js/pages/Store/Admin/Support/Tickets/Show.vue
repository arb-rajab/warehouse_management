<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Paperclip } from '@lucide/vue';
import { computed } from 'vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import {
    index as ticketsIndex,
    show,
} from '@/actions/App/Http/Controllers/Store/Admin/SupportTicketController';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreSanitizedHtml from '@/components/StoreSanitizedHtml.vue';
import StoreTicketReplyForm from '@/components/StoreTicketReplyForm.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import { ticketStatusLabel } from '@/lib/storeSupportTicket';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type {
    StoreTicketDetail,
    StoreTicketReply,
    StoreUpload,
} from '@/types/store';

const props = defineProps<{
    ticket: StoreTicketDetail;
    replies: Paginated<StoreTicketReply>;
    filters: PerPageFilters;
}>();
const access = useStoreAccess();
const title = computed(() =>
    t('store.support.ticketTitle', {
        subject: props.ticket.subject,
        code: props.ticket.code,
    }),
);

interface TicketMessage {
    key: string;
    author: string;
    createdAt: string;
    html: string;
    attachments: StoreUpload[];
    original: boolean;
}

/**
 * Newest first, as the Blade page listed them: the replies, then the
 * customer's original request, which closes the last page.
 */
const messages = computed<TicketMessage[]>(() => {
    const replies = props.replies.data.map((reply) => ({
        key: `reply-${reply.id}`,
        author: reply.user?.name ?? t('store.support.missingUser'),
        createdAt: reply.created_at,
        html: reply.reply_html,
        attachments: reply.attachments,
        original: false,
    }));
    const { current_page, last_page } = props.replies.meta;

    return current_page < last_page
        ? replies
        : [
              ...replies,
              {
                  key: 'original',
                  author:
                      props.ticket.user?.name ?? t('store.support.missingUser'),
                  createdAt: props.ticket.created_at,
                  html: props.ticket.details_html,
                  attachments: props.ticket.attachments,
                  original: true,
              },
          ];
});

function onPerPageChange(perPage: number): void {
    router.get(
        show.url(props.ticket),
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>

<template>
    <Head :title="title" />
    <StoreAdminLayout>
        <PageHeader :title="title"
            ><TableActionLink :href="ticketsIndex()">{{
                t('store.support.back')
            }}</TableActionLink></PageHeader
        >
        <p class="mb-6 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm">
            <span
                ><TableObjectLink
                    v-if="ticket.user"
                    :allowed="access.can('edit_customer')"
                    :href="customerEdit(ticket.user)"
                    >{{ ticket.user.name }}</TableObjectLink
                ><template v-else>{{
                    t('store.support.missingUser')
                }}</template></span
            >
            <span>{{ formatDateTime(ticket.created_at) }}</span>
            <span class="rounded-full bg-subtle px-2 py-0.5 text-ink"
                >{{ t('store.support.status') }}:
                {{ ticketStatusLabel(ticket.status) }}</span
            >
        </p>
        <StoreTicketReplyForm
            v-if="access.can('reply_to_support_tickets')"
            class="mb-8"
            :ticket="ticket"
        />
        <p v-if="replies.data.length === 0" class="mb-4 text-sm text-muted">
            {{ t('store.support.noReplies') }}
        </p>
        <ul class="space-y-4">
            <li
                v-for="message in messages"
                :key="message.key"
                class="rounded-md border border-line p-4"
            >
                <p
                    v-if="message.original"
                    class="mb-1 text-xs font-semibold tracking-wide text-muted uppercase"
                >
                    {{ t('store.support.originalMessage') }}
                </p>
                <p class="font-medium">{{ message.author }}</p>
                <p class="mb-3 text-sm text-muted">
                    {{ formatDateTime(message.createdAt) }}
                </p>
                <StoreSanitizedHtml :html="message.html" />
                <ul
                    v-if="message.attachments.length > 0"
                    class="mt-3 flex flex-wrap gap-2"
                    :aria-label="t('store.support.attachments')"
                >
                    <li
                        v-for="file in message.attachments"
                        :key="file.id"
                        class="flex items-center gap-1 rounded-full bg-subtle px-3 py-1 text-sm"
                    >
                        <Paperclip class="h-3.5 w-3.5" aria-hidden="true" />
                        <a
                            v-if="file.url"
                            :href="file.url"
                            download
                            class="underline"
                            >{{
                                file.name ?? t('store.uploads.unknownName')
                            }}</a
                        >
                        <span v-else>{{
                            file.name ?? t('store.uploads.unknownName')
                        }}</span>
                    </li>
                </ul>
            </li>
        </ul>
        <Pagination
            :links="replies.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
        />
    </StoreAdminLayout>
</template>
