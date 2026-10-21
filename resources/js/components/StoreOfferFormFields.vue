<script setup lang="ts">
import { useHttp } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import { brands } from '@/actions/App/Http/Controllers/Store/Admin/CampaignProductController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreCampaignFields from '@/components/StoreCampaignFields.vue';
import StoreCampaignProductRows from '@/components/StoreCampaignProductRows.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    StoreCampaignProduct,
    StoreCampaignRow,
    StoreOffer,
} from '@/types/store';
const props = defineProps<{
    offer?: StoreOffer;
    offerType?: StoreOffer['offer_type'];
    baseProductId?: number | null;
    baseQuantity?: number;
    products?: StoreCampaignRow[];
    selectedProducts: StoreCampaignProduct[];
    selectedBrand: { id: number; name: string } | null;
    errors: Record<string, string>;
}>();
const type = props.offer?.offer_type ?? props.offerType ?? 'products_offer';
const rows = ref<StoreCampaignRow[]>(
    props.products?.map((r) => ({ ...r })) ?? [],
);
const baseId = ref(props.baseProductId ?? '');
const brandId = ref(props.selectedBrand?.id ?? '');
const results = ref<{ id: number; name: string }[]>([]);
const rememberedBrands = new Map(
    props.selectedBrand ? [[props.selectedBrand.id, props.selectedBrand]] : [],
);
const brandOptions = computed(() => [
    ...new Map(
        [
            ...(props.selectedBrand ? [props.selectedBrand] : []),
            ...(rememberedBrands.has(Number(brandId.value))
                ? [rememberedBrands.get(Number(brandId.value))!]
                : []),
            ...results.value,
        ].map((b) => [b.id, b]),
    ).values(),
]);
const http = useHttp<{ q: string }, { id: number; name: string }[]>({ q: '' });
let sequence = 0;
function searchBrands() {
    const current = ++sequence;
    http.get(brands.url(), {
        onSuccess: (response) => {
            if (current !== sequence) {
                return;
            }

            for (const brand of response) {
                rememberedBrands.set(brand.id, brand);
            }

            results.value = response;
        },
    }).catch(() => undefined);
}
onMounted(() => {
    if (type === 'brand_offer') {
        searchBrands();
    }
});
</script>
<template>
    <div class="space-y-4">
        <input type="hidden" name="offer_type" :value="type" /><FieldError
            :message="errors.offer_type"
        />
        <StoreCampaignFields
            :campaign="offer"
            :title-max="255"
            :errors="errors"
        />
        <StoreCampaignProductRows
            v-model="rows"
            :selected-products="selectedProducts"
            :pinned-ids="baseId ? [Number(baseId)] : []"
            quantity
            :errors="errors"
            ><template #targeting="{ options }">
                <div v-if="type === 'brand_offer'" class="space-y-2">
                    <FormField
                        id="brand_search"
                        type="search"
                        maxlength="255"
                        :label="t('store.campaigns.fields.searchBrands')"
                        :error="http.errors.q"
                        @input="
                            http.q = ($event.target as HTMLInputElement).value;
                            searchBrands();
                        "
                    /><label for="brand_id" :class="fieldLabelClass"
                        >{{ t('store.campaigns.fields.brand')
                        }}<RequiredMark /></label
                    ><select
                        id="brand_id"
                        v-model="brandId"
                        name="brand_id"
                        required
                        :class="plainFieldInputClass"
                    >
                        <option value="" disabled>
                            {{ t('store.campaigns.fields.choose') }}
                        </option>
                        <option
                            v-for="brand in brandOptions"
                            :key="brand.id"
                            :value="brand.id"
                        >
                            {{ brand.name }}
                        </option></select
                    ><FieldError :message="errors.brand_id" />
                </div>
                <div v-else>
                    <label for="base_product_id" :class="fieldLabelClass"
                        >{{ t('store.campaigns.fields.baseProduct')
                        }}<RequiredMark /></label
                    ><select
                        id="base_product_id"
                        v-model="baseId"
                        name="base_product_id"
                        required
                        :class="plainFieldInputClass"
                    >
                        <option value="" disabled>
                            {{ t('store.campaigns.fields.choose') }}
                        </option>
                        <option
                            v-for="product in options"
                            :key="product.id"
                            :value="product.id"
                        >
                            {{ product.name }}
                        </option></select
                    ><FieldError :message="errors.base_product_id" />
                </div>
                <FormField
                    id="base_quantity"
                    type="number"
                    :min="type === 'brand_offer' ? 0.01 : 1"
                    :step="type === 'brand_offer' ? 0.01 : 1"
                    required
                    :value="baseQuantity ?? 1"
                    :label="
                        t(
                            `store.campaigns.fields.${type === 'brand_offer' ? 'spendThreshold' : 'baseQuantity'}`,
                        )
                    "
                    :error="errors.base_quantity"
                /> </template
        ></StoreCampaignProductRows>
    </div>
</template>
