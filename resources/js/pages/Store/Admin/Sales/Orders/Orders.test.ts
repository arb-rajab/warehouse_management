import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { edit as customerEdit } from '@/actions/App/Http/Controllers/Store/Admin/CustomerController';
import { edit as productEdit } from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import StoreListFilters from '@/components/StoreListFilters.vue';
import StoreOrderEntryFields from '@/components/StoreOrderEntryFields.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { paginated, storeOrder, storeSharedProps } from '@/testing/factories';
import { expectColumnCanBeHidden, headerTexts } from '@/testing/tableColumns';
import Create from './Create.vue';
import Edit from './Edit.vue';
import Index from './Index.vue';
import Show from './Show.vue';
const mocks = vi.hoisted(() => ({
    page: vi.fn(),
    get: vi.fn(),
    cancel: vi.fn(),
    slot: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(mocks.slot),
        Link: createLinkStub(),
        Head: headStub,
        usePage: mocks.page,
        useHttp: () => ({ get: vi.fn(), cancel: vi.fn() }),
        router: { get: mocks.get, cancel: vi.fn(), post: vi.fn() },
    };
});
const order = storeOrder();
// The layout's Clear dashboard cache form (in its confirm dialog) shows for a Super Admin; these tests are about the page's own forms.
const pageForms = (wrapper: ReturnType<typeof mount>) =>
    wrapper
        .findAll('form')
        .filter(
            (form) =>
                form.attributes('data-action-url') !==
                '/temp/admin/clear-cache',
        );
