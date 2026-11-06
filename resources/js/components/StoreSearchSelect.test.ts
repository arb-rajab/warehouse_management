import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import { i18n } from '@/lib/i18n';
import type { SearchEndpoint } from '@/lib/useSearchOptions';
import type { SearchOption } from '@/types/searchSelect';
import StoreSearchSelect from './StoreSearchSelect.vue';

type GetOptions = {
    onSuccess?: (response: unknown) => void;
    onError?: () => void;
    onHttpException?: () => void;
    onFinish?: () => void;
};

const mocks = vi.hoisted(() => ({
    get: vi.fn<(url: string, options?: GetOptions) => void>(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: mocks.get }),
}));

const endpoint: SearchEndpoint = {
    url: (options) =>
        `/search?${new URLSearchParams(
            Object.entries(options?.query ?? {}).map(([key, value]) => [
                key,
                String(value),
            ]),
        ).toString()}`,
};

const customers: SearchOption[] = [
    {
        id: 1,
        label: 'Ali Hassan Trading',
        description: '+31 6 1234 5678 · ali@hassan.nl',
        icon: 'user',
        badge: { text: 'Account 20481', tone: 'neutral' },
    },
    {
        id: 2,
        label: 'Alina Kowalska',
        description: '+31 6 8765 4321',
        icon: 'user',
    },
    { id: 3, label: 'Salim Alkhatib', icon: 'user' },
];

const wrappers: VueWrapper[] = [];

function mountSelect(props: Record<string, unknown> = {}) {
    const wrapper = mount(StoreSearchSelect, {
        props: { id: 'customer_id', label: 'Customer', endpoint, ...props },
        global: { stubs: { teleport: true } },
        attachTo: document.body,
    });
    wrappers.push(wrapper);

    return wrapper;
}

function respond(
    call: number,
    data: SearchOption[],
    meta: { current_page?: number; last_page?: number; total?: number } = {},
): void {
    mocks.get.mock.calls[call][1]?.onSuccess?.({
        data,
        meta: { current_page: 1, last_page: 1, total: data.length, ...meta },
    });
    mocks.get.mock.calls[call][1]?.onFinish?.();
}

async function openPanel(wrapper: VueWrapper): Promise<void> {
    await wrapper.get('#customer_id').trigger('click');
    await nextTick();
}

const input = (wrapper: VueWrapper) => wrapper.get('input[role="combobox"]');

beforeEach(() => {
    i18n.global.locale.value = 'en';
    mocks.get.mockReset();
});

afterEach(() => {
    vi.useRealTimers();
    wrappers.splice(0).forEach((wrapper) => wrapper.unmount());
});

describe('StoreSearchSelect field', () => {
    it('renders the label, a required mark, an empty hidden input and the native required guard', () => {
        const wrapper = mountSelect({ name: 'customer_id', required: true });

        expect(wrapper.get('label').text()).toContain('Customer');
        expect(wrapper.find('label [data-required-mark]').exists()).toBe(true);
        const trigger = wrapper.get('#customer_id');
        expect(trigger.attributes()).toMatchObject({
            type: 'button',
            'aria-haspopup': 'listbox',
            'aria-expanded': 'false',
            'aria-required': 'true',
        });
        expect(
            wrapper.get('input[type="hidden"][name="customer_id"]').element,
        ).toHaveProperty('value', '');
        expect(
            wrapper.get('[data-testid="search-select-guard"]').attributes(),
        ).toHaveProperty('required');
        expect(trigger.text()).toBe('Select an option');
    });

    it('marks nothing when optional and shows the server error with aria-invalid', () => {
        const wrapper = mountSelect({
            name: 'customer_id',
            error: 'The customer field is required.',
        });

        expect(wrapper.find('[data-required-mark]').exists()).toBe(false);
        expect(
            wrapper.find('[data-testid="search-select-guard"]').exists(),
        ).toBe(false);
        expect(wrapper.text()).toContain('The customer field is required.');
        expect(wrapper.get('#customer_id').attributes('aria-invalid')).toBe(
            'true',
        );
        expect(wrapper.get('#customer_id').attributes('aria-describedby')).toBe(
            'customer_id-error',
        );
    });

    it('shows a prefilled option as a chip without searching and drops the guard', () => {
        const wrapper = mountSelect({
            name: 'customer_id',
            required: true,
            modelValue: customers[0],
        });

        expect(
            wrapper.get('input[name="customer_id"]').attributes('value'),
        ).toBe('1');
        expect(wrapper.get('#customer_id').text()).toContain(
            'Ali Hassan Trading',
        );
        expect(wrapper.get('#customer_id').text()).toContain('ali@hassan.nl');
        expect(
            wrapper.find('[data-testid="search-select-guard"]').exists(),
        ).toBe(false);
        expect(mocks.get).not.toHaveBeenCalled();
    });

    it('hands focus to the trigger when the native guard is focused', async () => {
        const wrapper = mountSelect({ required: true });

        await wrapper
            .get('[data-testid="search-select-guard"]')
            .trigger('focus');

        expect(document.activeElement).toBe(
            wrapper.get('#customer_id').element,
        );
    });

    it('does not open while disabled', async () => {
        const wrapper = mountSelect({ disabled: true });

        await openPanel(wrapper);

        expect(wrapper.find('input[role="combobox"]').exists()).toBe(false);
        expect(mocks.get).not.toHaveBeenCalled();
    });
});

