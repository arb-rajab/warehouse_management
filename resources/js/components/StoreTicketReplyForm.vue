<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import { reply } from '@/actions/App/Http/Controllers/Store/Admin/SupportTicketController';
import FieldError from '@/components/FieldError.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import {
    isTicketStatus,
    ticketStatusLabel,
    ticketStatuses,
} from '@/lib/storeSupportTicket';

/**
 * The staff reply on the admin ticket page. It posts to the typed
 * `support_ticket.admin_store` endpoint: the reply HTML (cleaned on the
 * server), image attachments as comma-separated upload ids, and the status
 * the ticket takes, which starts at the ticket's current one.
 */
const props = defineProps<{ ticket: { id: number; status: string } }>();
const message = ref('');
const attachments = ref('');
const status = ref(
    isTicketStatus(props.ticket.status) ? props.ticket.status : 'pending',
);

function reset(): void {
    message.value = '';
    attachments.value = '';
}
</script>

<template>
    <Form
        :action="reply()"
        :options="{ preserveScroll: true }"
        class="space-y-4"
        @success="reset"
        #default="{ errors, processing }"
    >
        <input type="hidden" name="ticket_id" :value="ticket.id" />
        <StoreRichTextField
            id="reply"
            v-model="message"
            name="reply"
            :label="t('store.support.reply')"
            :error="errors.reply"
            required
        />
        <StoreUploadField
            id="attachments"
            v-model="attachments"
            name="attachments"
            :label="t('store.support.attachments')"
            type="image"
            multiple
            :error="errors.attachments"
        />
        <div class="max-w-xs">
            <label for="status" :class="fieldLabelClass">{{
                t('store.support.replyStatus')
            }}</label>
            <select
                id="status"
                v-model="status"
                name="status"
                required
                :class="plainFieldInputClass"
            >
                <option
                    v-for="value in ticketStatuses"
                    :key="value"
                    :value="value"
                >
                    {{ ticketStatusLabel(value) }}
                </option>
            </select>
            <FieldError :message="errors.status" />
        </div>
        <FieldError :message="errors.ticket_id" />
        <SubmitButton
            :label="t('store.support.send')"
            :processing-label="t('store.support.sending')"
            :processing="processing"
        />
    </Form>
</template>
