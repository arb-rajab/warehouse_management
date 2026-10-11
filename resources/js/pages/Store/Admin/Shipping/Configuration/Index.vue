<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { index as carriersIndex } from '@/actions/App/Http/Controllers/CarrierController';
import { index as citiesIndex } from '@/actions/App/Http/Controllers/Store/Admin/CityController';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/ShippingConfigurationController';
import FormField from '@/components/FormField.vue';
import PageHeader from '@/components/PageHeader.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';

defineProps<{
    settings: {
        shipping_type: string;
        flat_rate_shipping_cost: string;
        shipping_cost_admin: string;
    };
}>();

const shippingTypes = [
    'product_wise_shipping',
    'flat_rate',
    'seller_wise_shipping',
    'area_wise_shipping',
    'carrier_wise_shipping',
] as const;
</script>

<template>
    <Head :title="t('store.shippingConfiguration.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.shippingConfiguration.title')" />
        <div class="grid gap-6 xl:grid-cols-2">
            <Form
                :action="update()"
                class="space-y-4 rounded-md border border-gray-200 p-4 dark:border-neutral-800"
                #default="{ errors, processing }"
            >
                <input type="hidden" name="type" value="shipping_type" />
                <h2 class="font-semibold">
                    {{ t('store.shippingConfiguration.methodTitle') }}
                </h2>
                <label
                    v-for="shippingType in shippingTypes"
                    :key="shippingType"
                    class="flex cursor-pointer items-center gap-2 text-sm"
                >
                    <input
                        type="radio"
                        name="shipping_type"
                        :value="shippingType"
                        :checked="settings.shipping_type === shippingType"
                        required
                        class="cursor-pointer"
                    />
                    {{
                        t(`store.shippingConfiguration.methods.${shippingType}`)
                    }}
                </label>
                <p v-if="errors.shipping_type" class="text-sm text-red-600">
                    {{ errors.shipping_type }}
                </p>
                <SubmitButton
                    :label="t('common.save')"
                    :processing-label="t('common.saving')"
                    :processing="processing"
                />
            </Form>

            <aside
                class="rounded-md border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-200"
            >
                <h2 class="mb-3 flex items-center gap-2 font-semibold">
                    <TriangleAlert class="h-4 w-4" />
                    {{ t('store.shippingConfiguration.notesTitle') }}
                </h2>
                <ol class="list-inside list-decimal space-y-2">
                    <li>
                        {{ t('store.shippingConfiguration.notes.product') }}
                    </li>
                    <li>{{ t('store.shippingConfiguration.notes.flat') }}</li>
                    <li>{{ t('store.shippingConfiguration.notes.seller') }}</li>
                    <li>
                        {{ t('store.shippingConfiguration.notes.area') }}
                        <Link :href="citiesIndex()" class="underline">
                            {{ t('store.nav.shippingCities') }}
                        </Link>
                    </li>
                    <li>
                        {{ t('store.shippingConfiguration.notes.carrier') }}
                        <Link :href="carriersIndex()" class="underline">
                            {{ t('store.nav.shippingCarriers') }}
                        </Link>
                    </li>
                </ol>
            </aside>

            <Form
                :action="update()"
                class="space-y-4 rounded-md border border-gray-200 p-4 dark:border-neutral-800"
                #default="{ errors, processing }"
            >
                <input
                    type="hidden"
                    name="type"
                    value="flat_rate_shipping_cost"
                />
                <h2 class="font-semibold">
                    {{ t('store.shippingConfiguration.flatRateTitle') }}
                </h2>
                <FormField
                    id="flat_rate_shipping_cost"
                    :label="t('store.shippingConfiguration.cost')"
                    type="number"
                    :value="settings.flat_rate_shipping_cost"
                    :error="errors.flat_rate_shipping_cost"
                    min="0"
                    step="0.01"
                    required
                />
                <SubmitButton
                    :label="t('common.save')"
                    :processing-label="t('common.saving')"
                    :processing="processing"
                />
            </Form>

            <Form
                :action="update()"
                class="space-y-4 rounded-md border border-gray-200 p-4 dark:border-neutral-800"
                #default="{ errors, processing }"
            >
                <input type="hidden" name="type" value="shipping_cost_admin" />
                <h2 class="font-semibold">
                    {{ t('store.shippingConfiguration.adminCostTitle') }}
                </h2>
                <FormField
                    id="shipping_cost_admin"
                    :label="t('store.shippingConfiguration.cost')"
                    type="number"
                    :value="settings.shipping_cost_admin"
                    :error="errors.shipping_cost_admin"
                    min="0"
                    step="0.01"
                    required
                />
                <SubmitButton
                    :label="t('common.save')"
                    :processing-label="t('common.saving')"
                    :processing="processing"
                />
            </Form>
        </div>
    </StoreAdminLayout>
</template>
