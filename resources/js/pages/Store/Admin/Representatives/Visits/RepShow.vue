<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, PackageSearch } from '@lucide/vue';
import { ref } from 'vue';
import {
    index as visitsIndex,
    show_all_visits_in_a_day,
} from '@/actions/App/Http/Controllers/Store/Admin/VisitController';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import StoreVisitMap from '@/components/StoreVisitMap.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { usePageError } from '@/lib/pageErrors';
import type { RepresentativeVisitMarker } from '@/types/storeRepresentatives';

const props = defineProps<{
    rep: { id: number; name: string };
    date: string;
    visitCount: number;
    mapMarkers: RepresentativeVisitMarker[];
}>();
const selectedDate = ref(props.date);
const dateError = usePageError('date');
function filter(): void {
    router.get(
        show_all_visits_in_a_day.url(props.rep),
        { date: selectedDate.value },
        { preserveState: true, preserveScroll: true, replace: true },
    );
}
</script>

<template>
    <Head
        :title="t('store.representatives.visits.dayTitle', { name: rep.name })"
    />
    <StoreAdminLayout>
        <PageHeader
            :title="
                t('store.representatives.visits.dayTitle', { name: rep.name })
            "
        >
            <TableActionLink :href="visitsIndex()"
                ><ArrowLeft class="h-3.5 w-3.5 rtl:rotate-180" />{{
                    t('store.representatives.visits.back')
                }}</TableActionLink
            >
        </PageHeader>
        <form
            class="mb-6 flex flex-wrap items-end gap-3"
            @submit.prevent="filter"
        >
            <FormField
                id="date"
                type="date"
                :value="selectedDate"
                :label="t('store.representatives.visits.filterDate')"
                :error="dateError"
                input-class="dark:[color-scheme:dark]"
                @input="
                    selectedDate = ($event.target as HTMLInputElement).value
                "
            />
            <SubmitButton
                :label="t('common.filters.apply')"
                :processing-label="t('common.filters.apply')"
                :processing="false"
            />
        </form>
        <StoreVisitMap v-if="visitCount > 0" :markers="mapMarkers" />
        <div
            v-else
            role="status"
            class="rounded-md border border-gray-200 p-6 dark:border-neutral-700"
        >
            <h2 class="flex items-center gap-2 font-semibold">
                <PackageSearch class="h-5 w-5" />{{
                    t('store.representatives.visits.emptyDay')
                }}
            </h2>
            <p>{{ t('store.representatives.visits.tryDate') }}</p>
        </div>
    </StoreAdminLayout>
</template>
