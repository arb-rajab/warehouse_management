<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import { edit } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteFooterController';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteFooterController';
import FieldError from '@/components/FieldError.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreWebsiteFooterFormFields from '@/components/StoreWebsiteFooterFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type { StoreWebsiteFooterSettings } from '@/types/store';
const props = defineProps<{
    settings: StoreWebsiteFooterSettings;
    lang: string;
    languages: { code: string; name: string }[];
}>();
const settings = ref<StoreWebsiteFooterSettings>({
    ...props.settings,
    widget_one_labels: [...props.settings.widget_one_labels],
    widget_one_links: [...props.settings.widget_one_links],
});
function transform() {
    return { settings: { ...settings.value }, lang: props.lang };
}
</script>
<template>
    <ResourceFormPage
        :title="t('store.website.footer.title')"
        :action="update()"
        :submit-label="t('store.website.save')"
        :submitting-label="t('store.website.saving')"
        :layout="StoreAdminLayout"
        :transform="transform"
        wide
        ><template #header-actions
            ><label :class="fieldLabelClass"
                >{{ t('store.website.pages.language')
                }}<select
                    :value="lang"
                    :class="plainFieldInputClass"
                    @change="
                        router.get(
                            edit.url({
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
            ><StoreWebsiteFooterFormFields
                v-model="settings"
                :errors="errors" /><FieldError
                :message="errors.lang" /></template
    ></ResourceFormPage>
</template>
