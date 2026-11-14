<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { index as carriersIndex } from '@/actions/App/Http/Controllers/Store/Admin/CarrierController';
import { index as citiesIndex } from '@/actions/App/Http/Controllers/Store/Admin/CityController';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/ShippingConfigurationController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import RequiredLegend from '@/components/RequiredLegend.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreHighlight from '@/components/StoreHighlight.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { provideRequiredFields } from '@/lib/requiredFields';
import { useStoreAccess } from '@/lib/storeAccess';
/**
 * Each setting arrives exactly as saved: null when never saved, so no type
 * is shown as chosen and a cost field stays empty rather than showing a 0
 * that looks set. `highlight` (validated by the controller) marks the setting
 * a dashboard setup checklist link sent the admin here to fix
 * (.ai/rules/deep-link-highlight.md, .ai/rules/setup-checklist-reasons.md).
 */
const props = defineProps<{
    settings: {
        shipping_type: string | null;
        flat_rate_shipping_cost: string | number | null;
        shipping_cost_admin: string | number | null;
    };
    highlight?: string | null;
}>();
const access = useStoreAccess();
const hasRequiredFields = provideRequiredFields();
const methods = [
    'product_wise_shipping',
    'flat_rate',
    'seller_wise_shipping',
    'area_wise_shipping',
    'carrier_wise_shipping',
];
const typeChosen = computed(() =>
    methods.includes(props.settings.shipping_type ?? ''),
);
// The notes that explain each type; area and carrier wise also link to the
// data they are priced from, and a deep link can mark either of those.
const notes: { key: string; highlight?: 'cities' | 'carriers' }[] = [
    { key: 'productNote' },
    { key: 'flatNote' },
    { key: 'sellerNote' },
    { key: 'areaNote', highlight: 'cities' },
    { key: 'carrierNote', highlight: 'carriers' },
];
</script>

<template>
    <StoreAdminLayout>
        <Head :title="t('store.shippingConfiguration.title')" />
        <PageHeader :title="t('store.shippingConfiguration.title')" />
        <div class="grid gap-8 md:grid-cols-2">
            <section
                v-if="access.can('shipping_configuration')"
                class="space-y-8"
            >
                <Form
                    :action="update()"
                    class="space-y-4"
                    #default="{ errors, processing }"
                >
                    <RequiredLegend v-if="hasRequiredFields" />
                    <input type="hidden" name="type" value="shipping_type" />
                    <StoreHighlight :active="highlight === 'shipping_type'">
                        <label for="shipping_type" :class="fieldLabelClass"
                            >{{ t('store.shippingConfiguration.method')
                            }}<RequiredMark
                        /></label>
                        <select
                            id="shipping_type"
                            name="shipping_type"
                            :class="[plainFieldInputClass, 'cursor-pointer']"
                            required
                        >
                            <option value="" :selected="!typeChosen">
                                {{
                                    t(
                                        'store.shippingConfiguration.chooseMethod',
                                    )
                                }}
                            </option>
                            <option
                                v-for="method in methods"
                                :key="method"
                                :value="method"
                                :selected="settings.shipping_type === method"
                            >
                                {{ t(`store.shippingConfiguration.${method}`) }}
                            </option>
                        </select>
                        <FieldError :message="errors.shipping_type" />
                    </StoreHighlight>
                    <SubmitButton
                        :label="t('store.reference.save')"
                        :processing-label="t('store.reference.saving')"
                        :processing="processing"
                    />
                </Form>
                <Form
                    :action="update()"
                    class="space-y-4"
                    #default="{ errors, processing }"
                >
                    <RequiredLegend v-if="hasRequiredFields" />
                    <input
                        type="hidden"
                        name="type"
                        value="flat_rate_shipping_cost"
                    />
                    <StoreHighlight
                        :active="highlight === 'flat_rate_shipping_cost'"
                    >
                        <FormField
                            id="flat_rate_shipping_cost"
                            type="number"
                            :label="t('store.shippingConfiguration.flatCost')"
                            :value="settings.flat_rate_shipping_cost ?? ''"
                            :error="errors.flat_rate_shipping_cost"
                            required
                            min="0"
                            step="any"
                        />
                    </StoreHighlight>
                    <SubmitButton
                        :label="t('store.reference.save')"
                        :processing-label="t('store.reference.saving')"
                        :processing="processing"
                    />
                </Form>
                <Form
                    :action="update()"
                    class="space-y-4"
                    #default="{ errors, processing }"
                >
                    <RequiredLegend v-if="hasRequiredFields" />
                    <input
                        type="hidden"
                        name="type"
                        value="shipping_cost_admin"
                    />
                    <StoreHighlight
                        :active="highlight === 'shipping_cost_admin'"
                    >
                        <FormField
                            id="shipping_cost_admin"
                            type="number"
                            :label="t('store.shippingConfiguration.adminCost')"
                            :value="settings.shipping_cost_admin ?? ''"
                            :error="errors.shipping_cost_admin"
                            required
                            min="0"
                            step="any"
                        />
                    </StoreHighlight>
                    <SubmitButton
                        :label="t('store.reference.save')"
                        :processing-label="t('store.reference.saving')"
                        :processing="processing"
                    />
                </Form>
            </section>
            <section class="space-y-4 text-sm">
                <StoreHighlight
                    v-for="note in notes"
                    :key="note.key"
                    :active="!!note.highlight && highlight === note.highlight"
                    :data-note="note.key"
                    class="space-y-1"
                >
                    <p>{{ t(`store.shippingConfiguration.${note.key}`) }}</p>
                    <Link
                        v-if="
                            note.highlight === 'cities' &&
                            access.can('manage_shipping_cities')
                        "
                        :href="citiesIndex()"
                        class="block underline"
                        >{{ t('store.shippingConfiguration.cities') }}</Link
                    >
                    <Link
                        v-if="
                            note.highlight === 'carriers' &&
                            access.can('manage_carriers')
                        "
                        :href="carriersIndex()"
                        class="block underline"
                        >{{ t('store.shippingConfiguration.carriers') }}</Link
                    >
                </StoreHighlight>
            </section>
        </div>
    </StoreAdminLayout>
</template>
