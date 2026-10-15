<script setup lang="ts">
import { computed } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    StoreUpload,
    StoreWebsiteAppearanceImage,
    StoreWebsiteAppearanceSettings,
} from '@/types/store';

defineProps<{
    errors: Record<string, string>;
    initialFiles: Partial<Record<StoreWebsiteAppearanceImage, StoreUpload[]>>;
}>();
const settings = defineModel<StoreWebsiteAppearanceSettings>({
    required: true,
});

type TextKey = 'website_name' | 'site_motto' | 'meta_title';
type ColorKey = 'base_color' | 'base_hov_color';

/** The seller and delivery boy backgrounds are sent only while their feature is on. */
const generalImages = computed(() =>
    (
        [
            'login_page_image',
            'register_page_image',
            'seller_login_page_bg',
            'delivery_boy_login_page_bg',
            'flash_deal_banner',
            'flash_deal_banner_small',
        ] as const
    ).filter((key) => settings.value[key] !== undefined),
);
const colors: ColorKey[] = ['base_color', 'base_hov_color'];
const sectionClass =
    'space-y-4 rounded-md border border-gray-200 p-4 dark:border-neutral-700';
const legendClass = 'px-1 font-medium';
const hintClass = 'text-sm text-gray-600 dark:text-neutral-300';

function updateText(key: TextKey | ColorKey, event: Event): void {
    settings.value[key] = (event.target as HTMLInputElement).value;
}
function image(key: StoreWebsiteAppearanceImage): string {
    return settings.value[key] ?? '';
}
function setImage(key: StoreWebsiteAppearanceImage, ids: string): void {
    settings.value[key] = ids;
}
</script>

<template>
    <FieldError :message="errors.settings" />
    <fieldset :class="sectionClass">
        <legend :class="legendClass">
            {{ t('store.website.appearance.general') }}
        </legend>
        <FormField
            id="website_name"
            name="settings[website_name]"
            :label="t('store.website.fields.website_name')"
            :value="settings.website_name"
            :error="errors['settings.website_name']"
            maxlength="255"
            @input="updateText('website_name', $event)"
        />
        <FormField
            id="site_motto"
            name="settings[site_motto]"
            :label="t('store.website.fields.site_motto')"
            :value="settings.site_motto"
            :error="errors['settings.site_motto']"
            maxlength="255"
            @input="updateText('site_motto', $event)"
        />
        <div class="space-y-1">
            <StoreUploadField
                id="site_icon"
                :model-value="image('site_icon')"
                name="settings[site_icon]"
                :label="t('store.website.fields.site_icon')"
                type="image"
                :initial-files="initialFiles.site_icon"
                :error="errors['settings.site_icon']"
                @update:model-value="setImage('site_icon', $event)"
            />
            <p :class="hintClass">
                {{ t('store.website.appearance.siteIconHint') }}
            </p>
        </div>
        <div v-for="key in colors" :key="key" class="space-y-1">
            <FormField
                :id="key"
                :name="`settings[${key}]`"
                :label="t(`store.website.fields.${key}`)"
                :value="settings[key]"
                :error="errors[`settings.${key}`]"
                pattern="#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})"
                placeholder="#377dff"
                @input="updateText(key, $event)"
            />
            <p :class="hintClass">
                {{ t('store.website.appearance.colorHint') }}
            </p>
        </div>
        <StoreUploadField
            v-for="key in generalImages"
            :id="key"
            :key="key"
            :model-value="image(key)"
            :name="`settings[${key}]`"
            :label="t(`store.website.fields.${key}`)"
            type="image"
            :initial-files="initialFiles[key]"
            :error="errors[`settings.${key}`]"
            @update:model-value="setImage(key, $event)"
        />
    </fieldset>
    <fieldset :class="sectionClass">
        <legend :class="legendClass">
            {{ t('store.website.appearance.seo') }}
        </legend>
        <FormField
            id="meta_title"
            name="settings[meta_title]"
            :label="t('store.website.fields.meta_title')"
            :value="settings.meta_title"
            :error="errors['settings.meta_title']"
            maxlength="255"
            @input="updateText('meta_title', $event)"
        />
        <div>
            <label for="meta_description" :class="fieldLabelClass">{{
                t('store.website.fields.meta_description')
            }}</label>
            <textarea
                id="meta_description"
                v-model="settings.meta_description"
                name="settings[meta_description]"
                :class="plainFieldInputClass"
                maxlength="65535"
                rows="3"
            />
            <FieldError :message="errors['settings.meta_description']" />
        </div>
        <div>
            <label for="meta_keywords" :class="fieldLabelClass">{{
                t('store.website.fields.meta_keywords')
            }}</label>
            <textarea
                id="meta_keywords"
                v-model="settings.meta_keywords"
                name="settings[meta_keywords]"
                :class="plainFieldInputClass"
                maxlength="65535"
                rows="2"
            />
            <p :class="hintClass">
                {{ t('store.website.appearance.keywordsHint') }}
            </p>
            <FieldError :message="errors['settings.meta_keywords']" />
        </div>
        <StoreUploadField
            id="meta_image"
            v-model="settings.meta_image"
            name="settings[meta_image]"
            :label="t('store.website.fields.meta_image')"
            type="image"
            :initial-files="initialFiles.meta_image"
            :error="errors['settings.meta_image']"
        />
    </fieldset>
    <fieldset :class="sectionClass">
        <legend :class="legendClass">
            {{ t('store.website.appearance.cookies') }}
        </legend>
        <StoreRichTextField
            id="cookies_agreement_text"
            v-model="settings.cookies_agreement_text"
            name="settings[cookies_agreement_text]"
            :label="t('store.website.fields.cookies_agreement_text')"
            :error="errors['settings.cookies_agreement_text']"
        />
        <div>
            <label
                :class="fieldLabelClass"
                class="flex cursor-pointer items-center gap-2"
            >
                <input
                    id="show_cookies_agreement"
                    v-model="settings.show_cookies_agreement"
                    name="settings[show_cookies_agreement]"
                    type="checkbox"
                    class="cursor-pointer"
                />
                {{ t('store.website.fields.show_cookies_agreement') }}
            </label>
            <FieldError :message="errors['settings.show_cookies_agreement']" />
        </div>
    </fieldset>
    <fieldset :class="sectionClass">
        <legend :class="legendClass">
            {{ t('store.website.appearance.popup') }}
        </legend>
        <div>
            <label
                :class="fieldLabelClass"
                class="flex cursor-pointer items-center gap-2"
            >
                <input
                    id="show_website_popup"
                    v-model="settings.show_website_popup"
                    name="settings[show_website_popup]"
                    type="checkbox"
                    class="cursor-pointer"
                />
                {{ t('store.website.fields.show_website_popup') }}
            </label>
            <FieldError :message="errors['settings.show_website_popup']" />
        </div>
        <StoreRichTextField
            id="website_popup_content"
            v-model="settings.website_popup_content"
            name="settings[website_popup_content]"
            :label="t('store.website.fields.website_popup_content')"
            :error="errors['settings.website_popup_content']"
        />
        <div>
            <label
                :class="fieldLabelClass"
                class="flex cursor-pointer items-center gap-2"
            >
                <input
                    id="show_subscribe_form"
                    v-model="settings.show_subscribe_form"
                    name="settings[show_subscribe_form]"
                    type="checkbox"
                    class="cursor-pointer"
                />
                {{ t('store.website.fields.show_subscribe_form') }}
            </label>
            <FieldError :message="errors['settings.show_subscribe_form']" />
        </div>
    </fieldset>
</template>
