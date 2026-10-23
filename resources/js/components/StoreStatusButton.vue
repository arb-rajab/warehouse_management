<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import { CircleCheck, CircleSlash } from '@lucide/vue';
import FieldError from '@/components/FieldError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
withDefaults(
    defineProps<{
        action: UrlMethodPair;
        active: boolean;
        label: string;
        locked?: boolean;
        inputName?: 'status' | 'approved';
    }>(),
    { inputName: 'status' },
);
</script>

<template>
    <div>
        <span v-if="locked" class="inline-flex items-center gap-1">
            <CircleCheck class="h-4 w-4" />{{
                t('store.currencies.defaultCurrency')
            }}</span
        >
        <Form
            v-else
            :action="action"
            :options="{ preserveScroll: true }"
            #default="{ processing, errors }"
        >
            <slot />
            <input type="hidden" :name="inputName" :value="active ? 0 : 1" />
            <span class="inline-flex items-center gap-1">
                <CircleCheck v-if="active" class="h-4 w-4" />
                <CircleSlash v-else class="h-4 w-4" />{{
                    t(
                        active
                            ? 'store.reference.enabled'
                            : 'store.reference.disabled',
                    )
                }}</span
            >
            <SubmitButton
                :label="
                    t(
                        active
                            ? 'store.reference.disable'
                            : 'store.reference.enable',
                        { name: label },
                    )
                "
                :processing-label="t('store.reference.saving')"
                :processing="processing"
            />
            <FieldError :message="firstError(errors)" />
        </Form>
    </div>
</template>
