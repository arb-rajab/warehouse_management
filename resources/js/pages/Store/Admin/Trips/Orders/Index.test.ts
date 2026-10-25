import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { DefineComponent } from 'vue';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { edit as driverEdit } from '@/actions/App/Http/Controllers/Store/Admin/DriverController';
import { bulk_order_delete } from '@/actions/App/Http/Controllers/Store/Admin/OrderController';
import {
    edit as orderEdit,
    shippingCreate,
} from '@/actions/App/Http/Controllers/Store/Admin/OrderEntryController';
import {
    printCmr,
    show as tripsShow,
} from '@/actions/App/Http/Controllers/Store/Admin/TripCheckpointController';
import {
    bulkUpdateDeliveryStatus,
    index as tripOrders,
    markDelivered,
    updateInvoiceNumber,
} from '@/actions/App/Http/Controllers/Store/Admin/TripOrderController';
import { edit as truckEdit } from '@/actions/App/Http/Controllers/Store/Admin/TruckController';
import AddResourceLink from '@/components/AddResourceLink.vue';
import DataTable from '@/components/DataTable.vue';
import FieldError from '@/components/FieldError.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import TableLink from '@/components/TableLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeSharedProps,
    storeTripOrder,
} from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { StoreTripOrder, StoreTripOrderFilters } from '@/types/store';
import Index from './Index.vue';

const {
    usePageMock,
    routerGetMock,
    routerPostMock,
    formStateMock,
    submitMock,
    useFormStateMock,
} = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    routerPostMock: vi.fn(),
    formStateMock: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
    submitMock: vi.fn(),
    useFormStateMock: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, createUseFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(formStateMock),
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        useForm: createUseFormStub(submitMock, useFormStateMock),
        router: { get: routerGetMock, post: routerPostMock },
    };
});

const allPermissions = [
    'view_all_trips',
    'add_order',
    'edit_trip',
    'delete_order',
    'edit_order',
    'edit_customer',
    'edit_driver',
    'edit_truck',
];
const columns = [
    'code',
    'products',
    'otajerOrderId',
    'accountingId',
    'companyName',
    'orderFrom',
    'wmsStatus',
    'customer',
    'tripCode',
    'driver',
    'truck',
    'representative',
    'address',
    'country',
    'phone',
    'invoiceNumber',
    'customerNotes',
    'managerNotes',
    'amount',
    'deliveryStatus',
    'deliveryDate',
    'photos',
    'actions',
];
const deliveryStatuses = [
    'confirmed',
    'ready_for_delivery',
    'on_delivery',
    'delivered',
];
const photos = [
    { id: 601, name: 'cmr.jpg', url: 'https://cdn.example.com/cmr.jpg' },
    { id: 602, name: 'other.jpg', url: 'https://cdn.example.com/other.jpg' },
];

function mountPage(
    orders: StoreTripOrder[] = [],
    permissions = allPermissions,
    filters: Partial<StoreTripOrderFilters> = {},
) {
    usePageMock.mockReturnValue({
        url: tripOrders.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: {
            orders: paginated(orders),
            photos,
            filters: {
                per_page: 20,
                search: '',
                delivery_status: '',
                from: '',
                to: '',
                ...filters,
            },
            deliveryStatuses,
            bulkStatuses: ['confirmed', 'ready_for_delivery'],
        },
    });
}
function forms(wrapper: ReturnType<typeof mountPage>) {
    return wrapper.findAllComponents(Form);
}
function actionLink(wrapper: ReturnType<typeof mountPage>, key: string) {
    return wrapper
        .findAllComponents(TableActionLink)
        .find((link) => link.text().includes(t(`store.trips.orders.${key}`)));
}
function statusLabel(status: string): string {
    return t(`store.orders.status.${status}`);
}

