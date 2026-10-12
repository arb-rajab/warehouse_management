import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { updateStatus } from '@/actions/App/Http/Controllers/Store/Admin/CountryController';
import SubmitButton from '@/components/SubmitButton.vue';
import { t } from '@/lib/i18n';
import { storeCountry } from '@/testing/factories';
import StoreStatusButton from './StoreStatusButton.vue';
const { formSlotPropsMock } = vi.hoisted(() => ({
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub } = await import('@/testing/inertiaStubs');

    return { Form: createFormStub(formSlotPropsMock) };
});
describe('StoreStatusButton', () => {
    beforeEach(() =>
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false }),
    );
    it.each([true, false])(
        'posts the desired inverse status for active=%s',
        (active) => {
            const action = updateStatus(storeCountry());
            const wrapper = mount(StoreStatusButton, {
                props: { action, active, label: 'Netherlands' },
            });
            expect(wrapper.getComponent(Form).props()).toMatchObject({
                action,
                options: { preserveScroll: true },
            });
            expect(wrapper.get('input[name=status]').attributes('value')).toBe(
                active ? '0' : '1',
            );
            expect(wrapper.getComponent(SubmitButton).props('label')).toBe(
                t(
                    active
                        ? 'store.reference.disable'
                        : 'store.reference.enable',
                    { name: 'Netherlands' },
                ),
            );
        },
    );
    it('submits caller fields inside the shared status form', () => {
        const wrapper = mount(StoreStatusButton, {
            props: {
                action: updateStatus(storeCountry()),
                active: false,
                label: 'Featured',
            },
            slots: { default: '<input type="hidden" name="id" value="17" />' },
        });
        expect(wrapper.get('form input[name=id]').attributes('value')).toBe(
            '17',
        );
    });
    it('forwards processing and server errors', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { status: 'Invalid status' },
            processing: true,
        });
        const wrapper = mount(StoreStatusButton, {
            props: {
                action: updateStatus(storeCountry()),
                active: true,
                label: 'Country',
            },
        });
        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        expect(wrapper.text()).toContain('Invalid status');
    });
    it('offers no disabling action for a locked default currency', () => {
        const wrapper = mount(StoreStatusButton, {
            props: {
                action: updateStatus(storeCountry()),
                active: true,
                label: 'Euro',
                locked: true,
            },
        });
        expect(wrapper.findComponent(Form).exists()).toBe(false);
        expect(wrapper.text()).toBe(t('store.currencies.defaultCurrency'));
    });
});
