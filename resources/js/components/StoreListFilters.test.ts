import { Form } from '@inertiajs/vue3';
import { Check, LoaderCircle, SlidersHorizontal } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import FilterDialog from '@/components/FilterDialog.vue';
import FilterDialogFooter from '@/components/FilterDialogFooter.vue';
import { countBadgeClass, filterTriggerButtonClass } from '@/lib/filters';
import { t } from '@/lib/i18n';
import StoreListFilters from './StoreListFilters.vue';

const { formState, submitMock } = vi.hoisted(() => ({
    formState: {
        errors: {} as Record<string, string>,
        processing: false,
    },
    submitMock: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub } = await import('@/testing/inertiaStubs');

    return {
        Form: createFormStub(
            () => formState,
            (data: FormData) => submitMock(Object.fromEntries(data.entries())),
        ),
    };
});

const action = { url: '/temp/admin/products', method: 'get' as const };

const inlineSlot = `<template #default="{ errors, processing }">
    <input type="hidden" name="per_page" value="50" />
    <input id="search" name="search" value="milk" />
    <select id="seller" name="user_id">
        <option value="">All sellers</option>
        <option value="4" selected>Seller A</option>
    </select>
    <span id="inline-error">{{ errors.search }}</span>
    <span id="inline-processing">{{ processing }}</span>
</template>`;

const dialogSlot = `<template #filters="{ errors }">
    <select id="category" name="category_id">
        <option value="">All categories</option>
        <option value="7" selected>Food</option>
    </select>
    <input id="featured" type="checkbox" name="featured" value="1" checked />
    <span id="dialog-error">{{ errors.category_id }}</span>
</template>`;

let wrapper: VueWrapper | null = null;

function mountFilters(activeCount = 0): VueWrapper {
    wrapper = mount(StoreListFilters, {
        attachTo: document.body,
        props: { action, activeCount },
        slots: { default: inlineSlot, filters: dialogSlot },
    });

    return wrapper;
}

/** The trigger is the form's only direct `type="button"` child. */
function trigger(current: VueWrapper) {
    return current.get('form > button[type="button"]');
}

function dialogOverlay(current: VueWrapper): HTMLElement {
    return current.get('.fixed.inset-0.z-50').element as HTMLElement;
}

