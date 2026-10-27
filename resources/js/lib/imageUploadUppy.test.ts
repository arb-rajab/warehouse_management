import { beforeEach, expect, it, vi } from 'vitest';
import { compressUploadImage, ImageUploadError } from '@/lib/imageUpload';
import type * as ImageUploadModule from '@/lib/imageUpload';
import { prepareUppyImages } from '@/lib/imageUploadUppy';
import type { ImageUploadUppy } from '@/lib/imageUploadUppy';
vi.mock('@/lib/imageUpload', async (importOriginal) => ({
    ...(await importOriginal<typeof ImageUploadModule>()),
    compressUploadImage: vi.fn(),
}));
const compress = vi.mocked(compressUploadImage);
const messages = {
    preparing: 'Preparing',
    animated: 'Choose a still image',
    tooLarge: 'Too large',
};
function uploader() {
    const file = {
        id: '1',
        name: 'photo.jpg',
        type: 'image/jpeg',
        size: 1000,
        data: new File(['original'], 'photo.jpg', { type: 'image/jpeg' }),
    };
    const listeners = new Map<string, Parameters<ImageUploadUppy['on']>[1]>();
    const uppy: ImageUploadUppy = {
        getFile: vi.fn(() => file),
        setFileState: vi.fn(),
        emit: vi.fn(),
        info: vi.fn(),
        on: vi.fn((event, callback) => listeners.set(event, callback)),
        off: vi.fn((event) => {
            listeners.delete(event);
        }),
    };

    return { uppy, file, listeners };
}
beforeEach(() => {
    compress.mockReset();
});
it('replaces data and size before transport while keeping Uppy progress and display name', async () => {
    const { uppy, file } = uploader();
    const result = new File(['small'], 'photo.jpg', { type: 'image/jpeg' });
    compress.mockResolvedValue(result);
    await prepareUppyImages(uppy, ['1'], messages);
    expect(compress).toHaveBeenCalledWith(
        file.data,
        1500,
        expect.any(AbortSignal),
    );
    expect(uppy.setFileState).toHaveBeenCalledWith('1', {
        data: result,
        size: 5,
        type: 'image/jpeg',
    });
    expect(uppy.emit).toHaveBeenCalledWith('preprocess-progress', file, {
        mode: 'indeterminate',
        message: messages.preparing,
    });
    expect(uppy.emit).toHaveBeenCalledWith('preprocess-complete', file);
    expect(uppy.off).toHaveBeenCalledWith('cancel-all', expect.any(Function));
});
it('shows an actionable error and prevents transport for animation', async () => {
    const { uppy } = uploader();
    compress.mockRejectedValue(new ImageUploadError('animated'));
    await expect(prepareUppyImages(uppy, ['1'], messages)).rejects.toThrow(
        messages.animated,
    );
    expect(uppy.setFileState).not.toHaveBeenCalled();
    expect(uppy.info).toHaveBeenCalledWith(messages.animated, 'error', 5000);
});
it('stops compression when Uppy cancels and never overwrites a removed file', async () => {
    const { uppy, listeners } = uploader();
    compress.mockImplementation(async (_file, _dimension, signal) => {
        listeners.get('cancel-all')?.();
        signal!.throwIfAborted();

        return _file;
    });
    await expect(
        prepareUppyImages(uppy, ['1'], messages),
    ).rejects.toMatchObject({ name: 'AbortError' });
    expect(uppy.setFileState).not.toHaveBeenCalled();
});
