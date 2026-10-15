<script setup lang="ts">
import { Plus, X, Info } from '@lucide/vue';
import { ref } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import {
    fieldLabelClass,
    plainFieldInputClass,
    filterTriggerButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreWebsiteHomeSettings } from '@/types/store';

defineProps<{
    categories: { id: number; name: string }[];
    couponEnabled: boolean;
    errors: Record<string, string>;
}>();
const settings = defineModel<StoreWebsiteHomeSettings>({ required: true });
const slideKeys = ref(
    settings.value.home_slider_images.map((_, index) => index),
);
let nextSlideKey = slideKeys.value.length;
function addSlide(): void {
    slideKeys.value.push(nextSlideKey++);
    settings.value.home_slider_images.push('');
    settings.value.home_slider_links.push('');
}
function removeSlide(index: number): void {
    slideKeys.value.splice(index, 1);
    settings.value.home_slider_images.splice(index, 1);
    settings.value.home_slider_links.splice(index, 1);
}
function updateText(
    key: 'cupon_title' | 'cupon_subtitle' | 'cupon_background_color',
    event: Event,
): void {
    settings.value[key] = (event.target as HTMLInputElement).value;
}
</script>

<template>
    <FieldError :message="errors.settings" />
    <fieldset class="space-y-3">
        <legend class="font-medium">
            {{ t('store.website.home.slider') }}
        </legend>
        <p class="flex items-start gap-2 text-sm">
            <Info class="h-4 w-4 shrink-0" />{{
                t('store.website.home.cropHint')
            }}
        </p>
        <div
            v-for="(_, index) in settings.home_slider_images"
            :key="slideKeys[index]"
            class="space-y-3 rounded-md border border-gray-200 p-3 dark:border-neutral-700"
        >
            <StoreUploadField
                :id="`slider-image-${index}`"
                :name="`settings[home_slider_images][${index}]`"
                :label="t('store.website.home.image')"
                v-model="settings.home_slider_images[index]"
                type="image"
                :error="errors[`settings.home_slider_images.${index}`]"
            />
            <label :for="`slider-link-${index}`" :class="fieldLabelClass">{{
                t('store.website.home.link')
            }}</label>
            <input
                :id="`slider-link-${index}`"
                :name="`settings[home_slider_links][${index}]`"
                v-model="settings.home_slider_links[index]"
                :class="plainFieldInputClass"
                maxlength="2048"
            />
            <FieldError
                :message="errors[`settings.home_slider_links.${index}`]"
            />
            <button
                type="button"
                :class="filterTriggerButtonClass"
                :aria-label="
                    t('store.website.home.removeSlide', { row: index + 1 })
                "
                @click="removeSlide(index)"
            >
                <X class="h-4 w-4" />{{ t('store.website.header.remove') }}
            </button>
        </div>
        <FieldError :message="errors['settings.home_slider_images']" />
        <FieldError :message="errors['settings.home_slider_links']" />
        <button
            type="button"
            :class="filterTriggerButtonClass"
            class="disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="settings.home_slider_images.length >= 50"
            @click="addSlide"
        >
            <Plus class="h-4 w-4" />{{ t('store.website.home.addSlide') }}
        </button>
    </fieldset>
    <fieldset v-if="couponEnabled" class="space-y-3">
        <legend class="font-medium">
            {{ t('store.website.home.coupon') }}
        </legend>
        <FormField
            id="cupon_background_color"
            name="settings[cupon_background_color]"
            :label="t('store.website.home.color')"
            :value="settings.cupon_background_color"
            :error="errors['settings.cupon_background_color']"
            maxlength="7"
            pattern="#[0-9a-fA-F]{3}([0-9a-fA-F]{3})?"
            @input="updateText('cupon_background_color', $event)"
        />
        <FormField
            id="cupon_title"
            name="settings[cupon_title]"
            :label="t('store.website.home.couponTitle')"
            :value="settings.cupon_title"
            :error="errors['settings.cupon_title']"
            maxlength="255"
            @input="updateText('cupon_title', $event)"
        />
        <FormField
            id="cupon_subtitle"
            name="settings[cupon_subtitle]"
            :label="t('store.website.home.subtitle')"
            :value="settings.cupon_subtitle"
            :error="errors['settings.cupon_subtitle']"
            maxlength="255"
            @input="updateText('cupon_subtitle', $event)"
        />
    </fieldset>
    <fieldset class="space-y-3">
        <legend class="font-medium">
            {{ t('store.website.home.categories') }}
        </legend>
        <div
            v-for="(_, index) in settings.home_categories"
            :key="index"
            class="flex items-start gap-2"
        >
            <div class="flex-1">
                <label
                    :for="`home-category-${index}`"
                    :class="fieldLabelClass"
                    >{{ t('store.website.home.category') }}</label
                >
                <select
                    :id="`home-category-${index}`"
                    :name="`settings[home_categories][${index}]`"
                    v-model="settings.home_categories[index]"
                    :class="plainFieldInputClass"
                    required
                >
                    <option value="">
                        {{ t('store.website.home.chooseCategory') }}
                    </option>
                    <option
                        v-for="category in categories"
                        :key="category.id"
                        :value="String(category.id)"
                    >
                        {{ category.name }}
                    </option>
                </select>
                <FieldError
                    :message="errors[`settings.home_categories.${index}`]"
                />
            </div>
            <button
                type="button"
                :class="filterTriggerButtonClass"
                :aria-label="
                    t('store.website.home.removeCategory', { row: index + 1 })
                "
                @click="settings.home_categories.splice(index, 1)"
            >
                <X class="h-4 w-4" />{{ t('store.website.header.remove') }}
            </button>
        </div>
        <FieldError :message="errors['settings.home_categories']" />
        <button
            type="button"
            :class="filterTriggerButtonClass"
            class="disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="settings.home_categories.length >= 50"
            @click="settings.home_categories.push('')"
        >
            <Plus class="h-4 w-4" />{{ t('store.website.home.addCategory') }}
        </button>
    </fieldset>
</template>
