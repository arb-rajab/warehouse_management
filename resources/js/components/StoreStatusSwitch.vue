<script setup lang="ts">
import type { UrlMethodPair } from '@inertiajs/core';
import { Form } from '@inertiajs/vue3';
import { Check, LoaderCircle, Lock } from '@lucide/vue';
import { useId } from 'vue';
import FieldError from '@/components/FieldError.vue';
import { t } from '@/lib/i18n';
import { firstError } from '@/lib/pageErrors';
/**
 * The back office's on/off switch for one status flag of a list row
 * (.ai/rules/status-switches.md). It submits at once: the hidden input posts
 * the opposite of `checked` to `action`, with the caller's extra hidden
 * inputs from the default slot. `label` is the switch's accessible name.
 *
 * `locked` shows the state without a form: the switch is focusable, marked
 * `aria-disabled`, and the reason is shown under it and read out with it
 * (`hint`, when given, is the longer explanation in the tooltip).
 */
withDefaults(
    defineProps<{
        action: UrlMethodPair;
        checked: boolean;
        label: string;
        inputName?: 'status' | 'approved' | 'value';
        stateLabels?: { on: string; off: string };
        locked?: { reason: string; hint?: string };
        onBefore?: () => boolean;
    }>(),
    { inputName: 'status' },
);
const reasonId = useId();
const trackClass =
    'relative inline-flex h-6 w-11 shrink-0 rounded-full transition-colors motion-reduce:transition-none';
const knobClass =
    'absolute top-0.5 inline-flex size-5 items-center justify-center rounded-full bg-white transition-all motion-reduce:transition-none';
</script>

<template>
    <div>
        <template v-if="locked">
            <button
                type="button"
                role="switch"
                :aria-checked="checked"
                aria-disabled="true"
                :aria-label="label"
                :aria-describedby="reasonId"
                :title="locked.hint ?? locked.reason"
                class="inline-flex cursor-not-allowed items-center gap-2 rounded-full outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 dark:focus-visible:ring-blue-400 dark:focus-visible:ring-offset-neutral-900"
            >
                <span
                    aria-hidden="true"
                    data-switch-track
                    :class="[
                        trackClass,
                        checked
                            ? 'bg-green-600/40 dark:bg-green-500/35'
                            : 'bg-gray-500/40 dark:bg-neutral-500/40',
                    ]"
                    ><span
                        data-switch-knob
                        :class="[
                            knobClass,
                            checked ? 'start-[1.375rem]' : 'start-0.5',
                            'text-gray-600 shadow-sm dark:bg-neutral-200',
                        ]"
                        ><Lock class="size-3" /></span
                ></span>
                <span
                    class="text-sm font-medium text-gray-600 dark:text-neutral-400"
                    >{{
                        checked
                            ? (stateLabels?.on ?? t('store.reference.enabled'))
                            : (stateLabels?.off ??
                              t('store.reference.disabled'))
                    }}</span
                >
            </button>
            <p
                :id="reasonId"
                class="mt-1 flex items-center gap-1 text-xs text-gray-600 dark:text-neutral-400"
            >
                <Lock class="size-3 shrink-0" />{{ locked.reason }}
            </p>
        </template>
        <Form
            v-else
            :action="action"
            :options="{ preserveScroll: true }"
            :on-before="onBefore"
            #default="{ processing, errors }"
        >
            <slot />
            <input type="hidden" :name="inputName" :value="checked ? 0 : 1" />
            <button
                type="submit"
                role="switch"
                :aria-checked="checked"
                :aria-label="label"
                :aria-busy="processing"
                :disabled="processing"
                class="group inline-flex cursor-pointer items-center gap-2 rounded-full outline-none focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 disabled:cursor-wait dark:focus-visible:ring-blue-400 dark:focus-visible:ring-offset-neutral-900"
            >
                <span
                    aria-hidden="true"
                    data-switch-track
                    :class="[
                        trackClass,
                        processing ? 'opacity-70' : '',
                        checked
                            ? 'bg-green-600 group-hover:bg-green-700 dark:bg-green-500 dark:group-hover:bg-green-400'
                            : 'bg-gray-500 group-hover:bg-gray-600 dark:bg-neutral-500 dark:group-hover:bg-neutral-400',
                    ]"
                    ><span
                        data-switch-knob
                        :class="[
                            knobClass,
                            checked ? 'start-[1.375rem]' : 'start-0.5',
                            processing
                                ? 'text-gray-500'
                                : 'text-green-700 shadow',
                        ]"
                        ><LoaderCircle
                            v-if="processing"
                            class="size-3.5 animate-spin" /><Check
                            v-else-if="checked"
                            class="size-3" /></span
                ></span>
                <span
                    class="text-sm font-medium"
                    :class="
                        processing
                            ? 'text-gray-500 dark:text-neutral-400'
                            : checked
                              ? 'text-green-800 dark:text-green-300'
                              : 'text-gray-600 dark:text-neutral-400'
                    "
                    >{{
                        processing
                            ? t('store.reference.saving')
                            : checked
                              ? (stateLabels?.on ??
                                t('store.reference.enabled'))
                              : (stateLabels?.off ??
                                t('store.reference.disabled'))
                    }}</span
                >
            </button>
            <FieldError :message="firstError(errors)" />
        </Form>
    </div>
</template>
