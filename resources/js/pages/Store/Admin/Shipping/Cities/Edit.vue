<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import {
    edit,
    index as citiesIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/CityController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCityFormFields from '@/components/StoreCityFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { selectedToggleClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreCity, StoreLanguage, StoreState } from '@/types/store';

defineProps<{
    city: StoreCity;
    lang: string;
    name: string;
    languages: StoreLanguage[];
    states: StoreState[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.cities.edit.title', { name: city.name })"
        :action="update(city)"
        :submit-label="t('common.save')"
        :submitting-label="t('common.saving')"
        :cancel-href="citiesIndex()"
        :layout="StoreAdminLayout"
        #default="{ errors }"
    >
        <nav
            v-if="languages.length > 1"
            :aria-label="t('store.reference.languages')"
            class="flex flex-wrap items-center gap-1 text-sm"
        >
            <Languages class="h-4 w-4 text-gray-400" />
            <Link
                v-for="language in languages"
                :key="language.code"
                :href="edit(city, { query: { lang: language.code } })"
                :aria-current="language.code === lang ? 'page' : undefined"
                class="rounded-md px-3 py-1"
                :class="
                    language.code === lang
                        ? selectedToggleClass
                        : 'text-gray-600 hover:bg-gray-100 dark:text-neutral-400 dark:hover:bg-neutral-800'
                "
            >
                {{ language.name }}
            </Link>
        </nav>
        <input type="hidden" name="lang" :value="lang" />
        <StoreCityFormFields
            :key="lang"
            :city="city"
            :name="name"
            :states="states"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
