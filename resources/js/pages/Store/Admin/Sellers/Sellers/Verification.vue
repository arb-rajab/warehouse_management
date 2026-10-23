<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import {
    approve,
    reject,
    index as sellersIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/SellerController';
import PageHeader from '@/components/PageHeader.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { StoreSellerVerification } from '@/types/store';

defineProps<{ seller: StoreSellerVerification }>();
const access = useStoreAccess();
</script>

<template>
    <StoreAdminLayout>
        <Head :title="t('store.sellers.verification')" />
        <PageHeader :title="t('store.sellers.verification')"
            ><TableActionLink :href="sellersIndex()">{{
                t('store.sellers.back')
            }}</TableActionLink></PageHeader
        >
        <div class="grid gap-6 md:grid-cols-2">
            <section class="space-y-3">
                <h2 class="font-semibold">{{ t('store.sellers.userInfo') }}</h2>
                <dl class="grid grid-cols-2 gap-3">
                    <template
                        v-for="field in [
                            'name',
                            'email',
                            'address',
                            'phone',
                        ] as const"
                        :key="field"
                        ><dt>{{ t(`store.sellers.${field}`) }}</dt>
                        <dd class="break-words">
                            {{ seller.user[field] }}
                        </dd></template
                    >
                </dl>
                <h2 class="font-semibold">{{ t('store.sellers.shopInfo') }}</h2>
                <p>{{ seller.name }}</p>
                <p>{{ seller.address }}</p>
            </section>
            <section class="space-y-3">
                <h2 class="font-semibold">
                    {{ t('store.sellers.verificationInfo') }}
                </h2>
                <dl class="grid grid-cols-2 gap-3">
                    <template v-for="(field, i) in seller.verification" :key="i"
                        ><dt>{{ field.label }}</dt>
                        <dd class="break-words">
                            <a
                                v-if="field.file_url"
                                :href="field.file_url"
                                target="_blank"
                                rel="noopener"
                                class="text-blue-700 underline dark:text-blue-400"
                                >{{ t('store.sellers.download') }}</a
                            ><template v-else>{{ field.value }}</template>
                        </dd></template
                    >
                </dl>
                <div
                    v-if="
                        !seller.approved &&
                        seller.has_verification &&
                        access.can('edit_seller')
                    "
                    class="flex gap-3"
                >
                    <TableActionLink
                        :href="approve(seller)"
                        as="button"
                        class="cursor-pointer"
                        >{{ t('store.sellers.accept') }}</TableActionLink
                    >
                    <TableActionLink
                        :href="reject(seller)"
                        as="button"
                        class="cursor-pointer"
                        variant="danger"
                        >{{ t('store.sellers.reject') }}</TableActionLink
                    >
                </div>
            </section>
        </div>
    </StoreAdminLayout>
</template>
