<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import {
    fieldLabelClass,
    plainFieldInputClass,
    filterTriggerButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreWebsiteFooterSettings } from '@/types/store';
defineProps<{ errors: Record<string, string> }>();
const settings = defineModel<StoreWebsiteFooterSettings>({ required: true });
const images = ['footer_logo', 'payment_method_images'] as const;
const rich = ['about_us_description', 'frontend_copyright_text'] as const;
const flags = ['show_social_links', 'registration_tutorial_button'] as const;
const texts = [
    'play_store_link',
    'app_store_link',
    'contact_address',
    'contact_phone',
    'contact_email',
    'widget_one',
    'facebook_link',
    'twitter_link',
    'instagram_link',
    'youtube_link',
    'linkedin_link',
    'seller_app_link',
    'registration_tutorial_text',
    'registration_tutorial_link',
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
        :multiple="key === 'payment_method_images'"
        :error="errors[`settings.${key}`]"
    />
    <StoreRichTextField
        v-for="key in rich"
        :key="key"
        :id="key"
        :name="`settings[${key}]`"
        :label="t(`store.website.fields.${key}`)"
        v-model="settings[key]"
        :basic="true"
        :error="errors[`settings.${key}`]"
    />
    <FormField
        v-for="key in texts"
        :key="key"
        :id="key"
        :name="`settings[${key}]`"
        :label="t(`store.website.fields.${key}`)"
        :value="settings[key]"
        :type="key === 'contact_email' ? 'email' : 'text'"
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
    <fieldset class="space-y-3">
        <legend>{{ t('store.website.fields.widget_one_links') }}</legend>
        <div
            v-for="(_, index) in settings.widget_one_labels"
            :key="index"
            class="grid gap-2 sm:grid-cols-3"
        >
            <label :class="fieldLabelClass"
                >{{ t('store.website.header.menuLabel')
                }}<input
                    v-model="settings.widget_one_labels[index]"
                    :name="`settings[widget_one_labels][${index}]`"
                    :class="plainFieldInputClass"
                    required
                    maxlength="256" /><FieldError
                    :message="
                        errors[`settings.widget_one_labels.${index}`]
                    " /></label
            ><label :class="fieldLabelClass"
                >{{ t('store.website.header.menuLink')
                }}<input
                    v-model="settings.widget_one_links[index]"
                    :name="`settings[widget_one_links][${index}]`"
                    :class="plainFieldInputClass"
                    required
                    maxlength="2048" /><FieldError
                    :message="
                        errors[`settings.widget_one_links.${index}`]
                    " /></label
            ><button
                type="button"
                :class="filterTriggerButtonClass"
                :aria-label="
                    t('store.website.header.removeMenuRow', { row: index + 1 })
                "
                @click="
                    settings.widget_one_labels.splice(index, 1);
                    settings.widget_one_links.splice(index, 1);
                "
            >
                <X class="h-4 w-4" />{{ t('store.website.header.remove') }}
            </button>
        </div>
        <button
            type="button"
            :class="filterTriggerButtonClass"
            class="disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="settings.widget_one_labels.length >= 50"
            @click="
                settings.widget_one_labels.push('');
                settings.widget_one_links.push('');
            "
        >
            <Plus class="h-4 w-4" />{{ t('store.website.header.add') }}</button
        ><FieldError
            :message="errors['settings.widget_one_links']"
        /><FieldError :message="errors['settings.widget_one_labels']" />
    </fieldset>
</template>
