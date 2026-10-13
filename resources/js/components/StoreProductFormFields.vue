<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { activation } from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import { index as shippingConfigurationIndex } from '@/actions/App/Http/Controllers/Store/Admin/ShippingConfigurationController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
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
    StoreProductFormSections,
    StoreReferenceOption,
} from '@/types/store';

/**
 * Every field of the physical product create form, in the legacy form's
 * sections. Shipping, flash deals, taxes and the cash-on-delivery switch
 * show only while the business settings that showed them are on.
 */
defineProps<{
    categories: StoreReferenceOption[];
    brands: StoreReferenceOption[];
    colors: StoreProductColor[];
    attributes: StoreReferenceOption[];
    flashDeals: StoreReferenceOption[];
    taxes: StoreDigitalProductTax[];
    sections: StoreProductFormSections;
    errors: Record<string, string>;
}>();
const int = '2147483647';
const categoryId = ref('');
const brandId = ref('');
const photos = ref('');
const thumbnail = ref('');
const pdf = ref('');
const metaImage = ref('');
const metaDescription = ref('');
const videoProvider = ref('youtube');
const unitPrice = ref('0');
const hasVariants = ref(false);
const content = ref('');
const shippingType = ref('free');
const stockVisibility = ref('quantity');
const flashDealId = ref('');
const flashDiscountType = ref('');
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
                value=""
                :error="errors.name"
                maxlength="200"
                required
            />
            <div>
                <label for="category_id" :class="fieldLabelClass">{{
                    t('store.products.form.category')
                }}</label>
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
                value=""
                :error="errors.unit"
                :placeholder="t('store.products.form.unitPlaceholder')"
                maxlength="20"
                required
            />
            <FormField
                id="weight"
                type="number"
                :label="t('store.products.form.weight')"
                value="0.00"
                :error="errors.weight"
                min="0"
                max="999999.99"
                step="0.01"
            />
            <FormField
                id="max_qty"
                type="number"
                :label="t('store.products.form.maxQty')"
                value="1000"
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
                value="1"
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
                    value=""
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
                    :error="errors.thumbnail_img"
                />
                <p :class="helpClass">
                    {{ t('store.products.form.thumbnailHelp') }}
                </p>
            </div>
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
                    value=""
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
                :errors="errors"
            />
            <StoreProductListField
                id="product_barcodes"
                name="product_barcodes"
                :label="t('store.products.form.barcodes')"
                :add-label="t('store.products.form.addBarcode')"
                :remove-label="t('store.products.form.removeBarcode')"
                :placeholder="t('store.products.form.barcodePlaceholder')"
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
                :errors="errors"
            />
        </fieldset>

        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.products.form.priceAndStock') }}
            </legend>
            <div>
                <label for="unit_price" :class="fieldLabelClass">{{
                    t('store.products.form.unitPrice')
                }}</label>
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
            <StoreProductDiscountFields :errors="errors" />
            <!-- Hidden, not removed, while variants carry their own stock:
                 the legacy form posted these either way. -->
            <div v-show="!hasVariants" class="contents">
                <FormField
                    id="current_stock"
                    type="number"
                    :label="t('store.products.form.quantity')"
                    value="0"
                    :error="errors.current_stock"
                    min="0"
                    :max="int"
                    step="1"
                    required
                />
                <FormField
                    id="sku"
                    :label="t('store.products.form.sku')"
                    value=""
                    :error="errors.sku"
                    maxlength="255"
                />
            </div>
            <div>
                <FormField
                    id="external_link"
                    type="url"
                    :label="t('store.products.form.externalLink')"
                    value=""
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
                value=""
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
                value=""
                :error="errors.meta_title"
            />
            <StoreUploadField
                id="meta_img"
                v-model="metaImage"
                name="meta_img"
                type="image"
                :label="t('store.products.form.metaImage')"
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
        </fieldset>

        <fieldset :class="sectionClass">
            <legend :class="legendClass">
                {{ t('store.products.form.stockAndShipping') }}
            </legend>
            <div v-if="sections.shipping" class="lg:col-span-2">
                <fieldset v-if="sections.productWiseShipping" class="space-y-2">
                    <legend :class="fieldLabelClass">
                        {{ t('store.products.form.shippingConfiguration') }}
                    </legend>
                    <label
                        class="flex cursor-pointer items-center gap-2 text-sm"
                    >
                        <input
                            v-model="shippingType"
                            type="radio"
                            name="shipping_type"
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
                            value="flat_rate"
                            :class="checkboxClass"
                        />
                        {{ t('store.products.form.flatRate') }}
                    </label>
                    <FieldError :message="errors.shipping_type" />
                    <FormField
                        v-if="shippingType === 'flat_rate'"
                        id="flat_shipping_cost"
                        type="number"
                        :label="t('store.products.form.shippingCost')"
                        value="0"
                        :error="errors.flat_shipping_cost"
                        min="0"
                        :max="productAmountMax"
                        step="0.01"
                        required
                    />
                    <label
                        class="flex cursor-pointer items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            name="is_quantity_multiplied"
                            value="1"
                            :class="checkboxClass"
                        />
                        {{ t('store.products.form.quantityMultiplied') }}
                    </label>
                </fieldset>
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
                    value=""
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
                value="1"
                :error="errors.low_stock_quantity"
                min="0"
                :max="int"
                step="1"
            />
            <fieldset class="space-y-2">
                <legend :class="fieldLabelClass">
                    {{ t('store.products.form.stockVisibility') }}
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
                        checked
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
                        :class="checkboxClass"
                    />
                    {{ t('store.products.form.featured') }}
                </label>
                <label class="flex cursor-pointer items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        name="todays_deal"
                        value="1"
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
                value="0"
                :error="errors.flash_discount"
                min="0"
                :max="productAmountMax"
                step="0.01"
            />
            <div>
                <label for="flash_discount_type" :class="fieldLabelClass">{{
                    t('store.products.form.flashDiscountType')
                }}</label>
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
            </div>
        </fieldset>
    </div>
</template>
