<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import {
    index as queriesIndex,
    reply,
} from '@/actions/App/Http/Controllers/Store/Admin/ProductQueryController';
import DetailItem from '@/components/DetailItem.vue';
import DetailList from '@/components/DetailList.vue';
import PageHeader from '@/components/PageHeader.vue';
import StoreSupportReplyForm from '@/components/StoreSupportReplyForm.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableObjectLink from '@/components/TableObjectLink.vue';
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
        <DetailList :columns="2" class="mb-6">
            <DetailItem :label="t('store.support.customer')"
                ><TableObjectLink
                    v-if="query.user"
                    :allowed="access.can('edit_customer')"
                    :href="customerEdit(query.user)"
                    >{{ query.user.name }}</TableObjectLink
                ><template v-else>{{
                    t('store.support.missingUser')
                }}</template></DetailItem
            >
            <DetailItem
                :label="t('store.support.date')"
                :value="
                    query.created_at ? formatDateTime(query.created_at) : null
                "
            />
            <DetailItem
                full
                preserve
                :label="t('store.support.question')"
                :value="query.question"
            />
            <DetailItem
                full
                preserve
                :label="t('store.support.reply')"
                :value="query.reply ?? t('store.support.unanswered')"
            />
        </DetailList>
        <StoreSupportReplyForm
            v-if="canReply && access.can('reply_to_product_queries')"
            :action="reply(query)"
            field="reply"
        />
    </StoreAdminLayout>
</template>
