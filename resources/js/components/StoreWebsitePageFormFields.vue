<script setup lang="ts">
import { computed, ref } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreUpload, StoreWebsitePageForm } from '@/types/store';

const props = defineProps<{
    page?: StoreWebsitePageForm;
    title?: string;
    content?: string;
    homeUrl: string;
    initialFiles?: Record<'meta_image', StoreUpload[]>;
    errors: Record<string, string>;
}>();
// Only a custom page has an editable address; a policy page keeps its own.
const editableSlug = computed(
    () => props.page === undefined || props.page.type === 'custom_page',
);
const slug = ref(props.page?.slug ?? '');
const content = ref(props.content ?? '');
const metaDescription = ref(props.page?.meta_description ?? '');
const keywords = ref(props.page?.keywords ?? '');
const metaImage = ref(props.page?.meta_image ?? '');
const sectionClass =
    'space-y-4 rounded-md border border-gray-200 p-4 dark:border-neutral-700';
const legendClass = 'px-1 font-medium';
const hintClass = 'text-sm text-gray-600 dark:text-neutral-300';

/** The Blade writer's slug rule, applied as it is typed: spaces become hyphens, anything but letters, digits and hyphens is dropped. */
function slugChanged(event: Event): void {
    const input = event.target as HTMLInputElement;
    slug.value = input.value.replace(/ /g, '-').replace(/[^A-Za-z0-9-]/g, '');
    input.value = slug.value;
}
</script>

<template>
    <fieldset :class="sectionClass">
        <legend :class="legendClass">
            {{ t('store.website.pages.content') }}
        </legend>
        <FormField
            id="title"
            :label="t('store.website.fields.title')"
            :value="title ?? ''"
            :error="errors.title"
            maxlength="255"
            required
        />
        <div>
            <label for="slug" :class="fieldLabelClass">{{
                t('store.website.fields.slug')
            }}</label>
            <div v-if="editableSlug" class="flex items-stretch" dir="ltr">
                <span
                    class="flex items-center rounded-s-md border border-e-0 border-gray-300 bg-gray-100 px-3 text-sm text-gray-700 dark:border-neutral-700 dark:bg-neutral-900 dark:text-neutral-300"
                    >{{ homeUrl }}/</span
                >
                <input
                    id="slug"
                    name="slug"
                    :value="slug"
                    :class="[plainFieldInputClass, 'rounded-s-none']"
                    maxlength="255"
                    pattern="[A-Za-z0-9\-]+"
                    required
                    @input="slugChanged"
                />
            </div>
            <input
                v-else
                id="slug"
                :value="page?.public_url ?? ''"
                :class="plainFieldInputClass"
                dir="ltr"
                readonly
            />
            <p :class="hintClass">
                {{
                    editableSlug
                        ? t('store.website.pages.slugHint')
                        : t('store.website.pages.fixedSlug')
                }}
            </p>
            <FieldError :message="errors.slug" />
        </div>
        <StoreRichTextField
            id="content"
            v-model="content"
            name="content"
            :label="t('store.website.fields.content')"
            :error="errors.content"
            required
        />
    </fieldset>
    <fieldset :class="sectionClass">
        <legend :class="legendClass">
            {{ t('store.website.pages.seo') }}
        </legend>
        <FormField
            id="meta_title"
            :label="t('store.website.fields.meta_title')"
            :value="page?.meta_title ?? ''"
            :error="errors.meta_title"
            maxlength="65535"
        />
        <div>
            <label for="meta_description" :class="fieldLabelClass">{{
                t('store.website.fields.meta_description')
            }}</label>
            <textarea
                id="meta_description"
                v-model="metaDescription"
                name="meta_description"
                :class="plainFieldInputClass"
                maxlength="1000"
                rows="3"
            />
            <FieldError :message="errors.meta_description" />
        </div>
        <div>
            <label for="keywords" :class="fieldLabelClass">{{
                t('store.website.fields.keywords')
            }}</label>
            <textarea
                id="keywords"
                v-model="keywords"
                name="keywords"
                :class="plainFieldInputClass"
                maxlength="1000"
                rows="2"
            />
            <p :class="hintClass">
                {{ t('store.website.pages.keywordsHint') }}
            </p>
            <FieldError :message="errors.keywords" />
        </div>
        <StoreUploadField
            id="meta_image"
            v-model="metaImage"
            name="meta_image"
            type="image"
            :label="t('store.website.fields.meta_image')"
            :initial-files="initialFiles?.meta_image"
            :error="errors.meta_image"
        />
    </fieldset>
</template>