describe('StoreSearchSelect search', () => {
    it('loads the first page on open with the extra params and shows skeleton rows while loading', async () => {
        const wrapper = mountSelect({ params: { kind: 'customers' } });

        await openPanel(wrapper);

        expect(mocks.get).toHaveBeenCalledTimes(1);
        expect(mocks.get.mock.calls[0][0]).toBe(
            '/search?q=&page=1&kind=customers',
        );
        expect(wrapper.get('#customer_id').attributes('aria-expanded')).toBe(
            'true',
        );
        expect(
            wrapper.find('[data-testid="search-select-skeleton"]').exists(),
        ).toBe(true);
        expect(wrapper.get('[data-testid="search-select-status"]').text()).toBe(
            'Searching…',
        );
        expect(document.activeElement).toBe(input(wrapper).element);
    });

    it('renders rows with label, description and badge as options', async () => {
        const wrapper = mountSelect();
        await openPanel(wrapper);

        respond(0, customers, { total: 3 });
        await nextTick();

        const rows = wrapper.findAll('[role="option"]');
        expect(rows).toHaveLength(3);
        expect(rows[0].get('[data-testid="search-option-label"]').text()).toBe(
            'Ali Hassan Trading',
        );
        expect(
            rows[0].get('[data-testid="search-option-description"]').text(),
        ).toBe('+31 6 1234 5678 · ali@hassan.nl');
        expect(rows[0].get('[data-testid="search-option-badge"]').text()).toBe(
            'Account 20481',
        );
        expect(
            rows[0].find('[data-testid="search-option-initials"]').text(),
        ).toBe('AH');
        expect(
            rows[2].find('[data-testid="search-option-description"]').exists(),
        ).toBe(false);
        expect(wrapper.get('[role="listbox"]').attributes('aria-label')).toBe(
            'Customer options',
        );
        expect(wrapper.get('[data-testid="search-select-status"]').text()).toBe(
            '3 results',
        );
        expect(input(wrapper).attributes('aria-activedescendant')).toBe(
            'customer_id-option-0',
        );
    });

    it('debounces typing, highlights the matched words and ignores a stale response', async () => {
        vi.useFakeTimers();
        const wrapper = mountSelect();
        await openPanel(wrapper);
        respond(0, customers);
        await nextTick();

        await input(wrapper).setValue('ali');
        await input(wrapper).setValue('alina');
        expect(mocks.get).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(299);
        expect(mocks.get).toHaveBeenCalledTimes(1);
        await vi.advanceTimersByTimeAsync(1);
        expect(mocks.get).toHaveBeenCalledTimes(2);
        expect(mocks.get.mock.calls[1][0]).toContain('q=alina');

        await input(wrapper).setValue('sal');
        await vi.advanceTimersByTimeAsync(300);
        expect(mocks.get).toHaveBeenCalledTimes(3);
        respond(2, [customers[2]]);
        // The response of the earlier query lands late and must be dropped.
        respond(1, [customers[1]]);
        await nextTick();

        const rows = wrapper.findAll('[role="option"]');
        expect(rows).toHaveLength(1);
        expect(rows[0].text()).toContain('Salim Alkhatib');
        expect(rows[0].get('mark').text()).toBe('Sal');
    });

    it('shows the empty state with the query when nothing matches', async () => {
        vi.useFakeTimers();
        const wrapper = mountSelect();
        await openPanel(wrapper);
        respond(0, customers);
        await nextTick();

        await input(wrapper).setValue('zzzz');
        await vi.advanceTimersByTimeAsync(300);
        respond(1, []);
        await nextTick();

        expect(
            wrapper.get('[data-testid="search-select-empty"]').text(),
        ).toContain('No results found');
        expect(
            wrapper.get('[data-testid="search-select-empty"]').text(),
        ).toContain('zzzz');
        expect(wrapper.find('[role="option"]').exists()).toBe(false);
    });

    it('keeps the earlier rows on a failed search and retries from the error row', async () => {
        vi.useFakeTimers();
        const wrapper = mountSelect();
        await openPanel(wrapper);
        respond(0, customers);
        await nextTick();

        await input(wrapper).setValue('ali');
        await vi.advanceTimersByTimeAsync(300);
        mocks.get.mock.calls[1][1]?.onHttpException?.();
        mocks.get.mock.calls[1][1]?.onFinish?.();
        await nextTick();

        expect(
            wrapper.get('[data-testid="search-select-error"]').text(),
        ).toContain('Search failed. Try again.');
        expect(wrapper.findAll('[role="option"]')).toHaveLength(3);

        await wrapper
            .get('[data-testid="search-select-retry"]')
            .trigger('click');
        expect(mocks.get).toHaveBeenCalledTimes(3);
        expect(mocks.get.mock.calls[2][0]).toContain('q=ali');
        respond(2, [customers[0]]);
        await nextTick();
        expect(
            wrapper.find('[data-testid="search-select-error"]').exists(),
        ).toBe(false);
    });

    it('sends nothing below the minimum length and says how many characters are needed', async () => {
        vi.useFakeTimers();
        const wrapper = mountSelect({ minChars: 4 });
        await openPanel(wrapper);

        expect(mocks.get).not.toHaveBeenCalled();
        expect(
            wrapper.get('[data-testid="search-select-min-chars"]').text(),
        ).toContain('Type at least 4 characters to search.');

        await input(wrapper).setValue('ric');
        await vi.advanceTimersByTimeAsync(300);
        expect(mocks.get).not.toHaveBeenCalled();

        await input(wrapper).setValue('rice');
        await vi.advanceTimersByTimeAsync(300);
        expect(mocks.get).toHaveBeenCalledTimes(1);
        expect(mocks.get.mock.calls[0][0]).toContain('q=rice');
    });

    it('loads the next page on scroll and when arrowing past the last row', async () => {
        const wrapper = mountSelect();
        await openPanel(wrapper);
        respond(0, customers.slice(0, 2), { last_page: 2, total: 3 });
        await nextTick();
        expect(wrapper.get('[data-testid="search-select-status"]').text()).toBe(
            '3 results · scroll for more',
        );

        await input(wrapper).trigger('keydown', { key: 'ArrowDown' });
        await input(wrapper).trigger('keydown', { key: 'ArrowDown' });
        expect(mocks.get).toHaveBeenCalledTimes(2);
        expect(mocks.get.mock.calls[1][0]).toContain('page=2');
        respond(1, [customers[2]], { current_page: 2, last_page: 2, total: 3 });
        await nextTick();

        expect(wrapper.findAll('[role="option"]')).toHaveLength(3);
    });

    it('drops the loaded rows when the params change', async () => {
        const wrapper = mountSelect({ params: { state_id: 1 } });
        await openPanel(wrapper);
        respond(0, customers);
        await nextTick();
        expect(wrapper.findAll('[role="option"]')).toHaveLength(3);

        await wrapper.setProps({ params: { state_id: 2 } });

        // The open panel refetched for the new parent.
        expect(mocks.get).toHaveBeenCalledTimes(2);
        expect(mocks.get.mock.calls[1][0]).toContain('state_id=2');
        expect(wrapper.findAll('[role="option"]')).toHaveLength(0);
    });
});

