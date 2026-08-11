<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Form, Head } from '@inertiajs/vue3';
import AdminLayout from '@/layouts/AdminLayout.vue';
import SubmitButton from './SubmitButton.vue';

defineProps<{
    title: string;
    action: string | UrlMethodPair;
    submitLabel: string;
    submittingLabel: string;
}>();
</script>

<template>
    <Head :title="title" />

    <AdminLayout>
        <h1 class="mb-6 text-xl font-semibold">{{ title }}</h1>

        <div class="max-w-sm">
            <Form
                :action="action"
                #default="{ errors, processing }"
                class="space-y-4"
            >
                <slot :errors="errors" />

                <SubmitButton
                    :label="submitLabel"
                    :processing-label="submittingLabel"
                    :processing="processing"
                />
            </Form>
        </div>
    </AdminLayout>
</template>
