import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    create as staffCreate,
    index as staffIndex,
    store as staffStore,
} from '@/actions/App/Http/Controllers/Store/Admin/StaffController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreStaffFormFields from '@/components/StoreStaffFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeRole, storeSharedProps } from '@/testing/factories';
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

const roles = [storeRole({ id: 3, name: 'Clerk' })];

function mountPage() {
    usePageMock.mockReturnValue({
        url: staffCreate.url(),
        props: defaultAuthProps({ store: storeSharedProps() }),
    });

    return mount(Create, { props: { roles } });
}

describe('Store staff Create', () => {
    beforeEach(() => {
        resetMocks({ formSlotPropsMock, usePageMock, routerPostMock });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });

    it('renders the form page in the store layout, with its title and labels', () => {
        const wrapper = mountPage();
        const page = wrapper.getComponent(ResourceFormPage);

        expect(page.props()).toMatchObject({
            title: t('store.staff.create.title'),
            submitLabel: t('store.staff.create.submit'),
            submittingLabel: t('store.staff.create.submitting'),
            layout: StoreAdminLayout,
        });
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.get('h1').text()).toBe(t('store.staff.create.title'));
        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.staff.create.submit'),
        );
        expect(t('store.staff.create.title')).toBe('Add staff member');
    });

    it('submits to the staff-creation route and cancels back to the list', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Form).props('action')).toEqual(
            staffStore(),
        );
        expect(
            wrapper
                .findAllComponents(Link)
                .find((link) => link.text() === t('common.cancel'))
                ?.props('href'),
        ).toEqual(staffIndex());
    });

    it('offers the roles and requires a password', () => {
        const fields = mountPage().getComponent(StoreStaffFormFields);

        expect(fields.props('roles')).toEqual(roles);
        expect(fields.props('passwordRequired')).toBe(true);
    });

    it('forwards validation errors to the fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { email: 'The email has already been taken.' },
            processing: false,
        });

        expect(
            mountPage().getComponent(StoreStaffFormFields).props('errors'),
        ).toEqual({ email: 'The email has already been taken.' });
    });
});