describe('StoreSearchSelect selection', () => {
    it('picks with the arrow keys and Enter: updates the model, posts the id, closes and refocuses the trigger', async () => {
        const wrapper = mountSelect({ name: 'customer_id' });
        await openPanel(wrapper);
        respond(0, customers);
        await nextTick();

        await input(wrapper).trigger('keydown', { key: 'ArrowDown' });
        expect(input(wrapper).attributes('aria-activedescendant')).toBe(
            'customer_id-option-1',
        );
        await input(wrapper).trigger('keydown', { key: 'ArrowUp' });
        await input(wrapper).trigger('keydown', { key: 'ArrowUp' });
        expect(input(wrapper).attributes('aria-activedescendant')).toBe(
            'customer_id-option-2',
        );
        await input(wrapper).trigger('keydown', { key: 'Enter' });
        await nextTick();

        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([
            customers[2],
        ]);
        expect(wrapper.emitted('select')?.[0]).toEqual([customers[2]]);
        expect(wrapper.find('input[role="combobox"]').exists()).toBe(false);
        expect(document.activeElement).toBe(
            wrapper.get('#customer_id').element,
        );
    });

    it('picks a row on click and shows it as the selected value with a clear button', async () => {
        const wrapper = mountSelect({ name: 'customer_id' });
        await openPanel(wrapper);
        respond(0, customers);
        await nextTick();

        await wrapper.findAll('[role="option"]')[0].trigger('click');
        await wrapper.setProps({
            modelValue: wrapper.emitted(
                'update:modelValue',
            )?.[0][0] as SearchOption,
        });

        expect(
            wrapper.get('input[name="customer_id"]').attributes('value'),
        ).toBe('1');
        expect(wrapper.get('#customer_id').text()).toContain(
            'Ali Hassan Trading',
        );

        await wrapper
            .get('[data-testid="search-select-clear"]')
            .trigger('click');
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([null]);
        expect(wrapper.emitted('clear')).toHaveLength(1);
    });

    it('closes on Escape and returns focus to the trigger', async () => {
        const wrapper = mountSelect();
        await openPanel(wrapper);

        await input(wrapper).trigger('keydown', { key: 'Escape' });

        expect(wrapper.find('input[role="combobox"]').exists()).toBe(false);
        expect(document.activeElement).toBe(
            wrapper.get('#customer_id').element,
        );
        expect(wrapper.emitted('close')).toHaveLength(1);
    });

    it('opens from the closed trigger with ArrowDown or a typed character', async () => {
        vi.useFakeTimers();
        const wrapper = mountSelect();

        await wrapper
            .get('#customer_id')
            .trigger('keydown', { key: 'ArrowDown' });
        expect(wrapper.find('input[role="combobox"]').exists()).toBe(true);
        await input(wrapper).trigger('keydown', { key: 'Escape' });

        await wrapper.get('#customer_id').trigger('keydown', { key: 'a' });
        await nextTick();
        expect((input(wrapper).element as HTMLInputElement).value).toBe('a');
    });

    it('closes on a click outside but not on a click inside the panel', async () => {
        const wrapper = mountSelect();
        await openPanel(wrapper);

        input(wrapper).element.dispatchEvent(
            new MouseEvent('click', { bubbles: true }),
        );
        await nextTick();
        expect(wrapper.find('input[role="combobox"]').exists()).toBe(true);

        document.body.dispatchEvent(new MouseEvent('click', { bubbles: true }));
        await nextTick();
        expect(wrapper.find('input[role="combobox"]').exists()).toBe(false);
    });

    it('dispatches a bubbling change from the hidden input so form validation rechecks the field', async () => {
        const form = document.createElement('form');
        document.body.appendChild(form);
        const wrapper = mount(StoreSearchSelect, {
            props: {
                id: 'customer_id',
                label: 'Customer',
                endpoint,
                name: 'customer_id',
            },
            global: { stubs: { teleport: true } },
            attachTo: form,
        });
        wrappers.push(wrapper);
        const onChange = vi.fn((event: Event) =>
            (event.target as HTMLInputElement).getAttribute('name'),
        );
        form.addEventListener('change', onChange);
        await openPanel(wrapper);
        respond(0, customers);
        await nextTick();

        await wrapper.findAll('[role="option"]')[1].trigger('click');
        await nextTick();

        expect(onChange).toHaveBeenCalledTimes(1);
        expect(onChange.mock.results[0].value).toBe('customer_id');
        form.remove();
    });

    it('lets the page replace the row, badge and selected content with slots', async () => {
        const wrapper = mount(StoreSearchSelect, {
            props: {
                id: 'customer_id',
                label: 'Customer',
                endpoint,
                modelValue: customers[1],
            },
            slots: {
                badge: '<template #badge="{ option }"><b data-testid="custom-badge">{{ option.id }}</b></template>',
                selected:
                    '<template #selected="{ option }"><i data-testid="custom-selected">{{ option.label }}</i></template>',
            },
            global: { stubs: { teleport: true } },
            attachTo: document.body,
        });
        wrappers.push(wrapper);

        expect(wrapper.get('[data-testid="custom-selected"]').text()).toBe(
            'Alina Kowalska',
        );
        await wrapper.get('#customer_id').trigger('click');
        respond(0, customers);
        await nextTick();
        expect(wrapper.findAll('[data-testid="custom-badge"]')).toHaveLength(3);
    });
});

