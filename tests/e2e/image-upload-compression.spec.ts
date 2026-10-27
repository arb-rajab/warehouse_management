import type { Page, Request } from '@playwright/test';
import { expect, test } from '@playwright/test';
import { loginAsStoreAdmin } from './support/auth';

async function photograph(page: Page): Promise<Buffer> {
    const base64 = await page.evaluate(() => {
        const canvas = document.createElement('canvas');
        canvas.width = 3000;
        canvas.height = 2000;
        const context = canvas.getContext('2d')!;
        const pixels = context.createImageData(canvas.width, canvas.height);
        let seed = 12345;

        for (let i = 0; i < pixels.data.length; i += 4) {
            seed = (Math.imul(seed, 1664525) + 1013904223) >>> 0;
            pixels.data[i] = seed & 255;
            pixels.data[i + 1] = (seed >>> 8) & 255;
            pixels.data[i + 2] = (seed >>> 16) & 255;
            pixels.data[i + 3] = 255;
        }

        context.putImageData(pixels, 0, 0);

        return canvas.toDataURL('image/jpeg', 0.95).split(',')[1];
    });

    return Buffer.from(base64, 'base64');
}

function multipartImage(request: Request): Buffer {
    const body = request.postDataBuffer()!;
    const boundary = request.headers()['content-type'].split('boundary=')[1];
    const filename = body.indexOf(Buffer.from('filename="'));
    expect(filename).toBeGreaterThan(0);
    const start = body.indexOf(Buffer.from('\r\n\r\n'), filename) + 4;
    const end = body.indexOf(Buffer.from('\r\n--' + boundary), start);

    return body.subarray(start, end);
}

async function dimensions(page: Page, bytes: Buffer) {
    return page.evaluate(
        async (data) => {
            const bitmap = await createImageBitmap(
                new Blob([Uint8Array.from(data)]),
            );
            const result = [bitmap.width, bitmap.height];
            bitmap.close();

            return result;
        },
        [...bytes],
    );
}

test('the Vue file manager sends compressed JPEG bytes and retains its filename', async ({
    page,
}) => {
    await loginAsStoreAdmin(page);
    await page.goto('/temp/admin/uploads/create');
    const original = await photograph(page);
    // Interception makes Chromium include uploaded file bytes in the request body.
    await page.route('**/temp/admin/uploads', (route) => route.continue());
    const outgoing = page.waitForRequest(
        (request) =>
            request.method() === 'POST' &&
            new URL(request.url()).pathname === '/temp/admin/uploads',
    );
    await page.locator('#upload-files').setInputFiles({
        name: 'browser-compressed-photo.jpg',
        mimeType: 'image/jpeg',
        buffer: original,
    });
    await page
        .getByRole('button', { name: 'Upload files', exact: true })
        .last()
        .click();
    const request = await outgoing;
    const image = multipartImage(request);
    expect(image.length).toBeLessThanOrEqual(1024 * 1024);
    expect(image.length).toBeLessThan(original.length / 3);
    expect(await dimensions(page, image)).toEqual([1500, 1000]);
    await expect(
        page.getByRole('button', {
            name: 'browser-compressed-photo',
            exact: true,
        }),
    ).toBeVisible();
    console.log(`Browser JPEG: ${original.length} -> ${image.length} bytes`);
});

test('retained Uppy uploads compress before XHR and private native forms retain their submitter', async ({
    page,
}) => {
    await page.goto('/temp/login');
    await page.waitForFunction(() => !!(window as any).AIZImageUploads);
    const original = await photograph(page);
    await page.route('**/temp/aiz-uploader/upload', (route) =>
        route.fulfill({ json: { result: true } }),
    );
    await page.route('**/temp/aiz-uploader/get_uploaded_files*', (route) =>
        route.fulfill({ json: { data: [], total: 0 } }),
    );
    const outgoing = page.waitForRequest(
        (request) =>
            request.method() === 'POST' &&
            new URL(request.url()).pathname === '/temp/aiz-uploader/upload',
    );
    await page.evaluate((base64) => {
        const w = window as any;
        const target = document.createElement('div');
        target.id = 'aiz-upload-files';
        document.body.append(target);
        const core = w.Uppy.Core;
        w.Uppy.Core = (options: unknown) => {
            w.testImageUppy = core(options);

            return w.testImageUppy;
        };
        w.AIZ.plugins.aizUppy();
        w.Uppy.Core = core;
        w.testImageUppy.addFile({
            name: 'legacy-photo.jpg',
            type: 'image/jpeg',
            data: new File(
                [
                    Uint8Array.from(atob(base64), (character) =>
                        character.charCodeAt(0),
                    ),
                ],
                'legacy-photo.jpg',
                { type: 'image/jpeg' },
            ),
        });
    }, original.toString('base64'));
    const request = await outgoing;
    const image = multipartImage(request);
    expect(image.length).toBeLessThanOrEqual(1024 * 1024);
    expect(await dimensions(page, image)).toEqual([1500, 1000]);
    await page.route('**/compression-native-test', (route) =>
        route.fulfill({ body: '<p>Received</p>', contentType: 'text/html' }),
    );
    await page.evaluate(() => {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/compression-native-test';
        form.enctype = 'multipart/form-data';
        form.innerHTML =
            '<input type="file" name="trade_license" id="private-scan"><button type="submit" name="action" value="save">Send scan</button>';
        document.body.append(form);
    });
    await page.locator('#private-scan').setInputFiles({
        name: 'scan.jpg',
        mimeType: 'image/jpeg',
        buffer: original,
    });
    const native = page.waitForRequest((request) =>
        request.url().endsWith('/compression-native-test'),
    );
    await page.getByRole('button', { name: 'Send scan' }).click();
    const scanRequest = await native;
    const scan = multipartImage(scanRequest);
    expect(scan.length).toBeLessThanOrEqual(1024 * 1024);
    expect(scanRequest.postDataBuffer()!.toString('latin1')).toContain(
        'name="action"\r\n\r\nsave',
    );
    // Size adaptation may reduce a detailed scan below the 2400px ceiling.
    await page.waitForURL('**/compression-native-test');
    expect(Math.max(...(await dimensions(page, scan)))).toBeLessThanOrEqual(
        2400,
    );
});

