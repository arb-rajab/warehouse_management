<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import {
    fieldLabelClass,
    filterTriggerButtonClass,
    plainFieldInputClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreUpload, StoreWebsiteFooterSettings } from '@/types/store';

defineProps<{
    errors: Record<string, string>;
    initialFiles: Record<
        'footer_logo' | 'payment_method_images',
        StoreUpload[]
    >;
}>();
const settings = defineModel<StoreWebsiteFooterSettings>({ required: true });

type TextKey =
    | 'play_store_link'
    | 'app_store_link'
    | 'contact_address'
    | 'contact_phone'
    | 'contact_email'
    | 'widget_one'
    | 'facebook_link'
    | 'twitter_link'
    | 'instagram_link'
    | 'youtube_link'
    | 'linkedin_link'
    | 'seller_app_link'
    | 'delivery_boy_app_link'
    | 'registration_tutorial_text'
    | 'registration_tutorial_link';

/** UpdateWebsiteFooterRequest's link rule: http(s), root-relative or a fragment. */
const linkPattern = '(?:[Hh][Tt][Tt][Pp][Ss]?://\\S+|/(?!/)\\S*|#\\S*)';
const appLinks = ['play_store_link', 'app_store_link'] as const;
const contacts = ['contact_address', 'contact_phone'] as const;
const socialLinks = [
    'facebook_link',
    'twitter_link',
    'instagram_link',
    'youtube_link',
    'linkedin_link',
] as const;
const sectionClass =
    'space-y-4 rounded-md border border-gray-200 p-4 dark:border-neutral-700';
const legendClass = 'px-1 font-medium';

function updateText(key: TextKey, event: Event): void {
    settings.value[key] = (event.target as HTMLInputElement).value;
}
function addLink(): void {
    settings.value.widget_one_labels.push('');
    settings.value.widget_one_links.push('');
}
function removeLink(index: number): void {
    settings.value.widget_one_labels.splice(index, 1);
    settings.value.widget_one_links.splice(index, 1);
}
</script>

