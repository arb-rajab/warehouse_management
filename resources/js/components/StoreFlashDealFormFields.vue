<script setup lang="ts">
import { ref } from 'vue';
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
    StoreFlashDeal,
} from '@/types/store';
const props = defineProps<{
    deal?: StoreFlashDeal;
    title?: string;
    products?: StoreCampaignRow[];
    selectedProducts: StoreCampaignProduct[];
    errors: Record<string, string>;
}>();
const rows = ref<StoreCampaignRow[]>(
    props.products?.map((r) => ({ ...r })) ?? [],
);
</script>
<template>
    <div class="space-y-4">
        <StoreCampaignFields
            :campaign="deal"
            :title="title"
            :title-max="50"
            :errors="errors"
        />
        <FormField
            id="background_color"
            :label="t('store.campaigns.fields.backgroundColor')"
            :value="deal?.background_color ?? '#ffffff'"
            required
            maxlength="255"
            pattern="#[a-fA-F0-9]{3}|#[a-fA-F0-9]{6}|[a-zA-Z]+"
            :error="errors.background_color"
        />
        <div>
            <label for="text_color" :class="fieldLabelClass"
                >{{ t('store.campaigns.fields.textColor')
                }}<RequiredMark /></label
            ><select
                id="text_color"
                name="text_color"
                :value="deal?.text_color ?? 'dark'"
                required
                :class="plainFieldInputClass"
            >
                <option value="dark">
                    {{ t('store.campaigns.fields.dark') }}
                </option>
                <option value="light">
                    {{ t('store.campaigns.fields.light') }}
                </option></select
            ><FieldError :message="errors.text_color" />
        </div>
        <StoreCampaignProductRows
            v-model="rows"
            :selected-products="selectedProducts"
            :errors="errors"
        />
    </div>
</template>
