import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import ProductImageReviewFields from './ProductImageReviewFields.vue';

const { get } = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return { useHttp: () => ({ get }), Link: createLinkStub() };
});
const response = {
    id: 7,
    name: 'Widgets',
    ar_name: '',
    image_url: 'https://example.com/current.jpg',
    image_source: 'https://example.com/current.jpg',
    barcodes: ['ABC-123'],
    reviews: {
        data: [
            {
                id: 1,
                status: 'doesnt_match',
                note: 'Old packaging',
                image_url: 'https://example.com/old.jpg',
                user: { id: 3, name: 'Sam' },
                created_at: '2026-10-24T09:00:00Z',
            },
        ],
        current_page: 1,
        last_page: 2,
    },
};
function mountFields(history = false) {
    return mount(ProductImageReviewFields, {
        props: {
            productId: 7,
            idPrefix: 'test-review',
            status: '',
            note: '',
            source: '',
            history,
        },
    });
}

describe('ProductImageReviewFields', () => {
    beforeEach(() => {
        get.mockReset();
        get.mockImplementation(async (_url, options) => {
            options.onSuccess(response);

            return response;
        });
    });
    it('shows the reference, product identifiers and required result alongside an optional bounded note', async () => {
        const wrapper = mountFields();
        await flushPromises();
        expect(wrapper.text()).toContain('Widgets');
        expect(wrapper.text()).toContain('ABC-123');
        expect(wrapper.get('img').attributes('src')).toBe(response.image_url);

        for (const key of [
            'productCode',
            'instructions',
            'title',
            'note',
            'choose',
            'matches',
            'doesnt_match',
        ]) {
            expect(wrapper.text()).toContain(t(`imageReview.${key}`));
        }

        expect(wrapper.get('select').attributes('required')).toBeDefined();
        expect(wrapper.get('textarea').attributes('required')).toBeUndefined();
        expect(wrapper.get('textarea').attributes('maxlength')).toBe('1000');
        await wrapper.get('select').setValue('doesnt_match');
        await wrapper.get('textarea').setValue('Wrong variant');
        expect(wrapper.emitted('update:status')?.at(-1)).toEqual([
            'doesnt_match',
        ]);
        expect(wrapper.emitted('update:note')?.at(-1)).toEqual([
            'Wrong variant',
        ]);
        expect(wrapper.emitted('update:source')?.at(-1)).toEqual([
            response.image_url,
        ]);
    });
    it('offers Image missing only when no reference exists', async () => {
        get.mockImplementation(async (_url, options) =>
            options.onSuccess({
                ...response,
                image_url: null,
                image_source: '',
            }),
        );
        const wrapper = mountFields();
        await flushPromises();
        expect(wrapper.text()).toContain(t('imageReview.noImage'));
        expect(wrapper.find('option[value="image_missing"]').exists()).toBe(
            true,
        );
        expect(wrapper.find('option[value="matches"]').exists()).toBe(false);
        expect(wrapper.find('img').exists()).toBe(false);
    });
    it('retains old notes and reviewers in paginated history', async () => {
        const wrapper = mountFields(true);
        await flushPromises();
        expect(wrapper.text()).toContain('Old packaging');
        expect(wrapper.text()).toContain('Sam');
        expect(wrapper.text()).toContain(t('imageReview.history'));
        expect(wrapper.findAll('img')[1].attributes('src')).toBe(
            'https://example.com/old.jpg',
        );
        await wrapper.get('button').trigger('click');
        expect(get.mock.calls.at(-1)?.[0]).toContain('page=2');
    });
    it('shows failures with a retry and keeps submission unready', async () => {
        get.mockRejectedValueOnce(new Error('offline'));
        const wrapper = mountFields();
        await flushPromises();
        expect(wrapper.text()).toContain(t('imageReview.loadError'));
        expect(wrapper.emitted('ready')?.at(-1)).toEqual([false]);
        await wrapper.get('button').trigger('click');
        await flushPromises();
        expect(wrapper.emitted('ready')?.at(-1)).toEqual([true]);
    });
    it('ignores late responses after another product is selected', async () => {
        let finish: (response: unknown) => void = () => {};
        get.mockImplementationOnce(
            (_url, options) =>
                new Promise((resolve) => {
                    finish = (body) => {
                        options.onSuccess(body);
                        resolve(body);
                    };
                }),
        );
        const wrapper = mountFields();
        await wrapper.setProps({ productId: 8 });
        await flushPromises();
        finish({ ...response, id: 7, name: 'Stale product' });
        await flushPromises();
        expect(wrapper.text()).not.toContain('Stale product');
    });
});
