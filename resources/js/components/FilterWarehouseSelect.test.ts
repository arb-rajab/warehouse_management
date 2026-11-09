import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { search as searchWarehouses } from '@/actions/App/Http/Controllers/Admin/WarehouseController';
import { t } from '@/lib/i18n';
import { paginated } from '@/testing/factories';
import type { Paginated, WarehouseSummary } from '@/types/admin';
import FilterWarehouseSelect from './FilterWarehouseSelect.vue';

interface HttpGetOptions {
    onSuccess?: (response: Paginated<WarehouseSummary>) => void;
    onFinish?: () => void;
}

const { getMock } = vi.hoisted(() => ({
    getMock: vi.fn<(url: string, options?: HttpGetOptions) => void>(),
}));

vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: getMock }),
}));

const main = { id: 1, name: 'Main' };
const annex = { id: 2, name: 'Annex' };

function resolveCall(index: number, response: Paginated<WarehouseSummary>) {
    // A real request always calls onFinish after onSuccess (or after
    // onError), regardless of outcome.
    getMock.mock.calls[index][1]?.onSuccess?.(response);
    getMock.mock.calls[index][1]?.onFinish?.();
}

function mountSelect(
    props: Partial<{
        selected: WarehouseSummary[];
        modelValue: string[];
    }> = {},
) {
    return mount(FilterWarehouseSelect, {
        props: {
            id: 'filter-warehouse',
            label: 'Warehouse',
            allLabel: 'All',
            selectedCountLabel: (count: number) => `${count} selected`,
            selected: props.selected ?? [],
            modelValue: props.modelValue ?? [],
        },
    });
}

function scrollToEnd(wrapper: ReturnType<typeof mountSelect>) {
    const list = wrapper.get('[role="listbox"]').element as HTMLElement;

    for (const [property, value] of [
        ['scrollHeight', 100],
        ['clientHeight', 50],
        ['scrollTop', 60],
    ] as const) {
        Object.defineProperty(list, property, { value, configurable: true });
    }

    return wrapper.get('[role="listbox"]').trigger('scroll');
}