test('browser encoding preserves PNG alpha and applies JPEG EXIF orientation', async ({
    page,
}) => {
    await page.goto('/temp/login');
    await page.waitForFunction(() => !!(window as any).AIZImageUploads);
    const result = await page.evaluate(async () => {
        const canvas = document.createElement('canvas');
        canvas.width = 3000;
        canvas.height = 2000;
        const context = canvas.getContext('2d')!;
        context.fillStyle = 'red';
        context.fillRect(100, 100, 100, 100);
        const png = await new Promise<Blob>((resolve) =>
            canvas.toBlob((blob) => resolve(blob!), 'image/png'),
        );
        async function prepare(data: File) {
            let file: any = {
                id: '1',
                data,
                name: data.name,
                type: data.type,
                size: data.size,
            };
            await (window as any).AIZImageUploads.prepare(
                {
                    getFile: () => file,
                    setFileState: (_id: string, update: unknown) =>
                        (file = { ...file, ...(update as object) }),
                    emit: () => {},
                    on: () => {},
                    off: () => {},
                    info: () => {},
                },
                ['1'],
            );

            return file.data as File;
        }
        const compressed = await prepare(
            new File([png], 'transparent.png', { type: 'image/png' }),
        );
        const bitmap = await createImageBitmap(compressed);
        const alpha = document.createElement('canvas');
        alpha.width = bitmap.width;
        alpha.height = bitmap.height;
        const alphaContext = alpha.getContext('2d')!;
        alphaContext.drawImage(bitmap, 0, 0);
        const transparent = alphaContext.getImageData(0, 0, 1, 1).data[3];
        const pngSize = [bitmap.width, bitmap.height];
        bitmap.close();
        const jpegBlob = await new Promise<Blob>((resolve) =>
            canvas.toBlob((blob) => resolve(blob!), 'image/jpeg'),
        );
        const jpeg = new Uint8Array(await jpegBlob.arrayBuffer());
        const exif = Uint8Array.from([
            69, 120, 105, 102, 0, 0, 73, 73, 42, 0, 8, 0, 0, 0, 1, 0, 18, 1, 3,
            0, 1, 0, 0, 0, 6, 0, 0, 0, 0, 0, 0, 0,
        ]);
        const rotated = new File(
            [
                jpeg.slice(0, 2),
                Uint8Array.from([255, 225, 0, exif.length + 2]),
                exif,
                jpeg.slice(2),
            ],
            'rotated.jpg',
            { type: 'image/jpeg' },
        );
        const upright = await createImageBitmap(await prepare(rotated));
        const jpegSize = [upright.width, upright.height];
        upright.close();

        return {
            transparent,
            pngSize,
            pngType: compressed.type,
            jpegSize,
            originalBytes: png.size,
            compressedBytes: compressed.size,
        };
    });
    expect(result.transparent).toBe(0);
    expect(result.pngType).toBe('image/png');
    expect(result.pngSize).toEqual([1500, 1000]);
    expect(result.jpegSize).toEqual([1000, 1500]);
    console.log(
        `Browser PNG: ${result.originalBytes} -> ${result.compressedBytes} bytes`,
    );
});

for (const fixture of [
    {
        name: 'license.pdf',
        type: 'application/pdf',
        contents: '%PDF-1.4\n%%EOF',
        missingReader: false,
    },
    {
        name: 'bulk.xlsx',
        type: 'text/plain',
        contents: 'not a spreadsheet',
        missingReader: false,
    },
    {
        name: 'scan.png',
        type: 'image/png',
        contents: 'image for server validation',
        missingReader: true,
    },
]) {
    test(`native ${fixture.name} submission continues when compression is unnecessary or unsupported`, async ({
        page,
    }) => {
        await page.goto('/temp/login');
        await page.waitForFunction(() => !!(window as any).AIZImageUploads);
        await page.route('**/compression-passthrough-test', (route) =>
            route.fulfill({
                contentType: 'text/html',
                body: '<p>Received</p>',
            }),
        );
        await page.evaluate((missingReader) => {
            if (missingReader) {
                Object.defineProperty(Blob.prototype, 'arrayBuffer', {
                    configurable: true,
                    value: undefined,
                });
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/compression-passthrough-test';
            form.enctype = 'multipart/form-data';
            form.innerHTML =
                '<input type="file" name="trade_license" id="passthrough-file"><button type="submit" name="action" value="save">Send original</button>';
            document.body.append(form);
        }, fixture.missingReader);
        await page.locator('#passthrough-file').setInputFiles({
            name: fixture.name,
            mimeType: fixture.type,
            buffer: Buffer.from(fixture.contents),
        });
        const outgoing = page.waitForRequest((request) =>
            request.url().endsWith('/compression-passthrough-test'),
        );
        await page
            .getByRole('button', { name: 'Send original', exact: true })
            .click();
        const request = await outgoing;
        expect(multipartImage(request)).toEqual(Buffer.from(fixture.contents));
        expect(request.postDataBuffer()!.toString()).toContain(
            'name="action"\r\n\r\nsave',
        );
        await expect(page).toHaveURL(/\/compression-passthrough-test$/);
    });
}
