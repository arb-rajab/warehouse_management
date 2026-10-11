<script setup lang="ts">
import { Plus, X } from '@lucide/vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import {
    fieldLabelClass,
    plainFieldInputClass,
    filterTriggerButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreWebsiteHeaderSettings } from '@/types/store';

defineProps<{ errors: Record<string, string> }>();
const settings = defineModel<StoreWebsiteHeaderSettings>({ required: true });
const images = [
    { key: 'header_logo', label: 'logo' },
    { key: 'topbar_banner', label: 'bannerLarge' },
    { key: 'topbar_banner_medium', label: 'bannerMedium' },
    { key: 'topbar_banner_small', label: 'bannerSmall' },
] as const;
const switches = [
    { key: 'show_language_switcher', label: 'languageSwitcher' },
    { key: 'show_currency_switcher', label: 'currencySwitcher' },
    { key: 'header_stikcy', label: 'sticky' },
] as const;

function updateText(
    key: 'topbar_banner_link' | 'helpline_number',
    event: Event,
): void {
    settings.value[key] = (event.target as HTMLInputElement).value;
}
function addRow(): void {
    settings.value.header_menu_labels.push('');
    settings.value.header_menu_links.push('');
}
function removeRow(index: number): void {
    settings.value.header_menu_labels.splice(index, 1);
    settings.value.header_menu_links.splice(index, 1);
}
</script>

<template>
    <FieldError :message="errors.settings" />
    <StoreUploadField
        v-for="image in images"
        :key="image.key"
        :id="image.key"
        :name="`settings[${image.key}]`"
        :label="t(`store.website.header.${image.label}`)"
        v-model="settings[image.key]"
        type="image"
        :error="errors[`settings.${image.key}`]"
    />
    <div v-for="toggle in switches" :key="toggle.key">
        <label :class="fieldLabelClass" class="flex items-center gap-2">
            <input
                :id="toggle.key"
                :name="`settings[${toggle.key}]`"
                type="checkbox"
                v-model="settings[toggle.key]"
            />
            {{ t(`store.website.header.${toggle.label}`) }}
        </label>
        <FieldError :message="errors[`settings.${toggle.key}`]" />
    </div>
    <FormField
        id="topbar_banner_link"
        name="settings[topbar_banner_link]"
        :label="t('store.website.header.bannerLink')"
        :value="settings.topbar_banner_link"
        :error="errors['settings.topbar_banner_link']"
        maxlength="2048"
        @input="updateText('topbar_banner_link', $event)"
    />
    <FormField
        id="helpline_number"
        name="settings[helpline_number]"
        :label="t('store.website.header.helpline')"
        :value="settings.helpline_number"
        :error="errors['settings.helpline_number']"
        maxlength="100"
        @input="updateText('helpline_number', $event)"
    />
    <fieldset class="space-y-3">
        <legend class="font-medium">
            {{ t('store.website.header.menu') }}
        </legend>
        <div
            v-for="(_, index) in settings.header_menu_labels"
            :key="index"
            class="grid gap-3 rounded-md border border-gray-200 p-3 sm:grid-cols-2 dark:border-neutral-700"
        >
            <div>
                <label :for="`menu-label-${index}`" :class="fieldLabelClass">{{
                    t('store.website.header.menuLabel')
                }}</label>
                <input
                    :id="`menu-label-${index}`"
                    :name="`settings[header_menu_labels][${index}]`"
                    v-model="settings.header_menu_labels[index]"
                    :class="plainFieldInputClass"
                    required
                    maxlength="256"
                />
                <FieldError
                    :message="errors[`settings.header_menu_labels.${index}`]"
                />
            </div>
            <div>
                <label :for="`menu-link-${index}`" :class="fieldLabelClass">{{
                    t('store.website.header.menuLink')
                }}</label>
                <input
                    :id="`menu-link-${index}`"
                    :name="`settings[header_menu_links][${index}]`"
                    v-model="settings.header_menu_links[index]"
                    :class="plainFieldInputClass"
                    required
                    maxlength="2048"
                />
                <FieldError
                    :message="errors[`settings.header_menu_links.${index}`]"
                />
            </div>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                :aria-label="
                    t('store.website.header.removeMenuRow', { row: index + 1 })
                "
                @click="removeRow(index)"
            >
                <X class="h-4 w-4" />{{ t('store.website.header.remove') }}
            </button>
        </div>
        <FieldError :message="errors['settings.header_menu_links']" />
        <FieldError :message="errors['settings.header_menu_labels']" />
        <button
            type="button"
            :class="filterTriggerButtonClass"
            class="disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="settings.header_menu_labels.length >= 50"
            @click="addRow"
        >
            <Plus class="h-4 w-4" />{{ t('store.website.header.add') }}
        </button>
    </fieldset>
</template>
