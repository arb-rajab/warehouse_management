<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { smtp_settings } from '@/actions/App/Http/Controllers/BusinessSettingsController';
import PageHeader from '@/components/PageHeader.vue';
import StoreDashboardChart from '@/components/StoreDashboardChart.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
defineProps<{
    stats: {
        customers: number;
        orders: number;
        categories: number;
        brands: number;
        products: number;
        active_products: number;
        api_products: number;
        local_products: number;
    };
    charts: { labels: string[]; sales: number[]; stock: number[] };
    topProducts: {
        id: number;
        name: string;
        url: string;
        image: string | null;
        base_price: string;
        price: string;
        rating: number;
    }[];
    smtp_missing: boolean;
}>();
</script>
<template>
    <Head :title="t('store.nav.dashboard')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.nav.dashboard')" />
        <p
            v-if="smtp_missing"
            class="mb-5 flex items-center gap-2 rounded-lg border border-amber-300 p-3 text-amber-900 dark:text-amber-200"
        >
            <TriangleAlert class="h-5 w-5" />{{
                t('store.dashboard.smtpMissing')
            }}
            <a :href="smtp_settings.url()" class="underline">{{
                t('store.dashboard.configure')
            }}</a>
        </p>
        <dl class="mb-5 grid grid-cols-2 gap-4 md:grid-cols-4">
            <div
                v-for="key in [
                    'customers',
                    'orders',
                    'categories',
                    'brands',
                ] as const"
                :key="key"
                class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
            >
                <dt>{{ t(`store.dashboard.${key}`) }}</dt>
                <dd class="mt-2 text-2xl font-semibold">{{ stats[key] }}</dd>
            </div>
        </dl>
        <div class="grid gap-4 md:grid-cols-2">
            <StoreDashboardChart
                :title="t('store.dashboard.products')"
                :labels="[
                    t('store.dashboard.active'),
                    t('store.dashboard.inactive'),
                ]"
                :values="[
                    stats.active_products,
                    stats.products - stats.active_products,
                ]"
                kind="doughnut"
            /><StoreDashboardChart
                :title="t('store.dashboard.sources')"
                :labels="[t('store.dashboard.api'), t('store.dashboard.local')]"
                :values="[stats.api_products, stats.local_products]"
                kind="doughnut"
            /><StoreDashboardChart
                :title="t('store.dashboard.sales')"
                :labels="charts.labels"
                :values="charts.sales"
                kind="bar"
            /><StoreDashboardChart
                :title="t('store.dashboard.stock')"
                :labels="charts.labels"
                :values="charts.stock"
                kind="bar"
            />
        </div>
        <section class="mt-6">
            <h2 class="mb-3 text-xl font-semibold">
                {{ t('store.dashboard.topProducts') }}
            </h2>
            <div class="flex gap-4 overflow-x-auto pb-3">
                <a
                    v-for="product in topProducts"
                    :key="product.id"
                    :href="product.url"
                    class="w-52 shrink-0 rounded-lg border border-gray-200 p-3 dark:border-neutral-800"
                >
                    <img
                        v-if="product.image"
                        :src="product.image"
                        :alt="product.name"
                        loading="lazy"
                        class="h-36 w-full object-contain"
                    />
                    <h3 class="mt-2 font-medium">{{ product.name }}</h3>
                    <del
                        v-if="product.base_price !== product.price"
                        class="me-2 text-gray-500"
                        >{{ product.base_price }}</del
                    >
                    <span>{{ product.price }}</span>
                    <p>
                        {{ t('store.dashboard.rating') }}:
                        {{ product.rating.toFixed(1) }}/5
                    </p>
                </a>
            </div>
        </section>
    </StoreAdminLayout>
</template>
