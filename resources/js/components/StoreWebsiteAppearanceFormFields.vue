<script setup lang="ts">
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { fieldLabelClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreWebsiteAppearanceSettings } from '@/types/store';
defineProps<{ errors: Record<string, string> }>();
const settings = defineModel<StoreWebsiteAppearanceSettings>({
    required: true,
});
const images = [
    'site_icon',
    'login_page_image',
    'register_page_image',
    'seller_login_page_bg',
    'flash_deal_banner',
    'flash_deal_banner_small',
    'meta_image',
] as const;
const rich = ['cookies_agreement_text', 'website_popup_content'] as const;
const flags = [
    'show_cookies_agreement',
    'show_website_popup',
    'show_subscribe_form',
] as const;
const texts = [
    'website_name',
    'site_motto',
    'base_color',
    'base_hov_color',
    'meta_title',
    'meta_description',
    'meta_keywords',
] as const;
</script>
<template>
    <FieldError :message="errors.settings" />
    <StoreUploadField
        v-for="key in images"
        :key="key"
        :id="key"
        :name="`settings[${key}]`"
        :label="t(`store.website.fields.${key}`)"
        v-model="settings[key]"
        type="image"
        :error="errors[`settings.${key}`]"
    />
    <StoreRichTextField
        v-for="key in rich"
        :key="key"
        :id="key"
        :name="`settings[${key}]`"
        :label="t(`store.website.fields.${key}`)"
        v-model="settings[key]"
        :basic="key === 'cookies_agreement_text'"
        :error="errors[`settings.${key}`]"
    />
    <FormField
        v-for="key in texts"
        :key="key"
        :id="key"
        :name="`settings[${key}]`"
        :label="t(`store.website.fields.${key}`)"
        :value="settings[key]"
        type="text"
        :maxlength="
            key.endsWith('_link')
                ? 2048
                : ['meta_description', 'meta_keywords'].includes(key)
                  ? 2000
                  : 255
        "
        :pattern="
            ['base_color', 'base_hov_color'].includes(key)
                ? '#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?'
                : undefined
        "
        :error="errors[`settings.${key}`]"
        @input="settings[key] = ($event.target as HTMLInputElement).value"
    />
    <div v-for="key in flags" :key="key">
        <label :class="fieldLabelClass" class="flex items-center gap-2"
            ><input
                :id="key"
                :name="`settings[${key}]`"
                type="checkbox"
                v-model="settings[key]"
            />{{ t(`store.website.fields.${key}`) }}</label
        ><FieldError :message="errors[`settings.${key}`]" />
    </div>
</template>
