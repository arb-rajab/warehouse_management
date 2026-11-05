import { reactive, ref } from 'vue';
import type { Ref } from 'vue';
import {
    dependentPatterns,
    keysFor,
    patternFor,
    readForm,
    validateForm,
    validateKey,
} from '@/lib/formValidation';
import type { FormSnapshot, ValidationSchema } from '@/lib/formValidation';

/**
 * Browser-first validation for an Inertia `<Form>` (ResourceFormPage's
 * `validation` prop). A field is checked when it loses focus or changes,
 * and again as it is typed in once it shows an error or the form was
 * submitted; a submit checks every field and, with an error, sends nothing
 * and focuses the first invalid control. Server errors still show, each
 * until its field is edited; the server stays the authority.
 */
export function useFormValidation(
    schema: () => ValidationSchema | undefined,
    form: () => HTMLFormElement | null | undefined,
) {
    const clientErrors: Ref<Record<string, string>> = ref({});
    const clearedServerErrors = reactive(new Set<string>());
    const blocked = ref(false);
    const touched = new Set<string>();
    let submitted = false;

    /** The server's errors not yet edited away, with the browser's on top. */
    function errorsFor(
        serverErrors: Record<string, string>,
    ): Record<string, string> {
        const merged: Record<string, string> = {};
        for (const [key, message] of Object.entries(serverErrors)) {
            if (!clearedServerErrors.has(key)) {
                merged[key] = message;
            }
        }

        return { ...merged, ...clientErrors.value };
    }

    function markInvalid(snapshot: FormSnapshot): void {
        for (const [element, key] of snapshot.elements) {
            if (key in clientErrors.value) {
                element.setAttribute('aria-invalid', 'true');
            } else {
                element.removeAttribute('aria-invalid');
            }
        }
    }

    function setErrors(
        snapshot: FormSnapshot,
        next: Record<string, string>,
    ): void {
        clientErrors.value = next;
        if (Object.keys(next).length === 0) {
            blocked.value = false;
        }
        markInvalid(snapshot);
    }

    /** Checks these keys again, and the touched or failing keys that depend on them. */
    function revalidate(keys: string[], includeDependents: boolean): void {
        const rules = schema();
        const element = form();
        if (!rules || !element) {
            return;
        }
        const snapshot = readForm(element);
        const targets = new Set(keys);
        if (includeDependents) {
            for (const key of keys) {
                const field = patternFor(rules, key) ?? key;
                for (const pattern of dependentPatterns(rules, field)) {
                    for (const dependent of keysFor(snapshot, pattern)) {
                        if (
                            submitted ||
                            touched.has(dependent) ||
                            dependent in clientErrors.value
                        ) {
                            targets.add(dependent);
                        }
                    }
                }
            }
        }
        const next = { ...clientErrors.value };
        for (const key of targets) {
            const message = validateKey(snapshot, rules, key);
            if (message === undefined) {
                delete next[key];
            } else {
                next[key] = message;
            }
        }
        setErrors(snapshot, next);
    }

    function keyOf(event: Event): string | undefined {
        const target = event.target;
        const element = form();
        if (
            !schema() ||
            !element ||
            !(target instanceof Element) ||
            (target as HTMLInputElement).form !== element
        ) {
            return undefined;
        }

        return readForm(element).elements.get(target);
    }

    /** Typing: the field's server error goes, and a shown error follows the value. */
    function onInput(event: Event): void {
        const key = keyOf(event);
        if (key === undefined) {
            return;
        }
        clearedServerErrors.add(key);
        if (submitted || key in clientErrors.value) {
            revalidate([key], true);
        }
    }

    /** A committed change (select, checkbox, radio, or a text field left). */
    function onChange(event: Event): void {
        const key = keyOf(event);
        if (key === undefined) {
            return;
        }
        clearedServerErrors.add(key);
        touched.add(key);
        revalidate([key], true);
    }

    /** Leaving a field marks it touched and checks it. */
    function onFocusOut(event: Event): void {
        const key = keyOf(event);
        if (key === undefined) {
            return;
        }
        touched.add(key);
        revalidate([key], false);
    }

    function focusable(element: Element, root: HTMLFormElement): boolean {
        if (
            (element as HTMLInputElement).type === 'hidden' ||
            (element as HTMLInputElement).disabled
        ) {
            return false;
        }
        for (
            let node: Element | null = element;
            node && node !== root;
            node = node.parentElement
        ) {
            if (
                node.hasAttribute('hidden') ||
                (node as HTMLElement).style?.display === 'none'
            ) {
                return false;
            }
        }

        return true;
    }

    function focusFirstInvalid(
        root: HTMLFormElement,
        snapshot: FormSnapshot,
    ): void {
        const first = [...snapshot.elements].find(
            ([element, key]) =>
                key in clientErrors.value && focusable(element, root),
        )?.[0] as HTMLElement | undefined;
        first?.scrollIntoView?.({ block: 'center' });
        first?.focus({ preventScroll: true });
    }

    /** `<Form>`'s onBefore: false (no request) while a field is invalid. */
    function onBefore(): boolean {
        const rules = schema();
        const element = form();
        if (!rules || !element) {
            return true;
        }
        submitted = true;
        const snapshot = readForm(element);
        setErrors(snapshot, validateForm(snapshot, rules));
        if (Object.keys(clientErrors.value).length === 0) {
            blocked.value = false;

            return true;
        }
        blocked.value = true;
        focusFirstInvalid(element, snapshot);

        return false;
    }

    /** A request started: the server's answer replaces every error. */
    function onStart(): void {
        clearedServerErrors.clear();
        clientErrors.value = {};
        blocked.value = false;
    }

    return {
        clientErrors,
        blocked,
        errorsFor,
        onInput,
        onChange,
        onFocusOut,
        onBefore,
        onStart,
    };
}