describe('Store trip orders list', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerGetMock, routerPostMock, submitMock });
        formStateMock.mockReturnValue({ errors: {}, processing: false });
        useFormStateMock.mockReturnValue({ errors: {}, processing: false });
    });
    afterEach(() => {
        vi.restoreAllMocks();
        i18n.global.locale.value = 'en';
    });

    it.each(['en', 'ar'] as const)(
        'renders the layout, title, help, filters, bulk actions, every header and the empty label in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountPage();
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.trips.orders.title'),
            );
            expect(wrapper.get('h1').text()).toBe(
                t('store.trips.orders.title'),
            );
            expect(wrapper.text()).toContain(
                t('store.trips.orders.invoiceHelp'),
            );
            expect(wrapper.get('label[for="search"]').text()).toBe(
                t('store.trips.orders.search'),
            );
            expect(wrapper.get('label[for="from"]').text()).toBe(
                t('store.trips.orders.from'),
            );
            expect(wrapper.get('label[for="to"]').text()).toBe(
                t('store.trips.orders.to'),
            );
            expect(wrapper.get('legend').text()).toBe(
                t('store.trips.orders.statusFilter'),
            );
            expect(
                wrapper
                    .findAll('input[name="delivery_status"][type="radio"]')
                    .map((input) => input.element.parentElement?.textContent),
            ).toEqual([
                t('store.trips.orders.allStatuses'),
                ...deliveryStatuses.map(statusLabel),
            ]);
            expect(
                wrapper
                    .findAllComponents(SubmitButton)
                    .map((button) => [
                        button.props('label'),
                        button.props('processingLabel'),
                    ]),
            ).toEqual([
                [
                    t('store.trips.orders.apply'),
                    t('store.trips.orders.applying'),
                ],
                [
                    t('store.trips.orders.markSelection', {
                        status: statusLabel('confirmed'),
                    }),
                    t('store.trips.orders.updating'),
                ],
                [
                    t('store.trips.orders.markSelection', {
                        status: statusLabel('ready_for_delivery'),
                    }),
                    t('store.trips.orders.updating'),
                ],
                [
                    t('store.trips.orders.deleteSelection'),
                    t('store.trips.orders.deleting'),
                ],
            ]);
            expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual(
                ['select', ...columns].map((key) =>
                    t(`store.trips.orders.${key}`),
                ),
            );
            expect(wrapper.text()).toContain(t('store.trips.orders.empty'));
        },
    );

    it('links order creation as an Inertia visit for an order creator only', () => {
        expect(
            mountPage()
                .findAllComponents(AddResourceLink)
                .map((link) => link.props()),
        ).toEqual([
            {
                href: shippingCreate(),
                label: t('store.trips.orders.createOrder'),
                inertia: true,
            },
        ]);
        expect(
            mountPage([], ['view_all_trips'])
                .findComponent(AddResourceLink)
                .exists(),
        ).toBe(false);
    });

    it('renders every row field and passes the rows to the table', () => {
        const order = storeTripOrder();
        const plain = storeTripOrder({
            id: 78,
            code: '20261014-0001',
            is_new: false,
            details_count: 0,
            otajer_order_id: null,
            order_from: null,
            wms_status: null,
            customer: null,
            by_rep: false,
            representative: null,
            address: null,
            country: null,
            phone: null,
            invoice_number: null,
            customer_notes: null,
            manager_notes: null,
            amount: '€5.00',
            delivery_status: 'confirmed',
            delivery_date: null,
            editable: false,
            checkpoint: null,
            trip: null,
        });
        const wrapper = mountPage([order, plain], ['view_all_trips']);
        expect(
            wrapper
                .getComponent(
                    DataTable as unknown as DefineComponent<{
                        rows: StoreTripOrder[];
                    }>,
                )
                .props('rows'),
        ).toEqual([order, plain]);
        const rows = wrapper.findAll('tbody tr');
        expect(rows[0].findAll('td').map((td) => td.text())).toEqual([
            `20261015-0001 ${t('store.trips.orders.new')}`,
            '3',
            'OT-9',
            'ACC-7',
            'Alma BV',
            'app',
            'picked',
            'Alma Customer',
            'TRIP-LAST',
            'Sam Driver',
            'Cargo truck',
            'Rita Rep',
            'Main street 5',
            'Netherlands',
            '0611111111',
            'INV-1',
            'Ring twice',
            'Handle with care',
            '€42.50',
            statusLabel('on_delivery'),
            '2026-10-16',
            '',
            t('store.trips.orders.printCmr'),
        ]);
        expect(rows[0].get('a[target="_blank"]').attributes()).toMatchObject({
            href: 'https://www.google.com/maps?q=Main%20street%205',
            rel: 'noopener noreferrer',
        });
        expect(
            rows[0]
                .findAllComponents(Link)
                .find((link: VueWrapper) => link.text() === 'TRIP-LAST')
                ?.props('href'),
        ).toEqual(tripsShow(order.trip!));
        expect(rows[0].findAll('img').map((img) => img.attributes())).toEqual([
            expect.objectContaining({
                src: 'https://cdn.example.com/cmr.jpg',
                alt: t('store.trips.orders.photoOf', { code: order.code }),
                width: '100',
                height: '100',
            }),
        ]);
        expect(rows[1].findAll('td').map((td) => td.text())).toEqual([
            '20261014-0001',
            '0',
            '',
            '--',
            '--',
            '--',
            '',
            t('store.trips.orders.deletedAccount'),
            '-',
            '-',
            '-',
            t('store.trips.orders.noRepresentative'),
            '-',
            '-',
            '-',
            '',
            '',
            '',
            '€5.00',
            statusLabel('confirmed'),
            '',
            '-',
            '',
        ]);
        expect(wrapper.findAllComponents(TableLink)).toHaveLength(0);
        expect(
            wrapper
                .findAllComponents(TableActionLink)
                .map((link) => [link.props('href'), link.props('inertia')]),
        ).toEqual([[printCmr(order.checkpoint!), false]]);
    });

    it("names a representative's deleted account and links customer, driver and truck for viewers who may edit them", () => {
        const order = storeTripOrder();
        const wrapper = mountPage(
            [order, storeTripOrder({ id: 79, representative: null })],
            ['view_all_trips', 'edit_customer', 'edit_driver', 'edit_truck'],
        );
        expect(
            wrapper
                .findAllComponents(TableLink)
                .slice(0, 3)
                .map((link) => [link.props('href'), link.text()]),
        ).toEqual([
            [customerEdit(order.customer!), 'Alma Customer'],
            [driverEdit(order.trip!.driver!), 'Sam Driver'],
            [truckEdit(order.trip!.truck!), 'Cargo truck'],
        ]);
        expect(wrapper.findAll('tbody tr')[1].findAll('td')[11].text()).toBe(
            t('store.trips.orders.deletedAccount'),
        );

        const customersOnly = mountPage(
            [order],
            ['view_all_trips', 'edit_customer'],
        );
        expect(
            customersOnly
                .findAllComponents(TableLink)
                .map((link) => link.text()),
        ).toEqual(['Alma Customer']);
    });

    it('gates each control by its own permission', () => {
        const order = storeTripOrder();
        const reader = mountPage([order], ['view_all_trips', 'delete_trip']);
        expect(forms(reader)).toHaveLength(1);
        expect(reader.find('tbody input').exists()).toBe(false);
        expect(reader.text()).not.toContain(
            t('store.trips.orders.invoiceHelp'),
        );
        expect(reader.text()).toContain('INV-1');
        expect(reader.findAll('thead th').map((th) => th.text())).toEqual(
            columns.map((key) => t(`store.trips.orders.${key}`)),
        );

        const tripEditor = mountPage([order], ['view_all_trips', 'edit_trip']);
        expect(forms(tripEditor)).toHaveLength(3);
        expect(tripEditor.find(`#order-invoice-${order.id}`).exists()).toBe(
            true,
        );
        expect(
            tripEditor
                .findAllComponents(TableActionLink)
                .map((link) => link.text()),
        ).toEqual([
            t('store.trips.orders.printCmr'),
            t('store.trips.orders.markDelivered'),
        ]);

        const deleter = mountPage([order], ['view_all_trips', 'delete_order']);
        expect(forms(deleter).map((form) => form.props('action'))).toEqual([
            tripOrders(),
            bulk_order_delete({ mergeQuery: { return_to: 'trip_orders' } }),
        ]);
        expect(deleter.find('tbody input[type="checkbox"]').exists()).toBe(
            true,
        );
        expect(deleter.find(`#order-invoice-${order.id}`).exists()).toBe(false);

        const orderEditor = mountPage(
            [order, storeTripOrder({ id: 79, editable: false })],
            ['view_all_trips', 'edit_order'],
        );
        expect(
            orderEditor
                .findAllComponents(TableActionLink)
                .filter((link) => link.text() === t('store.trips.orders.edit'))
                .map((link) => link.props('href')),
        ).toEqual([orderEdit(order)]);
    });

    it('marks an order delivered with a PUT to this list after native confirmation, except one already delivered', () => {
        const order = storeTripOrder();
        const wrapper = mountPage([
            order,
            storeTripOrder({ id: 79, delivery_status: 'delivered' }),
            storeTripOrder({ id: 80, delivery_status: 'cancelled' }),
            storeTripOrder({ id: 81, delivery_status: 'pending' }),
            storeTripOrder({ id: 82, trip: null }),
        ]);
        const links = wrapper
            .findAllComponents(TableActionLink)
            .filter(
                (link) => link.text() === t('store.trips.orders.markDelivered'),
            );
        expect(links).toHaveLength(1);
        const action = actionLink(wrapper, 'markDelivered');
        expect(action?.props('href')).toEqual(
            markDelivered(order, { mergeQuery: { trip_id: order.trip!.id } }),
        );
        expect(action?.attributes('method')).toBe('put');
        const onBefore = action
            ?.getComponent(Link)
            .props('onBefore') as () => boolean;
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('store.trips.orders.confirmStatus', {
                label: t('store.trips.orders.order', { code: order.code }),
                status: statusLabel('delivered'),
            }),
        );
        confirm.mockReturnValue(true);
        expect(onBefore()).toBe(true);
    });

    it("saves a row's invoice number on change to this list and reports the save", async () => {
        const order = storeTripOrder();
        const wrapper = mountPage([order]);
        const invoice = wrapper.get<HTMLInputElement>(
            `#order-invoice-${order.id}`,
        );
        expect(invoice.element.value).toBe('INV-1');
        expect(invoice.attributes()).toMatchObject({
            type: 'text',
            maxlength: '255',
            'aria-label': t('store.trips.orders.invoiceFor', {
                code: order.code,
            }),
        });

        await invoice.setValue('INV-2');
        expect(submitMock).toHaveBeenCalledWith(
            updateInvoiceNumber(order, { mergeQuery: {} }),
            expect.objectContaining({
                invoice_number: 'INV-2',
                trip_id: order.trip!.id,
            }),
            expect.objectContaining({
                preserveScroll: true,
                preserveState: true,
            }),
        );
        expect(wrapper.find('[role="status"]').exists()).toBe(false);
        (submitMock.mock.calls[0][2] as { onSuccess: () => void }).onSuccess();
        await wrapper.vm.$nextTick();
        expect(wrapper.get('[role="status"]').text()).toBe(
            t('store.trips.orders.invoiceSaved'),
        );
    });

    it('shows a refused invoice number under the row that sent it', async () => {
        useFormStateMock.mockReturnValue({
            errors: {
                invoice_number:
                    'The invoice number may not be greater than 255 characters.',
            },
            processing: false,
        });
        const wrapper = mountPage([
            storeTripOrder(),
            storeTripOrder({ id: 79, code: '20261015-0002' }),
        ]);
        expect(
            wrapper
                .findAllComponents(FieldError)
                .filter((error) => error.props('message')),
        ).toHaveLength(0);

        await wrapper.get('#order-invoice-79').setValue('x'.repeat(300));
        const rows = wrapper.findAll('tbody tr');
        expect(rows[1].text()).toContain(
            'The invoice number may not be greater than 255 characters.',
        );
        expect(rows[0].text()).not.toContain(
            'The invoice number may not be greater than 255 characters.',
        );
    });

    it('sends the selection to each bulk action after confirmation and selects the whole page', async () => {
        const first = storeTripOrder();
        const second = storeTripOrder({ id: 79, code: '20261015-0002' });
        const wrapper = mountPage([first, second]);
        const [, confirmed, ready, remove] = forms(wrapper);
        expect(confirmed.props('action')).toEqual(
            bulkUpdateDeliveryStatus({ mergeQuery: {} }),
        );
        expect(ready.props('action')).toEqual(
            bulkUpdateDeliveryStatus({ mergeQuery: {} }),
        );
        expect(
            [confirmed, ready].map((form) =>
                form.get('input[name="delivery_status"]').attributes('value'),
            ),
        ).toEqual(['confirmed', 'ready_for_delivery']);
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);

        for (const form of [confirmed, ready, remove]) {
            expect((form.props('onBefore') as () => boolean)()).toBe(false);
            expect(
                form.getComponent(SubmitButton).attributes('disabled'),
            ).toBeDefined();
        }

        expect(confirm).not.toHaveBeenCalled();

        await wrapper
            .get(
                `input[aria-label="${t('store.trips.orders.selectOrder', { code: second.code })}"]`,
            )
            .setValue(true);
        expect(
            ready
                .findAll('input[name="id[]"]')
                .map((input) => input.attributes('value')),
        ).toEqual(['79']);
        const selection = t('store.trips.orders.selection', { count: 1 });
        expect((ready.props('onBefore') as () => boolean)()).toBe(true);
        expect(confirm).toHaveBeenLastCalledWith(
            t('store.trips.orders.confirmStatus', {
                label: selection,
                status: statusLabel('ready_for_delivery'),
            }),
        );
        expect((remove.props('onBefore') as () => boolean)()).toBe(true);
        expect(confirm).toHaveBeenLastCalledWith(
            t('common.confirmDelete', { label: selection }),
        );

        const selectAllLabel = wrapper
            .findAll('label')
            .find(
                (label) => label.text() === t('store.trips.orders.selectAll'),
            );
        await selectAllLabel?.get('input[type="checkbox"]').setValue(true);
        expect(
            remove
                .findAll('input[name="id[]"]')
                .map((input) => input.attributes('value')),
        ).toEqual(['77', '79']);
        confirmed.vm.$emit('success');
        await wrapper.vm.$nextTick();
        expect(remove.findAll('input[name="id[]"]')).toHaveLength(0);
    });

    it('offers writes only for attached orders and blocks mixed-trip bulk selections', async () => {
        const first = storeTripOrder();
        const second = storeTripOrder({
            id: 79,
            code: 'OTHER',
            trip: { ...first.trip!, id: 999 },
        });
        const plain = storeTripOrder({ id: 80, code: 'PLAIN', trip: null });
        const wrapper = mountPage([first, second, plain]);
        expect(wrapper.find('#order-invoice-80').exists()).toBe(false);
        const bulk = forms(wrapper)[1];
        await wrapper
            .get(
                `input[aria-label="${t('store.trips.orders.selectOrder', { code: first.code })}"]`,
            )
            .setValue(true);
        expect(bulk.get('input[name="trip_id"]').attributes('value')).toBe(
            String(first.trip!.id),
        );
        await wrapper
            .get(
                `input[aria-label="${t('store.trips.orders.selectOrder', { code: second.code })}"]`,
            )
            .setValue(true);
        expect(
            bulk.getComponent(SubmitButton).attributes('disabled'),
        ).toBeDefined();
        expect((bulk.props('onBefore') as () => boolean)()).toBe(false);
    });

    it('shows a refused bulk action under its button', () => {
        formStateMock.mockReturnValue({
            errors: { 'id.0': 'The selected order is invalid.' },
            processing: false,
        });
        const wrapper = mountPage([storeTripOrder()]);
        expect(forms(wrapper)[1].text()).toContain(
            'The selected order is invalid.',
        );
    });

    it('submits the filters to the list and keeps them when the page size changes', () => {
        const wrapper = mountPage([storeTripOrder()], allPermissions, {
            per_page: 50,
            search: 'Alma',
            delivery_status: 'delivered',
            from: '2026-10-01',
            to: '2026-10-09',
        });
        const filter = forms(wrapper)[0];
        expect(filter.props('action')).toEqual(tripOrders());
        expect(filter.get('input[name="per_page"]').attributes('value')).toBe(
            '50',
        );
        expect(filter.get<HTMLInputElement>('#search').element.value).toBe(
            'Alma',
        );
        expect(filter.get('#search').attributes('maxlength')).toBe('100');
        expect(filter.get('#from').attributes('type')).toBe('date');
        expect(filter.get<HTMLInputElement>('#from').element.value).toBe(
            '2026-10-01',
        );
        expect(filter.get<HTMLInputElement>('#to').element.value).toBe(
            '2026-10-09',
        );
        expect(
            filter
                .findAll<HTMLInputElement>('input[name="delivery_status"]')
                .map((input) => [input.element.value, input.element.checked]),
        ).toEqual([
            ['', false],
            ['confirmed', false],
            ['ready_for_delivery', false],
            ['on_delivery', false],
            ['delivered', true],
        ]);

        const pagination = wrapper.getComponent(Pagination);
        expect(pagination.props()).toMatchObject({ links: [], perPage: 50 });
        pagination.vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            tripOrders.url(),
            {
                per_page: 10,
                search: 'Alma',
                delivery_status: 'delivered',
                from: '2026-10-01',
                to: '2026-10-09',
            },
            { preserveState: true, replace: true },
        );

        const plain = mountPage();
        expect(
            plain.get<HTMLInputElement>('input[name="delivery_status"]').element
                .checked,
        ).toBe(true);
        plain.getComponent(Pagination).vm.$emit('update:perPage', 100);
        expect(routerGetMock).toHaveBeenLastCalledWith(
            tripOrders.url(),
            { per_page: 100 },
            { preserveState: true, replace: true },
        );
    });

    it("shows the server's filter errors", () => {
        formStateMock.mockReturnValue({
            errors: {
                search: 'The search must be a string.',
                delivery_status: 'The selected delivery status is invalid.',
                to: 'The to must be a date after or equal to from.',
            },
            processing: false,
        });
        const filter = forms(mountPage())[0];
        expect(filter.text()).toContain('The search must be a string.');
        expect(filter.text()).toContain(
            'The selected delivery status is invalid.',
        );
        expect(filter.text()).toContain(
            'The to must be a date after or equal to from.',
        );
    });

    it('escapes codes, names and notes rather than rendering supplied HTML', () => {
        const wrapper = mountPage(
            [
                storeTripOrder({
                    code: '<img src=x onerror=alert(1)>',
                    customer_notes: '<script>alert(1)</script>',
                    manager_notes: '<b>bold</b>',
                    checkpoint: null,
                }),
            ],
            ['view_all_trips'],
        );
        expect(wrapper.find('tbody script').exists()).toBe(false);
        expect(wrapper.find('tbody img').exists()).toBe(false);
        expect(wrapper.find('tbody b').exists()).toBe(false);
        expect(wrapper.get('tbody').text()).toContain(
            '<script>alert(1)</script>',
        );
    });
});
