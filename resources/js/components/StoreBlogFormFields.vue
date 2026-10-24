<script setup lang="ts">
import { ref } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreBlog, StoreBlogCategory, StoreUpload } from '@/types/store';

const props = defineProps<{
    blog?: StoreBlog;
    categories: StoreBlogCategory[];
    initialFiles?: Record<string, StoreUpload[]>;
    errors: Record<string, string>;
}>();
const slug = ref(props.blog?.slug ?? '');
// A new post's slug follows its title until it is edited by hand; an existing
// post keeps its public URL unless the slug itself is changed.
const slugEdited = ref(props.blog !== undefined);
const categoryId = ref(props.blog ? String(props.blog.category_id) : '');
const banner = ref(props.blog?.banner ?? '');
const metaImage = ref(props.blog?.meta_img ?? '');
const summary = ref(props.blog?.short_description ?? '');
const description = ref(props.blog?.description ?? '');
const metaDescription = ref(props.blog?.meta_description ?? '');
const metaKeywords = ref(props.blog?.meta_keywords ?? '');

function titleChanged(title: string): void {
    if (!slugEdited.value) {
        slug.value = title
            .trim()
            .toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9-]/g, '');
    }
}

function slugChanged(value: string): void {
    slugEdited.value = true;
    slug.value = value;
}
</script>

<template>
    <div class="grid gap-4 lg:grid-cols-2">
        <FormField
            id="title"
            :label="t('store.blogPosts.fields.title')"
            :value="blog?.title ?? ''"
            :error="errors.title"
            maxlength="255"
            required
            @input="titleChanged(($event.target as HTMLInputElement).value)"
        />
        <div>
            <label for="category_id" :class="fieldLabelClass"
                >{{ t('store.blogPosts.fields.category') }}<RequiredMark
            /></label>
            <select
                id="category_id"
                v-model="categoryId"
                name="category_id"
                :class="plainFieldInputClass"
                required
            >
                <option value="">
                    {{ t('store.blogPosts.fields.selectCategory') }}
                </option>
                <option
                    v-for="category in categories"
                    :key="category.id"
                    :value="String(category.id)"
                >
                    {{ category.category_name }}
                </option>
            </select>
            <FieldError :message="errors.category_id" />
        </div>
        <FormField
            id="slug"
            :label="t('store.blogPosts.fields.slug')"
            :value="slug"
            :error="errors.slug"
            maxlength="255"
            pattern="[A-Za-z0-9\-]+"
            required
            @input="slugChanged(($event.target as HTMLInputElement).value)"
        />
        <StoreUploadField
            id="banner"
            v-model="banner"
            name="banner"
            type="image"
            :label="t('store.blogPosts.fields.banner')"
            :initial-files="initialFiles?.banner"
            :error="errors.banner"
        />
        <div class="lg:col-span-2">
            <label for="short_description" :class="fieldLabelClass">{{
                t('store.blogPosts.fields.summary')
            }}</label>
            <textarea
                id="short_description"
                v-model="summary"
                name="short_description"
                :class="plainFieldInputClass"
                maxlength="65535"
                rows="3"
            />
            <FieldError :message="errors.short_description" />
        </div>
        <StoreRichTextField
            id="description"
            v-model="description"
            name="description"
            :label="t('store.blogPosts.fields.description')"
            :error="errors.description"
            class="lg:col-span-2"
        />
        <FormField
            id="meta_title"
            :label="t('store.blogPosts.fields.metaTitle')"
            :value="blog?.meta_title ?? ''"
            :error="errors.meta_title"
            maxlength="255"
        />
        <StoreUploadField
            id="meta_img"
            v-model="metaImage"
            name="meta_img"
            type="image"
            :label="t('store.blogPosts.fields.metaImage')"
            :initial-files="initialFiles?.meta_img"
            :error="errors.meta_img"
        />
        <div>
            <label for="meta_description" :class="fieldLabelClass">{{
                t('store.blogPosts.fields.metaDescription')
            }}</label>
            <textarea
                id="meta_description"
                v-model="metaDescription"
                name="meta_description"
                :class="plainFieldInputClass"
                maxlength="65535"
                rows="3"
            />
            <FieldError :message="errors.meta_description" />
        </div>
        <div>
            <label for="meta_keywords" :class="fieldLabelClass">{{
                t('store.blogPosts.fields.metaKeywords')
            }}</label>
            <textarea
                id="meta_keywords"
                v-model="metaKeywords"
                name="meta_keywords"
                :class="plainFieldInputClass"
                maxlength="65535"
                rows="3"
            />
            <FieldError :message="errors.meta_keywords" />
        </div>
    </div>
</template>