describe('StoreListFilters', () => {
    beforeEach(() => {
        formState.errors = {};
        formState.processing = false;
        submitMock.mockReset();
    });

    afterEach(() => {
        wrapper?.unmount();
        wrapper = null;
    });

    it('wraps a GET form for the given action, with the inline slot and its own apply button', () => {
        const current = mountFilters();
        const form = current.getComponent(Form);
        const apply = current.get('form > button[type="submit"]');

        expect(form.props('action')).toEqual(action);
        expect(form.attributes('id')).toBe('store-list-filters');
        expect(current.get('#search').attributes('name')).toBe('search');
        expect(current.get('#seller').attributes('name')).toBe('user_id');
        expect(current.get('input[type="hidden"]').attributes()).toMatchObject({
            name: 'per_page',
            value: '50',
        });
        expect(apply.text()).toBe(t('common.filters.apply'));
        expect(apply.findComponent(Check).exists()).toBe(true);
        expect(apply.attributes('disabled')).toBeUndefined();
    });

    it('labels the trigger and shows no badge while no dialog filter is active', () => {
        const button = trigger(mountFilters(0));

        expect(button.text()).toBe(t('common.filters.title'));
        expect(button.classes()).toEqual(filterTriggerButtonClass.split(' '));
        expect(button.findComponent(SlidersHorizontal).exists()).toBe(true);
        expect(button.find('span').exists()).toBe(false);
    });

    it('shows the active dialog filter count in a badge with screen reader text', () => {
        const badge = trigger(mountFilters(3)).get('span');

        expect(badge.classes()).toEqual(countBadgeClass.split(' '));
        expect(badge.get('[aria-hidden="true"]').text()).toBe('3');
        expect(badge.get('.sr-only').text()).toBe(
            t('store.listFilters.activeCount', { count: 3 }),
        );
    });

    it('keeps the dialog fields mounted but hidden until the trigger opens the dialog', async () => {
        const current = mountFilters();
        const dialog = current.getComponent(FilterDialog);

        expect(dialog.props()).toMatchObject({
            open: false,
            keepMounted: true,
            title: t('common.filters.title'),
            closeLabel: t('common.filters.close'),
        });
        expect(dialogOverlay(current).style.display).toBe('none');
        expect(current.find('#category').exists()).toBe(true);

        await trigger(current).trigger('click');
        await nextTick();

        expect(dialog.props('open')).toBe(true);
        expect(dialogOverlay(current).style.display).toBe('');
        expect(current.get('[role="dialog"]').attributes('aria-label')).toBe(
            t('common.filters.title'),
        );
        expect(document.activeElement?.id).toBe('category');
    });

    it('closes the dialog from its close button and restores focus to the trigger', async () => {
        const current = mountFilters();
        const button = trigger(current);

        (button.element as HTMLElement).focus();
        await button.trigger('click');
        await nextTick();
        await current
            .get(`[aria-label="${t('common.filters.close')}"]`)
            .trigger('click');
        await nextTick();

        expect(current.getComponent(FilterDialog).props('open')).toBe(false);
        expect(dialogOverlay(current).style.display).toBe('none');
        expect(document.activeElement).toBe(button.element);
    });

    it('puts Apply and Clear in the dialog footer, bound to the form by id', async () => {
        const current = mountFilters();
        await trigger(current).trigger('click');
        const footer = current.getComponent(FilterDialogFooter);

        expect(footer.props()).toEqual({
            formId: 'store-list-filters',
            applyLabel: t('common.filters.apply'),
            clearLabel: t('common.filters.clear'),
        });
        expect(footer.get('button[type="submit"]').attributes('form')).toBe(
            'store-list-filters',
        );
        expect(footer.text()).toContain(t('common.filters.clear'));
    });

    it('submits search, key filter, hidden fields and dialog filters together from the dialog Apply', async () => {
        const current = mountFilters();
        await trigger(current).trigger('click');

        await current
            .getComponent(FilterDialogFooter)
            .get('button[type="submit"]')
            .trigger('click');

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect(submitMock).toHaveBeenCalledWith({
            per_page: '50',
            search: 'milk',
            user_id: '4',
            category_id: '7',
            featured: '1',
        });
    });

    it('keeps the dialog filters when the inline apply submits with the dialog closed', async () => {
        const current = mountFilters();

        await current.get('form > button[type="submit"]').trigger('click');

        expect(submitMock).toHaveBeenCalledWith({
            per_page: '50',
            search: 'milk',
            user_id: '4',
            category_id: '7',
            featured: '1',
        });
    });

    it('clears only the dialog filters, keeping search, the key filter and hidden fields', async () => {
        const current = mountFilters(2);
        await trigger(current).trigger('click');

        await current
            .getComponent(FilterDialogFooter)
            .get('button[type="button"]')
            .trigger('click');

        expect(submitMock).toHaveBeenCalledTimes(1);
        expect(submitMock).toHaveBeenCalledWith({
            per_page: '50',
            search: 'milk',
            user_id: '4',
        });
        expect(
            (current.get('#category').element as HTMLSelectElement).disabled,
        ).toBe(false);
        expect(
            (current.get('#featured').element as HTMLInputElement).disabled,
        ).toBe(false);
    });

    it('closes the dialog once the filtered list has loaded', async () => {
        const current = mountFilters();
        await trigger(current).trigger('click');

        current.getComponent(Form).vm.$emit('success');
        await nextTick();

        expect(current.getComponent(FilterDialog).props('open')).toBe(false);
    });

    it('passes form errors to both slots', () => {
        formState.errors = {
            search: 'Search is too long.',
            category_id: 'Unknown category.',
        };
        const current = mountFilters();

        expect(current.get('#inline-error').text()).toBe('Search is too long.');
        expect(current.get('#dialog-error').text()).toBe('Unknown category.');
    });

    it('disables its apply button and says it is applying while processing', () => {
        formState.processing = true;
        const current = mountFilters();
        const apply = current.get('form > button[type="submit"]');

        expect(apply.attributes('disabled')).toBeDefined();
        expect(apply.text()).toBe(t('store.listFilters.applying'));
        expect(apply.findComponent(LoaderCircle).exists()).toBe(true);
        expect(current.get('#inline-processing').text()).toBe('true');
    });
});
