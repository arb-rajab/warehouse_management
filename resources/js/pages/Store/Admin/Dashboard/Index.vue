<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { PackageSearch, TriangleAlert } from '@lucide/vue';
import { computed } from 'vue';
import { smtp } from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import PageHeader from '@/components/PageHeader.vue';
import StoreDashboardChart from '@/components/StoreDashboardChart.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { StoreDashboardProps } from '@/types/store';

const props = defineProps<StoreDashboardProps>();
const access = useStoreAccess();
const charts = computed(() => [
    {
        key: 'products',
        type: 'doughnut' as const,
        labels: [
            t('store.dashboard.totalProducts'),
            t('store.dashboard.activeProducts'),
        ],
        values: props.series.products,
        valueLabel: t('store.dashboard.count'),
    },
    {
        key: 'sources',
        type: 'doughnut' as const,
        labels: [
            t('store.dashboard.apiProducts'),
            t('store.dashboard.adminProducts'),
        ],
        values: props.series.sources,
        valueLabel: t('store.dashboard.count'),
    },
    {
        key: 'sales',
        type: 'bar' as const,
        ...props.series.sales,
        valueLabel: t('store.dashboard.numberOfSales'),
    },
    {
        key: 'stock',
        type: 'bar' as const,
        ...props.series.stock,
        valueLabel: t('store.dashboard.numberOfStock'),
    },
    {
        key: 'visits',
        type: 'bar' as const,
        ...props.series.visits,
        valueLabel: t('store.dashboard.numberOfVisits'),
    },
]);
</script>

<template>
    <Head :title="t('store.dashboard.title')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.dashboard.title')" />
        <div v-if="access.can('admin_dashboard')" class="space-y-6">
            <div
                v-if="showSmtpAlert && access.can('smtp_settings')"
                role="alert"
                class="flex items-center gap-3 rounded-lg border border-red-300 bg-red-50 p-4 text-red-900 dark:border-red-700 dark:bg-red-950 dark:text-red-100"
            >
                <TriangleAlert class="size-5 shrink-0" />
                <span
                    >{{ t('store.dashboard.smtpWarning') }}
                    <Link :href="smtp()" class="font-semibold underline">{{
                        t('store.dashboard.configureNow')
                    }}</Link></span
                >
            </div>
            <dl class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div
                    v-for="(value, key) in stats"
                    :key="key"
                    class="rounded-lg border border-gray-200 p-4 dark:border-neutral-700"
                >
                    <dt class="text-sm text-gray-600 dark:text-neutral-300">
                        {{ t(`store.dashboard.${key}`) }}
                    </dt>
                    <dd class="mt-2 text-3xl font-semibold">{{ value }}</dd>
                </div>
            </dl>
            <div class="grid gap-4 lg:grid-cols-2">
                <StoreDashboardChart
                    v-for="chart in charts"
                    :key="chart.key"
                    :title="t(`store.dashboard.charts.${chart.key}`)"
                    :type="chart.type"
                    :labels="chart.labels"
                    :values="chart.values"
                    :value-label="chart.valueLabel"
                    :category-label="t('store.dashboard.category')"
                />
            </div>
            <section>
                <h2 class="mb-4 font-semibold">
                    {{ t('store.dashboard.topProducts') }}
                </h2>
                <div
                    v-if="topProducts.length"
                    class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
                >
                    <article
                        v-for="product in topProducts"
                        :key="product.id"
                        class="overflow-hidden rounded-lg border border-gray-200 dark:border-neutral-700"
                    >
                        <component
                            :is="product.public_url ? 'a' : 'div'"
                            :href="product.public_url ?? undefined"
                            class="block"
                        >
                            <img
                                :src="product.thumbnail_url"
                                :alt="product.name"
                                loading="lazy"
                                class="h-44 w-full object-contain"
                            />
                            <div class="space-y-2 p-4">
                                <h3 class="font-semibold">
                                    {{ product.name }}
                                </h3>
                                <p>
                                    <del
                                        v-if="
                                            product.base_price !==
                                            product.discounted_price
                                        "
                                        class="me-2 text-gray-600 dark:text-neutral-300"
                                        >{{ product.base_price }}</del
                                    ><span>{{ product.discounted_price }}</span>
                                </p>
                                <p
                                    class="text-sm text-gray-600 dark:text-neutral-300"
                                >
                                    {{
                                        t('store.dashboard.rating', {
                                            rating: product.rating,
                                        })
                                    }}
                                </p>
                            </div>
                        </component>
                    </article>
                </div>
                <p
                    v-else
                    class="flex items-center gap-2 text-gray-600 dark:text-neutral-300"
                >
                    <PackageSearch class="size-5" />{{
                        t('store.dashboard.emptyProducts')
                    }}
                </p>
            </section>
        </div>
    </StoreAdminLayout>
</template>
