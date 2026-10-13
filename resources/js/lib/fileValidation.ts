/** Mirror upload size rules through the browser's native form validation. */
export function validateFileSize(
    event: Event,
    maximumBytes: number,
    message: string,
): void {
    const input = event.currentTarget as HTMLInputElement;
    const file = input.files?.[0];
    input.setCustomValidity(file && file.size > maximumBytes ? message : '');
}
