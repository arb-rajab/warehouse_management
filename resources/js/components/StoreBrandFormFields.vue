<script setup lang="ts">
import { ref } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreBrandForm, StoreUpload } from '@/types/store';
const props = defineProps<{
    brand?: StoreBrandForm;
    name?: string;
    initialFiles?: Record<string, StoreUpload[]>;
    errors: Record<string, string>;
}>();
const description = ref(props.brand?.meta_description ?? '');
const logo = ref(props.brand?.logo ?? '');
</script>
<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.brands.name')"
            :value="name ?? ''"
            :error="errors.name"
            required
            maxlength="50"
        />
        <StoreUploadField
            v-model="logo"
            name="logo"
            :label="t('store.brands.logo')"
            type="image"
            :initial-files="initialFiles?.logo"
            :error="errors.logo"
        />
        <FormField
            id="meta_title"
            :label="t('store.brands.metaTitle')"
            :value="brand?.meta_title ?? ''"
            :error="errors.meta_title"
            maxlength="255"
        />
        <div>
            <label for="meta_description" :class="fieldLabelClass">{{
                t('store.brands.metaDescription')
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
            v-if="brand"
            id="slug"
            :label="t('store.brands.slug')"
            :value="brand.slug ?? ''"
            :error="errors.slug"
            maxlength="255"
        />
    </div>
</template>
