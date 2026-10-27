import { mount } from '@vue/test-utils';
import { afterEach, describe, expect, it } from 'vitest';
import FormField from '@/components/FormField.vue';
import { i18n, t } from '@/lib/i18n';
import { storeDriver } from '@/testing/factories';
import StoreDriverFormFields from './StoreDriverFormFields.vue';

const countries = [
    { id: 1, name: 'Netherlands' },
    { id: 2, name: 'Belgium' },
];

function mountFields(
    props: Record<string, unknown> = {},
    attachTo?: HTMLElement,
) {
    return mount(StoreDriverFormFields, {
        props: { countries, errors: {}, ...props },
        attachTo,
    });
}

describe('StoreDriverFormFields', () => {
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });
    it.each(['en', 'ar'] as const)(
        'renders every translated create label and placeholder in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mountFields();

            for (const key of [
                'name',
                'email',
                'phone',
                'country',
                'password',
                'confirmPassword',
                'countryPlaceholder',
            ]) {
                expect(wrapper.text()).toContain(
                    t(`store.drivers.fields.${key}`),
                );
            }
        },
    );

    it('mirrors required fields database widths email type and password rules', () => {
        const wrapper = mountFields();

        for (const [id, max] of [
            ['name', '191'],
            ['email', '191'],
            ['phone', '20'],
            ['password', '255'],
            ['password_confirmation', '255'],
        ]) {
            expect(wrapper.get(`#${id}`).attributes('maxlength')).toBe(max);
            expect(wrapper.get(`#${id}`).attributes('required')).toBeDefined();
        }

        expect(wrapper.get('#country').attributes('required')).toBeDefined();
        expect(wrapper.get('#email').attributes('type')).toBe('email');
        expect(wrapper.get('#phone').attributes('type')).toBe('tel');
        expect(wrapper.get('#password').attributes('minlength')).toBe('6');
        expect(
            wrapper.get('#password_confirmation').attributes('minlength'),
        ).toBe('6');
        expect(wrapper.get('#password').attributes('autocomplete')).toBe(
            'new-password',
        );
    });

    it('offers countries by name and asks for one on creation', () => {
        const wrapper = mountFields();
        expect(
            wrapper.findAll('option').map((option) => option.text()),
        ).toEqual([
            t('store.drivers.fields.countryPlaceholder'),
            'Netherlands',
            'Belgium',
        ]);
        expect(
            (wrapper.get('#country').element as HTMLSelectElement).value,
        ).toBe('');
    });

    it('prefills contact data, locks email and leaves the new password optional on edit', () => {
        const driver = storeDriver();
        const wrapper = mountFields({ ...driver, editing: true });

        for (const id of ['name', 'email', 'phone']) {
            expect(
                (wrapper.get(`#${id}`).element as HTMLInputElement).value,
            ).toBe(driver[id as 'name' | 'email' | 'phone']);
        }

        expect(
            (wrapper.get('#country').element as HTMLSelectElement).value,
        ).toBe('Netherlands');
        expect(wrapper.get('#email').attributes('readonly')).toBeDefined();
        expect(wrapper.get('#password').attributes('required')).toBeUndefined();
        expect(
            wrapper.get('#password_confirmation').attributes('required'),
        ).toBeUndefined();
        expect(wrapper.text()).toContain(t('store.drivers.fields.newPassword'));
        expect(wrapper.get('#password').attributes('placeholder')).toBe(
            t('store.drivers.fields.passwordPlaceholder'),
        );
    });

    it('keeps an existing country even if it is no longer active', () => {
        const wrapper = mountFields({
            country: 'Historical Country',
            editing: true,
        });
        expect(
            (wrapper.get('#country').element as HTMLSelectElement).value,
        ).toBe('Historical Country');
    });

    it('renders each field error', () => {
        const errors = {
            name: 'Required name',
            email: 'Taken email',
            phone: 'Required phone',
            country: 'Required country',
            password: 'Too short',
        };
        const wrapper = mountFields({ errors });

        for (const message of Object.values(errors)) {
            expect(wrapper.text()).toContain(message);
        }

        expect(
            wrapper
                .findAllComponents(FormField)
                .find((field) => field.props('id') === 'password')
                ?.props('error'),
        ).toBe('Too short');
    });

    it('flags a mismatched confirmation and clears it when corrected', async () => {
        const wrapper = mountFields({}, document.body);
        await wrapper.get('#password').setValue('new-password');
        await wrapper
            .get('#password_confirmation')
            .setValue('different-password');
        expect(
            (wrapper.get('#password_confirmation').element as HTMLInputElement)
                .validationMessage,
        ).toBe(t('users.fields.passwordMismatch'));
        await wrapper.get('#password_confirmation').setValue('new-password');
        expect(
            (wrapper.get('#password_confirmation').element as HTMLInputElement)
                .validationMessage,
        ).toBe('');
        wrapper.unmount();
    });
    it.each([false, true])(
        'marks exactly the server-required driver fields editing=%s',
        (editing) => {
            const wrapper = mountFields({ editing });

            for (const id of [
                'name',
                'email',
                'phone',
                'country',
                'password',
                'password_confirmation',
            ]) {
                const required =
                    !editing || ['name', 'phone', 'country'].includes(id);
                expect(
                    wrapper.get(`#${id}`).attributes('required') !== undefined,
                ).toBe(required);
                expect(
                    wrapper
                        .get(`label[for="${id}"]`)
                        .find('[data-required-mark]')
                        .exists(),
                ).toBe(required);
            }
        },
    );
});
