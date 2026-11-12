<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import {
    activation,
    updatePayments,
} from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import DataTable from '@/components/DataTable.vue';
import DetailItem from '@/components/DetailItem.vue';
import DetailList from '@/components/DetailList.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import StoreHighlight from '@/components/StoreHighlight.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDateTime } from '@/lib/date';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { paymentGatewaySettingsValidation } from '@/lib/paymentGatewaySettingsForm';
import { useStoreAccess } from '@/lib/storeAccess';
import type {
    StorePaymentGateway,
    StorePaymentGatewayAuditEntry,
    StorePaymentGatewayField,
    StorePaymentGatewaySettings,
    StorePaymentGatewaySource,
} from '@/types/store';

/**
 * The Payment methods page (StorePaymentGateways,
 * .ai/rules/admin-env-overrides.md): where the enabled gateways come from and,
 * one tab per gateway, its switch, mode, currency and credentials.
 *
 * A gateway's fields are one group: once any is saved here, all of them come
 * from this page and none from `.env`; with all empty, `.env` applies. A
 * secret is never sent to this page: an empty field keeps the saved one and a
 * checkbox removes it. Saving asks for the staff member's own current
 * password; it and every secret field are emptied after every submit. The
 * recent changes are AdminAuditLog rows: who, when, the IP and which fields,
 * never a value.
 */
const props = defineProps<{
    payments: StorePaymentGatewaySettings;
    /** The field a setup checklist link sent the admin here to fix (`<gateway>.<field>`). */
    highlight?: string | null;
    /** The last 10 saves and refused current passwords, newest first. */
    auditEntries?: StorePaymentGatewayAuditEntry[];
}>();

const access = useStoreAccess();

function inputName(gateway: string, field: string): string {
    return `gateways[${gateway}][${field}]`;
}

/** Emptied after every submit: never left in the page. */
const secretFields = [
    'current_password',
    ...props.payments.gateways.flatMap((gateway) =>
        gateway.fields
            .filter((field) => field.secret)
            .map((field) => inputName(gateway.key, field.key)),
    ),
];

const highlightedGateway = props.highlight?.split('.')[0];
const activeTab = ref(
    props.payments.gateways.find(
        (gateway) => gateway.key === highlightedGateway,
    )?.key ?? props.payments.gateways[0]?.key,
);
const enabledSource = ref<StorePaymentGatewaySource>(
    props.payments.enabledSource,
);

// Which group fields hold a typed value, by input name: the required marks
// follow them as the server's `required_with` does.
const typed = reactive<Record<string, boolean>>(
    Object.fromEntries(
        props.payments.gateways.flatMap((gateway) =>
            gateway.fields.map((field) => [
                inputName(gateway.key, field.key),
                !field.secret && Boolean(field.value),
            ]),
        ),
    ),
);
const cleared = reactive<Record<string, boolean>>({});

function onGroupInput(event: Event): void {
    const target = event.target as
        HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement;

    if (target.name in typed) {
        typed[target.name] = target.value.trim() !== '';
    }
}

function filled(gateway: StorePaymentGateway, field: string): boolean {
    return typed[inputName(gateway.key, field)] ?? false;
}

/**
 * The mark follows the server: a public required field once another field of
 * the group is typed (`required_with`); a secret once the identifying field is
 * typed, unless a readable saved one is kept.
 */
function isRequired(
    gateway: StorePaymentGateway,
    field: StorePaymentGatewayField,
): boolean {
    if (!field.required) {
        return false;
    }

    if (field.secret) {
        const kept =
            field.set &&
            !field.unreadable &&
            !cleared[inputName(gateway.key, field.key)];

        return filled(gateway, gateway.identifying) && !kept;
    }

    return gateway.fields.some(
        (other) => other.key !== field.key && filled(gateway, other.key),
    );
}

function fieldLabel(field: string): string {
    return t(`store.settings.payments.fields.${field}`);
}

