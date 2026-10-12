<script setup lang="ts">
import { computed, ref } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreSelectField from '@/components/StoreSelectField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    StoreCategoryForm,
    StoreCategoryOptions,
    StoreUpload,
} from '@/types/store';
const props = defineProps<
    StoreCategoryOptions & {
        category?: StoreCategoryForm;
        name?: string;
        initialFiles?: Record<string, StoreUpload[]>;
        errors: Record<string, string>;
    }
>();
const attributeError = computed(
    () =>
        props.errors.filtering_attributes ??
        Object.entries(props.errors).find(([key]) =>
            key.startsWith('filtering_attributes.'),
        )?.[1],
);
const description = ref(props.category?.meta_description ?? '');
const images = ref({
    banner: props.category?.banner ?? '',
    icon: props.category?.icon ?? '',
    cover_image: props.category?.cover_image ?? '',
});
</script>
<template>
    <div class="grid gap-4 sm:grid-cols-2">
        <FormField
            id="name"
            :label="t('store.categories.name')"
            :value="name ?? category?.name ?? ''"
            :error="errors.name"
            required
            maxlength="50"
        />
        <StoreSelectField
            id="parent_id"
            :label="t('store.categories.parent')"
            :options="[
                { id: 0, name: t('store.categories.noParent') },
                ...parents,
            ]"
            :value="category?.parent_id ?? 0"
            :error="errors.parent_id"
            required
        />
        <div>
            <FormField
                id="order_level"
                :label="t('store.categories.ordering')"
                :value="category?.order_level ?? 0"
                type="number"
                min="-2147483648"
                max="2147483647"
                step="1"
                :error="errors.order_level"
            />
            <p class="text-sm text-gray-600 dark:text-neutral-400">
                {{ t('store.categories.orderHint') }}
            </p>
        </div>
        <StoreSelectField
            id="digital"
            :label="t('store.categories.type')"
            :options="[
                { id: 0, name: t('store.categories.physical') },
                { id: 1, name: t('store.categories.digital') },
            ]"
            :value="category?.digital ? 1 : 0"
            :error="errors.digital"
            required
        />
        <StoreUploadField
            v-model="images.banner"
            name="banner"
            :label="t('store.categories.banner')"
            type="image"
            :initial-files="initialFiles?.banner"
            :error="errors.banner"
        />
        <StoreUploadField
            v-model="images.icon"
            name="icon"
            :label="t('store.categories.icon')"
            type="image"
            :initial-files="initialFiles?.icon"
            :error="errors.icon"
        />
        <StoreUploadField
            v-model="images.cover_image"
            name="cover_image"
            :label="t('store.categories.cover')"
            type="image"
            :initial-files="initialFiles?.cover_image"
            :error="errors.cover_image"
        />
        <FormField
            id="meta_title"
            :label="t('store.categories.metaTitle')"
            :value="category?.meta_title ?? ''"
            :error="errors.meta_title"
            maxlength="255"
        />
        <div class="sm:col-span-2">
            <label for="meta_description" :class="fieldLabelClass">{{
                t('store.categories.metaDescription')
            }}</label>
            <textarea
                id="meta_description"
                name="meta_description"
                :class="plainFieldInputClass"
                rows="5"
                maxlength="65535"
                v-model="description"
            />
            <FieldError :message="errors.meta_description" />
        </div>
        <FormField
            id="slug"
            :label="t('store.categories.slug')"
            :value="category?.slug ?? ''"
            :error="errors.slug"
            maxlength="255"
        />
        <FormField
            v-if="commissionEnabled"
            id="commision_rate"
            :label="t('store.categories.commission')"
            :value="category?.commision_rate ?? ''"
            :error="errors.commision_rate"
            type="number"
            min="0"
            max="999999.99"
            step="0.01"
        />
        <div class="sm:col-span-2">
            <label for="filtering_attributes" :class="fieldLabelClass">{{
                t('store.categories.attributes')
            }}</label>
            <select
                id="filtering_attributes"
                name="filtering_attributes[]"
                :class="[plainFieldInputClass, 'cursor-pointer']"
                multiple
            >
                <option
                    v-for="attribute in attributes"
                    :key="attribute.id"
                    :value="attribute.id"
                    :selected="
                        category?.filtering_attributes.includes(attribute.id)
                    "
                >
                    {{ attribute.name }}
                </option>
            </select>
            <FieldError :message="attributeError" />
        </div>
    </div>
</template>