describe('FilterWarehouseSelect', () => {
    beforeEach(() => {
        getMock.mockClear();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows the all label when nothing is selected', () => {
        expect(mountSelect().get('button').text()).toBe('All');
    });

    it('labels the trigger with the field label and links them by id', () => {
        const wrapper = mountSelect();

        expect(wrapper.get('label').text()).toBe('Warehouse');
        expect(wrapper.get('label').attributes('for')).toBe('filter-warehouse');
        expect(wrapper.get('button').attributes('id')).toBe('filter-warehouse');
    });

    it('uses a logical text-align utility on the trigger so RTL locales flip alignment', () => {
        expect(mountSelect().get('button').classes()).toContain('text-start');
    });

    it("resolves a single selection's label from the `selected` prop without fetching", () => {
        const wrapper = mountSelect({ selected: [main], modelValue: ['1'] });

        expect(wrapper.get('button > span:first-child').text()).toBe('Main');
        expect(getMock).not.toHaveBeenCalled();
    });

    it('shows the selected count label when more than one is selected', () => {
        const wrapper = mountSelect({
            selected: [main, annex],
            modelValue: ['1', '2'],
        });

        expect(wrapper.get('button').text()).toBe('2 selected');
    });

    it('caps the panel width to the room available so it cannot overflow past the viewport edge it grows toward', async () => {
        const wrapper = mountSelect();
        const container = wrapper.get<HTMLElement>('.relative').element;
        vi.spyOn(container, 'getBoundingClientRect').mockReturnValue({
            left: 200,
            right: 208,
            bottom: 40,
            width: 8,
        } as DOMRect);
        container.style.direction = 'rtl';

        await wrapper.get('button').trigger('click');

        const panel = wrapper.get('[role="listbox"]').element
            .parentElement as HTMLElement;
        expect(panel.style.maxWidth).toBe('192px');
    });

    it('positions the panel as a fixed overlay anchored under the trigger, so it can escape a scrolling ancestor instead of being clipped by it', async () => {
        vi.spyOn(window, 'innerWidth', 'get').mockReturnValue(1024);
        const wrapper = mountSelect();
        const container = wrapper.get<HTMLElement>('.relative').element;
        vi.spyOn(container, 'getBoundingClientRect').mockReturnValue({
            left: 100,
            right: 300,
            bottom: 250,
            width: 200,
        } as DOMRect);

        await wrapper.get('button').trigger('click');

        const panel = wrapper.get('[role="listbox"]').element
            .parentElement as HTMLElement;
        expect(panel.className).toContain('fixed');
        expect(panel.style.top).toBe('254px');
        expect(panel.style.insetInlineStart).toBe('100px');
        expect(panel.style.minWidth).toBe('200px');
    });

    it('re-anchors the panel when the window resizes while open, and stops tracking once closed', async () => {
        const wrapper = mountSelect();
        const container = wrapper.get<HTMLElement>('.relative').element;
        const getRectSpy = vi
            .spyOn(container, 'getBoundingClientRect')
            .mockReturnValue({
                left: 100,
                right: 300,
                bottom: 250,
                width: 200,
            } as DOMRect);

        await wrapper.get('button').trigger('click');
        const callsAfterOpen = getRectSpy.mock.calls.length;

        window.dispatchEvent(new Event('resize'));
        expect(getRectSpy.mock.calls.length).toBeGreaterThan(callsAfterOpen);

        const callsAfterResize = getRectSpy.mock.calls.length;
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        window.dispatchEvent(new Event('resize'));
        expect(getRectSpy.mock.calls.length).toBe(callsAfterResize);
    });

    it('fetches the first page from the warehouse search endpoint when opened', async () => {
        const wrapper = mountSelect();

        await wrapper.get('button').trigger('click');

        expect(
            wrapper.get('input[type="search"]').attributes('placeholder'),
        ).toBe(t('warehouses.filters.searchPlaceholder'));
        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][0]).toContain(searchWarehouses.url());
    });

    it('shows a loading message while the first page is in flight', async () => {
        const wrapper = mountSelect();

        await wrapper.get('button').trigger('click');

        expect(wrapper.text()).toContain(t('cellLog.filters.searching'));
    });

    it('renders fetched options and emits the selection on checkbox toggle', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(0, paginated([main, annex]));
        await wrapper.vm.$nextTick();

        const checkboxes = wrapper.findAll('input[type="checkbox"]');
        expect(checkboxes).toHaveLength(2);

        await checkboxes[1].setValue(true);
        expect(wrapper.emitted('update:modelValue')).toEqual([[['2']]]);
    });

    it('shows "no warehouses found" when a search resolves empty', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(0, paginated([]));
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain(
            t('warehouses.filters.noWarehousesFound'),
        );
    });

    it('debounces search-as-you-type into a single fetch after 300ms', async () => {
        vi.useFakeTimers();
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');
        getMock.mockClear();

        const input = wrapper.get('input[type="search"]');
        await input.setValue('a');
        await input.setValue('an');
        await input.setValue('ann');

        expect(getMock).not.toHaveBeenCalled();

        await vi.advanceTimersByTimeAsync(300);

        expect(getMock).toHaveBeenCalledTimes(1);
        expect(getMock.mock.calls[0][0]).toContain('q=ann');
    });

    it('appends (not replaces) results when scrolling loads the next page', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(0, paginated([main], 20, { currentPage: 1, lastPage: 2 }));
        await wrapper.vm.$nextTick();
        await scrollToEnd(wrapper);

        expect(getMock).toHaveBeenCalledTimes(2);
        expect(getMock.mock.calls[1][0]).toContain('page=2');

        resolveCall(1, paginated([annex], 20, { currentPage: 2, lastPage: 2 }));
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(2);
    });

    it('does not fetch another page once the last page is loaded', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(0, paginated([main], 20, { currentPage: 1, lastPage: 1 }));
        await wrapper.vm.$nextTick();
        await scrollToEnd(wrapper);

        expect(getMock).toHaveBeenCalledTimes(1);
    });

    it('discards a stale response that resolves after a newer search', async () => {
        vi.useFakeTimers();
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        const input = wrapper.get('input[type="search"]');
        await input.setValue('old');
        await vi.advanceTimersByTimeAsync(300);

        await input.setValue('new');
        await vi.advanceTimersByTimeAsync(300);

        expect(getMock).toHaveBeenCalledTimes(3);

        // Resolve the newer ("new") request first, then the stale ("old") one.
        resolveCall(2, paginated([{ id: 2, name: 'New warehouse' }]));
        await wrapper.vm.$nextTick();
        resolveCall(1, paginated([{ id: 1, name: 'Old warehouse' }]));
        await wrapper.vm.$nextTick();

        const names = wrapper
            .findAll('[data-testid="warehouse-option-name"]')
            .map((el) => el.text());
        expect(names).toEqual(['New warehouse']);
    });

    it('keeps resolving a selected id label once learned from a search page', async () => {
        const wrapper = mountSelect({ modelValue: ['1'] });
        await wrapper.get('button').trigger('click');

        expect(wrapper.get('button').text()).toBe('All');

        resolveCall(0, paginated([main]));
        await wrapper.vm.$nextTick();

        expect(wrapper.get('button > span:first-child').text()).toBe('Main');
    });

    it('closes the panel when Escape is pressed', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');
        expect(wrapper.find('[role="listbox"]').exists()).toBe(true);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="listbox"]').exists()).toBe(false);
    });

    it('keeps a selected warehouse pinned and checked after a new search replaces the results', async () => {
        const wrapper = mountSelect({ selected: [main], modelValue: ['1'] });

        await wrapper.get('button').trigger('click');
        resolveCall(0, paginated([annex]));
        await wrapper.vm.$nextTick();

        expect(wrapper.text()).toContain(t('cellLog.filters.selected'));

        const checkboxes = wrapper.findAll<HTMLInputElement>(
            'input[type="checkbox"]',
        );
        expect(checkboxes).toHaveLength(2);
        expect(checkboxes[0].element.checked).toBe(true);
        expect(checkboxes[1].element.checked).toBe(false);

        const names = wrapper
            .findAll('[data-testid="warehouse-option-name"]')
            .map((el) => el.text());
        expect(names).toEqual(['Main', 'Annex']);
    });

    it('separates each option row from the next with a divider', async () => {
        const wrapper = mountSelect();
        await wrapper.get('button').trigger('click');

        resolveCall(0, paginated([main, annex]));
        await wrapper.vm.$nextTick();

        const options = wrapper.findAll('[role="option"]');
        expect(options[0].element.parentElement?.className).toContain(
            'divide-y',
        );
    });

    it('does not duplicate a selected warehouse that also appears in the current search results', async () => {
        const wrapper = mountSelect({ selected: [main], modelValue: ['1'] });

        await wrapper.get('button').trigger('click');
        resolveCall(0, paginated([main, annex]));
        await wrapper.vm.$nextTick();

        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(2);
        expect(
            wrapper.findAll('[data-testid="warehouse-option-name"]'),
        ).toHaveLength(2);
    });

    it('lets an admin unselect a pinned warehouse directly, without re-searching for it', async () => {
        const wrapper = mountSelect({ selected: [main], modelValue: ['1'] });

        await wrapper.get('button').trigger('click');
        resolveCall(0, paginated([]));
        await wrapper.vm.$nextTick();

        await wrapper.get('input[type="checkbox"]').setValue(false);

        expect(wrapper.emitted('update:modelValue')).toEqual([[[]]]);
    });

    it('moves focus between options with the arrow keys', async () => {
        const wrapper = mount(FilterWarehouseSelect, {
            attachTo: document.body,
            props: {
                id: 'filter-warehouse-keys',
                label: 'Warehouse',
                allLabel: 'All',
                selectedCountLabel: (count: number) => `${count} selected`,
                selected: [main],
                modelValue: ['1'],
            },
        });

        await wrapper.get('button').trigger('click');
        resolveCall(0, paginated([annex]));
        await wrapper.vm.$nextTick();

        const checkboxes = wrapper.findAll<HTMLInputElement>(
            'input[type="checkbox"]',
        );
        checkboxes[0].element.focus();
        await checkboxes[0].trigger('keydown', { key: 'ArrowDown' });

        expect(document.activeElement).toBe(checkboxes[1].element);

        wrapper.unmount();
    });

    it('deselects every selected warehouse with one click and hides the button when none are selected', async () => {
        const wrapper = mountSelect({
            selected: [main, annex],
            modelValue: ['1', '2'],
        });

        await wrapper.get('button').trigger('click');
        resolveCall(0, paginated([]));
        await wrapper.vm.$nextTick();

        const deselectAll = wrapper.get(
            '[data-testid="deselect-all-warehouses"]',
        );
        expect(deselectAll.text()).toBe(t('cellLog.filters.deselectAll'));

        await deselectAll.trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[[]]]);

        const empty = mountSelect();
        await empty.get('button').trigger('click');
        resolveCall(1, paginated([]));
        await empty.vm.$nextTick();

        expect(
            empty.find('[data-testid="deselect-all-warehouses"]').exists(),
        ).toBe(false);
    });
});
