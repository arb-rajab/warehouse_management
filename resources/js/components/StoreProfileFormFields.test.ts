import { mount } from '@vue/test-utils';
import { expect, it, vi } from 'vitest';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { t } from '@/lib/i18n';
import { storeSharedProps, storeUpload } from '@/testing/factories';
import { storeProfile } from '@/testing/factories';
import { createUseHttpStub } from '@/testing/inertiaStubs';
import StoreProfileFormFields from './StoreProfileFormFields.vue';

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
it('renders all profile fields with constraints, selected preview and password confirmation', async () => {
    usePageMock.mockReturnValue({ props: { store: storeSharedProps() } });
    useHttpMock.mockImplementation((data) => createUseHttpStub(data));
    const profile = storeProfile({ avatar: storeUpload() });
    const wrapper = mount(StoreProfileFormFields, {
        props: { profile, errors: { email: 'Taken' } },
        attachTo: document.body,
    });

    for (const key of [
        'name',
        'email',
        'newPassword',
        'confirmPassword',
        'avatar',
    ]) {
        expect(wrapper.text()).toContain(t(`store.profile.${key}`));
    }

    expect(wrapper.get('#name').attributes('required')).toBeDefined();
    expect(wrapper.get('#email').attributes('type')).toBe('email');
    expect(wrapper.get('#email').attributes('maxlength')).toBe('255');
    expect(wrapper.text()).toContain('Taken');
    expect(
        wrapper.getComponent(StoreUploadField).props('initialFiles'),
    ).toEqual([storeUpload()]);
    expect(wrapper.getComponent(StoreUploadField).props('modelValue')).toBe(
        '1',
    );
    await wrapper.get('#new_password').setValue('password123');
    expect(
        wrapper.get('#confirm_password').attributes('required'),
    ).toBeDefined();
    await wrapper.get('#confirm_password').setValue('wrong');
    expect(
        (wrapper.get('#confirm_password').element as HTMLInputElement)
            .validationMessage,
    ).toBe(t('users.fields.passwordMismatch'));
    wrapper.unmount();
});
