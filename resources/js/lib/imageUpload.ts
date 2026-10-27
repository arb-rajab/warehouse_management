/** Keep these limits aligned with the server's ImageOptimizer. */
export const IMAGE_MAX_BYTES = 1024 * 1024;
const MAX_INPUT_BYTES = 20 * 1024 * 1024;
const MAX_PIXELS = 40_000_000;

export class ImageUploadError extends Error {
    constructor(public readonly code: 'animated' | 'tooLarge') {
        super(code);
    }
}

function checkAborted(signal?: AbortSignal): void {
    if (signal?.aborted) {
        throw new DOMException('Image preparation cancelled.', 'AbortError');
    }
}

function animated(bytes: Uint8Array, type: string): boolean {
    const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);

    if (type === 'image/webp') {
        return (
            String.fromCharCode(...bytes.slice(12, 16)) === 'VP8X' &&
            !!(bytes[20] & 2)
        );
    }

    if (type === 'image/png') {
        for (let offset = 8; offset + 12 <= bytes.length;) {
            if (
                String.fromCharCode(...bytes.slice(offset + 4, offset + 8)) ===
                'acTL'
            ) {
                return true;
            }

            offset += view.getUint32(offset) + 12;
        }
    }

    if (type === 'image/gif') {
        let frames = 0;
        let offset = 13 + (bytes[10] & 128 ? 3 * (2 << (bytes[10] & 7)) : 0);

        while (offset < bytes.length) {
            const block = bytes[offset++];

            if (block === 0x3b) {
                break;
            }

            if (block === 0x2c) {
                if (++frames > 1) {
                    return true;
                }

                if (offset + 9 > bytes.length) {
                    break;
                }

                const packed = bytes[offset + 8];
                offset += 10 + (packed & 128 ? 3 * (2 << (packed & 7)) : 0);
            } else if (block === 0x21) {
                offset++;
            } else {
                break;
            }

            while (offset < bytes.length) {
                const size = bytes[offset++];

                if (!size) {
                    break;
                }

                offset += size;
            }
        }
    }

    return false;
}

function contentType(bytes: Uint8Array): string | undefined {
    if (bytes[0] === 0xff && bytes[1] === 0xd8) {
        return 'image/jpeg';
    }

    if (String.fromCharCode(...bytes.slice(0, 8)) === '\x89PNG\r\n\x1a\n') {
        return 'image/png';
    }

    if (String.fromCharCode(...bytes.slice(0, 3)) === 'GIF') {
        return 'image/gif';
    }

    if (
        String.fromCharCode(...bytes.slice(0, 4)) === 'RIFF' &&
        String.fromCharCode(...bytes.slice(8, 12)) === 'WEBP'
    ) {
        return 'image/webp';
    }

    return undefined;
}

/**
 * Reduce upload bandwidth. The server still validates and normalizes every file.
 * Decode with EXIF orientation applied; preserve the source format and alpha.
 * Unsupported encoders/decoders fall back to the original for server validation.
 */
export async function compressUploadImage(
    file: File,
    maxDimension = 1500,
    signal?: AbortSignal,
): Promise<File> {
    checkAborted(signal);

    if (
        !file.type.startsWith('image/') &&
        !/\.(jpe?g|png|webp|gif)$/i.test(file.name)
    ) {
        return file;
    }

    if (file.size > MAX_INPUT_BYTES || typeof file.arrayBuffer !== 'function') {
        return file;
    }

    let bytes: Uint8Array;

    try {
        bytes = new Uint8Array(await file.arrayBuffer());
    } catch {
        checkAborted(signal);

        return file;
    }

    checkAborted(signal);
    const type = contentType(bytes);

    if (!type) {
        return file;
    }

    if (animated(bytes, type)) {
        throw new ImageUploadError('animated');
    }

    if (type === 'image/gif' || typeof createImageBitmap !== 'function') {
        return file;
    }

    let bitmap: ImageBitmap | undefined;
    let canvas: HTMLCanvasElement | undefined;

    try {
        bitmap = await createImageBitmap(file, {
            imageOrientation: 'from-image',
        });
        checkAborted(signal);

        if (bitmap.width * bitmap.height > MAX_PIXELS) {
            throw new ImageUploadError('tooLarge');
        }

        if (
            file.size <= IMAGE_MAX_BYTES &&
            bitmap.width <= maxDimension &&
            bitmap.height <= maxDimension
        ) {
            return file;
        }

        const scale = Math.min(
            1,
            maxDimension / Math.max(bitmap.width, bitmap.height),
        );
        let width = Math.max(1, Math.round(bitmap.width * scale));
        let height = Math.max(1, Math.round(bitmap.height * scale));
        canvas = document.createElement('canvas');
        const context = canvas.getContext('2d');

        if (!context) {
            return file;
        }

        for (let attempt = 0; attempt < 10; attempt++) {
            checkAborted(signal);
            canvas.width = width;
            canvas.height = height;
            context.drawImage(bitmap, 0, 0, width, height);
            const blob = await new Promise<Blob | null>((resolve) =>
                canvas!.toBlob(resolve, type, 0.82),
            );
            checkAborted(signal);

            if (!blob || blob.type !== type) {
                return file;
            }

            if (blob.size <= IMAGE_MAX_BYTES) {
                // Resizing is still needed when an already-small file is too wide.
                return blob.size < file.size ||
                    bitmap.width > maxDimension ||
                    bitmap.height > maxDimension
                    ? new File([blob], file.name, {
                          type: blob.type,
                          lastModified: file.lastModified,
                      })
                    : file;
            }

            width = Math.max(1, Math.floor(width * 0.8));
            height = Math.max(1, Math.floor(height * 0.8));
        }

        return file;
    } catch (error) {
        if (signal?.aborted || error instanceof ImageUploadError) {
            throw error;
        }

        return file;
    } finally {
        bitmap?.close();

        if (canvas) {
            canvas.width = canvas.height = 0;
        }
    }
}
