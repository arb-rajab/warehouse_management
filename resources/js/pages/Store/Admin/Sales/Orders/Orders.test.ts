import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import StoreOrderEntryFields from '@/components/StoreOrderEntryFields.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { paginated, storeOrder, storeSharedProps } from '@/testing/factories';
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
            expect(wrapper.get('form').attributes('data-action-url')).toContain(
                representativeOrder
                    ? '/feat/order/store'
                    : '/feat/new/order/trip/store',
            );
            expect(
                wrapper.get('input[name=submission_key]').attributes('value'),
            ).toBe('token');
            expect(wrapper.get('#customer_id').attributes()).toHaveProperty(
                'required',
            );
            expect(wrapper.find('#rep_id').exists()).toBe(representativeOrder);
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
        expect(wrapper.getComponent(Form).props('action')).toMatchObject({
            method: 'put',
        });
        expect(wrapper.text()).toContain('20.00');
        expect(wrapper.text()).toContain('6.30');
        expect(wrapper.text()).toContain('Invalid price');
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
        expect(wrapper.findAllComponents(Form).length).toBe(5);
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
        const form = wrapper.findAll('form')[1];
        expect(form.findAll('[data-required-legend]')).toHaveLength(1);
        expect(form.find('legend [data-required-mark]').exists()).toBe(true);
        expect(form.findAll('input[required]')).toHaveLength(1);
        expect((form.element as HTMLFormElement).checkValidity()).toBe(false);
        await form.findAll('input[type="checkbox"]')[1].setValue(true);
        expect(form.findAll('input[required]')).toHaveLength(0);
        expect((form.element as HTMLFormElement).checkValidity()).toBe(true);
        await form.findAll('input[type="checkbox"]')[1].setValue(false);
        expect(form.findAll('input[required]')).toHaveLength(1);
        expect(
            wrapper.findAll('form')[0].find('[data-required-legend]').exists(),
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
        const forms = wrapper.findAll('form');

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
});
