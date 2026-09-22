<script setup lang="ts">
import { index as usersIndex } from '@/actions/App/Http/Controllers/Admin/UserController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import HelpTopicPage from '@/components/HelpTopicPage.vue';
import HelpUiPreview from '@/components/HelpUiPreview.vue';
import UserFormFields from '@/components/UserFormFields.vue';
import UserRoleBadge from '@/components/UserRoleBadge.vue';
import { filterSectionHeadingClass as sectionHeadingClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
</script>

<template>
    <HelpTopicPage
        :title="t('help.users.title')"
        :feature-href="usersIndex()"
        :feature-label="t('users.index.title')"
    >
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
    </HelpTopicPage>
</template>
