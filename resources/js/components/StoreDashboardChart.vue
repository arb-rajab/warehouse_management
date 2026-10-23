<script setup lang="ts">
import {
    ArcElement,
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    DoughnutController,
    Legend,
    LinearScale,
    Tooltip,
} from 'chart.js';
import { onBeforeUnmount, onMounted, ref, useId, watch } from 'vue';
import { i18n } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';

Chart.register(
    DoughnutController,
    BarController,
    ArcElement,
    BarElement,
    CategoryScale,
    LinearScale,
    Legend,
    Tooltip,
);
const props = defineProps<{
    title: string;
    type: 'doughnut' | 'bar';
    labels: string[];
    values: number[];
    valueLabel: string;
    categoryLabel: string;
}>();
const locale = i18n.global.locale;
const access = useStoreAccess();
const canvas = ref<HTMLCanvasElement>();
const tableId = useId();
let chart: Chart<'bar' | 'doughnut'> | undefined;
let media: MediaQueryList | undefined;
const dark = ref(false);

function renderChart() {
    chart?.destroy();

    if (!canvas.value || !access.value.can('admin_dashboard')) {
        return;
    }

    const text = dark.value ? '#e5e7eb' : '#1f2937';
    const grid = dark.value ? '#525252' : '#d1d5db';
    const colors = dark.value ? ['#60a5fa', '#fbbf24'] : ['#1d4ed8', '#92400e'];
    chart = new Chart(canvas.value, {
        type: props.type,
        data: {
            labels: [...props.labels],
            datasets: [
                {
                    label: props.valueLabel,
                    data: [...props.values],
                    backgroundColor: props.type === 'bar' ? colors[0] : colors,
                    borderColor: dark.value ? '#171717' : '#ffffff',
                    borderWidth: 2,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            locale: locale.value,
            ...(props.type === 'doughnut' ? { cutout: '70%' } : {}),
            font: { family: 'Tajawal, Inter, sans-serif' },
            plugins: {
                legend: {
                    display: props.type === 'doughnut',
                    position: 'bottom',
                    rtl: locale.value === 'ar',
                    textDirection: locale.value === 'ar' ? 'rtl' : 'ltr',
                    labels: { color: text, boxWidth: 12 },
                    onClick: () => {},
                },
                tooltip: {
                    rtl: locale.value === 'ar',
                    textDirection: locale.value === 'ar' ? 'rtl' : 'ltr',
                },
            },
            ...(props.type === 'bar'
                ? {
                      scales: {
                          x: { ticks: { color: text }, grid: { color: grid } },
                          y: {
                              beginAtZero: true,
                              ticks: { color: text, precision: 0 },
                              grid: { color: grid },
                          },
                      },
                  }
                : {}),
        },
    });
}
function updateTheme() {
    dark.value = media?.matches ?? false;
}
onMounted(() => {
    media = window.matchMedia('(prefers-color-scheme: dark)');
    updateTheme();
    media.addEventListener('change', updateTheme);
    renderChart();
});
watch(
    [
        () => props.labels,
        () => props.values,
        () => props.valueLabel,
        locale,
        dark,
    ],
    renderChart,
    { deep: true },
);
onBeforeUnmount(() => {
    media?.removeEventListener('change', updateTheme);
    chart?.destroy();
});
</script>

<template>
    <section
        class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-700 dark:bg-neutral-900"
    >
        <h2 class="mb-4 font-semibold">{{ title }}</h2>
        <div dir="ltr" class="relative h-72">
            <canvas
                ref="canvas"
                role="img"
                :aria-label="title"
                :aria-describedby="tableId"
            />
        </div>
        <table :id="tableId" class="sr-only">
            <caption>
                {{
                    title
                }}
            </caption>
            <thead>
                <tr>
                    <th scope="col">{{ categoryLabel }}</th>
                    <th scope="col">{{ valueLabel }}</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="(label, index) in labels" :key="index">
                    <th scope="row">{{ label }}</th>
                    <td>{{ values[index] }}</td>
                </tr>
            </tbody>
        </table>
    </section>
</template>