describe('StoreSearchSelect multiple', () => {
    it('adds chips and one hidden input per pick, keeps the panel open and toggles a pick off', async () => {
        const wrapper = mountSelect({
            name: 'customer_ids[]',
            multiple: true,
            items: [],
        });
        await openPanel(wrapper);
        respond(0, customers);
        await nextTick();

        await wrapper.findAll('[role="option"]')[0].trigger('click');
        const added = wrapper.emitted('update:items')?.[0][0] as SearchOption[];
        expect(added.map((option) => option.id)).toEqual([1]);
        await wrapper.setProps({ items: added });
        await wrapper.findAll('[role="option"]')[1].trigger('click');
        const both = wrapper.emitted('update:items')?.[1][0] as SearchOption[];
        await wrapper.setProps({ items: both });

        expect(wrapper.find('input[role="combobox"]').exists()).toBe(true);
        expect(
            wrapper.findAll('[data-testid="search-select-chip"]'),
        ).toHaveLength(2);
        expect(
            wrapper
                .findAll('input[type="hidden"][name="customer_ids[]"]')
                .map((hidden) => hidden.attributes('value')),
        ).toEqual(['1', '2']);
        expect(
            wrapper.findAll('[role="option"]')[0].attributes('aria-selected'),
        ).toBe('true');
        expect(
            wrapper.get('[role="listbox"]').attributes('aria-multiselectable'),
        ).toBe('true');

        await wrapper.findAll('[role="option"]')[0].trigger('click');
        expect(
            (wrapper.emitted('update:items')?.[2][0] as SearchOption[]).map(
                (option) => option.id,
            ),
        ).toEqual([2]);
        expect(wrapper.emitted('remove')?.[0]).toEqual([customers[0]]);
    });

    it('removes a chip with its own labelled button and clears all', async () => {
        const wrapper = mountSelect({
            name: 'customer_ids[]',
            multiple: true,
            required: true,
            items: [customers[0], customers[1]],
        });

        expect(
            wrapper.find('[data-testid="search-select-guard"]').exists(),
        ).toBe(false);
        await wrapper
            .get('button[aria-label="Remove Ali Hassan Trading"]')
            .trigger('click');
        expect(
            (wrapper.emitted('update:items')?.[0][0] as SearchOption[]).map(
                (option) => option.id,
            ),
        ).toEqual([2]);

        await wrapper
            .get('[data-testid="search-select-clear"]')
            .trigger('click');
        expect(wrapper.emitted('update:items')?.[1]).toEqual([[]]);
        expect(wrapper.emitted('clear')).toHaveLength(1);
    });
});

describe('StoreSearchSelect on a phone', () => {
    it('opens as a sheet with a backdrop and a Close button', async () => {
        const matchMedia = vi.fn().mockReturnValue({ matches: true });
        vi.stubGlobal('matchMedia', matchMedia);
        const wrapper = mountSelect();

        await openPanel(wrapper);

        expect(matchMedia).toHaveBeenCalledWith('(max-width: 639.98px)');
        expect(
            wrapper.find('[data-testid="search-select-backdrop"]').exists(),
        ).toBe(true);
        await wrapper
            .get('[data-testid="search-select-backdrop"]')
            .trigger('click');
        expect(wrapper.find('input[role="combobox"]').exists()).toBe(false);
        vi.unstubAllGlobals();
    });
});

describe('StoreSearchSelect in Arabic', () => {
    it('reads its states in Arabic', async () => {
        i18n.global.locale.value = 'ar';
        const wrapper = mountSelect();

        expect(wrapper.get('#customer_id').text()).toBe('اختر خيارًا');
        await openPanel(wrapper);
        expect(wrapper.get('[data-testid="search-select-status"]').text()).toBe(
            'جارٍ البحث…',
        );
    });
});
