import { defineComponent, h } from 'vue';

/**
 * Shared `@inertiajs/vue3` component stubs for `vi.mock` factories across
 * page/component tests — every consumer stubbed the same `Link`/`Form`/`Head`
 * contract independently before this existed.
 */
export function createLinkStub() {
    return defineComponent({
        props: ['href', 'as'],
        setup(props, { slots }) {
            return () =>
                h(
                    props.as ?? 'a',
                    {
                        href:
                            typeof props.href === 'string'
                                ? props.href
                                : props.href?.url,
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
        props: ['action'],
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
