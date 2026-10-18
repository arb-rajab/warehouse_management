<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ArrowLeft, CircleCheck, PackageSearch } from '@lucide/vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { show as orderShow } from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import {
    index as visitsIndex,
    show_all_visits_in_a_day,
    update_note,
} from '@/actions/App/Http/Controllers/Store/Admin/VisitController';
import FieldError from '@/components/FieldError.vue';
import PageHeader from '@/components/PageHeader.vue';
import StoreVisitMap from '@/components/StoreVisitMap.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { formatDate, formatDateTime } from '@/lib/date';
import { fieldLabelClass, plainFieldInputClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import { useStoreAccess } from '@/lib/storeAccess';
import type {
    RepresentativeVisitDetail,
    RepresentativeVisitMarker,
} from '@/types/storeRepresentatives';

defineProps<{
    visit: RepresentativeVisitDetail;
    mapMarkers: RepresentativeVisitMarker[];
    canUpdateNote: boolean;
}>();
const access = useStoreAccess();
</script>

<template>
    <Head :title="t('store.representatives.visits.details')" />
    <StoreAdminLayout>
        <PageHeader :title="t('store.representatives.visits.details')">
            <TableActionLink :href="visitsIndex()"
                ><ArrowLeft class="h-3.5 w-3.5 rtl:rotate-180" />{{
                    t('store.representatives.visits.back')
                }}</TableActionLink
            >
        </PageHeader>
        <TableLink
            v-if="
                visit.representative &&
                visit.visit_date &&
                access.can('view_representative_visits')
            "
            :href="
                show_all_visits_in_a_day(visit.representative, {
                    query: { date: visit.visit_date.slice(0, 10) },
                })
            "
            >{{
                t('store.representatives.visits.dayTitle', {
                    name: visit.representative.name,
                })
            }}</TableLink
        >
        <div class="my-6 grid gap-6 md:grid-cols-2">
            <dl class="space-y-3" data-testid="visit-customer">
                <div>
                    <dt :class="fieldLabelClass">
                        {{ t('store.representatives.visits.customer') }}
                    </dt>
                    <dd>
                        <TableLink
                            v-if="visit.customer && access.can('edit_customer')"
                            :href="customerEdit(visit.customer)"
                            >{{ visit.customer.name }}</TableLink
                        >
                        <span v-else>{{
                            visit.customer?.name ??
                            t('store.representatives.visits.missingCustomer')
                        }}</span>
                    </dd>
                </div>
                <div
                    v-for="[key, value] in [
                        ['company', visit.customer?.company_name],
                        ['email', visit.customer?.email],
                        ['phone', visit.customer?.phone],
                        ['serial', visit.customer?.member_serial],
                        ['accountId', visit.customer?.accounting_id],
                        [
                            'date',
                            visit.visit_date
                                ? formatDateTime(visit.visit_date)
                                : null,
                        ],
                    ]"
                    :key="String(key)"
                >
                    <dt :class="fieldLabelClass">
                        {{ t(`store.representatives.visits.${key}`) }}
                    </dt>
                    <dd class="break-words whitespace-pre-wrap">
                        {{ value ?? '-' }}
                    </dd>
                </div>
            </dl>
            <dl v-if="visit.order" class="space-y-3" data-testid="visit-order">
                <div>
                    <dt :class="fieldLabelClass">
                        {{ t('store.representatives.visits.order') }}
                    </dt>
                    <dd>
                        <TableLink
                            v-if="access.can('view_order_details')"
                            :href="
                                orderShow['/temp/admin/orders/{order}/show'](
                                    visit.order.id,
                                )
                            "
                            >{{ visit.order.code }}</TableLink
                        >
                        <span v-else>{{ visit.order.code }}</span>
                    </dd>
                </div>
                <div
                    v-for="[key, value] in [
                        [
                            'status',
                            t(
                                `store.orders.statuses.${visit.order.delivery_status}`,
                            ),
                        ],
                        [
                            'orderDate',
                            formatDateTime(
                                new Date(visit.order.date * 1000).toISOString(),
                            ),
                        ],
                        ['amount', visit.order.amount],
                        ['paymentMethod', visit.order.payment_type],
                        ['customerNotes', visit.order.customer_notes],
                        [
                            'deliveryDate',
                            visit.order.delivery_date
                                ? formatDate(visit.order.delivery_date)
                                : null,
                        ],
                    ]"
                    :key="String(key)"
                >
                    <dt :class="fieldLabelClass">
                        {{ t(`store.representatives.visits.${key}`) }}
                    </dt>
                    <dd class="break-words whitespace-pre-wrap">
                        {{ value ?? '-' }}
                    </dd>
                </div>
                <div v-if="visit.order.customer_otajer_id !== null">
                    <dt :class="fieldLabelClass">
                        {{ t('store.representatives.visits.customerOtajerId') }}
                    </dt>
                    <dd>{{ visit.order.customer_otajer_id }}</dd>
                </div>
            </dl>
            <p v-else class="flex items-center gap-2">
                <PackageSearch class="h-5 w-5" />{{
                    t('store.representatives.visits.noOrder')
                }}
            </p>
        </div>
        <Form
            v-if="canUpdateNote && access.can('view_representative_visits')"
            :action="
                update_note['/temp/admin/representatives/visits/{visit}/note'](
                    visit,
                )
            "
            :options="{ preserveScroll: true }"
            #default="{ errors, processing, recentlySuccessful }"
            class="mb-6 space-y-3"
        >
            <div>
                <label for="note" :class="fieldLabelClass">{{
                    t('store.representatives.visits.note')
                }}</label>
                <textarea
                    id="note"
                    name="note"
                    rows="4"
                    :value="visit.note ?? ''"
                    :class="plainFieldInputClass"
                />
                <FieldError :message="errors.note ?? errors.visit_id" />
            </div>
            <SubmitButton
                :label="t('store.representatives.visits.saveNote')"
                :processing-label="t('store.representatives.visits.saving')"
                :processing="processing"
            />
            <p
                v-if="recentlySuccessful"
                role="status"
                class="flex items-center gap-1 text-sm"
            >
                <CircleCheck class="h-4 w-4" />{{
                    t('store.representatives.visits.saved')
                }}
            </p>
        </Form>
        <div v-else class="mb-6">
            <h2 :class="fieldLabelClass">
                {{ t('store.representatives.visits.note') }}
            </h2>
            <p class="whitespace-pre-wrap">{{ visit.note ?? '-' }}</p>
        </div>
        <StoreVisitMap :markers="mapMarkers" />
    </StoreAdminLayout>
</template>
