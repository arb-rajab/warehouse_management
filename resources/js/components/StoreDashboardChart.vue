<script setup lang="ts">
import Chart from 'chart.js/auto';
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
const props = defineProps<{
    title: string;
    labels: string[];
    values: number[];
    kind: 'bar' | 'doughnut';
}>();
const canvas = ref<HTMLCanvasElement | null>(null);
let chart: Chart | undefined;
function render(): void {
    chart?.destroy();

    if (!canvas.value) {
        return;
    }

    const dark =
        window.matchMedia?.('(prefers-color-scheme: dark)').matches ?? false;
    const textColor = dark ? '#d1d5db' : '#374151';
    chart = new Chart(canvas.value, {
        type: props.kind,
        data: {
            labels: props.labels,
            datasets: [
                {
                    label: props.title,
                    data: props.values,
                    backgroundColor: [
                        '#2563eb',
                        '#0d9488',
                        '#a855f7',
                        '#ea580c',
                    ],
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: false,
            plugins: {
                legend: {
                    display: props.kind === 'doughnut',
                    labels: { color: textColor },
                },
            },
            scales:
                props.kind === 'bar'
                    ? {
                          x: { ticks: { color: textColor } },
                          y: { beginAtZero: true, ticks: { color: textColor } },
                      }
                    : undefined,
        },
    });
}
onMounted(render);
watch(() => [props.labels, props.values, props.kind], render);
onBeforeUnmount(() => chart?.destroy());
</script>
<template>
    <section
        class="rounded-lg border border-gray-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
    >
        <h2 class="mb-3 font-semibold">{{ title }}</h2>
        <div class="h-64">
            <canvas ref="canvas" :aria-label="title" role="img" />
        </div>
        <dl class="mt-3 grid grid-cols-2 gap-1 text-xs">
            <template v-for="(label, index) in labels" :key="index"
                ><dt>{{ label }}</dt>
                <dd>{{ values[index] }}</dd></template
            >
        </dl>
    </section>
</template>
