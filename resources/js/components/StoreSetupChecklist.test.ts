import { Link } from '@inertiajs/vue3';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { all as allProducts } from '@/actions/App/Http/Controllers/Store/Admin/ProductController';
import { edit as generalSettings } from '@/actions/App/Http/Controllers/Store/Admin/GeneralSettingsController';
import { index as currencies } from '@/actions/App/Http/Controllers/Store/Admin/CurrencyController';
import { index as languages } from '@/actions/App/Http/Controllers/Store/Admin/LanguageController';
import {
    activation,
    firebase,
    payments,
    smtp,
} from '@/actions/App/Http/Controllers/Store/Admin/SettingsController';
import { index as shipping } from '@/actions/App/Http/Controllers/Store/Admin/ShippingConfigurationController';
import { index as taxes } from '@/actions/App/Http/Controllers/Store/Admin/TaxController';
import { i18n, t } from '@/lib/i18n';
import { storeSharedProps } from '@/testing/factories';
import { defaultAuthProps } from '@/testing/inertiaPageMocks';
import type {
    StoreSetupChecklist,
    StoreSetupChecklistKey,
} from '@/types/store';
import StoreSetupChecklistCard from './StoreSetupChecklist.vue';

const { permissions } = vi.hoisted(() => ({
    permissions: { value: [] as string[] },
}));
vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return {
        Link: createLinkStub(),
        usePage: () => ({
            url: '/temp/admin',
            props: defaultAuthProps({
                store: storeSharedProps({ permissions: permissions.value }),
            }),
        }),
    };
});

const KEYS: StoreSetupChecklistKey[] = [
    'smtp',
    'paymentMethod',
    'cashOnDeliveryProducts',
    'shipping',
    'firebasePush',
    'googleMaps',
    'currency',
    'vatTax',
    'catalog',
    'siteBasics',
    'language',
];

function checklist(doneKeys: StoreSetupChecklistKey[]): StoreSetupChecklist {
    return {
        items: KEYS.map((key) => ({ key, done: doneKeys.includes(key) })),
        done: doneKeys.length,
        total: KEYS.length,
    };
}

function hrefOf(
    wrapper: ReturnType<typeof mount>,
    key: StoreSetupChecklistKey,
): string | undefined {
    return wrapper.find(`li[data-key="${key}"] a`).attributes('href');
}

beforeEach(() => {
    permissions.value = [];
});
afterEach(() => {
    i18n.global.locale.value = 'en';
});

