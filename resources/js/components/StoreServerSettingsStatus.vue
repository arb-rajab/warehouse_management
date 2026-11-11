<script setup lang="ts">
import { computed } from 'vue';
import DetailItem from '@/components/DetailItem.vue';
import DetailList from '@/components/DetailList.vue';
import StoreHighlight from '@/components/StoreHighlight.vue';
import { t } from '@/lib/i18n';
import type { StoreServerSettings } from '@/types/store';

/**
 * Whether each server value a setup check reads is there (never the value of
 * a secret): FIREBASE_PROJECT and the credentials file on the Firebase page.
 * Only the server environment sets them, so the list is read-only; a setup
 * checklist link highlights it (.ai/rules/setup-checklist-reasons.md). The
 * SMTP page edits its settings instead (Settings/Smtp.vue).
 */
const props = defineProps<{
    section: 'firebase';
    server: StoreServerSettings;
    /** The deep-link key this block answers to. */
    anchor: string;
    highlight?: string | null;
}>();

function presence(set: boolean, yes: string, no: string): string {
    return t(`store.settings.server.${set ? yes : no}`);
}

const rows = computed(() => {
    const server = props.server;

    if (props.section === 'firebase') {
        return [
            {
                key: 'project',
                value: presence(server.project_set, 'set', 'notSet'),
            },
            {
                key: 'credentials',
                value: presence(server.credentials_found, 'found', 'notFound'),
            },
        ];
    }

    return [];
});
</script>

<template>
    <StoreHighlight
        v-if="rows.length"
        :active="highlight === anchor"
        :data-section="anchor"
        class="mt-6"
    >
        <DetailList :title="t(`store.settings.server.${section}.title`)">
            <DetailItem
                v-for="row in rows"
                :key="row.key"
                :label="t(`store.settings.server.${section}.${row.key}`)"
                :value="row.value"
            />
        </DetailList>
    </StoreHighlight>
</template>
