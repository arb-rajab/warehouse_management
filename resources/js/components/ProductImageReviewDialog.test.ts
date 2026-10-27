import { flushPromises, mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    store,
    updateImage,
} from '@/actions/App/Http/Controllers/Admin/ProductImageReviewController';
import ProductImageReviewFields from '@/components/ProductImageReviewFields.vue';
import { t } from '@/lib/i18n';
import { compressUploadImage } from '@/lib/imageUpload';
import ProductImageReviewDialog from './ProductImageReviewDialog.vue';

const { submit } = vi.hoisted(() => ({ submit: vi.fn() }));
vi.mock('@/lib/imageUpload', async () => ({
    ...(await vi.importActual<Record<string, unknown>>('@/lib/imageUpload')),
    compressUploadImage: vi.fn(),
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createUseFormStub } = await import('@/testing/inertiaStubs');

    return {
        useForm: createUseFormStub(submit),
        useHttp: () => ({ get: vi.fn() }),
    };
});
describe('ProductImageReviewDialog', () => {
    beforeEach(() => {
        submit.mockReset();
        vi.mocked(compressUploadImage).mockImplementation(async (file) => file);
    });
    it('submits notes and the reviewed source through the review action, preserving list filters', async () => {
        const wrapper = mount(ProductImageReviewDialog, {
            props: { productId: 7, open: true },
            global: { stubs: { ProductImageReviewFields: true } },
        });
        const fields = wrapper.getComponent(ProductImageReviewFields);
        fields.vm.$emit('ready', true);
        fields.vm.$emit('update:status', 'doesnt_match');
        fields.vm.$emit('update:note', 'Different package');
        fields.vm.$emit('update:source', 'https://example.com/image.jpg');
        await flushPromises();
        await wrapper.findAll('form')[0].trigger('submit');
        expect(submit.mock.calls[0][0]).toEqual(store(7, { mergeQuery: {} }));
        expect(submit.mock.calls[0][1]).toMatchObject({
            image_review_note: 'Different package',
            image_review_status: 'doesnt_match',
            image_source: 'https://example.com/image.jpg',
        });
        expect(wrapper.text()).toContain(t('imageReview.uploadHint'));
    });
    it('uploads a required image separately and closes after a successful save', async () => {
        const wrapper = mount(ProductImageReviewDialog, {
            props: { productId: 7, open: true },
            global: { stubs: { ProductImageReviewFields: true } },
        });
        const file = new File(['image'], 'new.jpg', { type: 'image/jpeg' });
        const input = wrapper.get('input[type=file]');
        expect(input.attributes('required')).toBeDefined();
        Object.defineProperty(input.element, 'files', { value: [file] });
        await input.trigger('change');
        await wrapper.findAll('form')[1].trigger('submit');
        await flushPromises();
        expect(submit.mock.calls[0][0]).toEqual(
            updateImage(7, { mergeQuery: {} }),
        );
        expect(submit.mock.calls[0][1]).toMatchObject({ image: file });
        submit.mock.calls[0][2].onSuccess();
        expect(wrapper.emitted('update:open')?.at(-1)).toEqual([false]);
    });
});

it('compresses to the warehouse limit and cancels a stale product upload', async () => {
    submit.mockReset();
    let done!: (file: File) => void;
    vi.mocked(compressUploadImage).mockImplementation(
        () =>
            new Promise((resolve) => {
                done = resolve;
            }),
    );
    const wrapper = mount(ProductImageReviewDialog, {
        props: { productId: 7, open: true },
        global: { stubs: { ProductImageReviewFields: true } },
    });
    const file = new File(['photo'], 'new.jpg', { type: 'image/jpeg' });
    const input = wrapper.get('input[type=file]');
    Object.defineProperty(input.element, 'files', { value: [file] });
    await input.trigger('change');
    await wrapper.findAll('form')[1].trigger('submit');
    expect(compressUploadImage).toHaveBeenCalledWith(
        file,
        1600,
        expect.any(AbortSignal),
    );
    expect(wrapper.text()).toContain(t('common.imageUpload.preparing'));
    await wrapper.setProps({ productId: 8 });
    done(file);
    await flushPromises();
    expect(submit).not.toHaveBeenCalled();
    wrapper.unmount();
});
