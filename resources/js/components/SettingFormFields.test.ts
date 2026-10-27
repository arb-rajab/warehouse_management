import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import RequiredMark from '@/components/RequiredMark.vue';
import { t } from '@/lib/i18n';
import { pxToCm } from '@/lib/qrCodeSize';
import SettingFormFields from './SettingFormFields.vue';

describe('SettingFormFields', () => {
    it('renders its labels', () => {
        const wrapper = mount(SettingFormFields, { props: { errors: {} } });

        expect(wrapper.text()).toContain(t('settings.fields.qrCodeWidth'));
        expect(wrapper.text()).toContain(t('settings.fields.qrCodeHeight'));
    });

    it('resolves its label keys to the English copy', () => {
        expect(t('settings.fields.qrCodeWidth')).toBe('QR code width (cm)');
        expect(t('settings.fields.qrCodeHeight')).toBe('QR code height (cm)');
    });

    it('pre-fills the current values converted from pixels to centimeters', () => {
        const wrapper = mount(SettingFormFields, {
            props: { errors: {}, qrCodeWidth: 300, qrCodeHeight: 350 },
        });

        expect(
            (wrapper.find('#qr_code_width').element as HTMLInputElement).value,
        ).toBe(String(pxToCm(300)));
        expect(
            (wrapper.find('#qr_code_height').element as HTMLInputElement).value,
        ).toBe(String(pxToCm(350)));
    });

    it('shows validation error messages', () => {
        const wrapper = mount(SettingFormFields, {
            props: {
                errors: {
                    qr_code_width: 'The qr code width field is required.',
                    qr_code_height: 'The qr code height field is required.',
                },
            },
        });

        expect(wrapper.text()).toContain(
            'The qr code width field is required.',
        );
        expect(wrapper.text()).toContain(
            'The qr code height field is required.',
        );
    });

    it('retains values the user typed when the errors prop changes after a failed submit', async () => {
        const wrapper = mount(SettingFormFields, { props: { errors: {} } });

        await wrapper.find('#qr_code_width').setValue('10.5');
        await wrapper.find('#qr_code_height').setValue('12.5');

        await wrapper.setProps({
            errors: { qr_code_width: 'The qr code width field is required.' },
        });

        expect(
            (wrapper.find('#qr_code_width').element as HTMLInputElement).value,
        ).toBe('10.5');
        expect(
            (wrapper.find('#qr_code_height').element as HTMLInputElement).value,
        ).toBe('12.5');
    });

    it('marks both fields as required and entered in tenths of a centimeter', () => {
        const wrapper = mount(SettingFormFields, { props: { errors: {} } });

        expect(
            (wrapper.find('#qr_code_width').element as HTMLInputElement)
                .required,
        ).toBe(true);
        expect(
            (wrapper.find('#qr_code_height').element as HTMLInputElement)
                .required,
        ).toBe(true);
        expect(wrapper.find('#qr_code_width').attributes('step')).toBe('0.1');
        expect(wrapper.find('#qr_code_height').attributes('step')).toBe('0.1');
    });

    it('bounds both fields to the cm equivalent of the backend 100-1000px rule', () => {
        const wrapper = mount(SettingFormFields, { props: { errors: {} } });
        const minCm = String(pxToCm(100));
        const maxCm = String(pxToCm(1000));

        expect(wrapper.find('#qr_code_width').attributes('min')).toBe(minCm);
        expect(wrapper.find('#qr_code_width').attributes('max')).toBe(maxCm);
        expect(wrapper.find('#qr_code_height').attributes('min')).toBe(minCm);
        expect(wrapper.find('#qr_code_height').attributes('max')).toBe(maxCm);
    });

    it('prefixes its ids when idPrefix is given, so it can render more than once on the same page', () => {
        const wrapper = mount(SettingFormFields, {
            props: { errors: {}, idPrefix: 'preview-' },
        });

        expect(wrapper.find('#preview-qr_code_width').exists()).toBe(true);
        expect(wrapper.find('#preview-qr_code_height').exists()).toBe(true);
        expect(wrapper.find('#qr_code_width').exists()).toBe(false);
    });

    describe('allowed open levels', () => {
        function levelBoxes(wrapper: ReturnType<typeof mount>) {
            return wrapper.findAll<HTMLInputElement>(
                'input[name="allowed_open_levels[]"]',
            );
        }

        it('renders its labels and one checkbox per level, checking the allowed ones', () => {
            const wrapper = mount(SettingFormFields, {
                props: {
                    errors: {},
                    levels: [1, 2, 3],
                    allowedOpenLevels: [1, 3],
                },
            });

            expect(wrapper.text()).toContain(
                'Levels where opening pallets is allowed',
            );
            expect(wrapper.text()).toContain(
                'A pallet can only be opened when its level is checked.',
            );
            expect(wrapper.text()).toContain('Level 1');
            expect(wrapper.text()).toContain('Level 3');
            expect(levelBoxes(wrapper).map((box) => box.element.value)).toEqual(
                ['1', '2', '3'],
            );
            expect(
                levelBoxes(wrapper).map((box) => box.element.checked),
            ).toEqual([true, false, true]);
        });

        it('renders no level checkboxes when no levels are given', () => {
            const wrapper = mount(SettingFormFields, {
                props: { errors: {} },
            });

            expect(levelBoxes(wrapper)).toHaveLength(0);
            expect(wrapper.text()).not.toContain(
                'Levels where opening pallets is allowed',
            );
        });

        it('only marks the checkboxes as required while no level is checked', async () => {
            const wrapper = mount(SettingFormFields, {
                props: {
                    errors: {},
                    levels: [1, 2],
                    allowedOpenLevels: [1],
                },
            });

            expect(
                levelBoxes(wrapper).map((box) => box.element.required),
            ).toEqual([false, false]);

            await levelBoxes(wrapper)[0].setValue(false);

            expect(
                levelBoxes(wrapper).map((box) => box.element.required),
            ).toEqual([true, true]);

            await levelBoxes(wrapper)[1].setValue(true);

            expect(
                levelBoxes(wrapper).map((box) => box.element.required),
            ).toEqual([false, false]);
        });

        it('shows the allowed-levels validation error', () => {
            const wrapper = mount(SettingFormFields, {
                props: {
                    errors: {
                        allowed_open_levels: 'Pick at least one level.',
                    },
                    levels: [1],
                    allowedOpenLevels: [1],
                },
            });

            expect(wrapper.text()).toContain('Pick at least one level.');
        });

        it('prefixes the level checkbox ids with idPrefix', () => {
            const wrapper = mount(SettingFormFields, {
                props: {
                    errors: {},
                    idPrefix: 'preview-',
                    levels: [1],
                    allowedOpenLevels: [1],
                },
            });

            expect(
                wrapper.find('#preview-allowed_open_levels_1').exists(),
            ).toBe(true);
        });
    });
});

it('marks the levels group and accepts any selected level, then restores the empty requirement', async () => {
    const wrapper = mount(SettingFormFields, {
        props: { errors: {}, levels: [1, 2], allowedOpenLevels: [] },
    });
    expect(wrapper.get('legend').findComponent(RequiredMark).exists()).toBe(
        true,
    );
    const boxes = wrapper.findAll<HTMLInputElement>('input[type="checkbox"]');
    expect(boxes.every((box) => box.element.required)).toBe(true);
    await boxes[1].setValue(true);
    expect(boxes.every((box) => !box.element.required)).toBe(true);
    await boxes[1].setValue(false);
    expect(boxes.every((box) => box.element.required)).toBe(true);
    expect(wrapper.get('legend').findComponent(RequiredMark).exists()).toBe(
        true,
    );
});
