<script setup lang="ts">
import { ref } from 'vue';
import {
    brands,
    index as productIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/CampaignProductController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreCampaignFields from '@/components/StoreCampaignFields.vue';
import StoreCampaignProductRows from '@/components/StoreCampaignProductRows.vue';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import { t } from '@/lib/i18n';
import type { SearchOption } from '@/types/searchSelect';
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
    selectedBrand: SearchOption | null;
    errors: Record<string, string>;
}>();
const type = props.offer?.offer_type ?? props.offerType ?? 'products_offer';
const rows = ref<StoreCampaignRow[]>(
    props.products?.map((r) => ({ ...r })) ?? [],
);
const baseProduct = ref<SearchOption | null>(
    props.selectedProducts.find(
        (product) => Number(product.id) === Number(props.baseProductId),
    ) ?? null,
);
const brand = ref<SearchOption | null>(props.selectedBrand);
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
            quantity
            :errors="errors"
            ><template #targeting>
                <StoreSearchSelect
                    v-if="type === 'brand_offer'"
                    id="brand_id"
                    v-model="brand"
                    name="brand_id"
                    :label="t('store.campaigns.fields.brand')"
                    :endpoint="brands"
                    :placeholder="t('store.campaigns.fields.chooseBrand')"
                    :search-placeholder="
                        t('store.campaigns.fields.searchBrands')
                    "
                    required
                    :error="errors.brand_id"
                />
                <StoreSearchSelect
                    v-else
                    id="base_product_id"
                    v-model="baseProduct"
                    name="base_product_id"
                    :label="t('store.campaigns.fields.baseProduct')"
                    :endpoint="productIndex"
                    :placeholder="t('store.campaigns.fields.chooseProduct')"
                    :search-placeholder="
                        t('store.campaigns.fields.searchProducts')
                    "
                    required
                    :error="errors.base_product_id"
                />
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
