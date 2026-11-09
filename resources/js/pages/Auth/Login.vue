<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { store } from '@/actions/App/Http/Controllers/LoginController';
import AuthCard from '@/components/AuthCard.vue';
import FormField from '@/components/FormField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { t } from '@/lib/i18n';

defineProps<{
    honeypot: {
        enabled: boolean;
        nameFieldName: string;
        validFromFieldName: string;
        encryptedValidFrom: string;
    };
}>();
</script>

<template>
    <Head :title="t('auth.login.submit')" />

    <AuthCard :area="t('auth.login.brand')">
        <Form
            :action="store()"
            #default="{ errors, processing }"
            class="space-y-4"
        >
            <div
                v-if="honeypot.enabled"
                style="display: none"
                aria-hidden="true"
            >
                <input
                    :id="honeypot.nameFieldName"
                    :name="honeypot.nameFieldName"
                    type="text"
                    value=""
                    autocomplete="nope"
                    tabindex="-1"
                />
                <input
                    :name="honeypot.validFromFieldName"
                    type="text"
                    :value="honeypot.encryptedValidFrom"
                    autocomplete="off"
                    tabindex="-1"
                />
            </div>

            <FormField
                id="email"
                :label="t('auth.login.email')"
                type="email"
                :error="errors.email"
                maxlength="255"
                autocomplete="username"
                required
            />

            <FormField
                id="password"
                :label="t('auth.login.password')"
                type="password"
                :error="errors.password"
                maxlength="255"
                autocomplete="current-password"
                required
            />

            <SubmitButton
                :label="t('auth.login.submit')"
                :processing-label="t('auth.login.submitting')"
                :processing="processing"
            />
        </Form>
    </AuthCard>
</template>
