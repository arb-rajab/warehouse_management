import {
    compressUploadImage,
    ImageUploadError,
    isImageUpload,
} from '@/lib/imageUpload';

export type ImageUploadMessages = {
    preparing: string;
    animated: string;
    tooLarge: string;
};

/** Native and Inertia Form submissions both serialize the replaced FileList. */
export function installImageUploadForms(
    messages: () => ImageUploadMessages,
    selector: string,
): () => void {
    const pending = new Map<HTMLFormElement, AbortController>();
    const prepared = new WeakSet<HTMLFormElement>();
    const invalid = new WeakSet<HTMLInputElement>();
    const change = (event: Event) => {
        if (
            event.target instanceof HTMLInputElement &&
            invalid.has(event.target)
        ) {
            event.target.setCustomValidity('');
            invalid.delete(event.target);
        }
    };
    const submit = (event: SubmitEvent) => {
        const target = event.target;

        if (!(target instanceof HTMLFormElement)) {
            return;
        }

        const form = target;

        if (prepared.delete(form)) {
            return;
        }

        if (pending.has(form)) {
            event.preventDefault();
            event.stopImmediatePropagation();

            return;
        }

        const inputs = Array.from(
            form.querySelectorAll<HTMLInputElement>(selector),
        ).filter(
            (input) =>
                !input.disabled &&
                Array.from(input.files ?? []).some(isImageUpload),
        );

        if (
            !inputs.length ||
            typeof DataTransfer !== 'function' ||
            typeof form.requestSubmit !== 'function'
        ) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        const controller = new AbortController();
        pending.set(form, controller);
        const submitter = event.submitter;
        const controls = Array.from(
            form.querySelectorAll<HTMLInputElement | HTMLButtonElement>(
                'input[type=file],input[type=submit],button[type=submit],button:not([type])',
            ),
        ).filter((control) => !control.disabled);
        const previousBusy = form.getAttribute('aria-busy');
        const status = document.createElement('p');
        status.setAttribute('role', 'status');
        status.textContent = messages().preparing;
        form.append(status);
        form.setAttribute('aria-busy', 'true');
        controls.forEach((control) => (control.disabled = true));
        let restored = false;
        void (async () => {
            let failed: HTMLInputElement | undefined;

            try {
                // Keep changes atomic: an error in a later field retains every original.
                const replacements: [HTMLInputElement, DataTransfer][] = [];

                for (const input of inputs) {
                    failed = input;
                    const transfer = new DataTransfer();
                    const dimension =
                        input.name === 'trade_license' ||
                        input.name.startsWith('element_')
                            ? 2400
                            : 1500;

                    for (const file of Array.from(input.files!)) {
                        transfer.items.add(
                            await compressUploadImage(
                                file,
                                dimension,
                                controller.signal,
                            ),
                        );
                    }

                    replacements.push([input, transfer]);
                }

                if (controller.signal.aborted) {
                    return;
                }

                if (!form.isConnected) {
                    return;
                }

                replacements.forEach(
                    ([input, transfer]) => (input.files = transfer.files),
                );
                await resume();
            } catch (error) {
                restore();

                if (
                    error instanceof ImageUploadError &&
                    failed &&
                    form.isConnected
                ) {
                    failed.setCustomValidity(messages()[error.code]);
                    invalid.add(failed);
                    failed.reportValidity();
                } else if (!controller.signal.aborted && form.isConnected) {
                    await resume();
                }
            } finally {
                restore();
                pending.delete(form);
            }
        })();
        async function resume(): Promise<void> {
            // Native submit events run a microtask checkpoint while the browser's
            // submission guard is still set. Resume in the next task so a fast
            // passthrough cannot turn requestSubmit() into a silent no-op.
            await new Promise<void>((resolve) => setTimeout(resolve, 0));

            if (controller.signal.aborted || !form.isConnected) {
                return;
            }

            restore();
            prepared.add(form);
            // Re-run validation and the original submit handlers (including Inertia).
            form.requestSubmit(
                submitter instanceof HTMLButtonElement ||
                    submitter instanceof HTMLInputElement
                    ? submitter
                    : undefined,
            );
            prepared.delete(form);
        }
        function restore() {
            if (restored) {
                return;
            }

            restored = true;
            pending.delete(form);
            controls.forEach((control) => (control.disabled = false));
            status.remove();

            if (previousBusy === null) {
                form.removeAttribute('aria-busy');
            } else {
                form.setAttribute('aria-busy', previousBusy);
            }
        }
    };
    const reset = (event: Event) => {
        if (event.target instanceof HTMLFormElement) {
            pending.get(event.target)?.abort();
        }
    };
    const cancel = () => pending.forEach((controller) => controller.abort());
    document.addEventListener('submit', submit, true);
    document.addEventListener('change', change, true);
    document.addEventListener('reset', reset, true);
    window.addEventListener('pagehide', cancel);

    return () => {
        cancel();
        document.removeEventListener('submit', submit, true);
        document.removeEventListener('change', change, true);
        document.removeEventListener('reset', reset, true);
        window.removeEventListener('pagehide', cancel);
    };
}
