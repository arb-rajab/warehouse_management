import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create as warehousesCreate,
    index as warehousesIndex,
} from '@/actions/App/Http/Controllers/Admin/WarehouseController';
import SubmitButton from '@/components/SubmitButton.vue';
import WarehouseFormFields from '@/components/WarehouseFormFields.vue';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Create from './Create.vue';

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

function mountPage(attachTo?: Element) {
    usePageMock.mockReturnValue({
        url: warehousesCreate.url(),
        props: defaultAuthProps(),
    });

    return mount(Create, { attachTo });
}

describe('Warehouses Create', () => {
    beforeEach(() => {
        resetMocks({ formSlotPropsMock, usePageMock, routerPostMock });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });

    it('renders the page title', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('warehouses.create.title'));
    });

    it('submits the form to the warehouse-creation route', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form').attributes('data-action-url')).toBe(
            warehousesIndex.url(),
        );
    });

    it('shows the required-fields legend because the name is required', async () => {
        const wrapper = mountPage();
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain(t('common.requiredLegend'));
    });

    it('forwards validation errors to the warehouse form fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { name: 'The name has already been taken.' },
            processing: false,
        });

        const wrapper = mountPage();

        expect(
            wrapper.findComponent(WarehouseFormFields).props('errors'),
        ).toEqual({ name: 'The name has already been taken.' });
    });

    it("checks the form in the browser first: an empty name is held back with the server's wording", async () => {
        const wrapper = mountPage(document.body);
        const onBefore = wrapper
            .getComponent(Form)
            .props('onBefore') as () => boolean;

        expect(wrapper.get('form').attributes('novalidate')).toBeDefined();

        await wrapper.get('form').trigger('submit');

        expect(onBefore()).toBe(false);
        expect(wrapper.get('[role="alert"]').text()).toBe(
            t('common.formValidation.blocked'),
        );
        expect(
            wrapper.get('#name').element.parentElement?.textContent,
        ).toContain('The name field is required.');
        expect(document.activeElement).toBe(wrapper.get('#name').element);

        await wrapper.get('#name').setValue('Annex');

        expect(onBefore()).toBe(true);
        wrapper.unmount();
    });

    it('holds back a code longer than the server allows, and lets the optional fields stay blank', async () => {
        const wrapper = mountPage(document.body);
        const onBefore = wrapper
            .getComponent(Form)
            .props('onBefore') as () => boolean;

        await wrapper.get('#name').setValue('Annex');
        expect(onBefore()).toBe(true);

        await wrapper.get('#code').setValue('X'.repeat(21));
        expect(onBefore()).toBe(false);
        await wrapper.vm.$nextTick();
        expect(
            wrapper.get('#code').element.parentElement?.textContent,
        ).toContain('The code field must not be greater than 20 characters.');
        wrapper.unmount();
    });

    it('shows the submitting label and disables the button while processing', () => {
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: true });

        const wrapper = mountPage();

        expect(wrapper.findComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        expect(wrapper.get('form button').text()).toBe(
            t('warehouses.create.submitting'),
        );
        expect(wrapper.get('form button').attributes('disabled')).toBeDefined();
    });

    it('shows the default submit label while not processing', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form button').text()).toBe(
            t('warehouses.create.submit'),
        );
    });

    it('links the cancel action back to the warehouse list', () => {
        const wrapper = mountPage();

        expect(wrapper.get('form a').attributes('href')).toBe(
            warehousesIndex.url(),
        );
    });
});
