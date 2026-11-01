import { Form, Head } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { update } from '@/actions/App/Http/Controllers/Store/Admin/ProductFieldSourceController';
import FieldError from '@/components/FieldError.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import ResourceFormPage from '@/components/ResourceFormPage.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { StoreProductFieldSourceRow } from '@/types/store';
import Index from './Index.vue';

const { usePageMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        useHttp: () => ({ processing: false, get: vi.fn(), cancel: vi.fn() }),
        usePage: usePageMock,
        router: { get: vi.fn(), post: vi.fn() },
    };
});

const fields: StoreProductFieldSourceRow[] = [
    { key: 'name', source: 'admin', default: 'otajer' },
    { key: 'ar_name', source: 'otajer', default: 'otajer' },
    { key: 'published', source: 'otajer', default: 'otajer' },
];

function page(rows: StoreProductFieldSourceRow[] = fields) {
    usePageMock.mockReturnValue({
        url: update.url(),
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: ['product_field_sources'] }),
        }),
    });

    return mount(Index, { props: { fields: rows } });
}

function checked(wrapper: ReturnType<typeof page>): [string, string][] {
    return wrapper
        .findAll('input[type="radio"]')
        .filter((input) => (input.element as HTMLInputElement).checked)
        .map((input) => [
            input.attributes('name') ?? '',
            (input.element as HTMLInputElement).value,
        ]);
}

describe('product data sources page', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    });
    afterEach(() => {
        i18n.global.locale.value = 'en';
    });

    it.each(['en', 'ar'] as const)(
        'renders the store form shell and every label and hint in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = page();

            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(
                wrapper.getComponent(ResourceFormPage).props(),
            ).toMatchObject({
                title: t('store.productFieldSources.title'),
                action: update(),
                submitLabel: t('store.settings.save'),
                submittingLabel: t('store.settings.saving'),
                layout: StoreAdminLayout,
                wide: true,
            });
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.productFieldSources.title'),
            );
            expect(wrapper.getComponent(Form).props('action')).toEqual(
                update(),
            );
            expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
                label: t('store.settings.save'),
                processingLabel: t('store.settings.saving'),
            });

            for (const key of [
                'title',
                'intro',
                'otajer',
                'otajerHint',
                'admin',
                'adminHint',
                'fields.name',
                'fields.ar_name',
                'fields.published',
            ]) {
                const label = t(`store.productFieldSources.${key}`);
                expect(label).not.toBe(`store.productFieldSources.${key}`);

                if (key !== 'title') {
                    expect(wrapper.text()).toContain(label);
                }
            }

            expect(wrapper.text()).toContain(
                t('store.productFieldSources.default', {
                    source: t('store.productFieldSources.otajer'),
                }),
            );
        },
    );

    it('offers one radio pair per field, sharing one name, with the stored owner checked', () => {
        const wrapper = page();

        expect(
            wrapper
                .findAll('fieldset')
                .map((fieldset) => fieldset.attributes('data-field')),
        ).toEqual(['name', 'ar_name', 'published']);

        for (const field of fields) {
            const radios = wrapper.findAll(
                `input[name="sources[${field.key}]"]`,
            );
            expect(
                radios.map(
                    (radio) => (radio.element as HTMLInputElement).value,
                ),
            ).toEqual(['otajer', 'admin']);
            expect(radios.map((radio) => radio.attributes('type'))).toEqual([
                'radio',
                'radio',
            ]);
        }

        expect(checked(wrapper)).toEqual([
            ['sources[name]', 'admin'],
            ['sources[ar_name]', 'otajer'],
            ['sources[published]', 'otajer'],
        ]);
    });

    it('never leaves both options of a field checked', async () => {
        const wrapper = page();
        const otajerName = wrapper.get(
            'input[name="sources[name]"][value="otajer"]',
        );

        await otajerName.setValue(true);

        expect(
            checked(wrapper).filter(([name]) => name === 'sources[name]'),
        ).toEqual([['sources[name]', 'otajer']]);
    });

    it('marks every field required on its legend and every radio, with the legend', () => {
        const wrapper = page();

        const legends = wrapper.findAll('legend');
        expect(legends).toHaveLength(3);

        for (const legend of legends) {
            expect(legend.findComponent(RequiredMark).exists()).toBe(true);
        }

        expect(wrapper.findAllComponents(RequiredMark)).toHaveLength(3);
        expect(
            wrapper
                .findAll('input[type="radio"]')
                .every((radio) => radio.attributes('required') !== undefined),
        ).toBe(true);
        expect(wrapper.findAll('[data-required-legend]')).toHaveLength(1);
    });

    it('shows each backend error under its field', () => {
        formSlotPropsMock.mockReturnValue({
            errors: {
                sources: 'Unknown field',
                'sources.name': 'Pick an owner',
                'sources.published': 'Invalid owner',
            },
            processing: true,
        });
        const wrapper = page();

        expect(
            wrapper
                .findAllComponents(FieldError)
                .map((error) => error.props('message'))
                .filter(Boolean),
        ).toEqual(['Unknown field', 'Pick an owner', 'Invalid owner']);
        expect(wrapper.getComponent(SubmitButton).props('processing')).toBe(
            true,
        );
    });
});