describe('StoreSetupChecklist', () => {
    it('renders nothing when there are no items or everything is done', () => {
        const empty = mount(StoreSetupChecklistCard, {
            props: { checklist: { items: [], done: 0, total: 0 } },
        });
        expect(empty.find('section').exists()).toBe(false);
        expect(empty.text()).toBe('');

        const complete = mount(StoreSetupChecklistCard, {
            props: { checklist: checklist(KEYS) },
        });
        expect(complete.find('section').exists()).toBe(false);
    });

    it.each(['en', 'ar'] as const)(
        'shows the title, the progress and a progress bar in %s',
        (locale) => {
            i18n.global.locale.value = locale;
            const wrapper = mount(StoreSetupChecklistCard, {
                props: { checklist: checklist(KEYS.slice(0, 8)) },
            });
            const progress = t('store.dashboard.checklist.progress', {
                done: 8,
                total: 11,
            });
            expect(wrapper.get('h2').text()).toBe(
                t('store.dashboard.checklist.title'),
            );
            expect(wrapper.text()).toContain(progress);
            const bar = wrapper.get('[role="progressbar"]');
            expect(bar.attributes()).toMatchObject({
                'aria-valuenow': '8',
                'aria-valuemin': '0',
                'aria-valuemax': '11',
                'aria-valuetext': progress,
            });
            expect(bar.get('div').attributes('style')).toContain('width: 73%');
        },
    );

    it('renders done rows without hint or link and to-do rows with both', () => {
        const wrapper = mount(StoreSetupChecklistCard, {
            props: { checklist: checklist(['smtp', 'paymentMethod']) },
        });
        expect(wrapper.findAll('li')).toHaveLength(11);
        const done = wrapper.get('li[data-key="smtp"]');
        expect(done.attributes('data-done')).toBe('true');
        expect(done.classes()).toContain('text-muted');
        expect(done.text()).toContain(
            t('store.dashboard.checklist.items.smtp.label'),
        );
        expect(done.text()).not.toContain(
            t('store.dashboard.checklist.items.smtp.hint'),
        );
        expect(done.find('a').exists()).toBe(false);

        const todo = wrapper.get('li[data-key="shipping"]');
        expect(todo.attributes('data-done')).toBe('false');
        expect(todo.text()).toContain(
            t('store.dashboard.checklist.items.shipping.label'),
        );
        expect(todo.text()).toContain(
            t('store.dashboard.checklist.items.shipping.hint'),
        );
        expect(todo.get('a').text()).toBe(
            t('store.dashboard.checklist.configure'),
        );
        expect(todo.get('a').attributes('aria-label')).toBe(
            t('store.dashboard.checklist.configureItem', {
                item: t('store.dashboard.checklist.items.shipping.label'),
            }),
        );
        expect(
            wrapper
                .findAll('svg')
                .every((icon) => icon.attributes('aria-hidden') === 'true'),
        ).toBe(true);
    });

    it('links each to-do row to the page that fixes it', () => {
        const wrapper = mount(StoreSetupChecklistCard, {
            props: { checklist: checklist([]) },
        });
        expect(wrapper.findAllComponents(Link)).toHaveLength(10);
        expect(hrefOf(wrapper, 'smtp')).toBe(smtp().url);
        expect(hrefOf(wrapper, 'paymentMethod')).toBe(payments().url);
        expect(hrefOf(wrapper, 'cashOnDeliveryProducts')).toBe(
            allProducts().url,
        );
        expect(hrefOf(wrapper, 'shipping')).toBe(shipping().url);
        expect(hrefOf(wrapper, 'firebasePush')).toBe(firebase().url);
        expect(hrefOf(wrapper, 'currency')).toBe(currencies().url);
        expect(hrefOf(wrapper, 'vatTax')).toBe(taxes().url);
        expect(hrefOf(wrapper, 'catalog')).toBe(allProducts().url);
        expect(hrefOf(wrapper, 'siteBasics')).toBe(generalSettings().url);
        expect(hrefOf(wrapper, 'language')).toBe(languages().url);
    });

    it('gives Google Maps a hint about the server environment and no link', () => {
        const wrapper = mount(StoreSetupChecklistCard, {
            props: { checklist: checklist([]) },
        });
        const row = wrapper.get('li[data-key="googleMaps"]');
        expect(row.text()).toContain('MAP_API_KEY');
        expect(row.find('a').exists()).toBe(false);
    });

    it('sends the payment row to the cash payment option when Features activation is allowed', () => {
        permissions.value = ['features_activation'];
        const wrapper = mount(StoreSetupChecklistCard, {
            props: { checklist: checklist([]) },
        });
        expect(hrefOf(wrapper, 'paymentMethod')).toBe(
            activation({ query: { highlight: 'cash_payment' } }).url,
        );
    });

    it('collapses and expands the list with an accessible toggle button', async () => {
        const wrapper = mount(StoreSetupChecklistCard, {
            props: { checklist: checklist(['smtp']) },
        });
        const button = wrapper.get('button');
        const list = wrapper.get('ul');
        expect(button.attributes('type')).toBe('button');
        expect(button.attributes('aria-expanded')).toBe('true');
        expect(button.attributes('aria-controls')).toBe(list.attributes('id'));
        expect(button.attributes('aria-label')).toBe(
            t('store.dashboard.checklist.collapse'),
        );
        expect(list.isVisible()).toBe(true);

        await button.trigger('click');
        expect(button.attributes('aria-expanded')).toBe('false');
        expect(button.attributes('aria-label')).toBe(
            t('store.dashboard.checklist.expand'),
        );
        expect(list.isVisible()).toBe(false);
        expect(wrapper.find('[role="progressbar"]').exists()).toBe(true);

        await button.trigger('click');
        expect(button.attributes('aria-expanded')).toBe('true');
        expect(list.isVisible()).toBe(true);
    });
});