function modeLabel(mode: string | null): string {
    return mode
        ? t(`store.settings.payments.modes.${mode}`)
        : t('store.settings.payments.modeUnknown');
}

function secretStatus(field: StorePaymentGatewayField): string {
    if (field.unreadable) {
        return t('store.settings.payments.secretUnreadable');
    }

    return t(
        `store.settings.payments.${field.set ? 'secretSaved' : 'secretNotSaved'}`,
    );
}

function envHint(field: StorePaymentGatewayField): string | null {
    if (field.secret || field.environment === null) {
        return null;
    }

    return t('store.settings.payments.envHint', {
        env: field.env,
        value:
            field.key === 'mode'
                ? modeLabel(field.environment)
                : field.environment,
    });
}

function effectiveValue(gateway: StorePaymentGateway, field: string): string {
    const entry = gateway.effective[field];

    if (!entry) {
        return '-';
    }

    if (entry.set !== undefined) {
        return t(
            `store.settings.payments.effective.${entry.set ? 'set' : 'notSet'}`,
        );
    }

    if (field === 'mode') {
        return modeLabel(entry.value ?? null);
    }

    return entry.value ?? '-';
}

const environmentList = computed(() =>
    props.payments.environmentEnabled.length > 0
        ? props.payments.environmentEnabled.join(', ')
        : t('store.settings.payments.none'),
);

function auditWhat(entry: StorePaymentGatewayAuditEntry): string {
    if (entry.action === 'payment_gateway_settings.password_failed') {
        return t('store.settings.payments.audit.passwordFailed');
    }

    return Object.entries(entry.changed_fields)
        .map(([key, kind]) => {
            const [gatewayKey, field] = key.split('.');
            const gateway = props.payments.gateways.find(
                (candidate) => candidate.key === gatewayKey,
            );
            let name = t('store.settings.payments.audit.enabledSource');

            if (key !== 'enabled_source') {
                name =
                    field === 'enabled'
                        ? (gateway?.name ?? gatewayKey)
                        : t('store.settings.payments.audit.gatewayField', {
                              gateway: gateway?.name ?? gatewayKey,
                              field: fieldLabel(field),
                          });
            }

            return t('store.settings.payments.audit.fieldChange', {
                field: name,
                change: t(`store.settings.payments.audit.kinds.${kind}`),
            });
        })
        .join(t('store.settings.payments.audit.separator'));
}
</script>

