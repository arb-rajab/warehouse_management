<script setup lang="ts">
import { computed, ref } from 'vue';
import { updateSmtp } from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import DataTable from '@/components/DataTable.vue';
import DetailItem from '@/components/DetailItem.vue';
import DetailList from '@/components/DetailList.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreHighlight from '@/components/StoreHighlight.vue';
import StoreSmtpTestForm from '@/components/StoreSmtpTestForm.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDateTime } from '@/lib/date';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { smtpSettingsValidation } from '@/lib/smtpSettingsForm';
import { useStoreAccess } from '@/lib/storeAccess';
import type {
    StoreSmtpEffectivePresence,
    StoreSmtpEffectiveSettings,
    StoreSmtpEffectiveValue,
    StoreSmtpAuditEntry,
    StoreSmtpSettings,
} from '@/types/store';

/**
 * The SMTP settings saved in the admin win over the server's MAIL_* values,
 * which stay the fallback for an empty field (StoreMailSettings,
 * .ai/rules/admin-env-overrides.md). The host, port, encryption, username and
 * password are one group: without a host all five come from `.env`. The saved
 * password is never sent here; an empty password field keeps it.
 *
 * Saving asks for the staff member's own current password (whoever controls
 * these settings can read every email the shop sends); it and the SMTP
 * password are emptied after every submit. The recent changes below are
 * AdminAuditLog rows: who, when, the IP and which fields, never a value.
 */
const props = defineProps<{
    smtp: StoreSmtpSettings;
    effective: StoreSmtpEffectiveSettings;
    /** The field a setup checklist link sent the admin here to fix. */
    highlight?: string | null;
    /** The last 10 saves and refused current passwords, newest first. */
    auditEntries?: StoreSmtpAuditEntry[];
}>();

/** Emptied after every submit: never left in the page. */
const secretFields = ['current_password', 'password'];

const mailers = ['smtp', 'log'] as const;
const schemes = ['smtps', 'smtp'] as const;
const access = useStoreAccess();
// The port is required with a host (`required_with:host`), so its mark follows the host field.
const hostFilled = ref(Boolean(props.smtp.host));
const clearPassword = ref(false);

function onHostInput(event: Event): void {
    hostFilled.value = (event.target as HTMLInputElement).value.trim() !== '';
}

const passwordStatus = computed(() => {
    if (props.smtp.password_unreadable) {
        return 'passwordUnreadable';
    }

    return props.smtp.password_set ? 'passwordSaved' : 'passwordNotSaved';
});

function withSource(value: string, source: string): string {
    return t('store.settings.smtp.effective.withSource', {
        value,
        source: t(`store.settings.smtp.effective.sources.${source}`),
    });
}

function shown(entry: StoreSmtpEffectiveValue, empty: string): string {
    return withSource(entry.value ?? empty, entry.source);
}

function presence(entry: StoreSmtpEffectivePresence): string {
    return withSource(
        t(`store.settings.server.${entry.set ? 'set' : 'notSet'}`),
        entry.source,
    );
}

function auditWhat(entry: StoreSmtpAuditEntry): string {
    if (entry.action === 'smtp_settings.password_failed') {
        return t('store.settings.smtp.audit.passwordFailed');
    }

    return Object.entries(entry.changed_fields)
        .map(([field, kind]) =>
            t('store.settings.smtp.audit.fieldChange', {
                field: t(`store.settings.smtp.effective.${field}`),
                change: t(`store.settings.smtp.audit.kinds.${kind}`),
            }),
        )
        .join(t('store.settings.smtp.audit.separator'));
}

const rows = computed(() => {
    const effective = props.effective;
    const none = t('store.settings.server.notSet');

    return [
        { key: 'mailer', value: shown(effective.mailer, none) },
        { key: 'host', value: shown(effective.host, none) },
        { key: 'port', value: shown(effective.port, none) },
        {
            key: 'scheme',
            value: shown(
                effective.scheme,
                t('store.settings.smtp.effective.automatic'),
            ),
        },
        { key: 'username', value: presence(effective.username) },
        { key: 'password', value: presence(effective.password) },
        { key: 'from_address', value: shown(effective.from_address, none) },
        { key: 'from_name', value: shown(effective.from_name, none) },
    ];
});
</script>

