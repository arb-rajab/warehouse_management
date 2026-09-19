<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import { index as productsIndex } from '@/actions/App/Http/Controllers/Admin/ProductController';
import FilterNumberField from '@/components/FilterNumberField.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import PageHeader from '@/components/PageHeader.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import {
    filterSectionHeadingClass as sectionHeadingClass,
    filterTriggerButtonClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';

const sections = ['defaultBoxes', 'settingBoxes'];
const previewBoxCountDraft = ref('50');
</script>

<template>
    <Head :title="t('help.products.title')" />

    <AdminLayout>
        <PageHeader :title="t('help.products.title')" />

        <Link
            :href="helpIndex()"
            class="mb-6 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
        >
            {{ t('help.backToHelp') }}
        </Link>

        <div class="max-w-2xl space-y-6">
            <section v-for="section in sections" :key="section">
                <h2 :class="sectionHeadingClass">
                    {{ t(`help.products.${section}.heading`) }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t(`help.products.${section}.body`) }}
                </p>
                <HelpUiPreview v-if="section === 'settingBoxes'" class="mt-2">
                    <button
                        type="button"
                        tabindex="-1"
                        :class="filterTriggerButtonClass"
                    >
                        50
                    </button>
                </HelpUiPreview>
                <HelpUiPreview
                    v-if="section === 'settingBoxes'"
                    inert
                    class="mt-2 block max-w-xs"
                >
                    <p class="mb-3 text-lg font-semibold">
                        {{ t('products.columns.boxesPerPallet') }}
                    </p>
                    <FilterNumberField
                        id="help-products-box-count"
                        v-model="previewBoxCountDraft"
                        :label="
                            t('products.boxesPerPalletLabel', {
                                product: t('products.columns.product'),
                            })
                        "
                    />
                </HelpUiPreview>
            </section>

            <Link
                :href="productsIndex()"
                class="inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
            >
                {{ t('products.title') }}
            </Link>
        </div>
    </AdminLayout>
</template>
