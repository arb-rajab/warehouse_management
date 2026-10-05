import { defineComponent, h, reactive } from 'vue';

/**
 * Shared `@inertiajs/vue3` component stubs for `vi.mock` factories across
 * page/component tests — every consumer stubbed the same `Link`/`Form`/`Head`
 * contract independently before this existed.
 */
export function createLinkStub() {
    return defineComponent({
        props: {
            href: { type: [String, Object], default: undefined },
            as: { type: String, default: undefined },
            data: { type: Object, default: undefined },
            preserveScroll: Boolean,
            preserveState: Boolean,
            onBefore: { type: Function, default: undefined },
            onSuccess: { type: Function, default: undefined },
        },
        setup(props, { slots }) {
            return () =>
                h(
                    props.as ?? 'a',
                    {
                        href:
                            typeof props.href === 'string'
                                ? props.href
                                : props.href?.url,
                        // Only rendered when a test passes `data` (a GET query
                        // payload, e.g. DashboardStatTile/Pallets/Index's "view
                        // on map" link) — omitted otherwise so a plain href-only
                        // stub render stays free of a stray empty attribute.
                        ...(props.data !== undefined
                            ? { 'data-query': JSON.stringify(props.data) }
                            : {}),
                    },
                    slots.default?.(),
                );
        },
    });
}

export function createFormStub(
    getSlotProps: () => {
        errors: Record<string, string>;
        processing: boolean;
    },
) {
    return defineComponent({
        props: {
            action: { type: [String, Object], default: undefined },
            transform: { type: Function, default: undefined },
            options: { type: Object, default: undefined },
            resetOnSuccess: Boolean,
            onBefore: { type: Function, default: undefined },
        },
        setup(props, { slots }) {
            return () =>
                h(
                    'form',
                    {
                        'data-action-url':
                            typeof props.action === 'string'
                                ? props.action
                                : props.action?.url,
                    },
                    slots.default?.(getSlotProps()),
                );
        },
    });
}

export const headStub = defineComponent({
    props: ['title'],
    render: () => null,
});

/**
 * Stands in for Inertia's `useForm`: a reactive bag of the initial fields plus
 * the bits dialogs drive. `transform()` is applied to the field values on
 * `submit()`, which is recorded through `onSubmit(action, payload, options)`
 * instead of visiting. `getState()` supplies `processing`/`errors` per test.
 */
export function createUseFormStub(
    onSubmit: (action: unknown, payload: unknown, options: unknown) => void,
    getState: () => {
        processing: boolean;
        errors: Record<string, string>;
    } = () => ({ processing: false, errors: {} }),
) {
    return (initial: Record<string, unknown>) => {
        let transformPayload: (data: unknown) => unknown = (data) => data;
        const form: Record<string, unknown> = reactive({
            ...initial,
            ...getState(),
            transform(callback: (data: unknown) => unknown) {
                transformPayload = callback;

                return form;
            },
            submit(action: unknown, options: unknown) {
                onSubmit(action, transformPayload({ ...form }), options);
            },
            reset() {
                Object.assign(form, initial);
            },
            clearErrors() {},
        });

        return form;
    };
}
