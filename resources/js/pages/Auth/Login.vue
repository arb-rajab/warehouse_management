<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Warehouse } from '@lucide/vue';
import { store } from '@/actions/App/Http/Controllers/LoginController';
import FormField from '@/components/FormField.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { t } from '@/lib/i18n';

const props = defineProps<{
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

    <div
        class="flex min-h-screen items-center justify-center bg-gray-50 text-gray-900 dark:bg-neutral-950 dark:text-neutral-100"
    >
        <div
            class="w-full max-w-sm rounded-lg border border-gray-200 bg-white p-8 shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
        >
            <div class="mb-6 flex items-center justify-between">
                <h1
                    class="flex items-center gap-2 text-lg font-semibold text-gray-900 dark:text-neutral-100"
                >
                    <Warehouse class="h-5 w-5 shrink-0" />
                    {{ t('auth.login.brand') }}
                </h1>
                <LanguageSwitcher />
            </div>

            <Form
                :action="store()"
                #default="{ errors, processing }"
                class="space-y-4"
            >
                <div
                    v-if="props.honeypot.enabled"
                    style="display: none"
                    aria-hidden="true"
                >
                    <input
                        :id="props.honeypot.nameFieldName"
                        :name="props.honeypot.nameFieldName"
                        type="text"
                        value=""
                        autocomplete="nope"
                        tabindex="-1"
                    />
                    <input
                        :name="props.honeypot.validFromFieldName"
                        type="text"
                        :value="props.honeypot.encryptedValidFrom"
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
        </div>
    </div>
</template>
