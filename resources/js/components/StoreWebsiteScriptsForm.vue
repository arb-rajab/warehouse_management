<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ShieldCheck } from '@lucide/vue';
import { ref } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteScriptsController';
import FieldError from '@/components/FieldError.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
const props = defineProps<{
    settings: { header_script: string; footer_script: string };
}>();
const settings = ref({ ...props.settings });
const access = useStoreAccess();
const keys = ['header_script', 'footer_script'] as const;
function transform() {
    return { settings: { ...settings.value } };
}
</script>
<template>
    <section v-if="access.can('manage_website_scripts')" class="mt-6 space-y-4">
        <h2 class="flex items-center gap-2 text-lg font-semibold">
            <ShieldCheck class="h-5 w-5" />{{
                t('store.website.scripts.title')
            }}
        </h2>
        <Form
            :action="update()"
            :transform="transform"
            #default="{ errors, processing }"
            class="space-y-4"
            ><FieldError :message="errors.settings" />
            <div v-for="key in keys" :key="key">
                <label :for="key" :class="fieldLabelClass">{{
                    t(`store.website.fields.${key}`)
                }}</label
                ><textarea
                    :id="key"
                    :name="`settings[${key}]`"
                    v-model="settings[key]"
                    :class="plainFieldInputClass"
                    rows="6"
                    maxlength="100000"
                /><FieldError :message="errors[`settings.${key}`]" />
            </div>
            <SubmitButton
                :label="t('store.website.save')"
                :processing-label="t('store.website.saving')"
                :processing="processing"
        /></Form>
    </section>
</template>
