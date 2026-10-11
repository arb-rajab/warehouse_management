<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { edit } from '@/actions/App/Http/Controllers/Store/Admin/WebsitePageController';
import {
    update,
    index as pagesIndex,
} from '@/actions/App/Http/Controllers/Store/Admin/WebsitePageController';
import FieldError from '@/components/FieldError.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreWebsitePageFormFields from '@/components/StoreWebsitePageFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreWebsitePageForm } from '@/types/store';
const props = defineProps<{
    lang: string;
    languages: { code: string; name: string }[];
    page: StoreWebsitePageForm & { id: number; type: string };
}>();
const page = ref<StoreWebsitePageForm>({ ...props.page });
function transform() {
    return {
        title: page.value.title,
        slug: page.value.slug,
        content: page.value.content,
        meta_title: page.value.meta_title,
        meta_description: page.value.meta_description,
        keywords: page.value.keywords,
        meta_image: page.value.meta_image,
        lang: props.lang,
    };
}
</script>
<template>
    <ResourceFormPage
        :title="t('store.website.pages.editTitle')"
        :action="update(props.page)"
        :cancel-href="pagesIndex()"
        :submit-label="t('store.website.save')"
        :submitting-label="t('store.website.saving')"
        :layout="StoreAdminLayout"
        :transform="transform"
        wide
    >
        <template #header-actions
            ><label :class="fieldLabelClass"
                >{{ t('store.website.pages.language')
                }}<select
                    :value="lang"
                    :class="plainFieldInputClass"
                    @change="
                        router.get(
                            edit.url(props.page, {
                                query: {
                                    lang: ($event.target as HTMLSelectElement)
                                        .value,
                                },
                            }),
                            {},
                            { preserveState: false },
                        )
                    "
                >
                    <option
                        v-if="
                            !languages.some(
                                (language) => language.code === lang,
                            )
                        "
                        :value="lang"
                    >
                        {{ lang }}
                    </option>
                    <option
                        v-for="language in languages"
                        :key="language.code"
                        :value="language.code"
                    >
                        {{ language.name }}
                    </option>
                </select></label
            ></template
        ><template #default="{ errors }"
            ><StoreWebsitePageFormFields
                v-model="page"
                :errors="errors"
                :built-in="props.page.type !== 'custom_page'" /><FieldError
                :message="errors.lang" /></template
    ></ResourceFormPage>
</template>
