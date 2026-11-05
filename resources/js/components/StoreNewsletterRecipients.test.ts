import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { recipients } from '@/actions/App/Http/Controllers/Store/Admin/NewsletterController';
import StoreSearchSelect from '@/components/StoreSearchSelect.vue';
import { i18n, t } from '@/lib/i18n';
import type { SearchOption } from '@/types/searchSelect';
import StoreNewsletterRecipients from './StoreNewsletterRecipients.vue';

const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: getMock }),
}));

const reader: SearchOption = {
    id: 'reader@example.test',
    label: 'reader@example.test',
    description: 'Reader',
    icon: 'mail',
};

function mountField(
    kind: 'users' | 'subscribers' = 'users',
    errors: Record<string, string> = {},
    modelValue: SearchOption[] = [],
    required = true,
) {
    return mount(StoreNewsletterRecipients, {
        props: { kind, required, errors, modelValue },
    });
}

describe('StoreNewsletterRecipients', () => {
    beforeEach(() => {
        getMock.mockReset();
        i18n.global.locale.value = 'en';
    });

    it.each([
        ['users', 'en'],
        ['subscribers', 'en'],
        ['users', 'ar'],
        ['subscribers', 'ar'],
    ] as const)(
        'is a multiple search select for the %s list, named and labelled in %s',
        (kind, locale) => {
            i18n.global.locale.value = locale;
            const field =
                kind === 'users' ? 'user_emails' : 'subscriber_emails';
            const wrapper = mountField(kind);

            expect(
                wrapper.getComponent(StoreSearchSelect).props(),
            ).toMatchObject({
                id: field,
                name: `${field}[]`,
                multiple: true,
                required: true,
                endpoint: recipients,
                params: { kind },
                label: t(`store.newsletters.recipients.${kind}`),
                placeholder: t(`store.newsletters.choose.${kind}`),
                searchPlaceholder: t(`store.newsletters.search.${kind}`),
            });
            expect(wrapper.get(`label[for="${field}"]`).text()).toBe(
                t(`store.newsletters.recipients.${kind}`) +
                    '*' +
                    t('common.required'),
            );
            expect(wrapper.get(`#${field}`).attributes('aria-required')).toBe(
                'true',
            );
            // Nothing is searched until the list is opened.
            expect(getMock).not.toHaveBeenCalled();
        },
    );

    it('drops the required flag when told', async () => {
        const wrapper = mountField('users', {}, [], true);

        await wrapper.setProps({ required: false });

        expect(wrapper.get('#user_emails').attributes('aria-required')).toBe(
            undefined,
        );
        expect(wrapper.find('[data-required-mark]').exists()).toBe(false);
    });

    it('posts each picked email as <field>[] and emits the picks', async () => {
        const wrapper = mountField('subscribers', {}, [reader]);

        expect(
            wrapper
                .findAll('input[type="hidden"][name="subscriber_emails[]"]')
                .map((input) => input.attributes('value')),
        ).toEqual(['reader@example.test']);
        expect(wrapper.get('[data-testid="search-select-chip"]').text()).toBe(
            'reader@example.test',
        );

        wrapper
            .getComponent(StoreSearchSelect)
            .vm.$emit('update:items', [
                reader,
                { id: '<script>x</script>', label: '<script>x</script>' },
            ]);
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
            [reader, { id: '<script>x</script>', label: '<script>x</script>' }],
        ]);
        expect(wrapper.find('script').exists()).toBe(false);
    });

    it('shows its own field and item errors and none of the other list', () => {
        const wrapper = mountField('users', {
            user_emails: 'Choose a recipient',
            'user_emails.0': 'Unknown user',
            'subscriber_emails.0': 'Not this list',
        });

        expect(wrapper.text()).toContain('Choose a recipient');
        expect(wrapper.text()).toContain('Unknown user');
        expect(wrapper.text()).not.toContain('Not this list');
        expect(wrapper.get('#user_emails').attributes('aria-invalid')).toBe(
            'true',
        );
    });
});
