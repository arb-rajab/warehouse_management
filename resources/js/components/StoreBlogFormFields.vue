<script setup lang="ts">
import { ref } from 'vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreBlog, StoreBlogCategory } from '@/types/store';

const props = defineProps<{
    blog?: StoreBlog;
    categories: StoreBlogCategory[];
    errors: Record<string, string>;
}>();
const slug = ref(props.blog?.slug ?? '');
const slugEdited = ref(Boolean(props.blog));
const categoryId = ref(
    props.blog?.category_id ? String(props.blog.category_id) : '',
);
const banner = ref(props.blog?.banner ?? '');
const metaImage = ref(props.blog?.meta_img ?? '');
const description = ref(props.blog?.description ?? '');
function titleChanged(value: string): void {
    if (!slugEdited.value) {
        slug.value = value
            .trim()
            .toLowerCase()
            .replace(/\s+/g, '-')
            .replace(/[^a-z0-9-]/g, '');
    }
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
            <label for="category_id" :class="fieldLabelClass">{{
                t('store.blogPosts.fields.category')
            }}</label>
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
            @input="
                slugEdited = true;
                slug = ($event.target as HTMLInputElement).value;
            "
        />
        <StoreUploadField
            id="banner"
            v-model="banner"
            name="banner"
            type="image"
            :label="t('store.blogPosts.fields.banner')"
            :error="errors.banner"
        />
        <div class="lg:col-span-2">
            <label for="short_description" :class="fieldLabelClass">{{
                t('store.blogPosts.fields.summary')
            }}</label>
            <textarea
                id="short_description"
                name="short_description"
                :value.attr="blog?.short_description ?? ''"
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
            :error="errors.meta_img"
        />
        <div>
            <label for="meta_description" :class="fieldLabelClass">{{
                t('store.blogPosts.fields.metaDescription')
            }}</label>
            <textarea
                id="meta_description"
                name="meta_description"
                :value.attr="blog?.meta_description ?? ''"
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
                name="meta_keywords"
                :value.attr="blog?.meta_keywords ?? ''"
                :class="plainFieldInputClass"
                maxlength="65535"
                rows="3"
            />
            <FieldError :message="errors.meta_keywords" />
        </div>
    </div>
</template>
