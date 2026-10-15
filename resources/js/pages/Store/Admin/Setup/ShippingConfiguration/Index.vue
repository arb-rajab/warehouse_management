<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { index as carriersIndex } from '@/actions/App/Http/Controllers/Store/Admin/CarrierController';
import { index as citiesIndex } from '@/actions/App/Http/Controllers/Store/Admin/CityController';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/ShippingConfigurationController';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
defineProps<{
    settings: {
        shipping_type: string | null;
        flat_rate_shipping_cost: string | number;
        shipping_cost_admin: string | number;
    };
}>();
const access = useStoreAccess();
const methods = [
    'product_wise_shipping',
    'flat_rate',
    'seller_wise_shipping',
    'area_wise_shipping',
    'carrier_wise_shipping',
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
                    <input type="hidden" name="type" value="shipping_type" />
                    <div>
                        <label for="shipping_type" :class="fieldLabelClass">{{
                            t('store.shippingConfiguration.method')
                        }}</label>
                        <select
                            id="shipping_type"
                            name="shipping_type"
                            :class="[plainFieldInputClass, 'cursor-pointer']"
                            required
                        >
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
                    </div>
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
                    <input
                        type="hidden"
                        name="type"
                        value="flat_rate_shipping_cost"
                    />
                    <FormField
                        id="flat_rate_shipping_cost"
                        type="number"
                        :label="t('store.shippingConfiguration.flatCost')"
                        :value="settings.flat_rate_shipping_cost"
                        :error="errors.flat_rate_shipping_cost"
                        required
                        min="0"
                        step="any"
                    />
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
                    <input
                        type="hidden"
                        name="type"
                        value="shipping_cost_admin"
                    />
                    <FormField
                        id="shipping_cost_admin"
                        type="number"
                        :label="t('store.shippingConfiguration.adminCost')"
                        :value="settings.shipping_cost_admin"
                        :error="errors.shipping_cost_admin"
                        required
                        min="0"
                        step="any"
                    />
                    <SubmitButton
                        :label="t('store.reference.save')"
                        :processing-label="t('store.reference.saving')"
                        :processing="processing"
                    />
                </Form>
            </section>
            <section class="space-y-4 text-sm">
                <p
                    v-for="note in [
                        'productNote',
                        'flatNote',
                        'sellerNote',
                        'areaNote',
                        'carrierNote',
                    ]"
                    :key="note"
                >
                    {{ t(`store.shippingConfiguration.${note}`) }}
                </p>
                <Link
                    v-if="access.can('manage_shipping_cities')"
                    :href="citiesIndex()"
                    class="block underline"
                    >{{ t('store.shippingConfiguration.cities') }}</Link
                >
                <Link
                    v-if="access.can('manage_carriers')"
                    :href="carriersIndex()"
                    class="block underline"
                    >{{ t('store.shippingConfiguration.carriers') }}</Link
                >
            </section>
        </div>
    </StoreAdminLayout>
</template>
