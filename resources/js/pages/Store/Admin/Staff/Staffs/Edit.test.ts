import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import {
    edit as staffEdit,
    index as staffIndex,
    update as staffUpdate,
} from '@/actions/App/Http/Controllers/Store/Admin/StaffController';
import RequiredLegend from '@/components/RequiredLegend.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreStaffFormFields from '@/components/StoreStaffFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { staffMember, storeRole, storeSharedProps } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
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

const member = staffMember();
const roles = [storeRole({ id: 3, name: 'Clerk' })];

function mountPage(staff = member) {
    usePageMock.mockReturnValue({
        url: staffEdit.url(staff),
        props: defaultAuthProps({ store: storeSharedProps() }),
    });

    return mount(Edit, { props: { staffMember: staff, roles } });
}

describe('Store staff Edit', () => {
    beforeEach(() => {
        resetMocks({ formSlotPropsMock, usePageMock, routerPostMock });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });

    it("renders the form page in the store layout, titled with the member's name", () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.staff.edit.title', { name: 'Sam Staff' }),
            submitLabel: t('store.staff.edit.submit'),
            submittingLabel: t('store.staff.edit.submitting'),
            layout: StoreAdminLayout,
        });
        expect(wrapper.get('h1').text()).toBe('Edit Sam Staff');
        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.staff.edit.submit'),
        );
    });

    it('submits to the member’s update route and cancels back to the list', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Form).props('action')).toEqual(
            staffUpdate(member),
        );
        expect(
            wrapper
                .findAllComponents(Link)
                .find((link) => link.text() === t('common.cancel'))
                ?.props('href'),
        ).toEqual(staffIndex());
    });

    it('fills the fields in from the member, with the password optional', () => {
        expect(
            mountPage().getComponent(StoreStaffFormFields).props(),
        ).toMatchObject({
            roles,
            name: 'Sam Staff',
            email: 'sam@example.com',
            phone: '0612345678',
            roleId: 3,
            passwordRequired: false,
        });
    });

    it('leaves the role unchosen for a member without one', () => {
        expect(
            mountPage(staffMember({ role: null }))
                .getComponent(StoreStaffFormFields)
                .props('roleId'),
        ).toBeNull();
    });

    it('forwards validation errors to the fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { role_id: 'The selected role id is invalid.' },
            processing: false,
        });

        expect(
            mountPage().getComponent(StoreStaffFormFields).props('errors'),
        ).toEqual({ role_id: 'The selected role id is invalid.' });
    });

    it('explains the required marks with the legend, the password unmarked', async () => {
        const wrapper = mountPage();
        await nextTick();

        expect(wrapper.findComponent(RequiredLegend).exists()).toBe(true);
        expect(wrapper.findAllComponents(RequiredMark)).toHaveLength(4);
    });
});
