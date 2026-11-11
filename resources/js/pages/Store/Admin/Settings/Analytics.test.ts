import { Form } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    updateAnalytics,
    updateFacebookChat,
    updateFacebookComments,
} from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type { StoreIntegrationId } from '@/types/store';
import Analytics from './Analytics.vue';
import FacebookChat from './FacebookChat.vue';
import FacebookComments from './FacebookComments.vue';

const { usePageMock, formSlotPropsMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
    formSlotPropsMock: vi.fn(() => ({ errors: {}, processing: false })),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createFormStub, createLinkStub, headStub } =
        await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Form: createFormStub(formSlotPropsMock),
        Link: createLinkStub(),
        usePage: usePageMock,
        router: { get: vi.fn(), post: vi.fn() },
    };
});

const analyticsIds = (
    overrides: Partial<Record<string, Partial<StoreIntegrationId>>> = {},
): Record<string, StoreIntegrationId> => ({
    google_analytics: {
        name: 'google_analytics_tracking_id',
        value: 'G-AB12CD34EF',
        effective: { value: 'G-AB12CD34EF', source: 'admin' },
        ...overrides.google_analytics,
    },
    facebook_pixel: {
        name: 'facebook_pixel_id',
        value: null,
        effective: { value: '1234567890123', source: 'env' },
        ...overrides.facebook_pixel,
    },
});

function mountAnalytics(
    options: {
        settings?: Record<string, boolean>;
        ids?: Record<string, StoreIntegrationId>;
        highlight?: string | null;
        attach?: boolean;
    } = {},
) {
    usePageMock.mockReturnValue({
        url: '/temp/admin/google-analytics',
        props: defaultAuthProps({
            store: storeSharedProps({
                permissions: ['analytics_tools_configuration'],
            }),
        }),
    });

    return mount(Analytics, {
        props: {
            settings: options.settings ?? {
                google_analytics: true,
                facebook_pixel: true,
            },
            ids: options.ids ?? analyticsIds(),
            highlight: options.highlight ?? null,
        },
        attachTo: options.attach ? document.body : undefined,
    });
}

beforeEach(() => {
    vi.clearAllMocks();
    formSlotPropsMock.mockReturnValue({ errors: {}, processing: false });
    Element.prototype.scrollIntoView = vi.fn();
});

describe('Analytics settings page', () => {
    it('posts the switches and IDs to the analytics update route', () => {
        const wrapper = mountAnalytics();

        expect(wrapper.findComponent(Form).props('action')).toEqual(
            updateAnalytics['/temp/admin/google-analytics'](),
        );
        expect(
            wrapper
                .findAll('select')
                .map((select) => select.attributes('name')),
        ).toEqual(['settings[google_analytics]', 'settings[facebook_pixel]']);
    });

    it('shows the saved IDs in full and leaves an unsaved one empty', () => {
        const wrapper = mountAnalytics();

        expect(
            (
                wrapper.get('#google_analytics_tracking_id')
                    .element as HTMLInputElement
            ).value,
        ).toBe('G-AB12CD34EF');
        expect(
            (wrapper.get('#facebook_pixel_id').element as HTMLInputElement)
                .value,
        ).toBe('');
    });

    it('lists the effective value of each ID and where it comes from', () => {
        const list = mountAnalytics()
            .get('[data-section="integration_effective"]')
            .text();
        const source = (key: 'admin' | 'env') =>
            t(`store.settings.integrations.effective.sources.${key}`);

        expect(list).toContain(
            t('store.settings.integrations.effective.withSource', {
                value: 'G-AB12CD34EF',
                source: source('admin'),
            }),
        );
        expect(list).toContain(
            t('store.settings.integrations.effective.withSource', {
                value: '1234567890123',
                source: source('env'),
            }),
        );
    });

    it('marks no field required: the .env may hold the ID', () => {
        const wrapper = mountAnalytics({
            ids: analyticsIds({
                google_analytics: { value: null },
            }),
        });

        expect(
            wrapper.get('#google_analytics_tracking_id').attributes('required'),
        ).toBeUndefined();
        expect(
            wrapper.get('#facebook_pixel_id').attributes('required'),
        ).toBeUndefined();
    });

    it('warns, naming the field, when a switch is on and no ID is in effect', () => {
        const wrapper = mountAnalytics({
            ids: analyticsIds({
                google_analytics: {
                    value: null,
                    effective: { value: null, source: 'env' },
                },
            }),
        });
        const warnings = wrapper.findAll('[role="alert"]');

        expect(warnings).toHaveLength(1);
        expect(warnings[0].attributes('data-warning')).toBe(
            'google_analytics_tracking_id',
        );
        expect(warnings[0].text()).toBe(
            t('store.settings.integrations.missing', {
                switch: t('store.settings.keys.google_analytics'),
                field: t(
                    'store.settings.integrations.fields.google_analytics_tracking_id.label',
                ),
            }),
        );
    });

    it('does not warn when the switch is off or an ID is in effect', () => {
        expect(mountAnalytics().findAll('[role="alert"]')).toHaveLength(0);
        expect(
            mountAnalytics({
                settings: { google_analytics: false, facebook_pixel: false },
                ids: analyticsIds({
                    google_analytics: {
                        value: null,
                        effective: { value: null, source: 'env' },
                    },
                }),
            }).findAll('[role="alert"]'),
        ).toHaveLength(0);
    });

    it.each([
        ['google_analytics', '#google_analytics'],
        ['google_analytics_tracking_id', '#google_analytics_tracking_id'],
        ['facebook_pixel_id', '#facebook_pixel_id'],
    ])(
        'marks and focuses %s for a checklist link, and nothing else',
        async (highlight, control) => {
            const wrapper = mountAnalytics({ highlight, attach: true });
            await wrapper.vm.$nextTick();
            await wrapper.vm.$nextTick();

            const marked = wrapper.findAll('[data-highlighted]');
            expect(marked).toHaveLength(1);
            expect(marked[0].find(control).exists()).toBe(true);
            wrapper.unmount();
        },
    );

    it('marks nothing without a highlight', () => {
        expect(mountAnalytics().findAll('[data-highlighted]')).toHaveLength(0);
    });

    it('blocks the submit in the browser for characters an ID never holds', async () => {
        const wrapper = mountAnalytics({ attach: true });
        const form = wrapper.findComponent(Form);
        const onBefore = form.props('onBefore') as () => boolean;

        expect(form.attributes('novalidate')).toBeDefined();
        expect(onBefore()).toBe(true);

        await wrapper
            .get('#google_analytics_tracking_id')
            .setValue("G-AB');alert(1);//");
        await wrapper.get('#facebook_pixel_id').setValue('12ab');
        expect(onBefore()).toBe(false);
        await wrapper.vm.$nextTick();
        expect(
            wrapper.get('#google_analytics_tracking_id').element.parentElement
                ?.textContent,
        ).toContain(
            'The Google Analytics tracking ID field format is invalid.',
        );
        expect(
            wrapper.get('#facebook_pixel_id').element.parentElement
                ?.textContent,
        ).toContain('The Facebook pixel ID field format is invalid.');

        await wrapper
            .get('#google_analytics_tracking_id')
            .setValue('G-AB12CD34EF');
        await wrapper.get('#facebook_pixel_id').setValue('');
        expect(onBefore()).toBe(true);
        wrapper.unmount();
    });

    it('shows server errors on their fields', () => {
        formSlotPropsMock.mockReturnValue({
            errors: {
                settings: 'Unknown keys',
                facebook_pixel_id:
                    'The Facebook pixel ID field format is invalid.',
            },
            processing: false,
        });
        const text = mountAnalytics().text();

        expect(text).toContain('Unknown keys');
        expect(text).toContain(
            'The Facebook pixel ID field format is invalid.',
        );
    });
});

