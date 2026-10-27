import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import {
    compressUploadImage,
    IMAGE_MAX_BYTES,
    ImageUploadError,
} from '@/lib/imageUpload';

const jpeg = new Uint8Array([0xff, 0xd8, 0xff, 0xd9]);
function image(
    bytes = jpeg,
    type = 'image/jpeg',
    size = 2 * IMAGE_MAX_BYTES,
): File {
    const file = new File([bytes], 'photo.jpg', { type, lastModified: 123 });
    Object.defineProperties(file, {
        arrayBuffer: { value: async () => bytes.buffer },
        size: { value: size },
    });

    return file;
}
let bitmap: { width: number; height: number; close: ReturnType<typeof vi.fn> };
let draw: ReturnType<typeof vi.fn>;
let encode: ReturnType<typeof vi.spyOn>;
beforeEach(() => {
    bitmap = { width: 3000, height: 2000, close: vi.fn() };
    draw = vi.fn();
    vi.stubGlobal('createImageBitmap', vi.fn().mockResolvedValue(bitmap));
    vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue({
        drawImage: draw,
    } as unknown as CanvasRenderingContext2D);
    encode = vi
        .spyOn(HTMLCanvasElement.prototype, 'toBlob')
        .mockImplementation((callback, type) =>
            callback(new Blob(['compressed'], { type })),
        );
});
afterEach(() => {
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});
it('resizes to the use-case limit, applies EXIF decoding and keeps filename, MIME and timestamp', async () => {
    const file = image();
    const result = await compressUploadImage(file, 1600);
    expect(createImageBitmap).toHaveBeenCalledWith(file, {
        imageOrientation: 'from-image',
    });
    expect(draw).toHaveBeenCalledWith(bitmap, 0, 0, 1600, 1067);
    expect(result).not.toBe(file);
    expect([
        result.name,
        result.type,
        result.lastModified,
        result.size,
    ]).toEqual(['photo.jpg', 'image/jpeg', 123, 10]);
    expect(bitmap.close).toHaveBeenCalled();
});
it('keeps small images byte-for-byte and never upscales', async () => {
    bitmap.width = 80;
    bitmap.height = 60;
    const file = image(jpeg, 'image/jpeg', 100);
    expect(await compressUploadImage(file)).toBe(file);
    expect(encode).not.toHaveBeenCalled();
    await compressUploadImage(image());
    expect(draw).toHaveBeenCalledWith(bitmap, 0, 0, 80, 60);
});
it('progressively resizes lossless images until they fit the byte budget', async () => {
    encode.mockImplementationOnce((callback: BlobCallback) =>
        callback(
            new Blob([new Uint8Array(IMAGE_MAX_BYTES + 1)], {
                type: 'image/jpeg',
            }),
        ),
    );
    const result = await compressUploadImage(image());
    expect(result.size).toBeLessThanOrEqual(IMAGE_MAX_BYTES);
    expect(draw.mock.calls.map((call) => call.slice(3))).toEqual([
        [1500, 1000],
        [1200, 800],
    ]);
});
it('preserves originals for documents, unsupported decoders, failed encoding and unavailable APIs', async () => {
    const pdf = new File(['%PDF'], 'license.pdf', { type: 'application/pdf' });
    expect(await compressUploadImage(pdf)).toBe(pdf);
    const file = image();
    vi.mocked(createImageBitmap).mockRejectedValueOnce(
        new Error('unsupported'),
    );
    expect(await compressUploadImage(file)).toBe(file);
    encode.mockImplementationOnce((callback: BlobCallback) => callback(null));
    expect(await compressUploadImage(file)).toBe(file);
    encode.mockImplementationOnce((callback: BlobCallback) =>
        callback(new Blob(['png fallback'], { type: 'image/png' })),
    );
    expect(await compressUploadImage(file)).toBe(file);
    vi.stubGlobal('createImageBitmap', undefined);
    expect(await compressUploadImage(file)).toBe(file);
});
it('rejects pixel bombs and frees the decoder', async () => {
    bitmap.width = 10000;
    bitmap.height = 10000;
    await expect(compressUploadImage(image())).rejects.toMatchObject({
        code: 'tooLarge',
    });
    expect(bitmap.close).toHaveBeenCalled();
    expect(draw).not.toHaveBeenCalled();
});
it('rejects animated GIF, WebP and PNG without decoding or flattening', async () => {
    const gif = Uint8Array.from(
        atob('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='),
        (c) => c.charCodeAt(0),
    );
    const frames = new Uint8Array([...gif.slice(0, -1), ...gif.slice(19)]);
    const png = new Uint8Array([
        137, 80, 78, 71, 13, 10, 26, 10, 0, 0, 0, 0, 97, 99, 84, 76, 0, 0, 0, 0,
    ]);
    const webp = new Uint8Array([
        ...new TextEncoder().encode('RIFF0000WEBPVP8X0000'),
        2,
    ]);

    for (const bytes of [frames, png, webp]) {
        await expect(compressUploadImage(image(bytes))).rejects.toBeInstanceOf(
            ImageUploadError,
        );
    }

    expect(createImageBitmap).not.toHaveBeenCalled();
});
it('cancels pending compression and closes the decoded bitmap', async () => {
    const controller = new AbortController();
    vi.mocked(createImageBitmap).mockImplementationOnce(async () => {
        controller.abort();

        return bitmap as unknown as ImageBitmap;
    });
    await expect(
        compressUploadImage(image(), 1500, controller.signal),
    ).rejects.toMatchObject({ name: 'AbortError' });
    expect(bitmap.close).toHaveBeenCalled();
    expect(encode).not.toHaveBeenCalled();
});
