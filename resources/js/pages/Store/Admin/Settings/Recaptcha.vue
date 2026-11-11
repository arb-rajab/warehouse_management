<script setup lang="ts">
import { computed, ref } from 'vue';
import { updateRecaptcha } from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import DetailItem from '@/components/DetailItem.vue';
import DetailList from '@/components/DetailList.vue';
import FieldError from '@/components/FieldError.vue';
import FormField from '@/components/FormField.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import StoreHighlight from '@/components/StoreHighlight.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { recaptchaSettingsValidation } from '@/lib/recaptchaSettingsForm';
import type {
    StoreRecaptchaEffective,
    StoreRecaptchaSettings,
} from '@/types/store';

/**
 * The reCAPTCHA switch and its site key and secret. The pair saved here wins
 * over CAPTCHA_KEY and CAPTCHA_SECRET: with a site key saved, the secret comes
 * from here too, never from `.env` (StoreRecaptchaSettings,
 * .ai/rules/admin-env-overrides.md). The secret is never sent to this page: an
 * empty field keeps the saved one and a checkbox removes it. Both empty uses
 * the server pair.
 */
const props = defineProps<{
    settings: Record<string, boolean>;
    recaptcha: StoreRecaptchaSettings;
    effective: StoreRecaptchaEffective;
    /** The field a setup link sent the admin here to fix. */
    highlight?: string | null;
}>();

const siteKeyValue = ref(props.recaptcha.site_key ?? '');
const secretFilled = ref(false);
const clearSecret = ref(false);

function onSiteKeyInput(event: Event): void {
    siteKeyValue.value = (event.target as HTMLInputElement).value;
}

function onSecretInput(event: Event): void {
    secretFilled.value = (event.target as HTMLInputElement).value !== '';
}

const secretStatus = computed(() => {
    if (props.recaptcha.secret_unreadable) {
        return 'secretUnreadable';
    }

    return props.recaptcha.secret_set ? 'secretSaved' : 'secretNotSaved';
});

const siteKeyFilled = computed(() => siteKeyValue.value.trim() !== '');
const siteKeyChanged = computed(
    () => siteKeyValue.value.trim() !== (props.recaptcha.site_key ?? ''),
);
// The site key is required with a secret (`required_with:recaptcha_secret_key`).
const siteKeyRequired = computed(() => secretFilled.value);
// The secret is needed exactly when the server asks for it again: a site key is
// typed and no readable saved secret stays for it (none saved, unreadable, or
// the key is not the saved one). "Remove the saved secret" is left alone.
const secretRequired = computed(
    () =>
        siteKeyFilled.value &&
        !clearSecret.value &&
        (!props.recaptcha.secret_set ||
            props.recaptcha.secret_unreadable ||
            siteKeyChanged.value),
);

const warnings = computed(() => {
    if (!props.settings.google_recaptcha) {
        return [];
    }

    return [
        props.effective.site_key.value ? null : 'missingSiteKey',
        props.effective.secret.set ? null : 'missingSecret',
    ].filter((key): key is string => key !== null);
});

function withSource(value: string, source: string): string {
    return t('store.settings.recaptcha.effective.withSource', {
        value,
        source: t(`store.settings.recaptcha.effective.sources.${source}`),
    });
}

const rows = computed(() => [
    {
        key: 'siteKey',
        value: withSource(
            props.effective.site_key.value ??
                t('store.settings.recaptcha.effective.notSet'),
            props.effective.site_key.source,
        ),
    },
    {
        key: 'secretKey',
        value: withSource(
            t(
                `store.settings.recaptcha.effective.${props.effective.secret.set ? 'set' : 'notSet'}`,
            ),
            props.effective.secret.source,
        ),
    },
]);
</script>

