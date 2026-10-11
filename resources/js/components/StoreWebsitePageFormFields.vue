<script setup lang="ts">
import FormField from '@/components/FormField.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { t } from '@/lib/i18n';
import type { StoreWebsitePageForm } from '@/types/store';
defineProps<{ errors: Record<string, string>; builtIn?: boolean }>();
const page = defineModel<StoreWebsitePageForm>({ required: true });
const texts = [
    'title',
    'slug',
    'meta_title',
    'meta_description',
    'keywords',
] as const;
</script>
<template>
    <FormField
        v-for="key in texts"
        :key="key"
        :id="key"
        :label="t(`store.website.fields.${key}`)"
        :value="page[key]"
        :error="errors[key]"
        :required="key === 'title' || key === 'slug'"
        :readonly="key === 'slug' && builtIn"
        :pattern="key === 'slug' ? '[a-zA-Z0-9]+(-[a-zA-Z0-9]+)*' : undefined"
        :maxlength="
            key === 'meta_description' ? 2000 : key === 'keywords' ? 1000 : 255
        "
        @input="page[key] = ($event.target as HTMLInputElement).value"
    />
    <StoreRichTextField
        id="content"
        name="content"
        :label="t('store.website.fields.content')"
        v-model="page.content"
        :error="errors.content"
        required
    />
    <StoreUploadField
        id="meta_image"
        name="meta_image"
        :label="t('store.website.fields.meta_image')"
        type="image"
        v-model="page.meta_image"
        :error="errors.meta_image"
    />
</template>
