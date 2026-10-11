<script setup lang="ts">
import { ref } from 'vue';
import FormField from '@/components/FormField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { t } from '@/lib/i18n';
import { usePasswordConfirmationMismatch } from '@/lib/passwordConfirmation';
import type { StoreProfile } from '@/types/store';
const props = defineProps<{
    profile: StoreProfile;
    errors: Partial<
        Record<'name' | 'email' | 'avatar' | 'new_password', string>
    >;
}>();
const avatar = ref(props.profile.avatar ? String(props.profile.avatar.id) : '');
const newPassword = ref('');
const { confirmationMismatchError, syncConfirmationValidity } =
    usePasswordConfirmationMismatch('new_password', 'confirm_password');
</script>
<template>
    <div class="space-y-4">
        <FormField
            id="name"
            :label="t('store.profile.name')"
            :value="profile.name"
            :error="errors.name"
            maxlength="255"
            required
        />
        <FormField
            id="email"
            :label="t('store.profile.email')"
            type="email"
            :value="profile.email"
            :error="errors.email"
            maxlength="255"
            required
        />
        <FormField
            id="new_password"
            :label="t('store.profile.newPassword')"
            type="password"
            :error="errors.new_password"
            minlength="8"
            maxlength="255"
            autocomplete="new-password"
            @input="
                newPassword = ($event.target as HTMLInputElement).value;
                syncConfirmationValidity();
            "
        />
        <FormField
            id="confirm_password"
            :label="t('store.profile.confirmPassword')"
            type="password"
            :error="confirmationMismatchError"
            maxlength="255"
            :required="newPassword !== ''"
            autocomplete="new-password"
            @input="syncConfirmationValidity"
        />
        <StoreUploadField
            v-model="avatar"
            name="avatar"
            :label="t('store.profile.avatar')"
            type="image"
            :initial-files="profile.avatar ? [profile.avatar] : []"
            :error="errors.avatar"
        />
    </div>
</template>
