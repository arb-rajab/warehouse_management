import { Form, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import {
    create as rolesCreate,
    index as rolesIndex,
    store as rolesStore,
} from '@/actions/App/Http/Controllers/Store/Admin/RoleController';
import RequiredLegend from '@/components/RequiredLegend.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreRoleFormFields from '@/components/StoreRoleFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { permissionGroup, storeSharedProps } from '@/testing/factories';
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

const groups = [permissionGroup()];

function mountPage() {
    usePageMock.mockReturnValue({
        url: rolesCreate.url(),
        props: defaultAuthProps({ store: storeSharedProps() }),
    });

    return mount(Create, { props: { permissionGroups: groups } });
}

describe('Store roles Create', () => {
    beforeEach(() => {
        resetMocks({ formSlotPropsMock, usePageMock, routerPostMock });
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });

    it('renders a full-width form page in the store layout, with its labels', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.roles.create.title'),
            submitLabel: t('store.roles.create.submit'),
            submittingLabel: t('store.roles.create.submitting'),
            layout: StoreAdminLayout,
            wide: true,
        });
        expect(wrapper.get('h1').text()).toBe(t('store.roles.create.title'));
        expect(wrapper.getComponent(SubmitButton).text()).toBe(
            t('store.roles.create.submit'),
        );
        expect(t('store.roles.create.title')).toBe('Add role');
    });

    it('submits to the role-creation route and cancels back to the list', () => {
        const wrapper = mountPage();

        expect(wrapper.getComponent(Form).props('action')).toEqual(
            rolesStore(),
        );
        expect(
            wrapper
                .findAllComponents(Link)
                .find((link) => link.text() === t('common.cancel'))
                ?.props('href'),
        ).toEqual(rolesIndex());
    });

    it('offers every permission group, none checked, and forwards errors', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { name: 'The name has already been taken.' },
            processing: false,
        });

        expect(mountPage().getComponent(StoreRoleFormFields).props()).toEqual({
            permissionGroups: groups,
            name: '',
            checkedPermissionIds: [],
            errors: { name: 'The name has already been taken.' },
        });
    });

    it('explains the required name with the legend', async () => {
        const wrapper = mountPage();
        await nextTick();

        expect(wrapper.findComponent(RequiredLegend).exists()).toBe(true);
        expect(wrapper.findAllComponents(RequiredMark)).toHaveLength(1);
    });
});
