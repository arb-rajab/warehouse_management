<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import FieldError from '@/components/FieldError.vue';
import RequiredLegend from '@/components/RequiredLegend.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { provideRequiredFields } from '@/lib/requiredFields';

defineProps<{ action: UrlMethodPair; field: 'message' | 'reply' }>();
const hasRequiredFields = provideRequiredFields();
</script>

<template>
    <Form
        :action="action"
        reset-on-success
        #default="{ errors, processing }"
        class="space-y-3"
    >
        <RequiredLegend v-if="hasRequiredFields" />
        <div>
            <label :for="field" :class="fieldLabelClass"
                >{{ t('store.support.replyLabel') }}<RequiredMark
            /></label>
            <textarea
                :id="field"
                :name="field"
                rows="4"
                required
                maxlength="10000"
                :class="plainFieldInputClass"
            />
            <FieldError :message="errors[field]" />
        </div>
        <SubmitButton
            :label="t('store.support.send')"
            :processing-label="t('store.support.sending')"
            :processing="processing"
        />
    </Form>
</template>
