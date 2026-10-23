<script setup lang="ts">
import { ArrowDown, ArrowUp, Plus, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/SellerVerificationFormController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import {
    fieldLabelClass,
    filterTriggerButtonClass,
    plainFieldInputClass,
} from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { StoreSellerVerificationField } from '@/types/store';

const props = defineProps<{ fields: StoreSellerVerificationField[] }>();
const access = useStoreAccess();
const buttonClass = [
    filterTriggerButtonClass,
    'disabled:cursor-not-allowed disabled:opacity-50',
];
const types = ['text', 'file', 'select', 'multi_select', 'radio'] as const;
let nextId = 0;
function option(value = '') {
    return { id: nextId++, value };
}
function row(
    field: StoreSellerVerificationField = { type: 'text', label: '' },
) {
    return {
        id: nextId++,
        type: field.type,
        label: field.label,
        options: (field.options ?? []).map((value) => option(value)),
    };
}
const rows = ref(props.fields.map(row));
function hasOptions(type: string) {
    return ['select', 'multi_select', 'radio'].includes(type);
}
function changeType(index: number) {
    const field = rows.value[index];

    if (hasOptions(field.type)) {
        if (!field.options.length) {
            field.options.push(option());
        }
    } else {
        field.options = [];
    }
}
function move(index: number, direction: number) {
    const target = index + direction;

    if (target < 0 || target >= rows.value.length) {
        return;
    }

    [rows.value[index], rows.value[target]] = [
        rows.value[target],
        rows.value[index],
    ];
}
function payload() {
    return {
        fields: rows.value.map((field) => ({
            type: field.type,
            label: field.label,
            ...(hasOptions(field.type)
                ? { options: field.options.map((item) => item.value) }
                : {}),
        })),
    };
}
function inputValue(event: Event) {
    return (event.target as HTMLInputElement).value;
}
</script>

<template>
    <div>
        <ResourceFormPage
            v-if="access.can('seller_verification_form_configuration')"
            :title="t('store.sellers.verificationFormTitle')"
            :action="update()"
            :transform="payload"
            :submit-label="t('store.sellers.save')"
            :submitting-label="t('store.sellers.saving')"
            :layout="StoreAdminLayout"
            wide
            #default="{ errors }"
        >
            <FieldError :message="errors.fields" />
            <p v-if="!rows.length" class="text-gray-700 dark:text-neutral-200">
                {{ t('store.sellers.emptyForm') }}
            </p>
            <fieldset
                v-for="(field, index) in rows"
                :key="field.id"
                class="space-y-3 rounded-md border border-gray-300 p-4 dark:border-neutral-700"
                data-verification-field
            >
                <legend class="px-1 font-semibold">
                    {{ t('store.sellers.formField', { number: index + 1 }) }}
                </legend>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        :class="buttonClass"
                        :disabled="index === 0"
                        :aria-label="
                            t('store.sellers.moveFieldUp', {
                                number: index + 1,
                            })
                        "
                        @click="move(index, -1)"
                    >
                        <ArrowUp class="h-4 w-4" />{{
                            t('store.sellers.moveUp')
                        }}
                    </button>
                    <button
                        type="button"
                        :class="buttonClass"
                        :disabled="index === rows.length - 1"
                        :aria-label="
                            t('store.sellers.moveFieldDown', {
                                number: index + 1,
                            })
                        "
                        @click="move(index, 1)"
                    >
                        <ArrowDown class="h-4 w-4" />{{
                            t('store.sellers.moveDown')
                        }}
                    </button>
                    <button
                        type="button"
                        :class="buttonClass"
                        :aria-label="
                            t('store.sellers.removeField', {
                                number: index + 1,
                            })
                        "
                        @click="rows.splice(index, 1)"
                    >
                        <Trash2 class="h-4 w-4" />{{
                            t('store.sellers.remove')
                        }}
                    </button>
                </div>
                <div>
                    <label :for="`type-${field.id}`" :class="fieldLabelClass">{{
                        t('store.sellers.fieldType', { number: index + 1 })
                    }}</label>
                    <select
                        :id="`type-${field.id}`"
                        v-model="field.type"
                        :class="[plainFieldInputClass, 'cursor-pointer']"
                        required
                        @change="changeType(index)"
                    >
                        <option v-for="type in types" :key="type" :value="type">
                            {{ t(`store.sellers.fieldTypes.${type}`) }}
                        </option>
                    </select>
                    <FieldError :message="errors[`fields.${index}.type`]" />
                </div>
                <FormField
                    :id="`label-${field.id}`"
                    :label="
                        t('store.sellers.fieldLabel', { number: index + 1 })
                    "
                    :value="field.label"
                    :error="errors[`fields.${index}.label`]"
                    required
                    maxlength="255"
                    @input="field.label = inputValue($event)"
                />
                <template v-if="hasOptions(field.type)">
                    <FieldError :message="errors[`fields.${index}.options`]" />
                    <div
                        v-for="(item, optionIndex) in field.options"
                        :key="item.id"
                        class="flex items-end gap-2"
                    >
                        <FormField
                            :id="`option-${item.id}`"
                            class="flex-1"
                            :label="
                                t('store.sellers.fieldOption', {
                                    number: index + 1,
                                    option: optionIndex + 1,
                                })
                            "
                            :value="item.value"
                            :error="
                                errors[`fields.${index}.options.${optionIndex}`]
                            "
                            required
                            maxlength="255"
                            @input="item.value = inputValue($event)"
                        />
                        <button
                            type="button"
                            :class="buttonClass"
                            :disabled="field.options.length === 1"
                            :aria-label="
                                t('store.sellers.removeOption', {
                                    number: index + 1,
                                    option: optionIndex + 1,
                                })
                            "
                            @click="field.options.splice(optionIndex, 1)"
                        >
                            <Trash2 class="h-4 w-4" />{{
                                t('store.sellers.remove')
                            }}
                        </button>
                    </div>
                    <button
                        type="button"
                        :class="buttonClass"
                        :disabled="field.options.length >= 100"
                        :aria-label="
                            t('store.sellers.addFieldOption', {
                                number: index + 1,
                            })
                        "
                        @click="field.options.push(option())"
                    >
                        <Plus class="h-4 w-4" />{{
                            t('store.sellers.addOption')
                        }}
                    </button>
                </template>
            </fieldset>
            <button
                type="button"
                :class="buttonClass"
                :disabled="rows.length >= 100"
                @click="rows.push(row())"
            >
                <Plus class="h-4 w-4" />{{ t('store.sellers.addField') }}
            </button>
        </ResourceFormPage>
        <StoreAdminLayout v-else
            ><PageHeader :title="t('store.sellers.verificationFormTitle')"
        /></StoreAdminLayout>
    </div>
</template>
