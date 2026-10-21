<script setup lang="ts">
import type { FormDataConvertible, UrlMethodPair } from '@inertiajs/core';
import { Form, Head, Link } from '@inertiajs/vue3';
import type { Component } from 'vue';
import PageHeader from '@/components/PageHeader.vue';
import RequiredLegend from '@/components/RequiredLegend.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import { provideRequiredFields } from '@/lib/requiredFields';

withDefaults(
    defineProps<{
        title: string;
        action: string | UrlMethodPair;
        submitLabel: string;
        submittingLabel: string;
        cancelHref?: string | UrlMethodPair;
        /**
         * Forwarded to the underlying Inertia `<Form>` — lets a caller convert
         * form values (e.g. Settings/Edit.vue converting cm back to the pixel
         * integers the backend expects) before they're submitted.
         */
        transform?: (
            data: Record<string, FormDataConvertible>,
        ) => Record<string, FormDataConvertible>;
        /**
         * The page shell: WMS's AdminLayout, or StoreAdminLayout for the
         * store back office's forms.
         */
        layout?: Component;
        /**
         * Let the form use the page's full width, for a form that lays its
         * fields out in a grid (the store role form's permission checkboxes).
         */
        wide?: boolean;
        /**
         * Show the "* Required fields" legend once any field inside the form
         * registers itself as required (lib/requiredFields.ts). Opt out for a
         * form that prints its own legend.
         */
        requiredLegend?: boolean;
    }>(),
    {
        transform: (data: Record<string, FormDataConvertible>) => data,
        layout: () => AdminLayout,
        wide: false,
        requiredLegend: true,
    },
);

const hasRequiredFields = provideRequiredFields();
</script>

<template>
    <Head :title="title" />

    <component :is="layout">
        <PageHeader :title="title">
            <slot name="header-actions" />
        </PageHeader>

        <div :class="wide ? 'max-w-none' : 'max-w-sm'">
            <Form
                :action="action"
                :transform="transform"
                #default="{ errors, processing }"
                class="space-y-4"
            >
                <RequiredLegend v-if="requiredLegend && hasRequiredFields" />
                <slot :errors="errors" />

                <div class="flex gap-3">
                    <Link
                        v-if="cancelHref"
                        :href="cancelHref"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-neutral-700 dark:text-neutral-200 dark:hover:bg-neutral-800"
                    >
                        {{ t('common.cancel') }}
                    </Link>
                    <SubmitButton
                        class="flex-1"
                        :label="submitLabel"
                        :processing-label="submittingLabel"
                        :processing="processing"
                    />
                </div>
            </Form>
            <slot name="after-form" />
        </div>
    </component>
</template>
