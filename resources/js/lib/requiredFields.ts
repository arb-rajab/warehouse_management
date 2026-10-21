import {
    computed,
    inject,
    onBeforeUnmount,
    provide,
    reactive,
    watch,
} from 'vue';
import type { InjectionKey } from 'vue';
import type { ComputedRef } from 'vue';

/**
 * The required fields a form currently renders, one token per field
 * component instance. A form provides it (`provideRequiredFields()`, done by
 * ResourceFormPage) to show the "* Required fields" legend only when at least
 * one descendant field is required; fields register themselves through
 * `useRequiredField()`. Outside a providing form, registration is a no-op.
 */
const requiredFieldsKey: InjectionKey<Set<symbol>> = Symbol('requiredFields');

export function provideRequiredFields(): ComputedRef<boolean> {
    const fields = reactive(new Set<symbol>());

    provide(requiredFieldsKey, fields);

    return computed(() => fields.size > 0);
}

/**
 * Registers the calling field while `required()` is true. Registration
 * happens during setup, so a form's legend renders in the same flush as the
 * fields (before the browser paints): no layout shift.
 */
export function useRequiredField(required: () => boolean): void {
    const fields = inject(requiredFieldsKey, null);

    if (fields === null) {
        return;
    }

    const token = Symbol('requiredField');

    watch(
        required,
        (isRequired) => {
            if (isRequired) {
                fields.add(token);
            } else {
                fields.delete(token);
            }
        },
        { immediate: true },
    );

    onBeforeUnmount(() => fields.delete(token));
}
