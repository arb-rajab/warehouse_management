<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Languages } from '@lucide/vue';
import {
    edit,
    index as pickupPointsIndex,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/PickupPointController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StorePickupPointFormFields from '@/components/StorePickupPointFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { selectedToggleClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    StoreLanguage,
    StorePickupPoint,
    StoreStaffOption,
} from '@/types/store';

defineProps<{
    pickupPoint: StorePickupPoint;
    lang: string;
    translatedName: string;
    translatedAddress: string;
    languages: StoreLanguage[];
    staffOptions: StoreStaffOption[];
}>();
</script>

<template>
    <ResourceFormPage
        :title="t('store.pickupPoints.edit.title', { name: pickupPoint.name })"
        :action="update(pickupPoint)"
        :submit-label="t('common.save')"
        :submitting-label="t('common.saving')"
        :cancel-href="pickupPointsIndex()"
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
                :href="edit(pickupPoint, { query: { lang: language.code } })"
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
        <StorePickupPointFormFields
            :key="lang"
            :pickup-point="pickupPoint"
            :name="translatedName"
            :address="translatedAddress"
            :staff-options="staffOptions"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
