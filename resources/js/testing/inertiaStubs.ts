import { defineComponent, h } from 'vue';

/**
 * Shared `@inertiajs/vue3` component stubs for `vi.mock` factories across
 * page/component tests — every consumer stubbed the same `Link`/`Form`/`Head`
 * contract independently before this existed.
 */
export function createLinkStub() {
    return defineComponent({
        props: ['href', 'as', 'data'],
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
        props: ['action', 'transform'],
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
