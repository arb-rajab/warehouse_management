<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    index as queriesIndex,
    reply,
} from '@/actions/App/Http/Controllers/Store/Admin/ProductQueryController';
import PageHeader from '@/components/PageHeader.vue';
import StoreSupportReplyForm from '@/components/StoreSupportReplyForm.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { StoreProductQuery } from '@/types/store';

defineProps<{ query: StoreProductQuery; canReply: boolean }>();
const access = useStoreAccess();
</script>

<template>
    <Head :title="query.product?.name ?? t('store.support.missingProduct')" />
    <StoreAdminLayout>
        <PageHeader
            :title="query.product?.name ?? t('store.support.missingProduct')"
            ><TableActionLink :href="queriesIndex()">{{
                t('store.support.back')
            }}</TableActionLink></PageHeader
        >
        <dl class="mb-6 space-y-3">
            <div>
                <dt class="font-medium">{{ t('store.support.customer') }}</dt>
                <dd>
                    {{ query.user?.name ?? t('store.support.missingUser') }}
                </dd>
            </div>
            <div>
                <dt class="font-medium">{{ t('store.support.date') }}</dt>
                <dd>
                    {{
                        query.created_at ? formatDateTime(query.created_at) : ''
                    }}
                </dd>
            </div>
            <div>
                <dt class="font-medium">{{ t('store.support.question') }}</dt>
                <dd class="whitespace-pre-wrap">{{ query.question }}</dd>
            </div>
            <div>
                <dt class="font-medium">{{ t('store.support.reply') }}</dt>
                <dd class="whitespace-pre-wrap">
                    {{ query.reply ?? t('store.support.unanswered') }}
                </dd>
            </div>
        </dl>
        <StoreSupportReplyForm
            v-if="canReply && access.can('reply_to_product_queries')"
            :action="reply(query)"
            field="reply"
        />
    </StoreAdminLayout>
</template>
