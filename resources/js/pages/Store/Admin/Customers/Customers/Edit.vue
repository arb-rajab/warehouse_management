<script setup lang="ts">
import { Form } from '@inertiajs/vue3';
import { ref } from 'vue';
import {
    store as storeAddress,
    update as updateAddress,
    destroy as destroyAddress,
    setDefault,
} from '@/actions/App/Http/Controllers/Store/Admin/AddressController';
import {
    update,
    index as customersIndex,
    repIndex,
    updateNotes,
    changeActivityStatus,
    sendAgreement,
    downloadTradeLicense,
} from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import FieldError from '@/components/FieldError.vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FormField from '@/components/FormField.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreCustomerAddressForm from '@/components/StoreCustomerAddressForm.vue';
import StoreCustomerFormFields from '@/components/StoreCustomerFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { confirmDelete } from '@/lib/confirm';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import type {
    AddressOption,
    CustomerDetails,
    CustomerAddress,
} from '@/types/storeCustomers';
defineProps<{
    customer: CustomerDetails;
    countries: AddressOption[];
    addresses: CustomerAddress[];
}>();
const addressOpen = ref(false);
const editingAddress = ref<CustomerAddress>();
function editAddress(address?: CustomerAddress): void {
    editingAddress.value = address;
    addressOpen.value = true;
}
</script>
<template>
    <ResourceFormPage
        :title="t('store.customers.editTitle', { name: customer.name })"
        :action="update(customer)"
        :submit-label="t('store.customers.save')"
        :submitting-label="t('store.customers.saving')"
        :cancel-href="customer.is_rep ? repIndex() : customersIndex()"
        :layout="StoreAdminLayout"
        wide
    >
        <template #default="{ errors }">
            <StoreCustomerFormFields
                :customer="customer"
                :countries="countries"
                :representative="false"
                :errors="errors"
            />
        </template>
        <template #header-actions>
            <a
                v-if="customer.trade_license"
                :href="downloadTradeLicense.url(customer.trade_license)"
                >{{ t('store.customers.downloadLicense') }}</a
            >
        </template>
        <template #after-form>
            <div class="mt-8 space-y-6">
                <Form
                    :action="updateNotes(customer)"
                    #default="{ errors, processing }"
                    class="space-y-3"
                    ><FormField
                        id="admin_notes"
                        :label="t('store.customers.notes')"
                        :value="customer.admin_notes ?? ''"
                        :error="errors.admin_notes"
                        maxlength="255" /><SubmitButton
                        :label="t('store.customers.save')"
                        :processing-label="t('store.customers.saving')"
                        :processing="processing"
                /></Form>
                <Form
                    :action="changeActivityStatus(customer)"
                    #default="{ errors, processing }"
                    class="space-y-3"
                    ><label for="new_status" :class="fieldLabelClass">{{
                        t('store.customers.activity')
                    }}</label
                    ><select
                        id="new_status"
                        name="new_status"
                        :class="plainFieldInputClass"
                        required
                    >
                        <option
                            value="new_user"
                            :selected="customer.activity_status === 'new_user'"
                        >
                            {{ t('store.customers.newUser') }}
                        </option>
                        <option
                            value="has_been_contacted"
                            :selected="
                                customer.activity_status ===
                                'has_been_contacted'
                            "
                        >
                            {{ t('store.customers.contacted') }}
                        </option></select
                    ><FieldError :message="errors.new_status" /><SubmitButton
                        :label="t('store.customers.save')"
                        :processing-label="t('store.customers.saving')"
                        :processing="processing"
                /></Form>
                <Form
                    v-if="!customer.registration_completed"
                    :action="sendAgreement(customer)"
                    #default="{ errors, processing }"
                    ><FieldError :message="errors.agreement" /><SubmitButton
                        :label="t('store.customers.sendAgreement')"
                        :processing-label="t('store.customers.saving')"
                        :processing="processing"
                /></Form>
                <section>
                    <h2 class="mb-3 text-lg font-semibold">
                        {{ t('store.customers.addresses') }}
                    </h2>
                    <button type="button" @click="editAddress()">
                        {{ t('store.customers.addAddress') }}
                    </button>
                    <div
                        v-for="address in addresses"
                        :key="address.id"
                        class="mt-3 rounded border border-gray-300 p-4 dark:border-neutral-700"
                    >
                        <p>
                            {{ address.address }} · {{ address.postal_code }} ·
                            {{ address.phone }}
                        </p>
                        <span v-if="address.set_default">{{
                            t('store.customers.defaultAddress')
                        }}</span>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" @click="editAddress(address)">
                                {{ t('store.customers.edit') }}</button
                            ><TableActionLink
                                v-if="!address.set_default"
                                :href="setDefault({ customer, address })"
                                method="post"
                                as="button"
                                >{{
                                    t('store.customers.makeDefault')
                                }}</TableActionLink
                            ><TableActionLink
                                v-if="!address.set_default"
                                :href="
                                    destroyAddress(
                                        { customer, address },
                                        { mergeQuery: {} },
                                    )
                                "
                                method="delete"
                                as="button"
                                variant="danger"
                                :on-before="
                                    () => confirmDelete(address.address)
                                "
                                >{{
                                    t('store.customers.deleteAddress')
                                }}</TableActionLink
                            >
                        </div>
                    </div>
                </section>
                <FilterDialog
                    v-model:open="addressOpen"
                    :title="t('store.customers.addresses')"
                    :close-label="t('common.close')"
                    ><Form
                        :key="editingAddress?.id ?? 'new'"
                        :action="
                            editingAddress
                                ? updateAddress({
                                      customer,
                                      address: editingAddress,
                                  })
                                : storeAddress(customer)
                        "
                        @success="addressOpen = false"
                        #default="{ errors, processing }"
                        class="space-y-4"
                        ><StoreCustomerAddressForm
                            :address="editingAddress"
                            :countries="countries"
                            :errors="errors" /><SubmitButton
                            :label="t('store.customers.save')"
                            :processing-label="t('store.customers.saving')"
                            :processing="processing" /></Form
                ></FilterDialog>
            </div>
        </template>
    </ResourceFormPage>
</template>
