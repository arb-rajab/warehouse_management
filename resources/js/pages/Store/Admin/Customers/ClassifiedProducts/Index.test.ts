import { Form, Head, Link } from '@inertiajs/vue3';
import { enableAutoUnmount, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import {
    customer_product_index as classifiedProductsIndex,
    destroy_by_admin as destroy,
    updatePublished,
} from '@/actions/App/Http/Controllers/Store/Admin/ClassifiedProductController';
import DataTable from '@/components/DataTable.vue';
import PageHeader from '@/components/PageHeader.vue';
import Pagination from '@/components/Pagination.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { i18n, t } from '@/lib/i18n';
import {
    paginated,
    storeClassifiedProduct,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { StoreClassifiedProduct } from '@/types/store';
import Index from './Index.vue';

const { usePageMock, routerGetMock, slotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    slotPropsMock: vi.fn(
        (): { processing: boolean; errors: Record<string, string> } => ({
            processing: false,
            errors: {},
        }),
    ),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(slotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: routerGetMock, post: vi.fn() },
    };
});
enableAutoUnmount(afterEach);
const permissions = [
    'view_classified_products',
    'publish_classified_product',
    'delete_classified_product',
];
function render(allowed = permissions, products = [storeClassifiedProduct()]) {
    usePageMock.mockReturnValue({
        url: classifiedProductsIndex.url(),
        props: defaultAuthProps({
            store: storeSharedProps({ permissions: allowed }),
        }),
    });
    const rows = paginated(products, 10, { currentPage: 2, lastPage: 3 });
    rows.meta.from = products.length ? 11 : null;

    return mount(Index, {
        props: { products: rows, filters: { per_page: 10 } },
    });
}
beforeEach(() => {
    vi.clearAllMocks();
    slotPropsMock.mockReturnValue({ processing: false, errors: {} });
});
afterEach(() => {
    i18n.global.locale.value = 'en';
    vi.restoreAllMocks();
});

