import { CircleCheck, X } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
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

    describe('scrolling into view', () => {
        const rect = (top: number, bottom: number) =>
            ({
                top,
                bottom,
                left: 0,
                right: 0,
                width: 0,
                height: bottom - top,
                x: 0,
                y: top,
                toJSON: () => ({}),
            }) as DOMRect;

        function mountWithRect(top: number, bottom: number) {
            const scrollIntoView = vi.fn();
            Element.prototype.scrollIntoView = scrollIntoView;
            vi.spyOn(
                Element.prototype,
                'getBoundingClientRect',
            ).mockReturnValue(rect(top, bottom));
            const wrapper = mount(SuccessBanner, { attachTo: document.body });

            return { wrapper, scrollIntoView };
        }

        async function flashAgain() {
            page.flash = { success: 'Saved' };
            await nextTick();
            await nextTick();
        }

        beforeEach(() => {
            window.matchMedia = vi
                .fn()
                .mockReturnValue({
                    matches: false,
                }) as unknown as typeof window.matchMedia;
        });

        afterEach(() => {
            vi.restoreAllMocks();
            delete (Element.prototype as { scrollIntoView?: unknown })
                .scrollIntoView;
        });

        it('scrolls a new message into view smoothly when the banner is above the viewport', async () => {
            const { wrapper, scrollIntoView } = mountWithRect(-200, -160);

            await flashAgain();

            expect(scrollIntoView).toHaveBeenCalledWith({
                block: 'nearest',
                behavior: 'smooth',
            });
            wrapper.unmount();
        });

        it('scrolls when the banner is only partly visible', async () => {
            const { wrapper, scrollIntoView } = mountWithRect(-10, 30);

            await flashAgain();

            expect(scrollIntoView).toHaveBeenCalledTimes(1);
            wrapper.unmount();
        });

        it('does not scroll when the banner is fully visible', async () => {
            const { wrapper, scrollIntoView } = mountWithRect(10, 50);

            await flashAgain();

            expect(scrollIntoView).not.toHaveBeenCalled();
            wrapper.unmount();
        });

        it('scrolls instantly when the user prefers reduced motion', async () => {
            window.matchMedia = vi
                .fn()
                .mockReturnValue({
                    matches: true,
                }) as unknown as typeof window.matchMedia;
            const { wrapper, scrollIntoView } = mountWithRect(-200, -160);

            await flashAgain();

            expect(scrollIntoView).toHaveBeenCalledWith({
                block: 'nearest',
                behavior: 'instant',
            });
            wrapper.unmount();
        });

        it('does nothing when scrollIntoView is not available', async () => {
            vi.spyOn(
                Element.prototype,
                'getBoundingClientRect',
            ).mockReturnValue(rect(-200, -160));
            const wrapper = mount(SuccessBanner, { attachTo: document.body });

            await flashAgain();

            expect(wrapper.get('[role="status"]').text()).toBe('Saved');
            wrapper.unmount();
        });
    });
});
