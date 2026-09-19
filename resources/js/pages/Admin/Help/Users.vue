<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { index as helpIndex } from '@/actions/App/Http/Controllers/Admin/HelpController';
import { index as usersIndex } from '@/actions/App/Http/Controllers/Admin/UserController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import PageHeader from '@/components/PageHeader.vue';
import UserFormFields from '@/components/UserFormFields.vue';
import UserRoleBadge from '@/components/UserRoleBadge.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { filterSectionHeadingClass as sectionHeadingClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
</script>

<template>
    <Head :title="t('help.users.title')" />

    <AdminLayout>
        <PageHeader :title="t('help.users.title')" />

        <Link
            :href="helpIndex()"
            class="mb-6 inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
        >
            {{ t('help.backToHelp') }}
        </Link>

        <div class="max-w-2xl space-y-6">
            <section>
                <h2 :class="sectionHeadingClass">
                    {{ t('help.users.accounts.heading') }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t('help.users.accounts.body') }}
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <HelpUiPreview>
                        <AddResourceLink
                            href="#"
                            tabindex="-1"
                            :label="t('users.index.addUser')"
                        />
                    </HelpUiPreview>
                </div>
                <HelpUiPreview inert class="mt-2 block max-w-xs">
                    <UserFormFields
                        password-required
                        :is-admin="true"
                        :errors="{}"
                    />
                </HelpUiPreview>
            </section>

            <section>
                <h2 :class="sectionHeadingClass">
                    {{ t('help.users.roles.heading') }}
                </h2>
                <p class="text-sm text-gray-700 dark:text-neutral-300">
                    {{ t('help.users.roles.body') }}
                </p>
                <div class="mt-2 flex flex-wrap items-center gap-3">
                    <HelpUiPreview>
                        <UserRoleBadge :is-admin="true" />
                    </HelpUiPreview>
                    <HelpUiPreview>
                        <UserRoleBadge :is-admin="false" />
                    </HelpUiPreview>
                </div>
            </section>

            <Link
                :href="usersIndex()"
                class="inline-block text-sm text-blue-600 hover:underline dark:text-blue-400"
            >
                {{ t('users.index.title') }}
            </Link>
        </div>
    </AdminLayout>
</template>
