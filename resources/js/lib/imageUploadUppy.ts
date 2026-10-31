import { compressUploadImage, ImageUploadError } from '@/lib/imageUpload';
import type { ImageUploadMessages } from '@/lib/imageUploadForms';

type UppyFile = {
    id: string;
    data: Blob;
    name: string;
    type: string;
    size: number;
};
export type ImageUploadUppy = {
    getFile(id: string): UppyFile | undefined;
    setFileState(id: string, state: Partial<UppyFile>): void;
    emit(event: string, ...args: unknown[]): void;
    on(event: string, callback: (file?: UppyFile) => void): void;
    off(event: string, callback: (file?: UppyFile) => void): void;
    info(message: string, type: string, duration: number): void;
};

/** Uppy keeps ownership of transport, retry, cancellation and upload progress. */
export async function prepareUppyImages(
    uppy: ImageUploadUppy,
    ids: string[],
    messages: ImageUploadMessages,
): Promise<void> {
    const controller = new AbortController();
    const cancel = () => controller.abort();
    uppy.on('cancel-all', cancel);

    try {
        for (const id of ids) {
            const file = uppy.getFile(id);

            if (!file || !(file.data instanceof Blob)) {
                continue;
            }

            const fileController = new AbortController();
            const removed = (current?: UppyFile) => {
                if (current?.id === id) {
                    fileController.abort();
                }
            };
            uppy.on('file-removed', removed);
            uppy.emit('preprocess-progress', file, {
                mode: 'indeterminate',
                message: messages.preparing,
            });

            try {
                const data = await compressUploadImage(
                    file.data instanceof File
                        ? file.data
                        : new File([file.data], file.name, { type: file.type }),
                    1500,
                    typeof AbortSignal.any === 'function'
                        ? AbortSignal.any([
                              controller.signal,
                              fileController.signal,
                          ])
                        : controller.signal,
                );

                if (uppy.getFile(id)?.data === file.data) {
                    uppy.setFileState(id, {
                        data,
                        size: data.size,
                        type: data.type || file.type,
                    });
                }
            } catch (error) {
                if (
                    fileController.signal.aborted &&
                    !controller.signal.aborted
                ) {
                    continue;
                }

                if (error instanceof ImageUploadError) {
                    const failure = new Error(messages[error.code]);
                    uppy.emit('upload-error', file, failure);
                    uppy.info(failure.message, 'error', 5000);

                    throw failure;
                }

                throw error;
            } finally {
                uppy.off('file-removed', removed);

                if (uppy.getFile(id)) {
                    uppy.emit('preprocess-complete', file);
                }
            }
        }
    } finally {
        uppy.off('cancel-all', cancel);
    }
}
