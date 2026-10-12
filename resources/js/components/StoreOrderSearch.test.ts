import { mount } from '@vue/test-utils';
import { afterEach, expect, it, vi } from 'vitest';
import StoreOrderSearch from './StoreOrderSearch.vue';
const mocks = vi.hoisted(() => ({ get: vi.fn(), cancel: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useHttp: () => ({ get: mocks.get, cancel: vi.fn() }),
}));
afterEach(() => {
    vi.useRealTimers();
    mocks.get.mockReset();
});
it('searches asynchronously, pages results, ignores stale responses and emits a selected option', async () => {
    vi.useFakeTimers();
    const wrapper = mount(StoreOrderSearch, {
        props: {
            id: 'customer',
            name: 'customer_id',
            label: 'Customer',
            kind: 'customers',
            required: true,
        },
    });
    await wrapper.get('input').setValue('Buyer');
    await vi.advanceTimersByTimeAsync(300);
    expect(mocks.get.mock.calls[0][0]).toContain('q=Buyer');
    const stale = mocks.get.mock.calls[0][1];
    await wrapper.get('input').setValue('New');
    await vi.advanceTimersByTimeAsync(300);
    mocks.get.mock.calls[1][1].onSuccess({
        data: [{ id: 7, name: 'New buyer' }],
        meta: { current_page: 1, last_page: 2 },
    });
    mocks.get.mock.calls[1][1].onFinish();
    stale.onSuccess({
        data: [{ id: 8, name: 'Old buyer' }],
        meta: { current_page: 1, last_page: 1 },
    });
    await wrapper.vm.$nextTick();
    expect(wrapper.text()).toContain('New buyer');
    expect(wrapper.text()).not.toContain('Old buyer');
    await wrapper.get('select').setValue('7');
    expect(wrapper.emitted('select')?.[0]).toEqual([
        { id: 7, name: 'New buyer' },
    ]);
    await wrapper.get('button:last-child').trigger('click');
    expect(mocks.get.mock.calls[2][0]).toContain('page=2');
    mocks.get.mock.calls[2][1].onError();
    await wrapper.vm.$nextTick();
    expect(wrapper.get('[role=alert]').text()).toContain('Search failed');
    wrapper.unmount();
});
