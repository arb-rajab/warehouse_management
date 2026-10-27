import { installImageUploadForms } from '@/lib/imageUploadForms';
import type { ImageUploadMessages } from '@/lib/imageUploadForms';
import { prepareUppyImages } from '@/lib/imageUploadUppy';
import type { ImageUploadUppy } from '@/lib/imageUploadUppy';

declare global {
    interface Window {
        AIZImageUploads?: {
            prepare: (uppy: ImageUploadUppy, ids: string[]) => Promise<void>;
        };
    }
}

const message = (key: keyof ImageUploadMessages) =>
    document.querySelector<HTMLMetaElement>(`meta[name="image-upload-${key}"]`)
        ?.content ?? '';
const messages = {
    preparing: message('preparing'),
    animated: message('animated'),
    tooLarge: message('tooLarge'),
};
installImageUploadForms(() => messages, 'input[type=file]');
window.AIZImageUploads = {
    prepare: (uppy, ids) => prepareUppyImages(uppy, ids, messages),
};
