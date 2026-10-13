import { flushPromises, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { recipients } from '@/actions/App/Http/Controllers/Store/Admin/NewsletterController';
import { i18n, t } from '@/lib/i18n';
import StoreNewsletterRecipients from './StoreNewsletterRecipients.vue';

const { getMock, cancelMock, http } = vi.hoisted(() => ({
    getMock: vi.fn(),
    cancelMock: vi.fn(),
    http: {
        current: undefined as
            | { q: string; kind: string; errors: Record<string, string> }
            | undefined,
    },
}));
vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue');

    return {
        useHttp: (data: { q: string; kind: string }) => {
            const state = reactive({
                ...data,
                errors: {} as Record<string, string>,
                processing: false,
                get: getMock,
                cancel: cancelMock,
            });
            http.current = state;

            return state;
        },
    };
});

/** A response the test settles by hand, to order overlapping searches. */
function deferred() {
    let resolve!: (value: string[]) => void;
    let reject!: (reason: unknown) => void;
    const promise = new Promise<string[]>((res, rej) => {
        resolve = res;
        reject = rej;
    });

    return { promise, resolve, reject };
}

function mountField(
    kind: 'users' | 'subscribers' = 'users',
    errors: Record<string, string> = {},
    modelValue: string[] = [],
) {
    return mount(StoreNewsletterRecipients, {
        props: { kind, required: true, errors, modelValue },
    });
}

describe('StoreNewsletterRecipients', () => {
    beforeEach(() => {
        getMock.mockReset();
        cancelMock.mockReset();
        getMock.mockResolvedValue([]);
    });
    afterEach(() => {
        vi.useRealTimers();
        i18n.global.locale.value = 'en';
    });

    it.each([
        ['users', 'en'],
        ['subscribers', 'en'],
        ['users', 'ar'],
        ['subscribers', 'ar'],
    ] as const)(
        'labels and names the %s search and recipient list in %s',
        async (kind, locale) => {
            i18n.global.locale.value = locale;
            const field =
                kind === 'users' ? 'user_emails' : 'subscriber_emails';
            const wrapper = mountField(kind);
            await flushPromises();

            expect(wrapper.get(`label[for="${field}_search"]`).text()).toBe(
                t(`store.newsletters.search.${kind}`),
            );
            expect(wrapper.get(`label[for="${field}"]`).text()).toBe(
                t(`store.newsletters.recipients.${kind}`),
            );
            const search = wrapper.get(`#${field}_search`);
            expect(search.attributes()).toMatchObject({
                type: 'search',
                maxlength: '255',
            });
            // The search text never posts with the newsletter.
            expect(search.attributes('name')).toBeUndefined();
            expect(wrapper.get(`#${field}`).attributes()).toMatchObject({
                multiple: '',
                name: `${field}[]`,
                required: '',
            });
            expect(http.current).toMatchObject({ q: '', kind });
            expect(getMock).toHaveBeenCalledExactlyOnceWith(recipients.url());
        },
    );

    it('lists the first results and drops the required flag when told', async () => {
        getMock.mockResolvedValue(['a@example.test', 'b@example.test']);
        const wrapper = mountField();
        await flushPromises();
        expect(
            wrapper.findAll('#user_emails option').map((o) => o.text()),
        ).toEqual(['a@example.test', 'b@example.test']);
        await wrapper.setProps({ required: false });
        expect(
            wrapper.get('#user_emails').attributes('required'),
        ).toBeUndefined();
    });

    it('debounces searches, keeps selections and ignores a stale response', async () => {
        vi.useFakeTimers();
        const wrapper = mountField();
        await flushPromises();
        await wrapper.setProps({ modelValue: ['kept@example.test'] });

        const stale = deferred();
        const latest = deferred();
        getMock.mockReturnValueOnce(stale.promise);
        getMock.mockReturnValueOnce(latest.promise);
        await wrapper.get('#user_emails_search').setValue('fir');
        await wrapper.get('#user_emails_search').setValue('first');
        expect(getMock).toHaveBeenCalledTimes(1);
        vi.advanceTimersByTime(300);
        expect(getMock).toHaveBeenCalledTimes(2);
        expect(http.current?.q).toBe('first');

        await wrapper.get('#user_emails_search').setValue('latest');
        vi.advanceTimersByTime(300);
        expect(getMock).toHaveBeenCalledTimes(3);
        expect(cancelMock).toHaveBeenCalled();
        latest.resolve(['latest@example.test']);
        await flushPromises();
        stale.resolve(['stale@example.test']);
        await flushPromises();

        const options = wrapper
            .findAll('#user_emails option')
            .map((o) => o.text());
        expect(options).toEqual(['kept@example.test', 'latest@example.test']);
        expect(
            (wrapper.get('#user_emails').element as HTMLSelectElement)
                .selectedOptions[0].value,
        ).toBe('kept@example.test');
    });

    it('emits the chosen emails and escapes returned text', async () => {
        getMock.mockResolvedValue([
            'first@example.test',
            '<script>unsafe</script>',
        ]);
        const wrapper = mountField();
        await flushPromises();
        expect(wrapper.find('select script').exists()).toBe(false);
        await wrapper.get('#user_emails').setValue(['first@example.test']);
        expect(wrapper.emitted('update:modelValue')?.at(-1)).toEqual([
            ['first@example.test'],
        ]);
    });

    it('shows its own field and item errors, a search error and a failed search', async () => {
        getMock.mockRejectedValue(new Error('offline'));
        const wrapper = mountField('users', {
            user_emails: 'Choose a recipient',
            'user_emails.0': 'Unknown user',
            'subscriber_emails.0': 'Not this list',
        });
        await flushPromises();
        expect(wrapper.text()).toContain('Choose a recipient');
        expect(wrapper.text()).toContain('Unknown user');
        expect(wrapper.text()).not.toContain('Not this list');
        expect(wrapper.text()).toContain(t('store.newsletters.searchFailed'));
        http.current!.errors = { q: 'Too long' };
        await flushPromises();
        expect(wrapper.text()).toContain('Too long');
    });

    it('cancels a pending search when it unmounts', async () => {
        const wrapper = mountField();
        await flushPromises();
        cancelMock.mockClear();
        wrapper.unmount();
        expect(cancelMock).toHaveBeenCalledOnce();
    });
});