describe('classified products', () => {
    it.each(['en', 'ar'] as const)(
        'full mounts the legacy row and all labels in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const rows = [
                storeClassifiedProduct(),
                storeClassifiedProduct({
                    id: 18,
                    name: '<script>customer name</script>',
                    added_by: '<b>seller</b>',
                    status: false,
                    published: true,
                }),
            ];
            const wrapper = render(permissions, rows);
            expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
            expect(wrapper.getComponent(Head).props('title')).toBe(
                t('store.classifiedProducts.title'),
            );
            expect(wrapper.getComponent(PageHeader).props('title')).toBe(
                t('store.classifiedProducts.title'),
            );
            expect(
                wrapper
                    .getComponent<
                        ComponentPublicInstance<{
                            rows: StoreClassifiedProduct[];
                            columns: string[];
                        }>
                    >(DataTable)
                    .props('rows'),
            ).toEqual(rows);
            expect(
                wrapper
                    .getComponent<
                        ComponentPublicInstance<{
                            rows: StoreClassifiedProduct[];
                            columns: string[];
                        }>
                    >(DataTable)
                    .props('columns'),
            ).toEqual(
                [
                    'number',
                    'name',
                    'image',
                    'uploadedBy',
                    'customerStatus',
                    'published',
                    'actions',
                ].map((key) => t(`store.classifiedProducts.${key}`)),
            );

            for (const key of [
                'number',
                'name',
                'image',
                'uploadedBy',
                'customerStatus',
                'published',
                'actions',
                'statusPublished',
                'statusUnpublished',
                'publish',
                'unpublish',
                'show',
                'delete',
            ]) {
                expect(wrapper.text()).toContain(
                    t(`store.classifiedProducts.${key}`),
                );
            }

            const first = wrapper.get('tbody tr');
            expect(first.get('td').text()).toBe('11');
            expect(wrapper.findAll('tbody tr')[1].get('td').text()).toBe('12');
            expect(first.get('img').attributes()).toMatchObject({
                src: rows[0].thumbnail_url,
                alt: t('store.classifiedProducts.image'),
            });
            expect(first.text()).toContain('customer');

            for (const anchor of wrapper.findAll('tbody a')) {
                expect(anchor.attributes()).toMatchObject({
                    href: rows[0].public_url,
                    target: '_blank',
                    rel: 'noopener',
                });
            }

            expect(wrapper.findAll('tbody script')).toHaveLength(0);
            expect(wrapper.findAll('tbody b')).toHaveLength(0);
            expect(wrapper.get('tbody').text()).toContain(
                '<script>customer name</script>',
            );
            expect(wrapper.getComponent(Pagination).props()).toMatchObject({
                links: wrapper.props('products').meta.links,
                perPage: 10,
            });
        },
    );

    it('passes bound actions, both publish directions and form processing/errors', async () => {
        const row = storeClassifiedProduct();
        const wrapper = render();
        const form = wrapper.findAllComponents(Form).find((form) => {
            const action = form.props('action');

            return (
                typeof action === 'object' &&
                action.url.includes('/classified_products/')
            );
        })!;
        expect(form.props()).toMatchObject({
            action: updatePublished(row, { mergeQuery: {} }),
            options: { preserveScroll: true },
        });
        expect(
            form.get<HTMLInputElement>('input[name="status"]').element.value,
        ).toBe('1');
        expect(form.get('[role="switch"]').attributes('aria-checked')).toBe(
            'false',
        );
        expect(form.get('[role="switch"]').attributes('aria-label')).toBe(
            t('store.classifiedProducts.toggle', { name: row.name }),
        );
        await wrapper.setProps({
            products: paginated([storeClassifiedProduct({ published: true })]),
        });
        expect(
            form.get<HTMLInputElement>('input[name="status"]').element.value,
        ).toBe('0');
        expect(form.get('[role="switch"]').attributes('aria-checked')).toBe(
            'true',
        );
        slotPropsMock.mockReturnValue({
            processing: true,
            errors: { status: 'Invalid status' },
        });
        const pending = render();
        expect(
            pending.get('tbody [role="switch"]').attributes('disabled'),
        ).toBeDefined();
        expect(pending.get('tbody').text()).toContain('Invalid status');
    });

    it('gates publishing and deletion independently', () => {
        const readonly = render(['view_classified_products']);
        expect(readonly.findComponent(TableActionLink).exists()).toBe(false);
        expect(
            readonly.get('tbody [role="switch"]').attributes('disabled'),
        ).toBeDefined();
        expect(readonly.find('tbody form').exists()).toBe(false);
        expect(readonly.findAll('tbody a')).toHaveLength(2);
        const publisher = render([
            'view_classified_products',
            'publish_classified_product',
        ]);
        expect(
            publisher.get('tbody [role="switch"]').attributes('disabled'),
        ).toBeUndefined();
        expect(publisher.findComponent(TableActionLink).exists()).toBe(false);
        const deleter = render([
            'view_classified_products',
            'delete_classified_product',
        ]);
        expect(
            deleter.get('tbody [role="switch"]').attributes('disabled'),
        ).toBeDefined();
        expect(deleter.getComponent(TableActionLink).props('href')).toEqual(
            destroy(storeClassifiedProduct(), { mergeQuery: {} }),
        );
    });

    it('wires the delete confirmation to cancel and accept with current query preserved', () => {
        window.history.replaceState({}, '', '?page=2&per_page=10');
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const wrapper = render();
        const action = wrapper.getComponent(TableActionLink);
        expect(action.props()).toMatchObject({
            href: destroy(storeClassifiedProduct(), { mergeQuery: {} }),
            variant: 'danger',
        });
        const link = action.getComponent(Link);
        expect(link.props('as')).toBe('button');
        expect(link.attributes('method')).toBe('delete');
        expect(link.props('href')).toEqual(
            destroy(storeClassifiedProduct(), { mergeQuery: {} }),
        );
        expect((link.props('onBefore') as () => boolean)()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            expect.stringContaining('Bicycle'),
        );
        confirm.mockReturnValue(true);
        expect((link.props('onBefore') as () => boolean)()).toBe(true);
        window.history.replaceState({}, '', '/');
    });

    it.each(['en', 'ar'] as const)(
        'shows the empty state and resets pagination on per-page changes in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = render(permissions, []);
            expect(wrapper.text()).toContain(
                t('store.classifiedProducts.empty'),
            );
            wrapper.getComponent(Pagination).vm.$emit('update:per-page', 50);
            expect(routerGetMock).toHaveBeenCalledWith(
                classifiedProductsIndex().url,
                { per_page: 50 },
                { preserveState: true, replace: true },
            );
        },
    );
});
