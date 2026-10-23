<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { activation } from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import { index as shippingConfigurationIndex } from '@/actions/App/Http/Controllers/Store/Admin/ShippingConfigurationController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreProductDiscountFields from '@/components/StoreProductDiscountFields.vue';
import StoreProductListField from '@/components/StoreProductListField.vue';
import StoreProductTaxFields from '@/components/StoreProductTaxFields.vue';
import StoreProductVariantFields from '@/components/StoreProductVariantFields.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { productAmountMax } from '@/lib/storeProductForm';
import type {
    StoreDigitalProductTax,
    StoreProductColor,
    StoreProductForm,
    StoreProductFormSections,
    StoreProductVariantState,
    StoreReferenceOption,
    StoreUpload,
} from '@/types/store';

/**
 * Every field of the physical product create and edit forms, in the legacy
 * forms' sections. Shipping, flash deals, taxes and the cash-on-delivery
 * switch show only while the business settings that showed them are on.
 *
 * On the edit form `product` holds the stored fields, `name`, `unit` and
 * `description` the edited language's, `variants` the stored colours,
 * choices and rows (regenerated through `variantsUrl`) and `initialFiles`
 * the previews of the stored uploads; the slug and the Otajer image show only
 * there, as on the Blade edit form.
 */
const props = defineProps<{
    categories: StoreReferenceOption[];
    brands: StoreReferenceOption[];
    colors: StoreProductColor[];
    attributes: StoreReferenceOption[];
    flashDeals: StoreReferenceOption[];
    taxes: StoreDigitalProductTax[];
    sections: StoreProductFormSections;
    product?: StoreProductForm;
    name?: string;
    unit?: string;
    description?: string;
    variants?: StoreProductVariantState;
    variantsUrl?: string;
    initialFiles?: Record<string, StoreUpload[]>;
    errors: Record<string, string>;
}>();
const int = '2147483647';
const stored = props.product;
const categoryId = ref(
    stored?.category_id == null ? '' : String(stored.category_id),
);
const brandId = ref(stored?.brand_id == null ? '' : String(stored.brand_id));
const photos = ref(stored?.photos ?? '');
const oldPhoto = ref(stored?.old_photo ?? '');
const thumbnail = ref(stored?.thumbnail_img ?? '');
const pdf = ref(stored?.pdf ?? '');
const metaImage = ref(stored?.meta_img ?? '');
const metaDescription = ref(stored?.meta_description ?? '');
const videoProvider = ref(stored?.video_provider ?? 'youtube');
const unitPrice = ref(stored?.unit_price ?? '0');
const hasVariants = ref((props.variants?.rows.length ?? 0) > 0);
const content = ref(props.description ?? '');
const shippingType = ref(stored?.shipping_type ?? 'free');
const stockVisibility = ref(stored?.stock_visibility_state ?? 'quantity');
const flashDealId = ref(
    stored?.flash_deal_id == null ? '' : String(stored.flash_deal_id),
);
// The Blade edit form offered the product's discount type for its deal.
const flashDiscountType = ref(
    stored?.flash_deal_id == null ? '' : (stored.discount_type ?? ''),
);
const sectionClass =
    'grid gap-4 rounded-lg border border-gray-200 p-4 lg:grid-cols-2 dark:border-neutral-800';
const legendClass = 'px-1 text-sm font-semibold';
const checkboxClass = 'h-4 w-4 cursor-pointer';
const helpClass = 'mt-1 text-xs text-gray-600 dark:text-neutral-400';
const linkClass =
    'text-blue-700 underline hover:no-underline dark:text-blue-300';
</script>

