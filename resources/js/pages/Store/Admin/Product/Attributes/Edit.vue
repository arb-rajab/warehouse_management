<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import {
    edit,
    index as attributesIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/AttributeController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreAttributeFormFields from '@/components/StoreAttributeFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { selectedToggleClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreLanguage } from '@/types/store';
import type { StoreAttribute } from '@/types/storeCatalogue';

defineProps<{
    attribute: StoreAttribute;
    lang: string;
    name: string;
    languages: StoreLanguage[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.attributes.edit.title', { name: attribute.name })"
        :action="update(attribute)"
        :submit-label="t('store.attributes.fields.save')"
        :submitting-label="t('store.attributes.fields.saving')"
        :cancel-href="attributesIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <nav
            v-if="languages.length > 1"
            :aria-label="t('store.attributes.edit.languages')"
            class="flex flex-wrap items-center gap-1 text-sm"
        >
            <Languages class="h-4 w-4 text-gray-400 dark:text-neutral-500" />
            <Link
                v-for="language in languages"
                :key="language.code"
                :href="edit(attribute, { query: { lang: language.code } })"
                :aria-current="language.code === lang ? 'page' : undefined"
                class="rounded-md px-3 py-1"
                :class="
                    language.code === lang
                        ? selectedToggleClass
                        : 'text-gray-600 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-800'
                "
                >{{ language.name }}</Link
            >
        </nav>
        <p
            v-if="languages.length > 1"
            class="text-sm text-gray-500 dark:text-neutral-400"
        >
            {{ t('store.attributes.edit.translationHint') }}
        </p>
        <input type="hidden" name="lang" :value="lang" />
        <StoreAttributeFormFields :key="lang" :name="name" :errors="errors" />
    </ResourceFormPage>
</template>
