<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { CircleAlert, TriangleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { ComponentPublicInstance } from 'vue';
import { updateDefault } from '@/actions/App/Http/Controllers/Store/Admin/LanguageController';
import FieldError from '@/components/FieldError.vue';
import StoreHighlight from '@/components/StoreHighlight.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { confirmDefaultLanguageChange } from '@/lib/confirm';
import { defaultLanguageValidation } from '@/lib/defaultLanguageForm';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useFormValidation } from '@/lib/useFormValidation';
import type { StoreDefaultLanguageSetting } from '@/types/store';

/**
 * Choose the default content language (StoreDefaultLanguage,
 * .ai/rules/admin-env-overrides.md). The language saved here wins over the
 * server's DEFAULT_LANGUAGE; "use the server setting" deletes the saved one.
 * Only active languages can be chosen. The saved language stays in the select,
 * marked "switched off", when it was switched off since, so the page shows
 * what the setup check reads (setup-checklist-reasons.md). The optional field
 * has no required mark. Changing the default asks first: text saved under the
 * old default is read as the new default's until it is translated.
 */
const props = defineProps<{
    setting: StoreDefaultLanguageSetting;
    /** `default_language` when a setup checklist link sent the admin here. */
    highlight?: string | null;
}>();

const formComponent = ref<ComponentPublicInstance | null>(null);
const validator = useFormValidation(
    () => defaultLanguageValidation,
    () => formComponent.value?.$el as HTMLFormElement | undefined,
);

const code = computed(() => props.setting.effective.value);
const inUse = computed(() =>
    t('store.languages.defaultInUse', {
        code: code.value ?? '-',
        source: t(
            `store.languages.defaultSources.${props.setting.effective.source}`,
        ),
    }),
);
const warning = computed(() => {
    if (props.setting.state === 'ok') {
        return null;
    }

    if (code.value === null) {
        return t('store.languages.defaultNone');
    }

    return t(`store.languages.default_${props.setting.state}`, {
        code: code.value,
    });
});

/** Confirm a change of the language in use; saving the same one needs none. */
function beforeSubmit(): boolean {
    if (!validator.onBefore()) {
        return false;
    }

    const element = formComponent.value?.$el as HTMLFormElement | undefined;
    const chosen = (
        element?.elements.namedItem(
            'default_language',
        ) as HTMLSelectElement | null
    )?.value;
    const next = chosen === '' ? props.setting.environment : chosen;

    if (next === code.value) {
        return true;
    }

    const option = props.setting.options.find((o) => o.code === next);

    return confirmDefaultLanguageChange(option?.name ?? next ?? '-');
}
</script>

<template>
    <StoreHighlight
        :active="highlight === 'default_language'"
        data-section="default_language"
        class="mb-6 max-w-xl space-y-3"
    >
        <h2 class="text-lg font-semibold">
            {{ t('store.languages.defaultTitle') }}
        </h2>
        <p data-testid="default-in-use">{{ inUse }}</p>
        <p
            v-if="warning"
            role="alert"
            class="flex items-center gap-2 text-sm text-danger"
            data-warning="default_language"
        >
            <TriangleAlert class="h-4 w-4 shrink-0" aria-hidden="true" />{{
                warning
            }}
        </p>
        <div
            @focusout="validator.onFocusOut"
            @input="validator.onInput"
            @change="validator.onChange"
        >
            <Form
                ref="formComponent"
                :action="updateDefault()"
                novalidate
                :on-before="beforeSubmit"
                :on-start="validator.onStart"
                #default="{ errors, processing }"
                class="space-y-3"
            >
                <div>
                    <label for="default_language" :class="fieldLabelClass">{{
                        t('store.languages.defaultLabel')
                    }}</label>
                    <select
                        id="default_language"
                        name="default_language"
                        :class="[plainFieldInputClass, 'cursor-pointer']"
                        aria-describedby="default_language_help"
                    >
                        <option value="" :selected="setting.saved === null">
                            {{
                                t('store.languages.defaultServer', {
                                    code: setting.environment ?? '-',
                                })
                            }}
                        </option>
                        <option
                            v-for="option in setting.options"
                            :key="option.code"
                            :value="option.code"
                            :selected="setting.saved === option.code"
                        >
                            {{ option.name }} ({{ option.code
                            }}{{
                                option.active
                                    ? ''
                                    : `, ${t('store.languages.defaultSwitchedOff')}`
                            }})
                        </option>
                    </select>
                    <p
                        id="default_language_help"
                        class="mt-1 text-sm text-muted"
                    >
                        {{ t('store.languages.defaultHelp') }}
                    </p>
                    <FieldError
                        :message="validator.errorsFor(errors).default_language"
                    />
                </div>
                <p
                    v-if="validator.blocked.value"
                    role="alert"
                    class="flex items-center gap-1 text-sm text-danger"
                >
                    <CircleAlert
                        class="h-3.5 w-3.5 shrink-0"
                        aria-hidden="true"
                    />
                    {{ t('common.formValidation.blocked') }}
                </p>
                <SubmitButton
                    :label="t('store.reference.save')"
                    :processing-label="t('store.reference.saving')"
                    :processing="processing"
                />
            </Form>
        </div>
    </StoreHighlight>
</template>