<template>
    <div class="space-y-6">
        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.products.form.information') }}
            </legend>
            <FormField
                id="name"
                :label="t('store.products.form.name')"
                :value="name ?? ''"
                :error="errors.name"
                maxlength="200"
                required
            />
            <div>
                <label for="category_id" :class="fieldLabelClass"
                    >{{ t('store.products.form.category') }}<RequiredMark
                /></label>
                <select
                    id="category_id"
                    v-model="categoryId"
                    name="category_id"
                    :class="plainFieldInputClass"
                    required
                >
                    <option value="">
                        {{ t('store.products.form.selectCategory') }}
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
            <div>
                <label for="brand_id" :class="fieldLabelClass">{{
                    t('store.products.form.brand')
                }}</label>
                <select
                    id="brand_id"
                    v-model="brandId"
                    name="brand_id"
                    :class="plainFieldInputClass"
                >
                    <option value="">
                        {{ t('store.products.form.selectBrand') }}
                    </option>
                    <option
                        v-for="brand in brands"
                        :key="brand.id"
                        :value="String(brand.id)"
                    >
                        {{ brand.name }}
                    </option>
                </select>
                <FieldError :message="errors.brand_id" />
            </div>
            <FormField
                id="unit"
                :label="t('store.products.form.unit')"
                :value="unit ?? ''"
                :error="errors.unit"
                :placeholder="t('store.products.form.unitPlaceholder')"
                maxlength="20"
                required
            />
            <FormField
                id="weight"
                type="number"
                :label="t('store.products.form.weight')"
                :value="product ? (product.weight ?? '') : '0.00'"
                :error="errors.weight"
                min="0"
                max="999999.99"
                step="0.01"
            />
            <FormField
                id="max_qty"
                type="number"
                :label="t('store.products.form.maxQty')"
                :value="product ? (product.max_qty ?? '') : '1000'"
                :error="errors.max_qty"
                min="1"
                :max="int"
                step="1"
                required
            />
            <FormField
                id="min_qty"
                type="number"
                :label="t('store.products.form.minQty')"
                :value="product?.min_qty ?? '1'"
                :error="errors.min_qty"
                min="1"
                :max="int"
                step="1"
                required
            />
            <div>
                <FormField
                    id="tags"
                    :label="t('store.products.form.tags')"
                    :value="product?.tags ?? ''"
                    :error="errors.tags"
                    maxlength="500"
                    aria-describedby="tags_help"
                />
                <p id="tags_help" :class="helpClass">
                    {{ t('store.products.form.tagsHelp') }}
                </p>
            </div>
        </fieldset>

        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.products.form.images') }}
            </legend>
            <div>
                <StoreUploadField
                    id="photos"
                    v-model="photos"
                    name="photos"
                    type="image"
                    multiple
                    :label="t('store.products.form.photos')"
                    :initial-files="initialFiles?.photos"
                    :error="errors.photos"
                />
                <p :class="helpClass">
                    {{ t('store.products.form.photosHelp') }}
                </p>
            </div>
            <div>
                <StoreUploadField
                    id="thumbnail_img"
                    v-model="thumbnail"
                    name="thumbnail_img"
                    type="image"
                    :label="t('store.products.form.thumbnail')"
                    :initial-files="initialFiles?.thumbnail_img"
                    :error="errors.thumbnail_img"
                />
                <p :class="helpClass">
                    {{ t('store.products.form.thumbnailHelp') }}
                </p>
            </div>
            <StoreUploadField
                v-if="product"
                id="old_photo"
                v-model="oldPhoto"
                name="old_photo"
                type="image"
                multiple
                :label="t('store.products.form.otajerImage')"
                :initial-files="initialFiles?.old_photo"
                :error="errors.old_photo"
            />
        </fieldset>

        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.products.form.videos') }}
            </legend>
            <div>
                <label for="video_provider" :class="fieldLabelClass">{{
                    t('store.products.form.videoProvider')
                }}</label>
                <select
                    id="video_provider"
                    v-model="videoProvider"
                    name="video_provider"
                    :class="plainFieldInputClass"
                >
                    <option value="youtube">YouTube</option>
                    <option value="dailymotion">Dailymotion</option>
                    <option value="vimeo">Vimeo</option>
                </select>
                <FieldError :message="errors.video_provider" />
            </div>
            <div>
                <FormField
                    id="video_link"
                    type="url"
                    :label="t('store.products.form.videoLink')"
                    :value="product?.video_link ?? ''"
                    :error="errors.video_link"
                    maxlength="100"
                    dir="ltr"
                    aria-describedby="video_link_help"
                />
                <p id="video_link_help" :class="helpClass">
                    {{ t('store.products.form.videoLinkHelp') }}
                </p>
            </div>
        </fieldset>

        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.products.form.typesAndBarcodes') }}
            </legend>
            <StoreProductListField
                id="product_types"
                name="product_types"
                :label="t('store.products.form.types')"
                :add-label="t('store.products.form.addType')"
                :remove-label="t('store.products.form.removeType')"
                :placeholder="t('store.products.form.typePlaceholder')"
                :values="product?.product_types"
                :errors="errors"
            />
            <StoreProductListField
                id="product_barcodes"
                name="product_barcodes"
                :label="t('store.products.form.barcodes')"
                :add-label="t('store.products.form.addBarcode')"
                :remove-label="t('store.products.form.removeBarcode')"
                :placeholder="t('store.products.form.barcodePlaceholder')"
                :values="product?.product_barcodes"
                :errors="errors"
            />
        </fieldset>

        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.products.form.variation') }}
            </legend>
            <StoreProductVariantFields
                v-model:has-variants="hasVariants"
                :colors="colors"
                :attributes="attributes"
                :unit-price="unitPrice"
                :initial="variants"
                :rows-url="variantsUrl"
                :errors="errors"
            />
        </fieldset>

        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.products.form.priceAndStock') }}
            </legend>
            <div>
                <label for="unit_price" :class="fieldLabelClass"
                    >{{ t('store.products.form.unitPrice') }}<RequiredMark
                /></label>
                <!-- Kept as typed: the variant rows echo it, as the Blade
                     table did, and v-model would cast it to a number. -->
                <input
                    id="unit_price"
                    :value="unitPrice"
                    name="unit_price"
                    type="number"
                    :class="plainFieldInputClass"
                    min="0"
                    :max="productAmountMax"
                    step="0.01"
                    required
                    @input="
                        unitPrice = ($event.target as HTMLInputElement).value
                    "
                />
                <FieldError :message="errors.unit_price" />
            </div>
            <StoreProductDiscountFields
                :discount="product?.discount"
                :discount-type="product?.discount_type"
                :date-range="product?.date_range"
                :errors="errors"
            />
            <!-- Hidden, not removed, while variants carry their own stock:
                 the legacy form posted these either way. -->
            <div v-show="!hasVariants" class="contents">
                <FormField
                    id="current_stock"
                    type="number"
                    :label="t('store.products.form.quantity')"
                    :value="product?.current_stock ?? '0'"
                    :error="errors.current_stock"
                    min="0"
                    :max="int"
                    step="1"
                    required
                />
                <FormField
                    id="sku"
                    :label="t('store.products.form.sku')"
                    :value="product?.sku ?? ''"
                    :error="errors.sku"
                    maxlength="255"
                />
            </div>
            <div>
                <FormField
                    id="external_link"
                    type="url"
                    :label="t('store.products.form.externalLink')"
                    :value="product?.external_link ?? ''"
                    :error="errors.external_link"
                    maxlength="500"
                    dir="ltr"
                    aria-describedby="external_link_help"
                />
                <p id="external_link_help" :class="helpClass">
                    {{ t('store.products.form.externalLinkHelp') }}
                </p>
            </div>
            <FormField
                id="external_link_btn"
                :label="t('store.products.form.externalLinkButton')"
                :value="product?.external_link_btn ?? ''"
                :error="errors.external_link_btn"
                maxlength="255"
            />
            <div v-if="sections.vat" class="space-y-4 lg:col-span-2">
                <p class="text-sm font-medium">
                    {{ t('store.products.form.vatAndTax') }}
                </p>
                <StoreProductTaxFields :taxes="taxes" :errors="errors" />
            </div>
        </fieldset>

        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.products.form.descriptionSection') }}
            </legend>
            <StoreRichTextField
                id="description"
                v-model="content"
                name="description"
                :label="t('store.products.form.description')"
                :error="errors.description"
                class="lg:col-span-2"
            />
            <StoreUploadField
                id="pdf"
                v-model="pdf"
                name="pdf"
                type="document"
                :label="t('store.products.form.pdf')"
                :initial-files="initialFiles?.pdf"
                :error="errors.pdf"
            />
        </fieldset>

        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.products.form.metaTags') }}
            </legend>
            <FormField
                id="meta_title"
                :label="t('store.products.form.metaTitle')"
                :value="product?.meta_title ?? ''"
                :error="errors.meta_title"
            />
            <StoreUploadField
                id="meta_img"
                v-model="metaImage"
                name="meta_img"
                type="image"
                :label="t('store.products.form.metaImage')"
                :initial-files="initialFiles?.meta_img"
                :error="errors.meta_img"
            />
            <div class="lg:col-span-2">
                <label for="meta_description" :class="fieldLabelClass">{{
                    t('store.products.form.metaDescription')
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
            <div v-if="product" class="lg:col-span-2">
                <FormField
                    id="slug"
                    :label="t('store.products.form.slug')"
                    :value="product.slug ?? ''"
                    :error="errors.slug"
                    dir="ltr"
                    aria-describedby="slug_help"
                />
                <p id="slug_help" :class="helpClass">
                    {{ t('store.products.form.slugHelp') }}
                </p>
            </div>
        </fieldset>

        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.products.form.stockAndShipping') }}
            </legend>
            <div v-if="sections.shipping" class="lg:col-span-2">
                <div v-if="sections.productWiseShipping" class="space-y-2">
                    <fieldset class="space-y-2">
                        <legend :class="fieldLabelClass">
                            {{ t('store.products.form.shippingConfiguration')
                            }}<RequiredMark
                                v-if="sections.productWiseShipping"
                            />
                        </legend>
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm"
                        >
                            <input
                                v-model="shippingType"
                                type="radio"
                                name="shipping_type"
                                :required="sections.productWiseShipping"
                                value="free"
                                :class="checkboxClass"
                            />
                            {{ t('store.products.form.freeShipping') }}
                        </label>
                        <label
                            class="flex cursor-pointer items-center gap-2 text-sm"
                        >
                            <input
                                v-model="shippingType"
                                type="radio"
                                name="shipping_type"
                                :required="sections.productWiseShipping"
                                value="flat_rate"
                                :class="checkboxClass"
                            />
                            {{ t('store.products.form.flatRate') }}
                        </label>
                        <FieldError :message="errors.shipping_type" />
                    </fieldset>
                    <FormField
                        v-if="shippingType === 'flat_rate'"
                        id="flat_shipping_cost"
                        type="number"
                        :label="t('store.products.form.shippingCost')"
                        :value="product?.flat_shipping_cost ?? '0'"
                        :error="errors.flat_shipping_cost"
                        aria-describedby="flat_shipping_cost_help"
                        min="0"
                        :max="productAmountMax"
                        step="0.01"
                        required
                    />
                    <p
                        v-if="shippingType === 'flat_rate'"
                        id="flat_shipping_cost_help"
                        class="text-xs text-gray-600 dark:text-neutral-400"
                    >
                        {{ t('store.products.form.shippingCostHelp') }}
                    </p>
                    <label
                        class="flex cursor-pointer items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            name="is_quantity_multiplied"
                            value="1"
                            :checked="product?.is_quantity_multiplied ?? false"
                            :class="checkboxClass"
                        />
                        {{ t('store.products.form.quantityMultiplied') }}
                    </label>
                </div>
                <p v-else class="text-sm">
                    {{ t('store.products.form.shippingConfiguredElsewhere') }}
                    <Link
                        :href="shippingConfigurationIndex()"
                        :class="linkClass"
                        >{{
                            t('store.products.form.shippingConfiguration')
                        }}</Link
                    >
                </p>
            </div>
            <div v-if="sections.shipping">
                <FormField
                    id="est_shipping_days"
                    type="number"
                    :label="t('store.products.form.shippingDays')"
                    :value="product?.est_shipping_days ?? ''"
                    :error="errors.est_shipping_days"
                    min="1"
                    :max="int"
                    step="1"
                />
            </div>
            <FormField
                id="low_stock_quantity"
                type="number"
                :label="t('store.products.form.lowStockQuantity')"
                :value="product ? (product.low_stock_quantity ?? '') : '1'"
                :error="errors.low_stock_quantity"
                min="0"
                :max="int"
                step="1"
            />
            <fieldset class="space-y-2">
                <legend :class="fieldLabelClass">
                    {{ t('store.products.form.stockVisibility')
                    }}<RequiredMark />
                </legend>
                <label
                    v-for="state in ['quantity', 'text', 'hide'] as const"
                    :key="state"
                    class="flex cursor-pointer items-center gap-2 text-sm"
                >
                    <input
                        v-model="stockVisibility"
                        type="radio"
                        name="stock_visibility_state"
                        required
                        :value="state"
                        :class="checkboxClass"
                    />
                    {{ t(`store.products.form.stockVisibility_${state}`) }}
                </label>
                <FieldError :message="errors.stock_visibility_state" />
            </fieldset>
            <div class="space-y-2">
                <label
                    v-if="sections.cashOnDelivery"
                    class="flex cursor-pointer items-center gap-2 text-sm"
                >
                    <input
                        type="checkbox"
                        name="cash_on_delivery"
                        value="1"
                        :checked="product?.cash_on_delivery ?? true"
                        :class="checkboxClass"
                    />
                    {{ t('store.products.form.cashOnDelivery') }}
                </label>
                <p v-else class="text-sm">
                    {{ t('store.products.form.cashOnDeliveryDisabled') }}
                    <Link :href="activation()" :class="linkClass">{{
                        t('store.products.form.cashPaymentActivation')
                    }}</Link>
                </p>
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        name="featured"
                        value="1"
                        :checked="product?.featured ?? false"
                        :class="checkboxClass"
                    />
                    {{ t('store.products.form.featured') }}
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        name="todays_deal"
                        value="1"
                        :checked="product?.todays_deal ?? false"
                        :class="checkboxClass"
                    />
                    {{ t('store.products.form.todaysDeal') }}
                </label>
            </div>
        </fieldset>

        <fieldset v-if="sections.flashDeals" :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.products.form.flashDeal') }}
            </legend>
            <div>
                <label for="flash_deal_id" :class="fieldLabelClass">{{
                    t('store.products.form.addToFlash')
                }}</label>
                <select
                    id="flash_deal_id"
                    v-model="flashDealId"
                    name="flash_deal_id"
                    :class="plainFieldInputClass"
                >
                    <option value="">
                        {{ t('store.products.form.chooseFlashDeal') }}
                    </option>
                    <option
                        v-for="deal in flashDeals"
                        :key="deal.id"
                        :value="String(deal.id)"
                    >
                        {{ deal.name }}
                    </option>
                </select>
                <FieldError :message="errors.flash_deal_id" />
            </div>
            <FormField
                id="flash_discount"
                type="number"
                :label="t('store.products.form.flashDiscount')"
                :value="product?.discount ?? '0'"
                :error="errors.flash_discount"
                :required="flashDealId !== ''"
                aria-describedby="flash_discount_help"
                min="0"
                :max="productAmountMax"
                step="0.01"
            />
            <div>
                <label for="flash_discount_type" :class="fieldLabelClass"
                    >{{ t('store.products.form.flashDiscountType')
                    }}<RequiredMark v-if="flashDealId !== ''"
                /></label>
                <select
                    id="flash_discount_type"
                    v-model="flashDiscountType"
                    name="flash_discount_type"
                    :class="plainFieldInputClass"
                    :required="flashDealId !== ''"
                >
                    <option value="">
                        {{ t('store.products.form.chooseDiscountType') }}
                    </option>
                    <option value="amount">
                        {{ t('store.productForm.flat') }}
                    </option>
                    <option value="percent">
                        {{ t('store.productForm.percent') }}
                    </option>
                </select>
                <FieldError :message="errors.flash_discount_type" />
                <p
                    id="flash_discount_help"
                    class="text-xs text-gray-600 dark:text-neutral-400"
                >
                    {{ t('store.products.form.flashDiscountHelp') }}
                </p>
            </div>
        </fieldset>
    </div>
</template>