<template>
    <FieldError :message="errors.settings" />
    <p class="text-sm text-gray-600 dark:text-neutral-300">
        {{ t('store.website.footer.translatableHint') }}
    </p>
    <fieldset :class="sectionClass">
        <legend :class="legendClass">
            {{ t('store.website.footer.about') }}
        </legend>
        <StoreUploadField
            id="footer_logo"
            v-model="settings.footer_logo"
            name="settings[footer_logo]"
            :label="t('store.website.fields.footer_logo')"
            type="image"
            :initial-files="initialFiles.footer_logo"
            :error="errors['settings.footer_logo']"
        />
        <StoreRichTextField
            id="about_us_description"
            v-model="settings.about_us_description"
            name="settings[about_us_description]"
            :label="t('store.website.fields.about_us_description')"
            :error="errors['settings.about_us_description']"
        />
        <FormField
            v-for="key in appLinks"
            :id="key"
            :key="key"
            :name="`settings[${key}]`"
            :label="t(`store.website.fields.${key}`)"
            :value="settings[key]"
            :error="errors[`settings.${key}`]"
            maxlength="2048"
            :pattern="linkPattern"
            placeholder="https://"
            @input="updateText(key, $event)"
        />
    </fieldset>
    <fieldset :class="sectionClass">
        <legend :class="legendClass">
            {{ t('store.website.footer.contact') }}
        </legend>
        <FormField
            v-for="key in contacts"
            :id="key"
            :key="key"
            :name="`settings[${key}]`"
            :label="t(`store.website.fields.${key}`)"
            :value="settings[key]"
            :error="errors[`settings.${key}`]"
            maxlength="255"
            @input="updateText(key, $event)"
        />
        <FormField
            id="contact_email"
            name="settings[contact_email]"
            type="email"
            :label="t('store.website.fields.contact_email')"
            :value="settings.contact_email"
            :error="errors['settings.contact_email']"
            maxlength="255"
            @input="updateText('contact_email', $event)"
        />
    </fieldset>
    <fieldset :class="sectionClass">
        <legend :class="legendClass">
            {{ t('store.website.footer.links') }}
        </legend>
        <FormField
            id="widget_one"
            name="settings[widget_one]"
            :label="t('store.website.fields.widget_one')"
            :value="settings.widget_one"
            :error="errors['settings.widget_one']"
            maxlength="255"
            @input="updateText('widget_one', $event)"
        />
        <div
            v-for="(_, index) in settings.widget_one_labels"
            :key="index"
            class="grid gap-3 rounded-md border border-gray-200 p-3 sm:grid-cols-2 dark:border-neutral-700"
        >
            <div>
                <label
                    :for="`footer-link-label-${index}`"
                    :class="fieldLabelClass"
                    >{{ t('store.website.footer.linkLabel') }}</label
                >
                <input
                    :id="`footer-link-label-${index}`"
                    v-model="settings.widget_one_labels[index]"
                    :name="`settings[widget_one_labels][${index}]`"
                    :class="plainFieldInputClass"
                    required
                    maxlength="255"
                />
                <FieldError
                    :message="errors[`settings.widget_one_labels.${index}`]"
                />
            </div>
            <div>
                <label
                    :for="`footer-link-url-${index}`"
                    :class="fieldLabelClass"
                    >{{ t('store.website.footer.linkUrl') }}</label
                >
                <input
                    :id="`footer-link-url-${index}`"
                    v-model="settings.widget_one_links[index]"
                    :name="`settings[widget_one_links][${index}]`"
                    :class="plainFieldInputClass"
                    required
                    maxlength="2048"
                    :pattern="linkPattern"
                />
                <FieldError
                    :message="errors[`settings.widget_one_links.${index}`]"
                />
            </div>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                :aria-label="
                    t('store.website.footer.removeLink', { row: index + 1 })
                "
                @click="removeLink(index)"
            >
                <X class="h-4 w-4" />{{ t('store.website.header.remove') }}
            </button>
        </div>
        <FieldError :message="errors['settings.widget_one_labels']" />
        <FieldError :message="errors['settings.widget_one_links']" />
        <button
            type="button"
            :class="filterTriggerButtonClass"
            class="disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="settings.widget_one_labels.length >= 50"
            @click="addLink"
        >
            <Plus class="h-4 w-4" />{{ t('store.website.footer.addLink') }}
        </button>
    </fieldset>
    <fieldset :class="sectionClass">
        <legend :class="legendClass">
            {{ t('store.website.footer.bottom') }}
        </legend>
        <StoreRichTextField
            id="frontend_copyright_text"
            v-model="settings.frontend_copyright_text"
            name="settings[frontend_copyright_text]"
            :label="t('store.website.fields.frontend_copyright_text')"
            :error="errors['settings.frontend_copyright_text']"
        />
        <fieldset class="space-y-3">
            <legend :class="legendClass">
                {{ t('store.website.footer.social') }}
            </legend>
            <div>
                <label
                    :class="fieldLabelClass"
                    class="flex cursor-pointer items-center gap-2"
                >
                    <input
                        id="show_social_links"
                        v-model="settings.show_social_links"
                        name="settings[show_social_links]"
                        type="checkbox"
                        class="cursor-pointer"
                    />
                    {{ t('store.website.fields.show_social_links') }}
                </label>
                <FieldError :message="errors['settings.show_social_links']" />
            </div>
            <FormField
                v-for="key in socialLinks"
                :id="key"
                :key="key"
                :name="`settings[${key}]`"
                :label="t(`store.website.fields.${key}`)"
                :value="settings[key]"
                :error="errors[`settings.${key}`]"
                maxlength="2048"
                :pattern="linkPattern"
                placeholder="https://"
                @input="updateText(key, $event)"
            />
        </fieldset>
        <fieldset
            v-if="
                settings.seller_app_link !== undefined ||
                settings.delivery_boy_app_link !== undefined
            "
            class="space-y-3"
        >
            <legend :class="legendClass">
                {{ t('store.website.footer.apps') }}
            </legend>
            <FormField
                v-if="settings.seller_app_link !== undefined"
                id="seller_app_link"
                name="settings[seller_app_link]"
                :label="t('store.website.fields.seller_app_link')"
                :value="settings.seller_app_link"
                :error="errors['settings.seller_app_link']"
                maxlength="2048"
                :pattern="linkPattern"
                placeholder="https://"
                @input="updateText('seller_app_link', $event)"
            />
            <FormField
                v-if="settings.delivery_boy_app_link !== undefined"
                id="delivery_boy_app_link"
                name="settings[delivery_boy_app_link]"
                :label="t('store.website.fields.delivery_boy_app_link')"
                :value="settings.delivery_boy_app_link"
                :error="errors['settings.delivery_boy_app_link']"
                maxlength="2048"
                :pattern="linkPattern"
                placeholder="https://"
                @input="updateText('delivery_boy_app_link', $event)"
            />
        </fieldset>
        <StoreUploadField
            id="payment_method_images"
            v-model="settings.payment_method_images"
            name="settings[payment_method_images]"
            :label="t('store.website.footer.payment')"
            type="image"
            multiple
            :initial-files="initialFiles.payment_method_images"
            :error="errors['settings.payment_method_images']"
        />
        <fieldset class="space-y-3">
            <legend :class="legendClass">
                {{ t('store.website.footer.tutorial') }}
            </legend>
            <div>
                <label
                    :class="fieldLabelClass"
                    class="flex cursor-pointer items-center gap-2"
                >
                    <input
                        id="registration_tutorial_button"
                        v-model="settings.registration_tutorial_button"
                        name="settings[registration_tutorial_button]"
                        type="checkbox"
                        class="cursor-pointer"
                    />
                    {{ t('store.website.fields.registration_tutorial_button') }}
                </label>
                <FieldError
                    :message="errors['settings.registration_tutorial_button']"
                />
            </div>
            <FormField
                id="registration_tutorial_text"
                name="settings[registration_tutorial_text]"
                :label="t('store.website.fields.registration_tutorial_text')"
                :value="settings.registration_tutorial_text"
                :error="errors['settings.registration_tutorial_text']"
                maxlength="255"
                @input="updateText('registration_tutorial_text', $event)"
            />
            <FormField
                id="registration_tutorial_link"
                name="settings[registration_tutorial_link]"
                :label="t('store.website.fields.registration_tutorial_link')"
                :value="settings.registration_tutorial_link"
                :error="errors['settings.registration_tutorial_link']"
                maxlength="2048"
                :pattern="linkPattern"
                placeholder="https://"
                @input="updateText('registration_tutorial_link', $event)"
            />
        </fieldset>
    </fieldset>
</template>
