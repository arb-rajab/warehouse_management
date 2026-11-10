<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Circle, CircleCheck } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import { all as allProducts } from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import { edit as generalSettings } from '@/actions/App/Http/Controllers/Store/Admin/GeneralSettingsController';
import { index as currencies } from '@/actions/App/Http/Controllers/Store/Admin/CurrencyController';
import { index as languages } from '@/actions/App/Http/Controllers/Store/Admin/LanguageController';
import {
    activation,
    firebase,
    payments,
    smtp,
} from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import { index as shipping } from '@/actions/App/Http/Controllers/Store/Admin/ShippingConfigurationController';
import { index as taxes } from '@/actions/App/Http/Controllers/Store/Admin/TaxController';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type { RouteDefinition } from '@/wayfinder';
import type {
    StoreSetupChecklist,
    StoreSetupChecklistKey,
} from '@/types/store';

/**
 * The dashboard's setup checklist: every setting the shop needs, each done or
 * still to do, with the page that fixes it and the overall progress.
 *
 * The backend lists only the items the signed-in staff member may fix, so a
 * row's link needs no permission check of its own (the cash payment link
 * below is the one exception, as on the dashboard's red banners). The card
 * renders nothing when there is nothing to list or everything is done. It
 * always starts expanded; the collapse is not remembered and cannot dismiss it.
 */
const props = defineProps<{ checklist: StoreSetupChecklist }>();
const access = useStoreAccess();
const expanded = ref(true);
const listId = useId();

// The page that fixes each item. Google Maps has none: its key is set in the
// server environment (MAP_API_KEY), so the row carries only its hint.
const links: Record<
    StoreSetupChecklistKey,
    () => RouteDefinition<'get'> | null
> = {
    smtp: () => smtp(),
    paymentMethod: () =>
        access.value.can('features_activation')
            ? activation({ query: { highlight: 'cash_payment' } })
            : payments(),
    cashOnDeliveryProducts: () => allProducts(),
    shipping: () => shipping(),
    firebasePush: () => firebase(),
    googleMaps: () => null,
    currency: () => currencies(),
    vatTax: () => taxes(),
    catalog: () => allProducts(),
    siteBasics: () => generalSettings(),
    language: () => languages(),
};

const visible = computed(
    () =>
        props.checklist.total > 0 &&
        props.checklist.done < props.checklist.total,
);
const percent = computed(() =>
    props.checklist.total > 0
        ? Math.round((props.checklist.done / props.checklist.total) * 100)
        : 0,
);
const progressText = computed(() =>
    t('store.dashboard.checklist.progress', {
        done: props.checklist.done,
        total: props.checklist.total,
    }),
);
const rows = computed(() =>
    props.checklist.items.map((item) => ({
        ...item,
        label: t(`store.dashboard.checklist.items.${item.key}.label`),
        hint: t(`store.dashboard.checklist.items.${item.key}.hint`),
        href: item.done ? null : links[item.key](),
    })),
);
</script>

<template>
    <section
        v-if="visible"
        :aria-labelledby="`${listId}-title`"
        class="rounded-lg border border-line p-4"
    >
        <div class="flex items-center justify-between gap-3">
            <h2 :id="`${listId}-title`" class="font-semibold">
                {{ t('store.dashboard.checklist.title') }}
            </h2>
            <div class="flex items-center gap-3">
                <span class="text-sm text-muted tabular-nums">{{
                    progressText
                }}</span>
                <button
                    type="button"
                    class="cursor-pointer rounded-md p-1.5 text-muted hover:bg-subtle hover:text-ink focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                    :aria-expanded="expanded"
                    :aria-controls="listId"
                    :aria-label="
                        expanded
                            ? t('store.dashboard.checklist.collapse')
                            : t('store.dashboard.checklist.expand')
                    "
                    @click="expanded = !expanded"
                >
                    <component
                        :is="expanded ? ChevronUp : ChevronDown"
                        class="size-5"
                        aria-hidden="true"
                    />
                </button>
            </div>
        </div>
        <div
            role="progressbar"
            :aria-label="t('store.dashboard.checklist.title')"
            :aria-valuenow="checklist.done"
            aria-valuemin="0"
            :aria-valuemax="checklist.total"
            :aria-valuetext="progressText"
            class="mt-3 h-2 overflow-hidden rounded-full bg-subtle"
        >
            <div
                class="h-full rounded-full bg-success"
                :style="{ width: `${percent}%` }"
            ></div>
        </div>
        <ul v-show="expanded" :id="listId" class="mt-3 divide-y divide-line">
            <li
                v-for="row in rows"
                :key="row.key"
                :data-key="row.key"
                :data-done="row.done"
                class="flex items-start gap-3 py-3"
                :class="row.done ? 'text-muted' : 'text-ink'"
            >
                <component
                    :is="row.done ? CircleCheck : Circle"
                    class="mt-0.5 size-5 shrink-0"
                    :class="row.done ? 'text-success' : 'text-muted'"
                    aria-hidden="true"
                />
                <div class="min-w-0 flex-1">
                    <p :class="row.done ? '' : 'font-semibold'">
                        <span class="sr-only"
                            >{{
                                row.done
                                    ? t('store.dashboard.checklist.statusDone')
                                    : t('store.dashboard.checklist.statusTodo')
                            }}:
                        </span>
                        {{ row.label }}
                    </p>
                    <p v-if="!row.done" class="mt-1 text-sm text-muted">
                        {{ row.hint }}
                    </p>
                </div>
                <Link
                    v-if="row.href"
                    :href="row.href"
                    :aria-label="
                        t('store.dashboard.checklist.configureItem', {
                            item: row.label,
                        })
                    "
                    class="shrink-0 font-semibold underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                >
                    {{ t('store.dashboard.checklist.configure') }}
                </Link>
            </li>
        </ul>
    </section>
</template>
