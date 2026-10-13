<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import FieldError from '@/components/FieldError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';

defineProps<{ action: UrlMethodPair; field: 'message' | 'reply' }>();
</script>

<template>
    <Form
        :action="action"
        reset-on-success
        #default="{ errors, processing }"
        class="space-y-3"
    >
        <div>
            <label :for="field" :class="fieldLabelClass">{{
                t('store.support.replyLabel')
            }}</label>
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
