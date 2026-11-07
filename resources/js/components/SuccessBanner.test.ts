import { CircleCheck, X } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick, reactive } from 'vue';
import { t } from '@/lib/i18n';
import SuccessBanner from './SuccessBanner.vue';

const { usePageMock } = vi.hoisted(() => ({ usePageMock: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ usePage: usePageMock }));

let page: { flash: { success?: string } };

beforeEach(() => {
    page = reactive({ flash: {} });
    usePageMock.mockReturnValue(page);
});

describe('SuccessBanner', () => {
    it('renders nothing when there is no success message', () => {
        const wrapper = mount(SuccessBanner);

        expect(wrapper.find('[role="status"]').exists()).toBe(false);
    });

    it('renders the flashed message with a check icon and a close button', () => {
        page.flash = { success: 'Product has been updated successfully' };
        const wrapper = mount(SuccessBanner);

        const status = wrapper.get('[role="status"]');
        expect(status.text()).toBe('Product has been updated successfully');
        expect(status.classes()).toEqual(
            expect.arrayContaining([
                'border-green-200',
                'bg-green-50',
                'text-green-700',
                'dark:border-green-900',
                'dark:bg-green-950',
                'dark:text-green-400',
            ]),
        );
        expect(wrapper.findComponent(CircleCheck).exists()).toBe(true);

        const close = wrapper.get('button');
        expect(close.attributes('type')).toBe('button');
        expect(close.attributes('aria-label')).toBe(t('common.close'));
        expect(close.classes()).toContain('cursor-pointer');
        expect(close.findComponent(X).exists()).toBe(true);
    });

    it('places the close button after the message, so it sits at the end edge in either direction', () => {
        page.flash = { success: 'Saved' };
        const wrapper = mount(SuccessBanner);

        const children = wrapper.get('[role="status"]').element.children;
        expect(children[children.length - 1].tagName).toBe('BUTTON');
        expect(wrapper.get('span').classes()).toContain('flex-1');
    });

    it('hides the message when closed', async () => {
        page.flash = { success: 'Saved' };
        const wrapper = mount(SuccessBanner);

        await wrapper.get('button').trigger('click');

        expect(wrapper.find('[role="status"]').exists()).toBe(false);
    });

    it('shows again when the next response flashes a message, even the same one', async () => {
        page.flash = { success: 'Saved' };
        const wrapper = mount(SuccessBanner);
        await wrapper.get('button').trigger('click');

        page.flash = { success: 'Saved' };
        await nextTick();

        expect(wrapper.get('[role="status"]').text()).toBe('Saved');
    });

    it('clears once a response brings no message', async () => {
        page.flash = { success: 'Saved' };
        const wrapper = mount(SuccessBanner);

        page.flash = {};
        await nextTick();

        expect(wrapper.find('[role="status"]').exists()).toBe(false);
    });
});
