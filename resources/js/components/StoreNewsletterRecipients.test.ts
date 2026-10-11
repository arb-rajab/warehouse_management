import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { recipients } from '@/actions/App/Http/Controllers/Store/Admin/NewsletterController';
import { t } from '@/lib/i18n';
import StoreNewsletterRecipients from './StoreNewsletterRecipients.vue';
const { getMock } = vi.hoisted(() => ({ getMock: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useHttp: (data: object) => ({
        ...data,
        get: getMock,
        errors: {},
        processing: false,
    }),
}));
function mountFields(kind: 'users' | 'subscribers' = 'users') {
    return mount(StoreNewsletterRecipients, {
        props: {
            kind,
            required: true,
            errors: { 'user_emails.0': 'Unavailable' },
        },
    });
}
describe('StoreNewsletterRecipients', () => {
    beforeEach(() => {
        getMock.mockReset();
        getMock.mockReturnValue(Promise.resolve());
    });
    it.each(['users', 'subscribers'] as const)(
        'labels and names the %s search and selected recipient fields',
        (kind) => {
            const wrapper = mountFields(kind);
            const field =
                kind === 'users' ? 'user_emails' : 'subscriber_emails';
            expect(wrapper.text()).toContain(
                t(`store.newsletters.search.${kind}`),
            );
            expect(wrapper.text()).toContain(
                t(`store.newsletters.recipients.${kind}`),
            );
            expect(wrapper.get(`#${field}_search`).attributes()).toMatchObject({
                type: 'search',
                maxlength: '255',
            });
            expect(wrapper.get(`#${field}`).attributes()).toMatchObject({
                multiple: '',
                name: `${field}[]`,
            });
            expect(getMock).toHaveBeenCalledWith(
                recipients.url(),
                expect.any(Object),
            );
        },
    );
    it('keeps selections across remote searches and ignores stale responses', async () => {
        const wrapper = mountFields();
        getMock.mock.calls[0][1].onSuccess([
            'first@example.test',
            '<script>unsafe</script>',
        ]);
        await wrapper.vm.$nextTick();
        expect(wrapper.find('script').exists()).toBe(false);
        await wrapper.get('#user_emails').setValue(['first@example.test']);
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([
            ['first@example.test'],
        ]);
        await wrapper.get('#user_emails_search').setValue('Latest');
        getMock.mock.calls[1][1].onSuccess(['latest@example.test']);
        getMock.mock.calls[0][1].onSuccess(['stale@example.test']);
        await wrapper.vm.$nextTick();
        expect(wrapper.get('#user_emails').text()).toContain(
            'first@example.test',
        );
        expect(wrapper.get('#user_emails').text()).toContain(
            'latest@example.test',
        );
        expect(wrapper.get('#user_emails').text()).not.toContain(
            'stale@example.test',
        );
        expect(wrapper.text()).toContain('Unavailable');
    });
});
