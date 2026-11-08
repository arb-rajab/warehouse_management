import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    edit as warehouseEdit,
    update as warehouseUpdate,
    index as warehousesIndex,
} from '@/actions/App/Http/Controllers/Admin/WarehouseController';
import SubmitButton from '@/components/SubmitButton.vue';
import WarehouseFormFields from '@/components/WarehouseFormFields.vue';
import { t } from '@/lib/i18n';
import { warehouse } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { Warehouse } from '@/types/admin';
import Edit from './Edit.vue';

const { formSlotPropsMock, usePageMock, routerPostMock } = vi.hoisted(() => ({
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: routerPostMock },
    };
});

function mountPage(overrides: Partial<Warehouse> = {}) {
    usePageMock.mockReturnValue({
        url: warehouseEdit.url({ warehouse: 1 }),
        props: defaultAuthProps(),
    });

    return mount(Edit, { props: { warehouse: warehouse(overrides) } });
}

describe('Warehouses Edit', () => {
    beforeEach(() => {
        resetMocks({ formSlotPropsMock, usePageMock, routerPostMock });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });

    it('renders the page title with the warehouse name', () => {
        const wrapper = mountPage({ name: 'Annex' });

        expect(wrapper.text()).toContain(
            t('warehouses.edit.title', { name: 'Annex' }),
        );
    });

    it("submits the form to that warehouse's update route", () => {
        const wrapper = mountPage({ id: 4 });

        expect(wrapper.get('form').attributes('data-action-url')).toBe(
            warehouseUpdate.url({ warehouse: 4 }),
        );
    });

    it('passes the warehouse values to the form fields', () => {
        const wrapper = mountPage({
            name: 'Annex',
            code: null,
            address: '2 Quay Street',
        });

        expect(
            wrapper.findComponent(WarehouseFormFields).props(),
        ).toMatchObject({
            name: 'Annex',
            code: null,
            address: '2 Quay Street',
        });
    });

    it('forwards validation errors to the form fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { code: 'The code has already been taken.' },
            processing: false,
        });

        const wrapper = mountPage();

        expect(
            wrapper.findComponent(WarehouseFormFields).props('errors'),
        ).toEqual({ code: 'The code has already been taken.' });
    });

    it('checks the form in the browser first, holding back a blanked name', async () => {
        const wrapper = mountPage();
        const onBefore = wrapper
            .getComponent(Form)
            .props('onBefore') as () => boolean;

        expect(onBefore()).toBe(true);

        await wrapper.get('#name').setValue('');

        expect(onBefore()).toBe(false);
        await wrapper.vm.$nextTick();
        expect(wrapper.text()).toContain('The name field is required.');
    });

    it('shows the submitting label and disables the button while processing', () => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });

        const wrapper = mountPage();

        expect(wrapper.findComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        expect(wrapper.get('form button').text()).toBe(
            t('warehouses.edit.submitting'),
        );
    });

    it('shows the default submit label while not processing', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form button').text()).toBe(
            t('warehouses.edit.submit'),
        );
    });

    it('links the cancel action back to the warehouse list', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form a').attributes('href')).toBe(
            warehousesIndex.url(),
        );
    });
});
