<script setup lang="ts">
import {
    index as usersIndex,
    store,
} from '@/actions/App/Http/Controllers/Admin/UserController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import UserFormFields from '@/components/UserFormFields.vue';
import { t } from '@/lib/i18n';
</script>

<template>
    <ResourceFormPage
        :title="t('users.create.title')"
        :action="store()"
        :submit-label="t('users.create.submit')"
        :submitting-label="t('users.create.submitting')"
        :cancel-href="usersIndex()"
        #default="{ errors }"
    >
        <UserFormFields :errors="errors" password-required />
    </ResourceFormPage>
</template>
