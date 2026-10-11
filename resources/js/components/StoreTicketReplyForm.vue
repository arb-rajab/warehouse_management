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
import { useStoreAccess } from '@/lib/storeAccess';
const props = defineProps<{ ticket: { id: number; status: string } }>();
const message = ref('');
const attachments = ref('');
const status = ref(props.ticket.status);
const access = useStoreAccess();
function transform() {
    return {
        ticket_id: props.ticket.id,
        reply: message.value,
        attachments: attachments.value,
        status: status.value,
    };
}
function reset() {
    message.value = '';
    attachments.value = '';
}
</script>
<template>
    <Form
        v-if="access.can('reply_to_support_tickets')"
        :action="reply()"
        :transform="transform"
        @success="reset"
        #default="{ errors, processing }"
        class="my-6 space-y-4"
        ><StoreRichTextField
            id="reply"
            name="reply"
            :label="t('store.support.reply')"
            v-model="message"
            :error="errors.reply"
            :maxlength="10000"
            required
            basic /><StoreUploadField
            id="attachments"
            name="attachments"
            :label="t('store.support.attachments')"
            v-model="attachments"
            multiple
            type="image"
            :error="errors.attachments" /><label :class="fieldLabelClass"
            >{{ t('store.support.status')
            }}<select
                v-model="status"
                name="status"
                required
                :class="plainFieldInputClass"
            >
                <option
                    v-for="value in ['pending', 'open', 'solved']"
                    :key="value"
                    :value="value"
                >
                    {{ t(`store.support.${value}`) }}
                </option>
            </select></label
        ><FieldError :message="errors.status" /><FieldError
            :message="errors.ticket_id" /><SubmitButton
            :label="t('store.support.send')"
            :processing-label="t('store.support.sending')"
            :processing="processing"
    /></Form>
</template>
