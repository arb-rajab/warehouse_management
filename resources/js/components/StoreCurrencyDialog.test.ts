import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import {
    store,
    update,
} from '@/actions/App/Http/Controllers/Store/Admin/CurrencyController';
import FilterDialog from '@/components/FilterDialog.vue';
import StoreCurrencyFormFields from '@/components/StoreCurrencyFormFields.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import { t } from '@/lib/i18n';
import { storeCurrency } from '@/testing/factories';
import StoreCurrencyDialog from './StoreCurrencyDialog.vue';
const { submitMock, stateMock } = vi.hoisted(() => ({
    submitMock: vi.fn(),
    stateMock: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createUseFormStub } = await import('@/testing/inertiaStubs');

    return { useForm: createUseFormStub(submitMock, stateMock) };
});
function mountDialog(
    currency = null as ReturnType<typeof storeCurrency> | null,
) {
    return mount(StoreCurrencyDialog, { props: { open: true, currency } });
}
describe('StoreCurrencyDialog', () => {
    beforeEach(() => {
        submitMock.mockReset();
        stateMock.mockReturnValue({ errors: {}, processing: false });
    });
    afterEach(() => vi.restoreAllMocks());
    it('uses the shared dialog and its external footer submit button', () => {
        const wrapper = mountDialog();
        expect(wrapper.getComponent(FilterDialog).props('title')).toBe(
            t('store.currencies.add'),
        );
        expect(wrapper.getComponent(SubmitButton).attributes('form')).toBe(
            'currency-form',
        );
        expect(
            wrapper
                .getComponent(SubmitButton)
                .element.closest('.overflow-y-auto'),
        ).toBeNull();
        wrapper.unmount();
    });
    it('posts new currency data and closes only after success', async () => {
        const wrapper = mountDialog();

        for (const [id, value] of Object.entries({
            name: 'Dirham',
            symbol: 'د.إ',
            code: 'AED',
            exchange_rate: '3.67',
        })) {
            await wrapper.get(`#${id}`).setValue(value);
        }

        await wrapper.get('form').trigger('submit');
        expect(submitMock).toHaveBeenCalledWith(
            store(),
            expect.objectContaining({
                name: 'Dirham',
                symbol: 'د.إ',
                code: 'AED',
                exchange_rate: '3.67',
            }),
            expect.any(Object),
        );
        expect(wrapper.emitted('update:open')).toBeUndefined();
        const options = submitMock.mock.calls[0][2] as {
            onSuccess: () => void;
        };
        options.onSuccess();
        expect(wrapper.emitted('update:open')).toEqual([[false]]);
        wrapper.unmount();
    });
    it('fills and updates the bound currency while retaining input on validation errors', async () => {
        stateMock.mockReturnValue({
            errors: { code: 'Code invalid' },
            processing: false,
        });
        const wrapper = mountDialog(storeCurrency());
        expect(wrapper.getComponent(FilterDialog).props('title')).toBe(
            t('store.currencies.edit'),
        );
        expect(wrapper.get('#name').element).toHaveProperty('value', 'Euro');
        await wrapper.get('#name').setValue('Edited Euro');
        await wrapper.get('form').trigger('submit');
        expect(submitMock).toHaveBeenCalledWith(
            update(storeCurrency()),
            expect.objectContaining({ name: 'Edited Euro', code: 'EUR' }),
            expect.any(Object),
        );
        expect(wrapper.get('#name').element).toHaveProperty(
            'value',
            'Edited Euro',
        );
        expect(wrapper.text()).toContain('Code invalid');
        expect(wrapper.emitted('update:open')).toBeUndefined();
        wrapper.unmount();
    });
    it('disables the footer during a request', () => {
        stateMock.mockReturnValue({ errors: {}, processing: true });
        const wrapper = mountDialog();
        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
        wrapper.unmount();
    });
    it('resets to a different currency and to an empty create form when reopened', async () => {
        const wrapper = mountDialog(storeCurrency());
        await wrapper.setProps({ open: false });
        await wrapper.setProps({
            open: true,
            currency: storeCurrency({ id: 22, name: 'Dirham', code: 'AED' }),
        });
        expect(wrapper.get('#name').element).toHaveProperty('value', 'Dirham');
        await wrapper.setProps({ open: false });
        await wrapper.setProps({ open: true, currency: null });
        expect(wrapper.get('#name').element).toHaveProperty('value', '');
        expect(
            wrapper.getComponent(StoreCurrencyFormFields).props('errors'),
        ).toEqual({});
        wrapper.unmount();
    });
});
