<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { CircleAlert } from '@lucide/vue';
import { computed, ref } from 'vue';
import type { ComponentPublicInstance } from 'vue';
import { updateDelivery } from '@/actions/App/Http/Controllers/Store/Admin/OrderConfigurationController';
import DetailItem from '@/components/DetailItem.vue';
import DetailList from '@/components/DetailList.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { deliverySettingsValidation } from '@/lib/deliverySettingsForm';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useFormValidation } from '@/lib/useFormValidation';
import type { StoreDeliverySettings } from '@/types/store';

/**
 * The delivery date settings of the Order configuration page: the lead days
 * and the timezone orders from the app are checked against. A value saved here
 * wins over the server's `.env`; an empty field uses `.env`
 * (StoreDeliverySettings, .ai/rules/admin-env-overrides.md). Both fields are
 * optional, so there is no required mark. Validated in the browser first from
 * a copy of the server's rules (client-side-validation.md).
 */
const props = defineProps<{ delivery: StoreDeliverySettings }>();

const formComponent = ref<ComponentPublicInstance | null>(null);
const validator = useFormValidation(
    () => deliverySettingsValidation,
    () => formComponent.value?.$el as HTMLFormElement | undefined,
);

function source(value: 'admin' | 'env'): string {
    return t(`store.orderConfiguration.delivery.sources.${value}`);
}

const inUse = computed(() => {
    const { delivery_lead_days: days, delivery_timezone: zone } =
        props.delivery.effective;

    return {
        leadDays: t('store.orderConfiguration.delivery.inUse.leadDays', {
            value: days.value ?? '-',
            source: source(days.source),
        }),
        timezone: t('store.orderConfiguration.delivery.inUse.timezone', {
            value: zone.value ?? '-',
            source: source(zone.source),
        }),
    };
});
</script>

<template>
    <section data-section="delivery_settings" class="space-y-4">
        <h2 class="text-lg font-semibold">
            {{ t('store.orderConfiguration.delivery.title') }}
        </h2>
        <p class="text-sm text-muted">
            {{ t('store.orderConfiguration.delivery.intro') }}
        </p>
        <DetailList
            :title="t('store.orderConfiguration.delivery.inUse.title')"
            plain
        >
            <DetailItem
                :label="t('store.orderConfiguration.delivery.leadDays')"
                :value="inUse.leadDays"
            />
            <DetailItem
                :label="t('store.orderConfiguration.delivery.timezone')"
                :value="inUse.timezone"
            />
        </DetailList>
        <div
            @focusout="validator.onFocusOut"
            @input="validator.onInput"
            @change="validator.onChange"
        >
            <Form
                ref="formComponent"
                :action="updateDelivery()"
                novalidate
                :on-before="validator.onBefore"
                :on-start="validator.onStart"
                #default="{ errors, processing }"
                class="space-y-4"
            >
                <FormField
                    id="delivery_lead_days"
                    :label="t('store.orderConfiguration.delivery.leadDays')"
                    type="number"
                    :value="delivery.saved.delivery_lead_days ?? ''"
                    :error="validator.errorsFor(errors).delivery_lead_days"
                    :min="delivery.bounds.min"
                    :max="delivery.bounds.max"
                    step="1"
                    inputmode="numeric"
                    :placeholder="
                        String(delivery.environment.delivery_lead_days ?? '')
                    "
                    aria-describedby="delivery_lead_days_help"
                />
                <p
                    id="delivery_lead_days_help"
                    class="-mt-3 text-sm text-muted"
                >
                    {{
                        t('store.orderConfiguration.delivery.leadDaysHelp', {
                            min: delivery.bounds.min,
                            max: delivery.bounds.max,
                        })
                    }}
                </p>
                <div>
                    <label for="delivery_timezone" :class="fieldLabelClass">{{
                        t('store.orderConfiguration.delivery.timezone')
                    }}</label>
                    <select
                        id="delivery_timezone"
                        name="delivery_timezone"
                        :class="[plainFieldInputClass, 'cursor-pointer']"
                        aria-describedby="delivery_timezone_help"
                    >
                        <option
                            value=""
                            :selected="
                                delivery.saved.delivery_timezone === null
                            "
                        >
                            {{
                                t(
                                    'store.orderConfiguration.delivery.timezoneServer',
                                    {
                                        timezone:
                                            delivery.environment
                                                .delivery_timezone ?? '-',
                                    },
                                )
                            }}
                        </option>
                        <option
                            v-for="zone in delivery.timezones"
                            :key="zone"
                            :value="zone"
                            :selected="
                                delivery.saved.delivery_timezone === zone
                            "
                        >
                            {{ zone }}
                        </option>
                    </select>
                    <p
                        id="delivery_timezone_help"
                        class="mt-1 text-sm text-muted"
                    >
                        {{
                            t('store.orderConfiguration.delivery.timezoneHelp')
                        }}
                    </p>
                    <FieldError
                        :message="validator.errorsFor(errors).delivery_timezone"
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
    </section>
</template>