<template>
    <ResourceFormPage
        :title="t('store.settings.titles.recaptcha')"
        :action="updateRecaptcha()"
        :submit-label="t('store.settings.save')"
        :submitting-label="t('store.settings.saving')"
        :layout="StoreAdminLayout"
        :validation="recaptchaSettingsValidation"
        wide
    >
        <template #default="{ errors }">
            <p class="text-sm text-muted">
                {{ t('store.settings.recaptcha.intro') }}
            </p>
            <FieldError :message="errors.settings" />
            <p
                v-if="recaptcha.secret_unreadable"
                role="alert"
                class="text-sm text-danger"
            >
                {{ t('store.settings.recaptcha.secretUnreadable') }}
            </p>
            <p
                v-for="key in warnings"
                :key="key"
                role="alert"
                class="text-sm text-danger"
                :data-warning="key"
            >
                {{ t(`store.settings.recaptcha.${key}`) }}
            </p>
            <div class="grid gap-4 sm:grid-cols-2">
                <StoreBooleanField
                    class="sm:col-span-2"
                    name="settings[google_recaptcha]"
                    :label="t('store.settings.keys.google_recaptcha')"
                    :value="settings.google_recaptcha"
                    :highlighted="highlight === 'google_recaptcha'"
                    :error="errors['settings.google_recaptcha']"
                />
                <StoreHighlight :active="highlight === 'recaptcha_site_key'">
                    <FormField
                        id="recaptcha_site_key"
                        :label="t('store.settings.recaptcha.siteKey')"
                        :value="recaptcha.site_key ?? ''"
                        :required="siteKeyRequired"
                        :error="errors.recaptcha_site_key"
                        maxlength="100"
                        autocomplete="off"
                        spellcheck="false"
                        dir="ltr"
                        aria-describedby="recaptcha_site_key_help"
                        @input="onSiteKeyInput"
                    />
                    <p
                        id="recaptcha_site_key_help"
                        class="mt-1 text-sm text-muted"
                    >
                        {{ t('store.settings.recaptcha.siteKeyHelp') }}
                    </p>
                </StoreHighlight>
                <StoreHighlight :active="highlight === 'recaptcha_secret_key'">
                    <FormField
                        id="recaptcha_secret_key"
                        type="password"
                        :label="t('store.settings.recaptcha.secretKey')"
                        :placeholder="
                            recaptcha.secret_set && !recaptcha.secret_unreadable
                                ? t('store.settings.recaptcha.secretKeep')
                                : undefined
                        "
                        :required="secretRequired"
                        :disabled="clearSecret"
                        :error="errors.recaptcha_secret_key"
                        maxlength="255"
                        autocomplete="new-password"
                        dir="ltr"
                        aria-describedby="recaptcha_secret_key_help"
                        @input="onSecretInput"
                    />
                    <div
                        id="recaptcha_secret_key_help"
                        class="mt-1 space-y-1 text-sm text-muted"
                    >
                        <p>
                            {{ t(`store.settings.recaptcha.${secretStatus}`) }}
                        </p>
                        <p>{{ t('store.settings.recaptcha.secretHelp') }}</p>
                    </div>
                    <label
                        v-if="recaptcha.secret_set"
                        class="mt-2 flex items-center gap-2 text-sm text-ink"
                    >
                        <input
                            id="recaptcha_secret_clear"
                            v-model="clearSecret"
                            type="checkbox"
                            name="recaptcha_secret_clear"
                            value="1"
                        />
                        {{ t('store.settings.recaptcha.removeSecret') }}
                    </label>
                    <FieldError :message="errors.recaptcha_secret_clear" />
                </StoreHighlight>
            </div>
        </template>
        <template #after-form>
            <div class="mt-6" data-section="recaptcha_effective">
                <DetailList
                    :title="t('store.settings.recaptcha.effective.title')"
                >
                    <DetailItem
                        v-for="row in rows"
                        :key="row.key"
                        :label="
                            t(`store.settings.recaptcha.effective.${row.key}`)
                        "
                        :value="row.value"
                    />
                </DetailList>
            </div>
        </template>
    </ResourceFormPage>
</template>
