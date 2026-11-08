import { Head } from '@inertiajs/vue3';
import { TriangleAlert } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { show as helpShow } from '@/actions/App/Http/Controllers/Admin/HelpController';
import {
    create as warehousesCreate,
    destroy as warehouseDestroy,
    edit as warehouseEdit,
    index as warehousesIndex,
} from '@/actions/App/Http/Controllers/Admin/WarehouseController';
import { t } from '@/lib/i18n';
import { rowCells } from '@/testing/dom';
import { paginated, warehouse } from '@/testing/factories';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import type { Warehouse } from '@/types/admin';
import Index from './Index.vue';

const { usePageMock, routerPostMock, routerGetMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerPostMock: vi.fn(),
    routerGetMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { post: routerPostMock, get: routerGetMock },
    };
});

function mountPage(
    warehouses: Warehouse[],
    errors: Partial<Record<'warehouse', string>> = {},
    perPage = 20,
) {
    usePageMock.mockReturnValue({
        url: warehousesIndex.url(),
        props: defaultAuthProps({ errors }),
    });

    return mount(Index, {
        props: {
            warehouses: paginated(warehouses),
            filters: { per_page: perPage },
        },
    });
}

describe('Warehouses Index', () => {
    beforeEach(() => {
        resetMocks({ usePageMock, routerPostMock, routerGetMock });
    });

    it('renders the page title in the Head and the PageHeader', () => {
        const wrapper = mountPage([]);

        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('warehouses.index.title'),
        );
        expect(wrapper.get('h1').text()).toBe(t('warehouses.index.title'));
    });

    it('renders every column header', () => {
        const wrapper = mountPage([]);

        expect(wrapper.findAll('thead th').map((th) => th.text())).toEqual([
            t('warehouses.index.columnName'),
            t('warehouses.index.columnCode'),
            t('warehouses.index.columnAddress'),
            t('warehouses.index.columnRows'),
            t('warehouses.index.columnAction'),
        ]);
    });

    it('shows the empty message when there are no warehouses', () => {
        const wrapper = mountPage([]);

        expect(wrapper.text()).toContain(t('warehouses.index.empty'));
    });

    it('links the "Add warehouse" button to the create page', () => {
        const wrapper = mountPage([]);

        const addLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('warehouses.index.addWarehouse'));
        expect(addLink?.attributes('href')).toBe(warehousesCreate.url());
    });

    it('links the help icon to the warehouses help page', () => {
        const wrapper = mountPage([]);

        const helpLink = wrapper
            .findAll('a')
            .find((a) => a.attributes('aria-label') === t('help.viewHelp'));
        expect(helpLink?.attributes('href')).toBe(
            helpShow.url({ topic: 'warehouses' }),
        );
    });

    it('renders the name, code, address and row count of a warehouse', () => {
        const wrapper = mountPage([
            warehouse({
                name: 'Annex',
                code: 'ANX',
                address: '2 Quay Street',
                rows_count: 3,
            }),
        ]);

        const cells = rowCells(wrapper);
        expect(cells[0].text()).toBe('Annex');
        expect(cells[1].text()).toBe('ANX');
        expect(cells[2].text()).toBe('2 Quay Street');
        expect(cells[3].text()).toBe('3');
    });

    it('shows a dash for a missing code or address', () => {
        const wrapper = mountPage([warehouse({ code: null, address: null })]);

        const cells = rowCells(wrapper);
        expect(cells[1].text()).toBe('—');
        expect(cells[2].text()).toBe('—');
    });

    it("links a warehouse's edit action to its edit page", () => {
        const wrapper = mountPage([warehouse({ id: 4 })]);

        const editLink = wrapper
            .findAll('a')
            .find((a) => a.text().includes(t('warehouses.index.edit')));
        expect(editLink?.attributes('href')).toBe(
            warehouseEdit.url({ warehouse: 4 }),
        );
    });

    it('offers a confirmed delete action for a warehouse with no rows', () => {
        const wrapper = mountPage([warehouse({ id: 4, rows_count: 0 })]);

        const deleteButton = wrapper
            .findAll('button')
            .find((b) => b.text().includes(t('warehouses.index.delete')));
        expect(deleteButton?.attributes('href')).toBe(
            warehouseDestroy.url({ warehouse: 4 }),
        );
        expect(wrapper.text()).not.toContain(t('warehouses.index.hasRows'));
    });

    it('hides the delete action and shows a "Has rows" badge for a warehouse that has rows', () => {
        const wrapper = mountPage([warehouse({ rows_count: 2 })]);

        const deleteButton = wrapper
            .findAll('button')
            .find((b) => b.text().includes(t('warehouses.index.delete')));
        expect(deleteButton).toBeUndefined();
        expect(wrapper.text()).toContain(t('warehouses.index.hasRows'));
        expect(wrapper.findComponent(TriangleAlert).exists()).toBe(true);
    });

    it('shows no banner when the backend reports no error', () => {
        const wrapper = mountPage([]);

        expect(wrapper.find('[role="alert"]').exists()).toBe(false);
    });

    it('shows the backend\'s "has rows" refusal in a banner when deleting is rejected', () => {
        const wrapper = mountPage([], {
            warehouse: 'Cannot delete a warehouse that still has rows.',
        });

        expect(wrapper.get('[role="alert"]').text()).toBe(
            'Cannot delete a warehouse that still has rows.',
        );
    });

    it('preselects the current per-page value in the page-size selector', () => {
        const wrapper = mountPage([], {}, 50);

        expect((wrapper.get('select').element as HTMLSelectElement).value).toBe(
            '50',
        );
    });

    it('requests the new page size when the selector changes', async () => {
        const wrapper = mountPage([], {}, 20);

        await wrapper.get('select').setValue('50');

        expect(routerGetMock).toHaveBeenCalledWith(
            warehousesIndex.url(),
            { per_page: 50 },
            { preserveState: true, replace: true },
        );
    });
});