<template>
    <ResourceFormPage
        :title="t('store.settings.titles.payments')"
        :action="updatePayments()"
        :submit-label="t('store.settings.save')"
        :submitting-label="t('store.settings.saving')"
        :layout="StoreAdminLayout"
        :validation="paymentGatewaySettingsValidation"
        :reset-on-success="secretFields"
        :reset-on-error="secretFields"
        wide
    >
        <template #default="{ errors }">
            <p class="text-sm text-muted">
                {{ t('store.settings.payments.intro') }}
            </p>
            <StoreHighlight
                :active="highlight === 'enabled_source'"
                class="max-w-xl"
            >
                <label for="enabled_source" :class="fieldLabelClass"
                    >{{ t('store.settings.payments.enabledSource')
                    }}<RequiredMark
                /></label>
                <select
                    id="enabled_source"
                    v-model="enabledSource"
                    name="enabled_source"
                    required
                    :class="[plainFieldInputClass, 'cursor-pointer']"
                    aria-describedby="enabled_source_help"
                >
                    <option value="env">
                        {{
                            t('store.settings.payments.enabledSources.env', {
                                list: environmentList,
                            })
                        }}
                    </option>
                    <option value="admin">
                        {{ t('store.settings.payments.enabledSources.admin') }}
                    </option>
                </select>
                <p id="enabled_source_help" class="mt-1 text-sm text-muted">
                    {{ t('store.settings.payments.enabledSourceHelp') }}
                </p>
                <FieldError :message="errors.enabled_source" />
            </StoreHighlight>

            <div
                role="tablist"
                :aria-label="t('store.settings.payments.tabs')"
                class="flex flex-wrap gap-2 border-b border-line pb-2"
            >
                <button
                    v-for="gateway in payments.gateways"
                    :id="`gateway-tab-${gateway.key}`"
                    :key="gateway.key"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === gateway.key"
                    :aria-controls="`gateway-panel-${gateway.key}`"
                    :data-tab="gateway.key"
                    class="cursor-pointer rounded-md px-3 py-1.5 text-sm"
                    :class="
                        activeTab === gateway.key
                            ? 'bg-ink text-page'
                            : 'text-ink hover:bg-line'
                    "
                    @click="activeTab = gateway.key"
                >
                    {{ gateway.name }}
                    <TriangleAlert
                        v-if="gateway.enabled && gateway.missing.length"
                        class="ms-1 inline h-3.5 w-3.5 text-danger"
                        aria-hidden="true"
                    />
                </button>
            </div>

            <section
                v-for="gateway in payments.gateways"
                v-show="activeTab === gateway.key"
                :id="`gateway-panel-${gateway.key}`"
                :key="gateway.key"
                role="tabpanel"
                :aria-labelledby="`gateway-tab-${gateway.key}`"
                :data-gateway="gateway.key"
                class="space-y-4"
                @input="onGroupInput"
                @change="onGroupInput"
            >
                <h2 class="text-lg font-semibold">{{ gateway.name }}</h2>
                <p
                    v-if="!gateway.flow"
                    class="text-sm text-muted"
                    data-note="no-flow"
                >
                    {{ t('store.settings.payments.noFlow') }}
                </p>
                <div
                    v-if="gateway.enabled"
                    class="space-y-1"
                    data-section="warnings"
                >
                    <p
                        v-for="field in gateway.missing"
                        :key="field"
                        role="alert"
                        class="flex items-center gap-2 text-sm text-danger"
                        :data-missing="field"
                    >
                        <TriangleAlert class="h-4 w-4" aria-hidden="true" />
                        {{
                            t('store.settings.payments.missing', {
                                gateway: gateway.name,
                                field: fieldLabel(field),
                            })
                        }}
                    </p>
                    <p
                        v-for="entry in gateway.env_only.filter(
                            (envOnly) => !envOnly.set,
                        )"
                        :key="entry.key"
                        role="alert"
                        class="flex items-center gap-2 text-sm text-danger"
                        :data-missing-env="`${gateway.key}.${entry.key}`"
                    >
                        <TriangleAlert class="h-4 w-4" aria-hidden="true" />
                        {{
                            t('store.settings.payments.envOnlyMissing', {
                                gateway: gateway.name,
                                env: entry.env,
                            })
                        }}
                    </p>
                    <p
                        v-if="gateway.offered === false"
                        class="text-sm text-danger"
                        data-warning="not-offered"
                    >
                        {{
                            t('store.settings.payments.notOffered', {
                                gateway: gateway.name,
                            })
                        }}
                    </p>
                </div>
                <p
                    v-if="gateway.live_unconfirmed"
                    role="note"
                    class="flex items-center gap-2 text-sm text-warning"
                    :data-note="`unconfirmed-live-${gateway.key}`"
                >
                    <TriangleAlert class="h-4 w-4" aria-hidden="true" />
                    {{
                        t(
                            `store.settings.payments.liveUnconfirmed.${gateway.key}`,
                        )
                    }}
                </p>
                <p
                    v-if="gateway.currency_without_rate"
                    class="text-sm text-danger"
                    data-warning="no-rate"
                >
                    {{
                        t('store.settings.payments.currencyNoRate', {
                            code: gateway.currency_without_rate,
                        })
                    }}
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <StoreBooleanField
                        :name="inputName(gateway.key, 'enabled')"
                        :label="t('store.settings.payments.enabled')"
                        :value="gateway.enabled"
                        :required="false"
                        :disabled="!gateway.flow || enabledSource === 'env'"
                        :highlighted="highlight === `${gateway.key}.enabled`"
                        :error="errors[`gateways.${gateway.key}.enabled`]"
                    />
                    <p
                        v-if="gateway.offered !== null"
                        class="self-end text-sm text-muted"
                        data-section="offered"
                    >
                        {{
                            t('store.settings.payments.offered', {
                                state: t(
                                    `store.settings.payments.offeredStates.${gateway.offered ? 'on' : 'off'}`,
                                ),
                            })
                        }}
                        <Link
                            v-if="
                                gateway.offered_setting &&
                                access.can('features_activation')
                            "
                            :href="
                                activation({
                                    query: {
                                        highlight: gateway.offered_setting,
                                    },
                                })
                            "
                            class="ms-1 underline"
                            >{{
                                t('store.settings.payments.openActivation')
                            }}</Link
                        >
                    </p>
                    <StoreHighlight
                        v-if="gateway.mode_kind === 'setting'"
                        :active="highlight === `${gateway.key}.mode`"
                    >
                        <label
                            :for="inputName(gateway.key, 'mode')"
                            :class="fieldLabelClass"
                            >{{ t('store.settings.payments.mode')
                            }}<RequiredMark
                        /></label>
                        <select
                            :id="inputName(gateway.key, 'mode')"
                            :name="inputName(gateway.key, 'mode')"
                            required
                            :class="[plainFieldInputClass, 'cursor-pointer']"
                        >
                            <option
                                v-for="mode in ['sandbox', 'live']"
                                :key="mode"
                                :value="mode"
                                :selected="gateway.mode === mode"
                            >
                                {{ modeLabel(mode) }}
                            </option>
                        </select>
                        <FieldError
                            :message="errors[`gateways.${gateway.key}.mode`]"
                        />
                    </StoreHighlight>
                    <p
                        v-else-if="gateway.mode_kind === 'derived'"
                        class="self-end text-sm text-muted"
                    >
                        {{
                            t('store.settings.payments.modeDerived', {
                                mode: modeLabel(gateway.mode),
                            })
                        }}
                    </p>
                    <p
                        v-if="gateway.fixed_currency"
                        class="self-end text-sm text-muted"
                    >
                        {{
                            t('store.settings.payments.currencyFixed', {
                                code: gateway.fixed_currency,
                            })
                        }}
                    </p>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <StoreHighlight
                        v-for="field in gateway.fields"
                        :key="field.key"
                        :active="highlight === `${gateway.key}.${field.key}`"
                        :class="{ 'sm:col-span-2': field.textarea }"
                        :data-field="`${gateway.key}.${field.key}`"
                    >
                        <template v-if="field.options">
                            <label
                                :for="inputName(gateway.key, field.key)"
                                :class="fieldLabelClass"
                                >{{ fieldLabel(field.key)
                                }}<RequiredMark
                                    v-if="isRequired(gateway, field)"
                            /></label>
                            <select
                                :id="inputName(gateway.key, field.key)"
                                :name="inputName(gateway.key, field.key)"
                                :required="isRequired(gateway, field)"
                                :class="[
                                    plainFieldInputClass,
                                    'cursor-pointer',
                                ]"
                            >
                                <option value="" :selected="!field.value">
                                    {{
                                        field.key === 'mode'
                                            ? t(
                                                  'store.settings.payments.modeFromEnv',
                                                  {
                                                      mode: modeLabel(
                                                          field.environment,
                                                      ),
                                                  },
                                              )
                                            : (envHint(field) ?? '-')
                                    }}
                                </option>
                                <option
                                    v-for="option in field.options"
                                    :key="option"
                                    :value="option"
                                    :selected="field.value === option"
                                >
                                    {{
                                        field.key === 'mode'
                                            ? modeLabel(option)
                                            : option
                                    }}
                                </option>
                            </select>
                        </template>
                        <template v-else-if="field.textarea">
                            <label
                                :for="inputName(gateway.key, field.key)"
                                :class="fieldLabelClass"
                                >{{ fieldLabel(field.key)
                                }}<RequiredMark
                                    v-if="isRequired(gateway, field)"
                            /></label>
                            <textarea
                                :id="inputName(gateway.key, field.key)"
                                :name="inputName(gateway.key, field.key)"
                                :required="isRequired(gateway, field)"
                                :disabled="
                                    cleared[inputName(gateway.key, field.key)]
                                "
                                :placeholder="
                                    field.secret && field.set
                                        ? t(
                                              'store.settings.payments.secretKeep',
                                          )
                                        : undefined
                                "
                                :value="field.secret ? '' : (field.value ?? '')"
                                rows="4"
                                dir="ltr"
                                spellcheck="false"
                                autocomplete="off"
                                :class="[plainFieldInputClass, 'font-mono']"
                            />
                            <p class="mt-1 text-sm text-muted">
                                {{ t('store.settings.payments.keyHelp') }}
                            </p>
                        </template>
                        <FormField
                            v-else
                            :id="inputName(gateway.key, field.key)"
                            :type="field.secret ? 'password' : 'text'"
                            :label="fieldLabel(field.key)"
                            :value="field.secret ? '' : (field.value ?? '')"
                            :required="isRequired(gateway, field)"
                            :disabled="
                                field.secret &&
                                cleared[inputName(gateway.key, field.key)]
                            "
                            :placeholder="
                                field.secret && field.set && !field.unreadable
                                    ? t('store.settings.payments.secretKeep')
                                    : (field.environment ?? undefined)
                            "
                            :error="
                                errors[`gateways.${gateway.key}.${field.key}`]
                            "
                            :maxlength="field.max ?? undefined"
                            :autocomplete="
                                field.secret ? 'new-password' : 'off'
                            "
                            spellcheck="false"
                            dir="ltr"
                        />
                        <FieldError
                            v-if="field.options || field.textarea"
                            :message="
                                errors[`gateways.${gateway.key}.${field.key}`]
                            "
                        />
                        <div class="mt-1 space-y-1 text-sm text-muted">
                            <p v-if="envHint(field)">{{ envHint(field) }}</p>
                            <template v-if="field.secret">
                                <p>{{ secretStatus(field) }}</p>
                                <p>
                                    {{
                                        t(
                                            'store.settings.payments.secretAgain',
                                            {
                                                identifying: fieldLabel(
                                                    gateway.identifying,
                                                ),
                                            },
                                        )
                                    }}
                                </p>
                            </template>
                            <p
                                v-if="
                                    field.key === 'currency' && !field.required
                                "
                            >
                                {{ t('store.settings.payments.currencyStore') }}
                            </p>
                        </div>
                        <label
                            v-if="field.secret && field.set"
                            class="mt-2 flex items-center gap-2 text-sm text-ink"
                        >
                            <input
                                v-model="
                                    cleared[inputName(gateway.key, field.key)]
                                "
                                type="checkbox"
                                :name="
                                    inputName(gateway.key, `${field.key}_clear`)
                                "
                                value="1"
                            />
                            {{
                                t('store.settings.payments.removeSecret', {
                                    field: fieldLabel(field.key),
                                })
                            }}
                        </label>
                        <FieldError
                            v-if="field.secret"
                            :message="
                                errors[
                                    `gateways.${gateway.key}.${field.key}_clear`
                                ]
                            "
                        />
                    </StoreHighlight>
                </div>
                <StoreHighlight
                    v-for="entry in gateway.env_only"
                    :key="entry.key"
                    :active="highlight === `${gateway.key}.${entry.key}`"
                    :data-field="`${gateway.key}.${entry.key}`"
                >
                    <DetailList>
                        <DetailItem
                            :label="
                                t(
                                    `store.settings.payments.envOnlyFields.${entry.key}`,
                                    { env: entry.env },
                                )
                            "
                            :value="
                                t(
                                    `store.settings.payments.effective.${entry.set ? 'set' : 'notSet'}`,
                                )
                            "
                        />
                    </DetailList>
                </StoreHighlight>
                <p class="text-sm text-muted">
                    {{
                        t('store.settings.payments.groupHelp', {
                            gateway: gateway.name,
                            identifying: fieldLabel(gateway.identifying),
                        })
                    }}
                </p>

                <div :data-section="`effective-${gateway.key}`">
                    <DetailList
                        :title="
                            t('store.settings.payments.effective.title', {
                                gateway: gateway.name,
                            })
                        "
                    >
                        <DetailItem
                            :label="
                                t('store.settings.payments.effective.source')
                            "
                            :value="
                                t(
                                    `store.settings.payments.effective.sources.${gateway.source}`,
                                )
                            "
                        />
                        <DetailItem
                            :label="
                                t('store.settings.payments.effective.enabled')
                            "
                            :value="
                                t(
                                    'store.settings.payments.effective.enabledValue',
                                    {
                                        state: t(
                                            `store.settings.payments.effective.${gateway.enabled ? 'yes' : 'no'}`,
                                        ),
                                        source: t(
                                            `store.settings.payments.effective.listSources.${payments.enabledSource}`,
                                        ),
                                    },
                                )
                            "
                        />
                        <DetailItem
                            v-if="gateway.mode_kind !== 'field'"
                            :label="t('store.settings.payments.mode')"
                            :value="modeLabel(gateway.mode)"
                        />
                        <DetailItem
                            v-for="field in gateway.fields"
                            :key="field.key"
                            :label="fieldLabel(field.key)"
                            :value="effectiveValue(gateway, field.key)"
                        />
                    </DetailList>
                </div>
            </section>

            <div
                class="max-w-sm border-t border-line pt-4"
                data-section="current_password"
            >
                <FormField
                    id="current_password"
                    type="password"
                    :label="t('store.settings.payments.currentPassword')"
                    :error="errors.current_password"
                    autocomplete="current-password"
                    maxlength="255"
                    dir="ltr"
                    aria-describedby="current_password_help"
                    required
                />
                <p id="current_password_help" class="mt-1 text-sm text-muted">
                    {{ t('store.settings.payments.currentPasswordHelp') }}
                </p>
            </div>
        </template>
        <template #after-form>
            <section class="mt-6 space-y-2" data-section="payment_audit">
                <h2 class="text-lg font-semibold">
                    {{ t('store.settings.payments.audit.title') }}
                </h2>
                <p class="text-sm text-muted">
                    {{ t('store.settings.payments.audit.help') }}
                </p>
                <DataTable
                    :columns="[
                        t('store.settings.payments.audit.when'),
                        t('store.settings.payments.audit.who'),
                        t('store.settings.payments.audit.ip'),
                        t('store.settings.payments.audit.what'),
                    ]"
                    :rows="auditEntries ?? []"
                    :empty-message="t('store.settings.payments.audit.empty')"
                >
                    <template #row="{ row: entry }">
                        <td class="px-4 py-2">
                            {{
                                entry.created_at
                                    ? formatDateTime(entry.created_at)
                                    : '-'
                            }}
                        </td>
                        <td class="px-4 py-2 whitespace-normal">
                            <span class="font-medium text-ink">{{
                                entry.user_name ?? '-'
                            }}</span>
                            <span
                                v-if="entry.user_email"
                                class="block text-muted"
                                dir="ltr"
                                >{{ entry.user_email }}</span
                            >
                            <span
                                v-if="entry.user_deleted"
                                class="block text-muted"
                                >{{
                                    t(
                                        'store.settings.payments.audit.deletedUser',
                                        {
                                            id: entry.user_id,
                                        },
                                    )
                                }}</span
                            >
                        </td>
                        <td class="px-4 py-2" dir="ltr">
                            {{ entry.ip_address ?? '-' }}
                        </td>
                        <td
                            class="px-4 py-2 whitespace-normal"
                            :class="{
                                'text-danger':
                                    entry.action ===
                                    'payment_gateway_settings.password_failed',
                            }"
                        >
                            {{ auditWhat(entry) }}
                        </td>
                    </template>
                </DataTable>
            </section>
        </template>
    </ResourceFormPage>
</template>
