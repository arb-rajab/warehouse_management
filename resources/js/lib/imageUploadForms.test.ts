import { flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { compressUploadImage, ImageUploadError } from '@/lib/imageUpload';
import type * as ImageUploadModule from '@/lib/imageUpload';
import { installImageUploadForms } from '@/lib/imageUploadForms';
vi.mock('@/lib/imageUpload', async (importOriginal) => ({
    ...(await importOriginal<typeof ImageUploadModule>()),
    compressUploadImage: vi.fn(),
}));
const compress = vi.mocked(compressUploadImage);
const messages = {
    preparing: 'Preparing images',
    animated: 'Choose a still image',
    tooLarge: 'Too large',
};
let dispose: () => void;
beforeEach(() => {
    compress.mockReset();
    vi.stubGlobal(
        'DataTransfer',
        class {
            files: File[] = [];
            items = {
                add: (file: File) => {
                    this.files.push(file);
                },
            };
        },
    );
    dispose = installImageUploadForms(() => messages, 'input[type=file]');
});
afterEach(() => {
    dispose();
    document.body.innerHTML = '';
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});
function form() {
    document.body.innerHTML =
        '<form><input type="file" name="trade_license"><button type="submit" name="action" value="save">Save</button></form>';
    const element = document.querySelector('form')!;
    const input = element.querySelector('input')!;
    const button = element.querySelector('button')!;
    const file = new File(['original'], 'scan.png', { type: 'image/png' });
    Object.defineProperty(input, 'files', { writable: true, value: [file] });
    const submitted = vi.fn((event: Event) => event.preventDefault());
    element.addEventListener('submit', submitted);
    const send = () =>
        element.dispatchEvent(
            new SubmitEvent('submit', {
                bubbles: true,
                cancelable: true,
                submitter: button,
            }),
        );

    return { element, input, button, file, submitted, send };
}
it('replaces private scan bytes at 2400px before the original handler and prevents duplicate submissions', async () => {
    const { element, input, button, submitted, send } = form();
    const result = new File(['small'], 'scan.png', { type: 'image/png' });
    let resolve!: (file: File) => void;
    compress.mockImplementation(
        () =>
            new Promise((done) => {
                resolve = done;
            }),
    );
    send();
    send();
    expect(compress).toHaveBeenCalledTimes(1);
    expect(submitted).not.toHaveBeenCalled();
    expect(element.getAttribute('aria-busy')).toBe('true');
    expect(element.textContent).toContain(messages.preparing);
    expect(button.disabled).toBe(true);
    resolve(result);
    await flushPromises();
    expect(compress).toHaveBeenCalledWith(
        expect.any(File),
        2400,
        expect.any(AbortSignal),
    );
    expect(input.files![0]).toBe(result);
    expect(submitted).toHaveBeenCalledTimes(1);
    expect((submitted.mock.calls[0][0] as SubmitEvent).submitter).toBe(button);
    expect(button.disabled).toBe(false);
    expect(element.hasAttribute('aria-busy')).toBe(false);
});
it('retains the original and shows native validation on animation, clearing it when replaced', async () => {
    const { input, file, submitted, send } = form();
    compress.mockRejectedValue(new ImageUploadError('animated'));
    const report = vi.spyOn(input, 'reportValidity');
    send();
    await flushPromises();
    expect(input.files![0]).toBe(file);
    expect(input.validationMessage).toBe(messages.animated);
    expect(report).toHaveBeenCalled();
    expect(submitted).not.toHaveBeenCalled();
    input.dispatchEvent(new Event('change', { bubbles: true }));
    expect(input.validationMessage).toBe('');
});
it('never submits a detached form after navigation or reset', async () => {
    const { element, file, submitted, send } = form();
    let resolve!: (file: File) => void;
    compress.mockImplementation(
        () =>
            new Promise((done) => {
                resolve = done;
            }),
    );
    send();
    element.remove();
    resolve(file);
    await flushPromises();
    expect(submitted).not.toHaveBeenCalled();
});
it('does not re-enable buttons that the original form handler locks during upload', async () => {
    const { element, file, button, send } = form();
    compress.mockResolvedValue(file);
    element.addEventListener('submit', () => (button.disabled = true));
    send();
    await flushPromises();
    expect(button.disabled).toBe(true);
});
