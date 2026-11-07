import { mount } from '@vue/test-utils';
import { beforeEach, expect, it, vi } from 'vitest';
import StoreColumnPicker from '@/components/StoreColumnPicker.vue';
import { t } from '@/lib/i18n';
import { useTableColumns } from '@/lib/useTableColumns';
import {
    columnsDialog,
    offeredColumns,
    openColumnPicker,
    setColumnShown,
    storedColumns,
} from '@/testing/tableColumns';

const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ usePage: usePageMock }));

const name = t('store.customers.fields.name');
const email = t('store.customers.fields.email');
const phone = t('store.customers.fields.phone');
const country = t('store.customers.fields.country');

function pickerPage() {
    const state = useTableColumns({
        listId: 'test.picker',
        columns: [
            {
                key: 'name',
                label: 'store.customers.fields.name',
                required: true,
            },
            { key: 'email', label: 'store.customers.fields.email' },
            { key: 'phone', label: 'store.customers.fields.phone' },
            {
                key: 'country',
                label: 'store.customers.fields.country',
                defaultVisible: false,
            },
        ],
    });

    return {
        state,
        wrapper: mount(StoreColumnPicker, {
            props: { state },
            slots: { default: '<span id="bulk">Bulk action</span>' },
        }),
    };
}

beforeEach(() => {
    window.localStorage.clear();
    usePageMock.mockReturnValue({ props: { auth: { user: { id: 7 } } } });
});

it('puts the bulk actions on the start side and the Columns button on the end side', () => {
    const { wrapper } = pickerPage();
    const row = wrapper.get('div');
    expect(row.classes()).toEqual(
        expect.arrayContaining([
            'mb-4',
            'flex',
            'flex-wrap',
            'justify-between',
        ]),
    );
    expect(row.get('#bulk').text()).toBe('Bulk action');

    const button = row.get('button');
    expect(button.text()).toBe(t('store.table.columns'));
    // Logical margin: it mirrors under dir="rtl".
    expect(button.classes()).toContain('ms-auto');
    expect(button.classes().join(' ')).not.toMatch(/\b(ml|mr)-/);
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    wrapper.unmount();
});

it('opens a dialog listing every available column, with the required one locked', async () => {
    const { wrapper } = pickerPage();
    const dialog = await openColumnPicker(wrapper);
    expect(dialog.text()).toContain(t('store.table.columnsTitle'));
    expect(offeredColumns(wrapper)).toEqual([name, email, phone, country]);

    const box = (label: string) =>
        dialog
            .findAll('label')
            .find((item) => item.text() === label)!
            .get('input').element as HTMLInputElement;
    expect(box(name).checked).toBe(true);
    expect(box(name).disabled).toBe(true);
    expect(box(email).checked).toBe(true);
    expect(box(email).disabled).toBe(false);
    expect(box(country).checked).toBe(false);

    const hint = dialog.get(`#${box(name).getAttribute('aria-describedby')}`);
    expect(hint.text()).toBe(t('store.table.alwaysShown'));
    expect(box(email).getAttribute('aria-describedby')).toBeNull();
    wrapper.unmount();
});

it('toggles columns, shows all and resets, remembering each choice', async () => {
    const { state, wrapper } = pickerPage();
    await openColumnPicker(wrapper);
    await setColumnShown(wrapper, country, true);
    expect(state.visibleKeys.value).toEqual([
        'name',
        'email',
        'phone',
        'country',
    ]);
    expect(storedColumns('test.picker')).toContain('country');
    await setColumnShown(wrapper, email, false);
    expect(state.visibleKeys.value).toEqual(['name', 'phone', 'country']);
    expect(storedColumns('test.picker')).not.toContain('email');

    const dialog = columnsDialog(wrapper);
    const button = (label: string) =>
        dialog.findAll('button').find((item) => item.text() === label)!;
    await button(t('store.table.showAll')).trigger('click');
    expect(state.visibleKeys.value).toHaveLength(4);
    await button(t('store.table.reset')).trigger('click');
    expect(state.visibleKeys.value).toEqual(['name', 'email', 'phone']);
    expect(storedColumns('test.picker')).toEqual(['name', 'email', 'phone']);
    wrapper.unmount();
});

it('closes the dialog with its close button', async () => {
    const { wrapper } = pickerPage();
    await openColumnPicker(wrapper);
    await columnsDialog(wrapper)
        .findAll('button')
        .find((item) => item.attributes('aria-label') === t('common.close'))!
        .trigger('click');
    expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    wrapper.unmount();
});
