<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Paperclip } from '@lucide/vue';
import { show } from '@/actions/App/Http/Controllers/Store/Admin/SupportTicketController';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import StoreSanitizedHtml from '@/components/StoreSanitizedHtml.vue';
import StoreTicketReplyForm from '@/components/StoreTicketReplyForm.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDateTime } from '@/lib/date';
import type { Paginated, PerPageFilters } from '@/types/admin';
import type { StoreTicketDetail, StoreTicketReply } from '@/types/store';
const props = defineProps<{
    ticket: StoreTicketDetail;
    replies: Paginated<StoreTicketReply>;
    filters: PerPageFilters;
}>();
function onPerPageChange(perPage: number) {
    router.get(
        show.url(props.ticket),
        { per_page: perPage },
        { preserveState: true, replace: true },
    );
}
</script>
<template>
    <Head :title="ticket.subject" /><StoreAdminLayout
        ><PageHeader :title="`${ticket.subject} #${ticket.code}`" />
        <p>{{ ticket.author }} · {{ formatDateTime(ticket.created_at) }}</p>
        <StoreSanitizedHtml :html="ticket.details_html" />
        <div class="my-3 flex flex-wrap gap-2">
            <a
                v-for="file in ticket.attachments"
                :key="file.id"
                :href="file.url"
                download
                class="flex items-center gap-1 underline"
                ><Paperclip class="h-4 w-4" />{{ file.name }}</a
            >
        </div>
        <StoreTicketReplyForm :ticket="ticket" />
        <article
            v-for="message in replies.data"
            :key="message.id"
            class="my-4 space-y-3 border-t border-gray-200 py-4 dark:border-neutral-700"
        >
            <p>
                {{ message.author }} · {{ formatDateTime(message.created_at) }}
            </p>
            <StoreSanitizedHtml :html="message.reply_html" />
            <div class="flex flex-wrap gap-2">
                <a
                    v-for="file in message.attachments"
                    :key="file.id"
                    :href="file.url"
                    download
                    class="flex items-center gap-1 underline"
                    ><Paperclip class="h-4 w-4" />{{ file.name }}</a
                >
            </div>
        </article>
        <Pagination
            :links="replies.meta.links"
            :per-page="filters.per_page ?? 20"
            @update:per-page="onPerPageChange"
    /></StoreAdminLayout>
</template>
