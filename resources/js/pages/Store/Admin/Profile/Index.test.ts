import { mount } from '@vue/test-utils';
import { expect, it, vi } from 'vitest';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/ProfileController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import StoreProfileFormFields from '@/components/StoreProfileFormFields.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { storeProfile } from '@/testing/factories';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import Page from './Index.vue';

const { usePageMock, useHttpMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub } =
        await import('@/testing/inertiaStubs');

    return {
        usePage: usePageMock,
        useHttp: useHttpMock,
        Link: createLinkStub(),
        Head: { template: '<span />' },
        Form: createFormStub(() => ({ errors: {}, processing: false })),
        router: { post: vi.fn() },
    };
});
it('sends only the profile form to its typed own-account action', () => {
    usePageMock.mockReturnValue({
        url: '/temp/admin/profile',
        props: { auth: { user: null }, store: storeSharedProps() },
    });
    useHttpMock.mockImplementation((data) => createUseHttpStub(data));
    const profile = storeProfile();
    const wrapper = mount(Page, { props: { profile } });
    const form = wrapper.getComponent(ResourceFormPage);
    expect(form.props('action')).toEqual(update(profile));
    expect(form.props('layout')).toBe(StoreAdminLayout);
    expect(form.props('title')).toBe(t('store.profile.title'));
    expect(wrapper.text()).toContain(t('store.profile.save'));
    expect(form.props('submittingLabel')).toBe(t('store.profile.saving'));
    expect(
        wrapper.getComponent(StoreProfileFormFields).props('profile'),
    ).toEqual(profile);
});