describe.each([
    {
        name: 'Facebook chat',
        component: FacebookChat,
        action: updateFacebookChat(),
        toggle: 'facebook_chat',
        field: 'facebook_page_id' as const,
    },
    {
        name: 'Facebook comments',
        component: FacebookComments,
        action: updateFacebookComments(),
        toggle: 'facebook_comment',
        field: 'facebook_app_id' as const,
    },
])('$name settings page', ({ component, action, toggle, field }) => {
    function mountIt(effective: string | null, enabled = true) {
        usePageMock.mockReturnValue({
            url: '/temp/admin/x',
            props: defaultAuthProps({
                store: storeSharedProps({ permissions: [toggle] }),
            }),
        });

        return mount(component, {
            props: {
                settings: { [toggle]: enabled },
                ids: {
                    [toggle]: {
                        name: field,
                        value: effective,
                        effective: {
                            value: effective,
                            source: effective ? 'admin' : 'env',
                        },
                    },
                },
            },
        });
    }

    it('posts its switch and one ID to its own update route', () => {
        const wrapper = mountIt('123456789');

        expect(wrapper.findComponent(Form).props('action')).toEqual(action);
        expect(
            wrapper
                .findAll('select')
                .map((select) => select.attributes('name')),
        ).toEqual([`settings[${toggle}]`]);
        expect(
            (wrapper.get(`#${field}`).element as HTMLInputElement).value,
        ).toBe('123456789');
        expect(wrapper.get(`#${field}`).attributes('required')).toBeUndefined();
    });

    it('warns by field name when on without an ID, and refuses a non-numeric ID', async () => {
        const wrapper = mountIt(null);

        expect(wrapper.get('[role="alert"]').attributes('data-warning')).toBe(
            field,
        );
        expect(mountIt(null, false).findAll('[role="alert"]')).toHaveLength(0);

        await wrapper.get(`#${field}`).setValue('12x');
        const onBefore = wrapper
            .findComponent(Form)
            .props('onBefore') as () => boolean;
        expect(onBefore()).toBe(false);
    });
});

describe('integration strings', () => {
    it('has every string in English and Arabic', () => {
        const keys = [
            'intro.analytics',
            'intro.facebookChat',
            'intro.facebookComments',
            'missing',
            'effective.title',
            'effective.withSource',
            'effective.notSet',
            'effective.sources.admin',
            'effective.sources.env',
            ...[
                'google_analytics_tracking_id',
                'facebook_pixel_id',
                'facebook_page_id',
                'facebook_app_id',
            ].flatMap((name) => [
                `fields.${name}.label`,
                `fields.${name}.help`,
            ]),
        ];

        for (const locale of ['en', 'ar'] as const) {
            for (const key of keys) {
                expect(
                    i18n.global.te(
                        `store.settings.integrations.${key}`,
                        locale,
                    ),
                    `${locale}: store.settings.integrations.${key}`,
                ).toBe(true);
            }
        }
    });
});
