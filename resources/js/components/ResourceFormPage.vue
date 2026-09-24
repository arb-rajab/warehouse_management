<script setup lang="ts">
import type { FormDataConvertible, UrlMethodPair } from '@inertiajs/core';
import { Form, Head, Link } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { t } from '@/lib/i18n';
import SubmitButton from './SubmitButton.vue';

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
    }>(),
    {
        cancelHref: undefined,
        transform: (data: Record<string, FormDataConvertible>) => data,
    },
);
</script>

<template>
    <Head :title="title" />

    <AdminLayout>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">{{ title }}</h1>
            <slot name="header-actions" />
        </div>

        <div class="max-w-sm">
            <Form
                :action="action"
                :transform="transform"
                #default="{ errors, processing }"
                class="space-y-4"
            >
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
        </div>
    </AdminLayout>
</template>
