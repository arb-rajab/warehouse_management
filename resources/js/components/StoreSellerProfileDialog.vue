<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { LoaderCircle } from '@lucide/vue';
import { onBeforeUnmount, ref, watch } from 'vue';
import { profile } from '@/actions/App/Http/Controllers/Store/Admin/SellerController';
import ActionErrorBanner from '@/components/ActionErrorBanner.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import { t } from '@/lib/i18n';
import type { StoreSellerProfile } from '@/types/store';

const props = defineProps<{ sellerId: number | null }>();
const open = defineModel<boolean>('open', { required: true });
const details = ref<StoreSellerProfile | null>(null);
const failed = ref(false);
const http = useHttp<{ id: number }, StoreSellerProfile>({ id: 0 });
let sequence = 0;
watch(
    [open, () => props.sellerId],
    async () => {
        const current = ++sequence;
        http.cancel();
        details.value = null;
        failed.value = false;

        if (!open.value || props.sellerId === null) {
            return;
        }

        http.id = props.sellerId;

        try {
            const response = await http.post(profile.url());

            if (current === sequence) {
                details.value = response ?? null;
                failed.value = !response;
            }
        } catch {
            if (current === sequence) {
                failed.value = true;
            }
        }
    },
    { immediate: true },
);
onBeforeUnmount(() => http.cancel());
const fields = [
    'bank_name',
    'bank_acc_name',
    'bank_acc_no',
    'bank_routing_no',
    'products_count',
    'orders_count',
    'sold_amount',
    'wallet_balance',
] as const;
</script>

<template>
    <FilterDialog
        v-model:open="open"
        :title="t('store.sellers.profile')"
        :close-label="t('common.close')"
    >
        <p v-if="http.processing" role="status" class="flex items-center gap-2">
            <LoaderCircle class="h-4 w-4 animate-spin" />{{
                t('store.sellers.loading')
            }}
        </p>
        <ActionErrorBanner
            :message="failed ? t('store.sellers.profileError') : undefined"
        />
        <div v-if="details" class="space-y-4">
            <img
                v-if="details.avatar_url"
                :src="details.avatar_url"
                :alt="details.name"
                class="h-20 w-20 rounded-full object-cover"
            />
            <h3 class="font-semibold">{{ details.name }}</h3>
            <p>{{ details.address }}</p>
            <p>{{ details.phone }}</p>
            <a
                v-if="details.shop_url"
                :href="details.shop_url"
                class="text-blue-700 underline dark:text-blue-400"
                >{{ details.shop_name }}</a
            >
            <p v-else>{{ details.shop_name }}</p>
            <div class="flex gap-3">
                <template v-for="(url, key) in details.social" :key="key"
                    ><a
                        v-if="url"
                        :href="url"
                        class="text-blue-700 underline dark:text-blue-400"
                        >{{ t(`store.sellers.${key}`) }}</a
                    ></template
                >
            </div>
            <h3 class="font-semibold">{{ t('store.sellers.payoutInfo') }}</h3>
            <dl class="grid grid-cols-2 gap-3">
                <template v-for="field in fields" :key="field"
                    ><dt>{{ t(`store.sellers.${field}`) }}</dt>
                    <dd class="break-words">{{ details[field] }}</dd></template
                >
            </dl>
        </div>
    </FilterDialog>
</template>
