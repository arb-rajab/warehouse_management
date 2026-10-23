<script setup lang="ts">
import { ref } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreProductDiscountFields from '@/components/StoreProductDiscountFields.vue';
import StoreProductTaxFields from '@/components/StoreProductTaxFields.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { productAmountMax } from '@/lib/storeProductForm';
import type {
    StoreDigitalProductForm,
    StoreDigitalProductTax,
    StoreReferenceOption,
    StoreUpload,
} from '@/types/store';

const props = defineProps<{
    product?: StoreDigitalProductForm;
    name?: string;
    description?: string;
    categories: StoreReferenceOption[];
    taxes: StoreDigitalProductTax[];
    initialFiles?: Record<string, StoreUpload[]>;
    errors: Record<string, string>;
}>();
const categoryId = ref(
    props.product?.category_id == null ? '' : String(props.product.category_id),
);
const file = ref(props.product?.file ?? '');
const photos = ref(props.product?.photos ?? '');
const thumbnail = ref(props.product?.thumbnail_img ?? '');
const metaImage = ref(props.product?.meta_img ?? '');
const metaDescription = ref(props.product?.meta_description ?? '');
const content = ref(props.description ?? '');
const sectionClass =
    'grid gap-4 rounded-lg border border-gray-200 p-4 lg:grid-cols-2 dark:border-neutral-800';
const legendClass = 'px-1 text-sm font-semibold';
</script>

<template>
    <div class="space-y-6">
        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.digitalProducts.form.general') }}
            </legend>
            <FormField
                id="name"
                :label="t('store.digitalProducts.form.name')"
                :value="name ?? ''"
                :error="errors.name"
                maxlength="200"
                required
            />
            <div>
                <label for="category_id" :class="fieldLabelClass"
                    >{{ t('store.digitalProducts.form.category')
                    }}<RequiredMark
                /></label>
                <select
                    id="category_id"
                    v-model="categoryId"
                    name="category_id"
                    :class="plainFieldInputClass"
                    required
                >
                    <option value="">
                        {{ t('store.digitalProducts.form.selectCategory') }}
                    </option>
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="String(category.id)"
                    >
                        {{ category.name }}
                    </option>
                </select>
                <FieldError :message="errors.category_id" />
            </div>
            <StoreUploadField
                id="file"
                v-model="file"
                name="file"
                :label="t('store.digitalProducts.form.file')"
                :initial-files="initialFiles?.file"
                :error="errors.file"
            />
            <div>
                <FormField
                    id="tags"
                    :label="t('store.digitalProducts.form.tags')"
                    :value="product?.tags ?? ''"
                    :error="errors.tags"
                    maxlength="500"
                    aria-describedby="tags_help"
                />
                <p
                    id="tags_help"
                    class="mt-1 text-xs text-gray-600 dark:text-neutral-400"
                >
                    {{ t('store.digitalProducts.form.tagsHelp') }}
                </p>
            </div>
        </fieldset>

        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.digitalProducts.form.images') }}
            </legend>
            <StoreUploadField
                id="photos"
                v-model="photos"
                name="photos"
                type="image"
                multiple
                :label="t('store.digitalProducts.form.photos')"
                :initial-files="initialFiles?.photos"
                :error="errors.photos"
            />
            <StoreUploadField
                id="thumbnail_img"
                v-model="thumbnail"
                name="thumbnail_img"
                type="image"
                :label="t('store.digitalProducts.form.thumbnail')"
                :initial-files="initialFiles?.thumbnail_img"
                :error="errors.thumbnail_img"
            />
        </fieldset>

        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.digitalProducts.form.metaTags') }}
            </legend>
            <FormField
                id="meta_title"
                :label="t('store.digitalProducts.form.metaTitle')"
                :value="product?.meta_title ?? ''"
                :error="errors.meta_title"
            />
            <StoreUploadField
                id="meta_img"
                v-model="metaImage"
                name="meta_img"
                type="image"
                :label="t('store.digitalProducts.form.metaImage')"
                :initial-files="initialFiles?.meta_img"
                :error="errors.meta_img"
            />
            <div class="lg:col-span-2">
                <label for="meta_description" :class="fieldLabelClass">{{
                    t('store.digitalProducts.form.metaDescription')
                }}</label>
                <textarea
                    id="meta_description"
                    v-model="metaDescription"
                    name="meta_description"
                    :class="plainFieldInputClass"
                    rows="5"
                />
                <FieldError :message="errors.meta_description" />
            </div>
            <FormField
                v-if="product"
                id="slug"
                :label="t('store.digitalProducts.form.slug')"
                :value="product.slug ?? ''"
                :error="errors.slug"
            />
        </fieldset>

        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.digitalProducts.form.price') }}
            </legend>
            <FormField
                id="unit_price"
                type="number"
                :label="t('store.digitalProducts.form.unitPrice')"
                :value="product?.unit_price ?? '0'"
                :error="errors.unit_price"
                min="0"
                :max="productAmountMax"
                step="0.01"
                required
            />
            <FormField
                id="purchase_price"
                type="number"
                :label="t('store.digitalProducts.form.purchasePrice')"
                :value="product?.purchase_price ?? '0'"
                :error="errors.purchase_price"
                min="0"
                :max="productAmountMax"
                step="0.01"
                required
            />
            <StoreProductTaxFields :taxes="taxes" :errors="errors" />
            <StoreProductDiscountFields
                :discount="product?.discount"
                :discount-type="product?.discount_type"
                :date-range="product?.date_range"
                :errors="errors"
            />
        </fieldset>

        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.digitalProducts.form.information') }}
            </legend>
            <StoreRichTextField
                id="description"
                v-model="content"
                name="description"
                :label="t('store.digitalProducts.form.description')"
                :error="errors.description"
                class="lg:col-span-2"
            />
        </fieldset>
    </div>
</template>