<template>
    <ResourceFormPage
        :title="t('store.settings.titles.smtp')"
        :action="updateSmtp()"
        :submit-label="t('store.settings.save')"
        :submitting-label="t('store.settings.saving')"
        :layout="StoreAdminLayout"
        :validation="smtpSettingsValidation"
        :reset-on-success="secretFields"
        :reset-on-error="secretFields"
        wide
    >
        <template #default="{ errors }">
            <p class="text-sm text-muted">
                {{ t('store.settings.smtp.intro') }}
            </p>
            <p
                v-if="smtp.password_unreadable"
                role="alert"
                class="text-sm text-danger"
            >
                {{ t('store.settings.smtp.passwordUnreadable') }}
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <StoreHighlight :active="highlight === 'mail_mailer'">
                    <label for="mailer" :class="fieldLabelClass">{{
                        t('store.settings.smtp.mailer')
                    }}</label>
                    <select
                        id="mailer"
                        name="mailer"
                        :class="[plainFieldInputClass, 'cursor-pointer']"
                        aria-describedby="mailer_help"
                    >
                        <option value="" :selected="smtp.mailer === null">
                            {{
                                t('store.settings.smtp.mailerServer', {
                                    mailer: smtp.environment_mailer,
                                })
                            }}
                        </option>
                        <option
                            v-for="mailer in mailers"
                            :key="mailer"
                            :value="mailer"
                            :selected="smtp.mailer === mailer"
                        >
                            {{ t(`store.settings.smtp.mailers.${mailer}`) }}
                        </option>
                    </select>
                    <p id="mailer_help" class="mt-1 text-sm text-muted">
                        {{ t('store.settings.smtp.mailerHelp') }}
                    </p>
                    <FieldError :message="errors.mailer" />
                </StoreHighlight>
                <StoreHighlight :active="highlight === 'mail_host'">
                    <FormField
                        id="host"
                        :label="t('store.settings.smtp.host')"
                        :value="smtp.host ?? ''"
                        :error="errors.host"
                        maxlength="255"
                        autocomplete="off"
                        spellcheck="false"
                        dir="ltr"
                        aria-describedby="host_help"
                        @input="onHostInput"
                    />
                    <p id="host_help" class="mt-1 text-sm text-muted">
                        {{ t('store.settings.smtp.hostHelp') }}
                    </p>
                </StoreHighlight>
                <div>
                    <FormField
                        id="port"
                        type="number"
                        :label="t('store.settings.smtp.port')"
                        :value="smtp.port ?? ''"
                        :required="hostFilled"
                        :error="errors.port"
                        min="1"
                        max="65535"
                        step="1"
                        dir="ltr"
                        aria-describedby="port_help"
                    />
                    <p id="port_help" class="mt-1 text-sm text-muted">
                        {{ t('store.settings.smtp.portHelp') }}
                    </p>
                </div>
                <div>
                    <label for="scheme" :class="fieldLabelClass">{{
                        t('store.settings.smtp.scheme')
                    }}</label>
                    <select
                        id="scheme"
                        name="scheme"
                        :class="[plainFieldInputClass, 'cursor-pointer']"
                    >
                        <option value="" :selected="smtp.scheme === null">
                            {{ t('store.settings.smtp.schemeAutomatic') }}
                        </option>
                        <option
                            v-for="scheme in schemes"
                            :key="scheme"
                            :value="scheme"
                            :selected="smtp.scheme === scheme"
                        >
                            {{ t(`store.settings.smtp.schemes.${scheme}`) }}
                        </option>
                    </select>
                    <FieldError :message="errors.scheme" />
                </div>
                <StoreHighlight
                    :active="highlight === 'mail_credentials'"
                    data-section="mail_credentials"
                    class="grid gap-4 sm:col-span-2 sm:grid-cols-2"
                >
                    <FormField
                        id="username"
                        :label="t('store.settings.smtp.username')"
                        :value="smtp.username ?? ''"
                        :error="errors.username"
                        maxlength="255"
                        autocomplete="off"
                        spellcheck="false"
                        dir="ltr"
                    />
                    <div>
                        <FormField
                            id="password"
                            type="password"
                            :label="t('store.settings.smtp.password')"
                            :placeholder="
                                smtp.password_set && !smtp.password_unreadable
                                    ? t('store.settings.smtp.passwordKeep')
                                    : undefined
                            "
                            :disabled="clearPassword"
                            :error="errors.password"
                            maxlength="255"
                            autocomplete="new-password"
                            dir="ltr"
                            aria-describedby="password_help"
                        />
                        <div
                            id="password_help"
                            class="mt-1 space-y-1 text-sm text-muted"
                        >
                            <p>
                                {{ t(`store.settings.smtp.${passwordStatus}`) }}
                            </p>
                            <p>{{ t('store.settings.smtp.passwordHelp') }}</p>
                        </div>
                        <label
                            v-if="smtp.password_set"
                            class="mt-2 flex items-center gap-2 text-sm text-ink"
                        >
                            <input
                                id="password_clear"
                                v-model="clearPassword"
                                type="checkbox"
                                name="password_clear"
                                value="1"
                            />
                            {{ t('store.settings.smtp.removePassword') }}
                        </label>
                        <FieldError :message="errors.password_clear" />
                    </div>
                </StoreHighlight>
                <FormField
                    id="from_address"
                    type="email"
                    :label="t('store.settings.smtp.fromAddress')"
                    :value="smtp.from_address ?? ''"
                    :error="errors.from_address"
                    maxlength="255"
                    autocomplete="off"
                    dir="ltr"
                />
                <FormField
                    id="from_name"
                    :label="t('store.settings.smtp.fromName')"
                    :value="smtp.from_name ?? ''"
                    :error="errors.from_name"
                    maxlength="255"
                    autocomplete="off"
                />
                <p class="text-sm text-muted sm:col-span-2">
                    {{ t('store.settings.smtp.fromHelp') }}
                </p>
            </div>
            <div
                class="max-w-sm border-t border-line pt-4"
                data-section="current_password"
            >
                <FormField
                    id="current_password"
                    type="password"
                    :label="t('store.settings.smtp.currentPassword')"
                    :error="errors.current_password"
                    autocomplete="current-password"
                    maxlength="255"
                    dir="ltr"
                    aria-describedby="current_password_help"
                    required
                />
                <p id="current_password_help" class="mt-1 text-sm text-muted">
                    {{ t('store.settings.smtp.currentPasswordHelp') }}
                </p>
            </div>
        </template>
        <template #after-form>
            <div class="mt-6" data-section="mail_effective">
                <DetailList :title="t('store.settings.smtp.effective.title')">
                    <DetailItem
                        v-for="row in rows"
                        :key="row.key"
                        :label="t(`store.settings.smtp.effective.${row.key}`)"
                        :value="row.value"
                    />
                </DetailList>
            </div>
            <section class="mt-6 space-y-2" data-section="smtp_audit">
                <h2 class="text-lg font-semibold">
                    {{ t('store.settings.smtp.audit.title') }}
                </h2>
                <p class="text-sm text-muted">
                    {{ t('store.settings.smtp.audit.help') }}
                </p>
                <DataTable
                    :columns="[
                        t('store.settings.smtp.audit.when'),
                        t('store.settings.smtp.audit.who'),
                        t('store.settings.smtp.audit.ip'),
                        t('store.settings.smtp.audit.what'),
                    ]"
                    :rows="auditEntries ?? []"
                    :empty-message="t('store.settings.smtp.audit.empty')"
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
                                    t('store.settings.smtp.audit.deletedUser', {
                                        id: entry.user_id,
                                    })
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
                                    'smtp_settings.password_failed',
                            }"
                        >
                            {{ auditWhat(entry) }}
                        </td>
                    </template>
                </DataTable>
            </section>
            <StoreSmtpTestForm v-if="access.can('send_newsletter')" />
        </template>
    </ResourceFormPage>
</template>
