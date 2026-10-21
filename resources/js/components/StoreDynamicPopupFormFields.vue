<script setup lang="ts">
import { ref } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreDynamicPopup, StoreMarketingOption } from '@/types/store';

const props = defineProps<{
    popup?: StoreDynamicPopup;
    offers: StoreMarketingOption[];
    categories: StoreMarketingOption[];
    errors: Record<string, string>;
}>();
const banner = ref(props.popup?.banner ?? '');
const summary = ref(props.popup?.summary ?? '');
const offerId = ref(props.popup?.offer_id ?? '');
const showPage = ref(props.popup?.show_page ?? '');
</script>

<template>
    <div class="grid gap-4 md:grid-cols-2">
        <FormField
            id="title"
            :label="t('store.dynamicPopups.fields.title')"
            :value="popup?.title ?? ''"
            :error="errors.title"
            required
            maxlength="100"
        />
        <div>
            <label for="summary" :class="fieldLabelClass"
                >{{ t('store.dynamicPopups.fields.summary') }}<RequiredMark
            /></label>
            <textarea
                id="summary"
                name="summary"
                v-model="summary"
                :class="plainFieldInputClass"
                required
                maxlength="191"
                rows="3"
            />
            <FieldError :message="errors.summary" />
        </div>
        <StoreUploadField
            id="banner"
            name="banner"
            v-model="banner"
            :label="t('store.dynamicPopups.fields.banner')"
            type="image"
            required
            :error="errors.banner"
        />
        <FormField
            id="btn_text"
            :label="t('store.dynamicPopups.fields.buttonText')"
            :value="popup?.btn_text ?? ''"
            :error="errors.btn_text"
            required
            maxlength="191"
        />
        <FormField
            id="delay_seconds"
            type="number"
            :label="t('store.dynamicPopups.fields.delay')"
            :value="popup?.delay_seconds ?? 10"
            :error="errors.delay_seconds"
            required
            min="0"
            max="100"
            step="1"
        />
        <div>
            <label for="offer_id" :class="fieldLabelClass"
                >{{ t('store.dynamicPopups.fields.offer') }}<RequiredMark
            /></label>
            <select
                id="offer_id"
                name="offer_id"
                v-model="offerId"
                :class="plainFieldInputClass"
                required
            >
                <option value="">
                    {{ t('store.dynamicPopups.fields.choose') }}
                </option>
                <option
                    v-for="offer in offers"
                    :key="offer.id"
                    :value="offer.id"
                >
                    {{ offer.name }}
                </option>
            </select>
            <FieldError :message="errors.offer_id" />
        </div>
        <div>
            <label for="show_page" :class="fieldLabelClass"
                >{{ t('store.dynamicPopups.fields.page') }}<RequiredMark
            /></label>
            <select
                id="show_page"
                name="show_page"
                v-model="showPage"
                :class="plainFieldInputClass"
                required
            >
                <option value="">
                    {{ t('store.dynamicPopups.fields.choose') }}
                </option>
                <option value="0">
                    {{ t('store.dynamicPopups.fields.home') }}
                </option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="category.id"
                >
                    {{ category.name }}
                </option>
            </select>
            <FieldError :message="errors.show_page" />
        </div>
    </div>
</template>
