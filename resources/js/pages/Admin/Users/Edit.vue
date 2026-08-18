<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import {
    index,
    update,
} from '@/actions/App/Http/Controllers/Admin/UserController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import UserFormFields from '@/components/UserFormFields.vue';
import { t } from '@/lib/i18n';
import type { User } from '@/types/admin';

const props = defineProps<{
    user: User;
}>();

const page = usePage();
const isEditingSelf = page.props.auth.user?.id === props.user.id;
</script>

<template>
    <ResourceFormPage
        :title="t('users.edit.title', { name: props.user.name })"
        :action="update(props.user)"
        :submit-label="t('users.edit.submit')"
        :submitting-label="t('users.edit.submitting')"
        :cancel-href="index()"
        #default="{ errors }"
    >
        <UserFormFields
            :name="props.user.name"
            :email="props.user.email"
            :is-admin="props.user.is_admin"
            :disable-admin-toggle="isEditingSelf"
            :errors="errors"
        />
    </ResourceFormPage>
</template>
