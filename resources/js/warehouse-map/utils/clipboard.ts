/** Copies text; resolves false when the browser refuses (so the caller can show the text instead). */
export async function copyText(text: string): Promise<boolean> {
    try {
        if (!navigator.clipboard) {
            return false;
        }

        await navigator.clipboard.writeText(text);

        return true;
    } catch {
        return false;
    }
}
