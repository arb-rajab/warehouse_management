import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { updateStatus } from '@/actions/App/Http/Controllers/Store/Admin/CountryController';
import { t } from '@/lib/i18n';
import { storeCountry } from '@/testing/factories';
import StoreStatusSwitch from './StoreStatusSwitch.vue';
const { formSlotPropsMock } = vi.hoisted(() => ({
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub } = await import('@/testing/inertiaStubs');

    return { Form: createFormStub(formSlotPropsMock) };
});
describe('StoreStatusSwitch', () => {
    beforeEach(() =>
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false }),
    );
    it.each([true, false])(
        'is a switch posting the inverse status for checked=%s',
        (checked) => {
            const action = updateStatus(storeCountry());
            const wrapper = mount(StoreStatusSwitch, {
                props: { action, checked, label: 'Netherlands' },
            });
            expect(wrapper.getComponent(Form).props()).toMatchObject({
                action,
                options: { preserveScroll: true },
            });
            expect(wrapper.get('input[name=status]').attributes('value')).toBe(
                checked ? '0' : '1',
            );
            const button = wrapper.get('button[role="switch"]');
            expect(button.attributes('type')).toBe('submit');
            expect(button.attributes('aria-checked')).toBe(String(checked));
            expect(button.attributes('aria-label')).toBe('Netherlands');
            expect(button.attributes('disabled')).toBeUndefined();
            expect(button.classes()).toContain('cursor-pointer');
            expect(button.classes()).toContain('focus-visible:ring-2');
            expect(button.text()).toBe(
                t(
                    checked
                        ? 'store.reference.enabled'
                        : 'store.reference.disabled',
                ),
            );
            expect(wrapper.get('[data-switch-knob]').classes()).toContain(
                checked ? 'start-[1.375rem]' : 'start-0.5',
            );
            expect(wrapper.get('[data-switch-track]').classes()).toContain(
                checked ? 'bg-green-600' : 'bg-gray-500',
            );
        },
    );
    it.each(['approved', 'value'] as const)(
        'posts the inverse under the typed field name %s',
        (inputName) => {
            const wrapper = mount(StoreStatusSwitch, {
                props: {
                    action: updateStatus(storeCountry()),
                    checked: false,
                    label: 'Approved',
                    inputName,
                },
            });
            expect(
                wrapper.get(`input[name=${inputName}]`).attributes('value'),
            ).toBe('1');
            expect(wrapper.find('input[name=status]').exists()).toBe(false);
        },
    );
    it('shows the caller state labels', () => {
        const wrapper = mount(StoreStatusSwitch, {
            props: {
                action: updateStatus(storeCountry()),
                checked: true,
                label: 'Post',
                stateLabels: { on: 'Published', off: 'Hidden' },
            },
        });
        expect(wrapper.get('button').text()).toBe('Published');
        expect(wrapper.find('[data-switch-knob] svg').exists()).toBe(true);
    });
    it('submits caller fields and forwards the before hook', () => {
        const onBefore = () => true;
        const wrapper = mount(StoreStatusSwitch, {
            props: {
                action: updateStatus(storeCountry()),
                checked: false,
                label: 'Featured',
                onBefore,
            },
            slots: { default: '<input type="hidden" name="id" value="17" />' },
        });
        expect(wrapper.get('form input[name=id]').attributes('value')).toBe(
            '17',
        );
        expect(wrapper.getComponent(Form).props('onBefore')).toBe(onBefore);
    });
    it('marks a submitting switch busy and forwards server errors', () => {
        formSlotPropsMock.mockReturnValue({
            errors: { status: 'Invalid status' },
            processing: true,
        });
        const wrapper = mount(StoreStatusSwitch, {
            props: {
                action: updateStatus(storeCountry()),
                checked: true,
                label: 'Country',
            },
        });
        const button = wrapper.get('button[role="switch"]');
        expect(button.attributes('disabled')).toBeDefined();
        expect(button.attributes('aria-busy')).toBe('true');
        expect(button.classes()).toContain('disabled:cursor-wait');
        expect(button.text()).toBe(t('store.reference.saving'));
        expect(wrapper.find('[data-switch-knob] .animate-spin').exists()).toBe(
            true,
        );
        expect(wrapper.text()).toContain('Invalid status');
    });
    it('shows a locked switch with its reason and no form', () => {
        const wrapper = mount(StoreStatusSwitch, {
            props: {
                action: updateStatus(storeCountry()),
                checked: true,
                label: 'Euro',
                locked: {
                    reason: 'System default currency',
                    hint: 'Pick another default first',
                },
            },
        });
        expect(wrapper.findComponent(Form).exists()).toBe(false);
        expect(wrapper.find('input').exists()).toBe(false);
        const button = wrapper.get('button[role="switch"]');
        expect(button.attributes()).toMatchObject({
            type: 'button',
            'aria-checked': 'true',
            'aria-disabled': 'true',
            'aria-label': 'Euro',
            title: 'Pick another default first',
        });
        expect(button.classes()).toContain('cursor-not-allowed');
        expect(button.text()).toBe(t('store.reference.enabled'));
        const reason = wrapper.get(`#${button.attributes('aria-describedby')}`);
        expect(reason.text()).toBe('System default currency');
        expect(wrapper.get('[data-switch-track]').classes()).toContain(
            'bg-green-600/40',
        );
    });
    it('uses the reason as the tooltip without a hint', () => {
        const wrapper = mount(StoreStatusSwitch, {
            props: {
                action: updateStatus(storeCountry()),
                checked: false,
                label: 'Language',
                locked: { reason: 'Default language' },
            },
        });
        const button = wrapper.get('button[role="switch"]');
        expect(button.attributes('title')).toBe('Default language');
        expect(button.attributes('aria-checked')).toBe('false');
        expect(button.text()).toBe(t('store.reference.disabled'));
    });
});
