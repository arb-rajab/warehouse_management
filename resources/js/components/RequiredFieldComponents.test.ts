import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { Component } from 'vue';
import FilterDateField from '@/components/FilterDateField.vue';
import FilterNumberField from '@/components/FilterNumberField.vue';
import FormField from '@/components/FormField.vue';
import ProductSelect from '@/components/ProductSelect.vue';
import RequiredMark from '@/components/RequiredMark.vue';
import StoreBooleanField from '@/components/StoreBooleanField.vue';
import StoreNewsletterRecipients from '@/components/StoreNewsletterRecipients.vue';
import StoreOrderSearch from '@/components/StoreOrderSearch.vue';
import StoreRichTextField from '@/components/StoreRichTextField.vue';
import StoreSelectField from '@/components/StoreSelectField.vue';
import StoreUploadField from '@/components/StoreUploadField.vue';
import { i18n } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { createUseHttpStub } from '@/testing/inertiaStubs';

const { usePageMock, useHttpMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    useHttpMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', () => ({
    usePage: usePageMock,
    useHttp: useHttpMock,
}));

/**
 * Every shared field component that renders a label and forwards `required`
 * to its control. Each must show RequiredMark in its label exactly when the
 * control is required (.ai/rules/form-required-fields.md). A new field
 * component that forwards `required` belongs in this table: the source guard
 * below fails until it is added.
 */
const requiredFieldComponents: {
    name: string;
    component: Component;
    props: Record<string, unknown>;
    /** The element that carries `required` (or `aria-required`). */
    control: string;
    /** The control's label, when the component renders more than one. */
    label?: string;
    /** Whether the control is required when no `required` prop is passed. */
    requiredByDefault?: boolean;
}[] = [
    {
        name: 'FormField',
        component: FormField,
        props: { id: 'name', label: 'Name' },
        control: 'input',
    },
    {
        name: 'StoreSelectField',
        component: StoreSelectField,
        props: { id: 'country_id', label: 'Country', options: [] },
        control: 'select',
    },
    {
        name: 'StoreBooleanField',
        component: StoreBooleanField,
        props: { name: 'status', label: 'Status' },
        control: 'select',
        requiredByDefault: true,
    },
    {
        name: 'FilterNumberField',
        component: FilterNumberField,
        props: { id: 'days', label: 'Days', modelValue: '' },
        control: 'input',
    },
    {
        name: 'FilterDateField',
        component: FilterDateField,
        props: { id: 'date', label: 'Date', modelValue: '' },
        control: 'input',
    },
    {
        name: 'StoreUploadField',
        component: StoreUploadField,
        props: { name: 'banner', label: 'Banner', modelValue: '' },
        control: 'input[type="text"]',
    },
    {
        name: 'StoreRichTextField',
        component: StoreRichTextField,
        props: { id: 'body', name: 'body', label: 'Body', modelValue: '' },
        control: '[contenteditable]',
    },
    {
        name: 'ProductSelect',
        component: ProductSelect,
        props: {
            id: 'product',
            label: 'Product',
            placeholder: 'Choose',
            modelValue: null,
        },
        control: '#product-guard',
    },
    {
        name: 'StoreNewsletterRecipients',
        component: StoreNewsletterRecipients,
        props: { kind: 'users', errors: {}, required: false },
        control: 'select',
        label: 'label[for="user_emails"]',
    },
    {
        name: 'StoreOrderSearch',
        component: StoreOrderSearch,
        props: {
            id: 'customer',
            name: 'customer_id',
            label: 'Customer',
            kind: 'customers',
        },
        control: 'select',
        label: 'label[for="customer"]',
    },
];

/**
 * Source of every shared component, for the guard: a component that takes a
 * `required` prop (or passes `$attrs` to an input) and renders a label is a
 * field component and must be in the table above.
 */
const componentSources = import.meta.glob<string>('./*.vue', {
    query: '?raw',
    import: 'default',
    eager: true,
});

function forwardsRequired(source: string): boolean {
    const template = source.slice(source.indexOf('<template>'));

    return (
        /<label\b/.test(template) &&
        (/\brequired\??:\s*boolean/.test(source) ||
            (/v-bind="\$attrs"/.test(template) &&
                /<(input|select|textarea)\b/.test(template)))
    );
}

const wrappers: VueWrapper[] = [];

async function mountField(
    entry: (typeof requiredFieldComponents)[number],
    required?: boolean,
): Promise<VueWrapper> {
    const wrapper = mount(entry.component, {
        props: {
            ...entry.props,
            ...(required === undefined ? {} : { required }),
        },
        attachTo: document.body,
    });
    wrappers.push(wrapper);
    await flushPromises();

    return wrapper;
}

function controlIsRequired(wrapper: VueWrapper, selector: string): boolean {
    const control = wrapper.find(selector);

    if (!control.exists()) {
        return false;
    }

    return (
        control.attributes('required') !== undefined ||
        control.attributes('aria-required') === 'true'
    );
}

beforeEach(() => {
    i18n.global.locale.value = 'en';
    usePageMock.mockReturnValue({
        props: { store: storeSharedProps({ permissions: ['view_uploads'] }) },
    });
    useHttpMock.mockImplementation((data) => createUseHttpStub(data, []));
});

afterEach(() => {
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
    i18n.global.locale.value = 'en';
});

describe.each(requiredFieldComponents)('$name', (entry) => {
    it('marks the label and requires the control when required', async () => {
        const wrapper = await mountField(entry, true);
        const label = wrapper.get(entry.label ?? 'label');
        const mark = label.findComponent(RequiredMark);

        expect(mark.exists()).toBe(true);
        expect(mark.get('[aria-hidden="true"]').text()).toBe('*');
        expect(mark.get('.sr-only').text()).toBe('required');
        expect(controlIsRequired(wrapper, entry.control)).toBe(true);
    });

    it('shows no mark and no required control when not required', async () => {
        const wrapper = await mountField(entry, false);

        expect(wrapper.findComponent(RequiredMark).exists()).toBe(false);
        expect(controlIsRequired(wrapper, entry.control)).toBe(false);
    });

    it('defaults to the control it always rendered', async () => {
        const wrapper = await mountField(
            entry,
            'required' in entry.props ? false : undefined,
        );
        const required = entry.requiredByDefault ?? false;

        expect(wrapper.findComponent(RequiredMark).exists()).toBe(required);
        expect(controlIsRequired(wrapper, entry.control)).toBe(required);
    });

    it('reads the screen-reader text in Arabic', async () => {
        i18n.global.locale.value = 'ar';
        const wrapper = await mountField(entry, true);

        expect(wrapper.findComponent(RequiredMark).get('.sr-only').text()).toBe(
            'مطلوب',
        );
    });
});

describe('field component guard', () => {
    it('lists every component that forwards required to a labelled control', () => {
        const forwarders = Object.entries(componentSources)
            .filter(([, source]) => forwardsRequired(source))
            .map(([file]) => file.replace('./', '').replace('.vue', ''))
            .sort();

        expect(forwarders).toEqual(
            requiredFieldComponents.map((entry) => entry.name).sort(),
        );
    });

    it.each(requiredFieldComponents.map((entry) => entry.name))(
        '%s renders RequiredMark and registers with the form legend',
        (name) => {
            const source = componentSources[`./${name}.vue`];

            expect(source).toContain(
                "import RequiredMark from '@/components/RequiredMark.vue';",
            );
            expect(source).toContain('<RequiredMark v-if="required"');
            expect(source).toContain('useRequiredField(() => props.required)');
        },
    );
});
