<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, ChevronUp, Circle, CircleCheck } from '@lucide/vue';
import { computed, ref, useId } from 'vue';
import { index as categories } from '@/actions/App/Http/Controllers/Store/Admin/CategoryController';
import { index as currencies } from '@/actions/App/Http/Controllers/Store/Admin/CurrencyController';
import { edit as generalSettings } from '@/actions/App/Http/Controllers/Store/Admin/GeneralSettingsController';
import { index as googleMap } from '@/actions/App/Http/Controllers/Store/Admin/GoogleMapController';
import { index as languages } from '@/actions/App/Http/Controllers/Store/Admin/LanguageController';
import { all as allProducts } from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import {
    activation,
    firebase,
    smtp,
} from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import { index as shipping } from '@/actions/App/Http/Controllers/Store/Admin/ShippingConfigurationController';
import { index as taxes } from '@/actions/App/Http/Controllers/Store/Admin/TaxController';
import { edit as websiteFooter } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteFooterController';
import { edit as websiteHeader } from '@/actions/App/Http/Controllers/Store/Admin/WebsiteHeaderController';
import { t } from '@/lib/i18n';
import type {
    StoreSetupChecklist,
    StoreSetupChecklistReason,
    StoreSetupChecklistRoute,
} from '@/types/store';
import type { RouteDefinition, RouteQueryOptions } from '@/wayfinder';

/**
 * The dashboard's setup checklist: every setting the shop needs, each done or
 * still to do, with the overall progress.
 *
 * A to-do row never shows a bare "not done" (.ai/rules/setup-checklist-reasons.md):
 * each reason the backend gives names the exact missing setting and links to
 * the page that sets it with `?highlight=<key>`, so that page marks and
 * scrolls to it (.ai/rules/deep-link-highlight.md). The backend lists only
 * the items the signed-in staff member may fix and picks a page they may
 * open, so a link needs no permission check of its own. The card renders
 * nothing when there is nothing to list or everything is done. It always
 * starts expanded; the collapse is not remembered and cannot dismiss it.
 */
const props = defineProps<{ checklist: StoreSetupChecklist }>();
const expanded = ref(true);
const listId = useId();

// The page behind each route name a reason can name.
const routes: Record<
    StoreSetupChecklistRoute,
    (options?: RouteQueryOptions) => RouteDefinition<'get'>
> = {
    'smtp_settings.index': smtp,
    'activation.index': activation,
    'products.all': allProducts,
    'shipping_configuration.index': shipping,
    'google-firebase.index': firebase,
    'google-map.index': googleMap,
    'currency.index': currencies,
    'tax.index': taxes,
    'categories.index': categories,
    'general_setting.index': generalSettings,
    'website.header': websiteHeader,
    'website.footer': websiteFooter,
    'languages.index': languages,
};

function href(reason: StoreSetupChecklistReason): RouteDefinition<'get'> {
    const route = routes[reason.route];

    return reason.highlight
        ? route({ query: { highlight: reason.highlight } })
        : route();
}

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
        key: item.key,
        done: item.done,
        label: t(`store.dashboard.checklist.items.${item.key}.label`),
        reasons: item.done
            ? []
            : item.reasons.map((reason) => {
                  const text = t(
                      `store.dashboard.checklist.reasons.${reason.key}`,
                      reason.params,
                  );

                  return {
                      key: reason.key,
                      text,
                      href: href(reason),
                      ariaLabel: t('store.dashboard.checklist.fixReason', {
                          reason: text,
                      }),
                  };
              }),
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
                    <ul
                        v-if="row.reasons.length"
                        class="mt-1 space-y-1 text-sm text-muted"
                    >
                        <li
                            v-for="reason in row.reasons"
                            :key="reason.key"
                            :data-reason="reason.key"
                            class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1"
                        >
                            <span class="min-w-0">{{ reason.text }}</span>
                            <Link
                                :href="reason.href"
                                :aria-label="reason.ariaLabel"
                                class="shrink-0 font-semibold text-ink underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus"
                            >
                                {{ t('store.dashboard.checklist.fix') }}
                            </Link>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
    </section>
</template>
