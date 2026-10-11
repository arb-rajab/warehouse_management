import { Form, Head, Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ComponentPublicInstance } from 'vue';
import {
    destroy,
    index as productsIndex,
    publish,
} from '@/actions/App/Http/Controllers/Store/Admin/ClassifiedProductController';
import DataTable from '@/components/DataTable.vue';
import Pagination from '@/components/Pagination.vue';
import SubmitButton from '@/components/SubmitButton.vue';
import TableActionLink from '@/components/TableActionLink.vue';
import StoreAdminLayout from '@/layouts/StoreAdminLayout.vue';
import { t } from '@/lib/i18n';
import {
    classifiedProduct,
    paginated,
    storeSharedProps,
} from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { ClassifiedProduct } from '@/types/store';
import Index from './Index.vue';

const { usePageMock, routerGetMock, formStateMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    routerGetMock: vi.fn(),
    formStateMock:
        vi.fn<() => { errors: Record<string, string>; processing: boolean }>(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, createFormStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        Form: createFormStub(() => formStateMock()),
        usePage: usePageMock,
        router: { get: routerGetMock },
    };
});
function mountPage(
    products: ClassifiedProduct[] = [],
    permissions = [
        'view_classified_products',
        'publish_classified_product',
        'delete_classified_product',
    ],
) {
    usePageMock.mockReturnValue({
        url: productsIndex.url(),
        props: defaultAuthProps({ store: storeSharedProps({ permissions }) }),
    });

    return mount(Index, {
        props: { products: paginated(products), filters: { per_page: 20 } },
    });
}
describe('Classified product administration', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        formStateMock.mockReturnValue({ errors: {}, processing: false });
    });
    afterEach(() => vi.restoreAllMocks());
    it('renders the layout, title, all headers and empty message', () => {
        const wrapper = mountPage();
        expect(wrapper.findComponent(StoreAdminLayout).exists()).toBe(true);
        expect(wrapper.getComponent(Head).props('title')).toBe(
            t('store.classifiedProducts.title'),
        );
        expect(wrapper.get('h1').text()).toBe('Classified products');
        expect(
            wrapper.getComponent<ComponentPublicInstance>(DataTable).props(),
        ).toMatchObject({
            rows: [],
            emptyMessage: 'No classified products found.',
            columns: [
                'Name',
                'Product image',
                'Uploaded by',
                'Customer status',
                'Published',
                'Actions',
            ],
        });
        expect(wrapper.text()).toContain('No classified products found.');
    });
    it('renders escaped names and uses full loads for the public product', () => {
        const product = classifiedProduct({
            name: '<script>bad</script>',
            thumbnail_url: 'https://example.com/photo.webp',
        });
        const wrapper = mountPage([product]);
        expect(wrapper.find('script').exists()).toBe(false);
        expect(wrapper.text()).toContain('<script>bad</script>');
        const links = wrapper.findAll(`a[href="${product.public_url}"]`);
        expect(links).toHaveLength(2);
        expect(
            links.every(
                (link) => link.attributes('rel') === 'noopener noreferrer',
            ),
        ).toBe(true);
        expect(wrapper.get('img').attributes('src')).toBe(
            product.thumbnail_url,
        );
        expect(wrapper.get('img').attributes('alt')).toBe('Product image');
        expect(wrapper.text()).toContain('View');
        expect(wrapper.text()).toContain('customer');
        expect(wrapper.text()).toContain('Published');
    });
    it('sends the opposite publication flag with its guarded action', () => {
        const product = classifiedProduct();
        const wrapper = mountPage([product]);
        expect(wrapper.getComponent(Form).props('action')).toEqual(
            publish(product, { mergeQuery: {} }),
        );
        expect(wrapper.get('input[name="published"]').attributes('value')).toBe(
            '1',
        );
        expect(wrapper.getComponent(SubmitButton).props()).toMatchObject({
            label: 'Publish',
            processingLabel: 'Saving…',
            processing: false,
        });
        const published = mountPage([classifiedProduct({ published: true })]);
        expect(
            published.get('input[name="published"]').attributes('value'),
        ).toBe('0');
        expect(published.text()).toContain('Unpublish');
    });
    it('renders a missing thumbnail without a broken image', () => {
        expect(mountPage([classifiedProduct()]).find('img').exists()).toBe(
            false,
        );
    });
    it('displays publication validation errors', () => {
        formStateMock.mockReturnValue({
            errors: { published: 'The published field must be true or false.' },
            processing: false,
        });
        expect(mountPage([classifiedProduct()]).text()).toContain(
            'The published field must be true or false.',
        );
    });
    it('read-only users see the status and have no mutation controls', () => {
        const wrapper = mountPage(
            [classifiedProduct({ status: false })],
            ['view_classified_products'],
        );
        expect(wrapper.findComponent(Form).exists()).toBe(false);
        expect(wrapper.findComponent(TableActionLink).exists()).toBe(false);
        expect(wrapper.text()).toContain('Unpublished');
    });
    it('wires DELETE confirmation and preserves pagination', () => {
        const product = classifiedProduct();
        const wrapper = mountPage([product]);
        const link = wrapper.getComponent(TableActionLink);
        expect(link.props('href')).toEqual(
            destroy(product, { mergeQuery: {} }),
        );
        expect(link.attributes('method')).toBe('delete');
        expect(link.text()).toBe('Delete');
        const confirm = vi.spyOn(window, 'confirm').mockReturnValue(false);
        const before = link
            .getComponent(Link)
            .props('onBefore') as () => boolean;
        expect(before()).toBe(false);
        expect(confirm).toHaveBeenCalledWith(
            t('common.confirmDelete', { label: 'Customer advert' }),
        );
    });
    it('passes links and per-page to pagination and reloads on change', () => {
        const wrapper = mountPage([classifiedProduct()]);
        const pagination = wrapper.getComponent(Pagination);
        expect(pagination.props()).toMatchObject({ links: [], perPage: 20 });
        pagination.vm.$emit('update:perPage', 10);
        expect(routerGetMock).toHaveBeenCalledWith(
            productsIndex.url(),
            { per_page: 10 },
            { preserveState: true, replace: true },
        );
    });
});
