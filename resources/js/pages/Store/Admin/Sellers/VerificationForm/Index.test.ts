import { Form } from '@inertiajs/vue3';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/SellerVerificationFormController';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    storeSellerVerificationFields,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import Index from './Index.vue';

enableAutoUnmount(afterEach);

const { usePageMock, slotProps } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    slotProps: vi.fn(() => ({
        errors: {} as Record<string, string>,
        processing: false,
    })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(slotProps),
        Link: createLinkStub(),
        Head: headStub,
        usePage: usePageMock,
        router: { post: vi.fn() },
    };
});
function mountPage(fields = storeSellerVerificationFields()) {
    return mount(Index, { props: { fields } });
}
beforeEach(() => {
    usePageMock.mockReturnValue({
        url: '/temp/admin/verification/form',
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: ['seller_verification_form_configuration'],
            }),
        }),
    });
    slotProps.mockReturnValue({ errors: {}, processing: false });
});
afterEach(() => {
    i18n.global.locale.value = 'en';
});
it.each(['en', 'ar'] as const)(
    'fully mounts the ordered builder, labels, choices and constraints in %s',
    (locale) => {
        i18n.global.locale.value = locale;
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(ResourceFormPage).props()).toMatchObject({
            title: t('store.sellers.verificationFormTitle'),
            action: update(),
            layout: StoreAdminLayout,
            wide: true,
            submitLabel: t('store.sellers.save'),
            submittingLabel: t('store.sellers.saving'),
        });
        expect(
            wrapper
                .getComponent(ResourceFormPage)
                .getComponent(Form)
                .props('action'),
        ).toEqual(update());
        expect(wrapper.findAll('fieldset')).toHaveLength(2);
        expect(
            wrapper
                .findAll('fieldset')[0]
                .findAll('button')[0]
                .attributes('disabled'),
        ).toBeDefined();
        expect(
            wrapper
                .findAll('fieldset')[1]
                .findAll('button')[1]
                .attributes('disabled'),
        ).toBeDefined();
        const inputs = wrapper.findAll('main input');
        expect(
            inputs.every(
                (input) =>
                    input.attributes('required') !== undefined &&
                    input.attributes('maxlength') === '255',
            ),
        ).toBe(true);
        expect(wrapper.get('select').attributes('required')).toBeDefined();

        for (const type of [
            'text',
            'file',
            'select',
            'multi_select',
            'radio',
        ]) {
            expect(wrapper.text()).toContain(
                t(`store.sellers.fieldTypes.${type}`),
            );
        }

        for (const key of [
            'verificationFormTitle',
            'moveUp',
            'moveDown',
            'remove',
            'addField',
            'addOption',
            'save',
        ]) {
            expect(wrapper.text()).toContain(t(`store.sellers.${key}`));
        }

        for (const number of [1, 2]) {
            expect(wrapper.text()).toContain(
                t('store.sellers.formField', { number }),
            );
            expect(wrapper.text()).toContain(
                t('store.sellers.fieldType', { number }),
            );
            expect(wrapper.text()).toContain(
                t('store.sellers.fieldLabel', { number }),
            );
        }

        expect(wrapper.text()).toContain(
            t('store.sellers.fieldOption', { number: 2, option: 1 }),
        );
        wrapper.unmount();
    },
);
it('adds reorders removes and submits only the edited ordered legacy fields', async () => {
    const wrapper = mountPage();
    const transform = wrapper
        .getComponent(ResourceFormPage)
        .getComponent(Form)
        .props('transform')!;
    await wrapper.get('button[aria-label="Move field 2 up"]').trigger('click');
    expect(transform({})).toEqual({
        fields: [
            storeSellerVerificationFields()[1],
            storeSellerVerificationFields()[0],
        ],
    });
    await wrapper
        .get('button[aria-label="Move field 1 down"]')
        .trigger('click');
    await wrapper.get('button[aria-label="Remove field 1"]').trigger('click');
    await wrapper
        .findAll('button')
        .find((button) => button.text().includes(t('store.sellers.addField')))!
        .trigger('click');
    const field = wrapper.findAll('fieldset')[1];
    await field.get('select').setValue('radio');
    await field.get('input[id^="label-"]').setValue('Registered');
    await field.get('input[id^="option-"]').setValue('Yes');
    await field
        .get('button[aria-label="Add option to field 2"]')
        .trigger('click');
    await field.findAll('input[id^="option-"]')[1].setValue('No');
    expect(transform({})).toEqual({
        fields: [
            storeSellerVerificationFields()[1],
            { type: 'radio', label: 'Registered', options: ['Yes', 'No'] },
        ],
    });
    await field
        .get('button[aria-label="Remove option 1 from field 2"]')
        .trigger('click');
    expect(
        (field.get('input[id^="option-"]').element as HTMLInputElement).value,
    ).toBe('No');
    expect(
        field
            .get('button[aria-label="Remove option 1 from field 2"]')
            .attributes('disabled'),
    ).toBeDefined();
    await field.get('select').setValue('file');
    expect(transform({})).toEqual({
        fields: [
            storeSellerVerificationFields()[1],
            { type: 'file', label: 'Registered' },
        ],
    });
    expect(field.find('input[id^="option-"]').exists()).toBe(false);
});
it('saves an empty list after removing the final row', async () => {
    const wrapper = mountPage([{ type: 'text', label: 'Only' }]);
    await wrapper.get('button[aria-label="Remove field 1"]').trigger('click');
    expect(wrapper.text()).toContain(t('store.sellers.emptyForm'));
    expect(
        wrapper
            .getComponent(ResourceFormPage)
            .getComponent(Form)
            .props('transform')!({}),
    ).toEqual({
        fields: [],
    });
    expect(wrapper.findComponent(SubmitButton).exists()).toBe(true);
});
it('shows row option and form validation errors and processing', () => {
    slotProps.mockReturnValue({
        errors: {
            fields: 'Invalid form',
            'fields.0.label': 'Label required',
            'fields.1.type': 'Invalid type',
            'fields.1.options': 'Options required',
            'fields.1.options.0': 'Option required',
        },
        processing: true,
    });
    const wrapper = mountPage();

    for (const error of [
        'Invalid form',
        'Label required',
        'Invalid type',
        'Options required',
        'Option required',
    ]) {
        expect(wrapper.text()).toContain(error);
    }

    expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(true);
});
it('gates every builder control on the configuration permission', () => {
    usePageMock.mockReturnValue({
        url: '/temp/admin/verification/form',
        props: defaultAuthProps({ store: storeSharedProps() }),
    });
    const wrapper = mountPage();
    expect(wrapper.findComponent(ResourceFormPage).exists()).toBe(false);
    expect(wrapper.find('fieldset').exists()).toBe(false);
    expect(wrapper.findComponent(SubmitButton).exists()).toBe(false);
});
