<script setup lang="ts">
import { computed, ref } from 'vue';
import { updateSmtp } from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import DetailItem from '@/components/DetailItem.vue';
import DetailList from '@/components/DetailList.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreHighlight from '@/components/StoreHighlight.vue';
import StoreSmtpTestForm from '@/components/StoreSmtpTestForm.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { smtpSettingsValidation } from '@/lib/smtpSettingsForm';
import { useStoreAccess } from '@/lib/storeAccess';
import type {
    StoreSmtpEffectivePresence,
    StoreSmtpEffectiveSettings,
    StoreSmtpEffectiveValue,
    StoreSmtpSettings,
} from '@/types/store';

/**
 * The SMTP settings saved in the admin win over the server's MAIL_* values,
 * which stay the fallback for an empty field (StoreMailSettings,
 * .ai/rules/admin-env-overrides.md). The host, port, encryption, username and
 * password are one group: without a host all five come from `.env`. The saved
 * password is never sent here; an empty password field keeps it.
 */
const props = defineProps<{
    smtp: StoreSmtpSettings;
    effective: StoreSmtpEffectiveSettings;
    /** The field a setup checklist link sent the admin here to fix. */
    highlight?: string | null;
}>();

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
            <StoreSmtpTestForm v-if="access.can('send_newsletter')" />
        </template>
    </ResourceFormPage>
</template>
