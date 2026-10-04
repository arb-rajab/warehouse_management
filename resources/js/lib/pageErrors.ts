import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * The server error for `key` from the current page's shared `errors` bag, for
 * a banner outside any form (a delete/toggle link has no `<Form>` slot to read
 * from). Errors from a submitted `<Form>`/`useForm` are read from that form's
 * own `errors` instead.
 */
export function usePageError(key: string): ComputedRef<string | undefined> {
    const page = usePage();

    return computed(
        () => (page.props.errors as Record<string, string> | undefined)?.[key],
    );
}

/**
 * The first message in a form's `errors` — for a dialog that shows one banner
 * for whichever field the server rejected rather than one error per field.
 */
export function firstError(errors: Record<string, string>): string | undefined {
    return Object.values(errors)[0];
}