beforeEach(() => {
    mocks.page.mockReturnValue({
        url: '/temp/admin/all_orders',
        props: {
            locale: 'en',
            auth: { user: { id: 1, name: 'Admin' } },
            store: storeSharedProps({ isSuperAdmin: true }),
            errors: {},
        },
    });
    mocks.slot.mockReturnValue({ errors: {}, processing: false });
});
describe('Store order pages', () => {
    it.each([true, false])(
        'mounts the shared create form for representative=%s with native constraints',
        (representativeOrder) => {
            const wrapper = mount(Create, {
                props: { representativeOrder, submissionKey: 'token' },
            });
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(
                pageForms(wrapper)[0].attributes('data-action-url'),
            ).toContain(
                representativeOrder
                    ? '/feat/order/store'
                    : '/feat/new/order/trip/store',
            );
            expect(
                wrapper.get('input[name=submission_key]').attributes('value'),
            ).toBe('token');
            // The customer is a search select: a combobox trigger marked
            // required, a hidden input named as the form field, and the
            // native required guard the browser validates.
            expect(
                wrapper.get('#customer_id').attributes('aria-required'),
            ).toBe('true');
            expect(
                wrapper.get('input[type="hidden"][name="customer_id"]').element,
            ).toHaveProperty('value', '');
            expect(
                wrapper.get('[data-testid="search-select-guard"]').attributes(),
            ).toHaveProperty('required');
            expect(wrapper.find('#rep_id').exists()).toBe(representativeOrder);
            expect(
                wrapper.find('input[type="hidden"][name="rep_id"]').exists(),
            ).toBe(representativeOrder);
            expect(
                wrapper
                    .get('input[name="products[0][product_id]"]')
                    .attributes('type'),
            ).toBe('hidden');
            expect(wrapper.get('#quantity-1').attributes()).toMatchObject({
                min: '1',
                max: '100000',
                step: '1',
                required: '',
            });
            expect(wrapper.get('#price-1').attributes()).toMatchObject({
                min: '0',
                step: '0.01',
            });

            for (const label of [
                'Customer',
                'Delivery date',
                'Notes',
                'Product',
                'Quantity',
                'Unit',
                'Unit price',
                'Box',
                'Piece',
                'Variant',
                'Subtotal',
                'Tax',
                'Total',
                'Save order',
                'Add product',
            ]) {
                expect(wrapper.text()).toContain(label);
            }
        },
    );
    it('wires permitted deletes through native confirmation and the DELETE action', () => {
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const wrapper = mount(Index, {
            props: {
                orders: paginated([order]),
                filters: { per_page: 20 },
                listRoute: 'all_orders.index',
            },
        });
        const button = wrapper
            .findAllComponents(TableActionLink)
            .find((link) => link.text() === 'Delete')!;
        expect(button.props('href')).toMatchObject({
            method: 'delete',
            url: '/temp/admin/orders/7',
        });
        const onBefore = button
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(onBefore()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(expect.stringContaining('ORD-7'));
        confirm.mockRestore();
    });
    it('mounts edit with persisted tax shipping and coupon and forwards indexed errors', () => {
        mocks.slot.mockReturnValue({
            errors: { 'products.0.price': 'Invalid price' },
            processing: false,
        });
        const wrapper = mount(Edit, {
            props: { order, additionalInfo: 'Retain this', couponDiscount: 1 },
        });
        expect(
            wrapper.getComponent(StoreOrderEntryFields).props('order'),
        ).toEqual(order);
        expect(
            wrapper
                .findAllComponents(Form)
                .find(
                    (form) =>
                        form.attributes('data-action-url') !==
                        '/temp/admin/clear-cache',
                )
                ?.props('action'),
        ).toMatchObject({
            method: 'put',
        });
        expect(wrapper.text()).toContain('20.00');
        expect(wrapper.text()).toContain('6.30');
        expect(wrapper.text()).toContain('Invalid price');
        // The selects start prefilled: their labels show before any search and
        // their hidden inputs post the stored ids.
        expect(
            wrapper.get('input[name="customer_id"]').attributes('value'),
        ).toBe('3');
        expect(wrapper.get('#customer_id').text()).toContain('Buyer');
        expect(
            wrapper
                .get('input[name="products[0][product_id]"]')
                .attributes('value'),
        ).toBe('5');
        expect(wrapper.get('#product-1').text()).toContain('SER-5');
        expect(
            wrapper.get('input[name="products[0][id]"]').attributes('value'),
        ).toBe('1');
        expect(wrapper.get('#additional_info').attributes('value')).toBe(
            'Retain this',
        );
    });
    it('mounts the paginated list and hides write controls without permission', () => {
        mocks.page.mockReturnValue({
            url: '/temp/admin/all_orders',
            props: {
                locale: 'en',
                auth: { user: { id: 1, name: 'Viewer' } },
                store: storeSharedProps({
                    permissions: ['view_all_orders', 'view_order_details'],
                }),
                errors: {},
            },
        });
        const wrapper = mount(Index, {
            props: {
                orders: {
                    data: [order],
                    links: [],
                    meta: {
                        links: [],
                        current_page: 1,
                        last_page: 1,
                        per_page: 20,
                        total: 1,
                        from: 1,
                        to: 1,
                    },
                },
                filters: { per_page: 20, search: 'ORD' },
                listRoute: 'all_orders.index',
            },
        });
        expect(wrapper.text()).toContain('ORD-7');
        expect(wrapper.text()).toContain('Buyer');
        expect(
            wrapper.find('a[href="/temp/admin/orders/7/show"]').exists(),
        ).toBe(true);
        expect(wrapper.text()).not.toContain('Delete');
        expect(wrapper.find('input[type=checkbox]').exists()).toBe(false);
    });
    it('mounts details with safe text, permitted actions and terminal restrictions', async () => {
        const wrapper = mount(Show, {
            props: {
                order,
                additionalInfo: '<img onerror=bad()>',
                trackingCode: 'TRACK',
                shippingAddress: { address: 'Street' },
                couponDiscount: 0,
            },
        });
        expect(wrapper.text()).toContain('<script>unsafe()</script>');
        expect(wrapper.find('script').exists()).toBe(false);
        expect(wrapper.text()).toContain('30.3');
        expect(pageForms(wrapper)).toHaveLength(5);
        await wrapper.setProps({
            order: {
                ...order,
                editable: false,
                payment_status: 'paid',
                delivery_status: 'delivered',
            },
        });
        expect(wrapper.find('input[name=admin_price]').exists()).toBe(false);
        expect(wrapper.find('select#delivery_status').exists()).toBe(false);
    });
    it.each([
        ['all_orders.index', '/temp/admin/all_orders'],
        ['inhouse_orders.index', '/temp/admin/inhouse-orders'],
        ['seller_orders.index', '/temp/admin/seller_orders'],
        ['pick_up_point.index', '/temp/admin/orders_by_pickup_point'],
    ])(
        'keeps search and delivery status inline and the rest in the dialog for %s',
        (listRoute, url) => {
            const wrapper = mount(Index, {
                props: {
                    orders: paginated([order]),
                    filters: {
                        per_page: 50,
                        search: 'ORD',
                        delivery_status: 'delivered',
                        payment_status: 'paid',
                        from: '2026-01-01',
                    },
                    listRoute,
                },
            });
            const form = wrapper.getComponent(StoreListFilters);
            expect(form.props('action').url).toBe(url);
            const inline = wrapper.get('form#store-list-filters');
            expect(
                inline.get('input[name="per_page"]').attributes('value'),
            ).toBe('50');
            expect(inline.get('input[name="search"]').element).toHaveProperty(
                'value',
                'ORD',
            );
            expect(
                (
                    inline.get('select[name="delivery_status"]')
                        .element as HTMLSelectElement
                ).value,
            ).toBe('delivered');
            const dialog = wrapper.get('[role="dialog"]');
            expect(
                dialog
                    .findAll('select, input')
                    .map((control) => control.attributes('name')),
            ).toEqual(['payment_status', 'from', 'to']);
            expect(dialog.find('select[name="delivery_status"]').exists()).toBe(
                false,
            );
            expect(
                (
                    dialog.get('select[name="payment_status"]')
                        .element as HTMLSelectElement
                ).value,
            ).toBe('paid');
            // payment status and "from" are applied; "to" is not.
            expect(form.props('chips')).toEqual([
                {
                    key: 'payment_status',
                    label: t('store.orders.paymentStatus'),
                    value: t('store.orders.status.paid'),
                    keys: ['payment_status'],
                },
                {
                    key: 'from',
                    label: t('store.orders.from'),
                    value: '2026-01-01',
                    keys: ['from'],
                },
            ]);
            expect(
                wrapper
                    .get('form#store-list-filters > button[type="button"]')
                    .text(),
            ).toContain('2');
        },
    );
    it('requires any one order selection and marks the group without requiring every checkbox', async () => {
        const wrapper = mount(Index, {
            props: {
                orders: paginated([
                    order,
                    storeOrder({ id: 8, code: 'ORD-8' }),
                ]),
                filters: { per_page: 20 },
                listRoute: 'all_orders.index',
            },
        });
        await wrapper.vm.$nextTick();
        const form = pageForms(wrapper)[1];
        expect(form.findAll('[data-required-legend]')).toHaveLength(1);
        expect(form.find('legend [data-required-mark]').exists()).toBe(true);
        // A fieldset is as wide as its content by default; the table's own
        // wrapper must be what scrolls on a phone, not the page.
        expect(form.get('fieldset').classes()).toContain('min-w-0');
        expect(form.findAll('input[required]')).toHaveLength(1);
        expect((form.element as HTMLFormElement).checkValidity()).toBe(false);
        await form.findAll('input[type="checkbox"]')[1].setValue(true);
        expect(form.findAll('input[required]')).toHaveLength(0);
        expect((form.element as HTMLFormElement).checkValidity()).toBe(true);
        await form.findAll('input[type="checkbox"]')[1].setValue(false);
        expect(form.findAll('input[required]')).toHaveLength(1);
        expect(
            pageForms(wrapper)[0].find('[data-required-legend]').exists(),
        ).toBe(false);
    });
    it('marks required order actions with one legend per form and leaves notes and tracking optional', async () => {
        const wrapper = mount(Show, {
            props: {
                order,
                additionalInfo: '',
                trackingCode: '',
                shippingAddress: {},
                couponDiscount: 0,
            },
        });
        await wrapper.vm.$nextTick();
        const forms = pageForms(wrapper);

        for (const form of forms) {
            const required = form.find('[required]').exists();
            expect(form.findAll('[data-required-legend]')).toHaveLength(
                required ? 1 : 0,
            );
            expect(form.findAll('[data-required-mark]')).toHaveLength(
                required ? 1 : 0,
            );
        }

        for (const id of ['delivery', 'payment', 'price-1']) {
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
            expect(
                wrapper
                    .find(`label[for="${id}"] [data-required-mark]`)
                    .exists(),
            ).toBe(true);
        }

        for (const id of ['tracking_code', 'notes']) {
            expect(
                wrapper.get(`#${id}`).attributes('required'),
            ).toBeUndefined();
            expect(
                wrapper
                    .get(`label[for="${id}"]`)
                    .find('[data-required-mark]')
                    .exists(),
            ).toBe(false);
        }
    });

    it('groups the details into four cards with stacked labels and "-" for empty values', () => {
        const wrapper = mount(Show, {
            props: {
                order: storeOrder({
                    delivery_date: null,
                    manager_notes: null,
                }),
                additionalInfo: 'Line one\nLine two',
                trackingCode: null,
                shippingAddress: { address: 'Street 1', city: 'Town' },
                couponDiscount: 2.5,
            },
        });
        const cards = wrapper.findAll('section');
        const card = (title: string) =>
            cards.find((section) => section.find('h2').text() === title)!;
        const pairs = (title: string) =>
            card(title)
                .findAll('dl > div')
                .map((item) => [item.get('dt').text(), item.get('dd').text()]);

        expect(cards.map((section) => section.get('h2').text())).toEqual([
            t('store.orders.groups.order'),
            t('store.orders.groups.people'),
            t('store.orders.groups.money'),
            t('store.orders.groups.notes'),
        ]);
        expect(pairs(t('store.orders.groups.order'))).toEqual([
            [t('store.orders.tracking'), '-'],
            [t('store.orders.deliveryDate'), '-'],
            [t('store.orders.indicator'), '10%'],
        ]);
        expect(pairs(t('store.orders.groups.people'))).toEqual([
            [t('store.orders.customer'), 'Buyer'],
            [t('store.orders.representative'), '-'],
        ]);
        expect(pairs(t('store.orders.groups.money'))).toEqual([
            [t('store.orders.total'), '30.30'],
            [t('store.orders.couponDiscount'), '2.50'],
            [t('store.orders.discount'), '16.67%'],
            [t('store.orders.profits'), '2.00'],
        ]);
        expect(
            card(t('store.orders.groups.money'))
                .findAll('dd')
                .every((dd) => dd.classes().includes('tabular-nums')),
        ).toBe(true);
        expect(pairs(t('store.orders.groups.notes'))).toEqual([
            [t('store.orders.managerNotes'), '-'],
            [t('store.orders.notes'), 'Line one\nLine two'],
            [t('store.orders.address'), 'Street 1, Town'],
        ]);
        expect(
            card(t('store.orders.groups.notes')).findAll('dd')[1].classes(),
        ).toContain('whitespace-pre-wrap');
    });

    it('links the customer, representative and products to their edit pages by permission', () => {
        const withRep = {
            ...order,
            representative: {
                id: 4,
                name: 'Rep',
                rep_discount_percentage: null,
            },
        };
        const props = {
            order: withRep,
            additionalInfo: '',
            trackingCode: 'TRACK',
            shippingAddress: { address: 'Street' },
            couponDiscount: 0,
        };
        const hrefs = (wrapper: ReturnType<typeof mount>) =>
            wrapper.findAll('a').map((link) => link.attributes('href'));

        const admin = mount(Show, { props });
        expect(hrefs(admin)).toEqual(
            expect.arrayContaining([
                customerEdit(3).url,
                customerEdit(4).url,
                productEdit(5).url,
            ]),
        );

        mocks.page.mockReturnValue({
            url: '/temp/admin/orders/7/show',
            props: {
                locale: 'en',
                auth: { user: { id: 1, name: 'Staff' } },
                store: storeSharedProps({
                    permissions: ['view_order_details'],
                }),
                errors: {},
            },
        });
        const staff = mount(Show, { props });
        expect(hrefs(staff)).not.toContain(customerEdit(3).url);
        expect(hrefs(staff)).not.toContain(customerEdit(4).url);
        expect(hrefs(staff)).not.toContain(productEdit(5).url);
        expect(staff.text()).toContain('Buyer');
    });

    it('links the order list customer to their edit page by permission', () => {
        const props = {
            orders: paginated([order]),
            filters: { per_page: 20 },
            listRoute: 'all_orders.index',
        };
        const admin = mount(Index, { props });
        expect(
            admin.findAll('a').map((link) => link.attributes('href')),
        ).toContain(customerEdit(3).url);

        mocks.page.mockReturnValue({
            url: '/temp/admin/all_orders',
            props: {
                locale: 'en',
                auth: { user: { id: 1, name: 'Staff' } },
                store: storeSharedProps({
                    permissions: ['view_order_details'],
                }),
                errors: {},
            },
        });
        const staff = mount(Index, { props });
        expect(
            staff.findAll('a').map((link) => link.attributes('href')),
        ).not.toContain(customerEdit(3).url);
    });

    it('lets staff hide optional order columns and keeps the code and the select cell', async () => {
        window.localStorage.clear();
        const wrapper = mount(Index, {
            props: {
                orders: paginated([order]),
                filters: { per_page: 20 },
                listRoute: 'all_orders.index',
            },
        });
        expect(headerTexts(wrapper)).toEqual([
            t('store.orders.select'),
            t('store.orders.code'),
            t('store.orders.customer'),
            t('store.orders.total'),
            t('store.orders.deliveryStatus'),
            t('store.orders.paymentStatus'),
            t('store.orders.actions'),
        ]);
        expect(wrapper.get('tbody').text()).toContain('Buyer');
        await expectColumnCanBeHidden(
            wrapper,
            'sales.orders',
            { key: 'customer', label: t('store.orders.customer') },
            1,
        );
        expect(wrapper.get('tbody').text()).not.toContain('Buyer');
        expect(headerTexts(wrapper)).toContain(t('store.orders.code'));
        window.localStorage.clear();
    });
});
