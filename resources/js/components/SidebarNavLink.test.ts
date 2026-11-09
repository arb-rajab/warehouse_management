import { Link } from '@inertiajs/vue3';
import { Rows3 } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { index as rowsIndex } from '@/actions/App/Http/Controllers/Admin/RowController';
import SidebarNavLink from './SidebarNavLink.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return { Link: createLinkStub() };
});

function mountLink(props: Record<string, unknown> = {}) {
    return mount(SidebarNavLink, {
        props: {
            href: rowsIndex(),
            label: 'Rows',
            active: false,
            icon: Rows3,
            ...props,
        },
    });
}

describe('SidebarNavLink', () => {
    it('is an Inertia link to the route object, with its icon and label', () => {
        const wrapper = mountLink();

        expect(wrapper.getComponent(Link).props('href')).toEqual(rowsIndex());
        expect(wrapper.findComponent(Rows3).exists()).toBe(true);
        expect(wrapper.text()).toBe('Rows');
    });

    it('loads a Blade target in full through a plain anchor', () => {
        const wrapper = mountLink({ inertia: false });

        expect(wrapper.findComponent(Link).exists()).toBe(false);
        expect(wrapper.get('a').attributes('href')).toBe(rowsIndex.url());
    });

    it('marks the active link with aria-current, the brand-soft fill and the inline-start bar', () => {
        const active = mountLink({ active: true }).get('a');
        const idle = mountLink().get('a');

        expect(active.attributes('aria-current')).toBe('page');
        expect(active.classes()).toEqual(
            expect.arrayContaining([
                'bg-sidebar-active',
                'text-sidebar-active-ink',
                'before:-start-2.5',
                'before:bg-sidebar-indicator',
            ]),
        );
        expect(idle.attributes('aria-current')).toBeUndefined();
        expect(idle.classes()).not.toContain('bg-sidebar-active');
        expect(idle.classes()).toContain('hover:bg-sidebar-hover');
    });

    it('shows a count only when there is one', () => {
        expect(
            mountLink({ count: 0 })
                .find('[data-testid="sidebar-count"]')
                .exists(),
        ).toBe(false);
        expect(
            mountLink({ count: 4 }).get('[data-testid="sidebar-count"]').text(),
        ).toBe('4');
    });

    it('keeps the label for screen readers and as the tooltip on the folded rail', () => {
        const wrapper = mountLink({ collapsed: true, count: 3 });

        expect(wrapper.get('a').attributes('title')).toBe('Rows');
        expect(wrapper.get('span').classes()).toContain('lg:sr-only');
        expect(wrapper.get('span').text()).toBe('Rows');
        expect(
            wrapper.get('[data-testid="sidebar-count"]').classes(),
        ).toContain('lg:absolute');
    });

    it('has no tooltip while the sidebar is open', () => {
        const wrapper = mountLink();

        expect(wrapper.get('a').attributes('title')).toBeUndefined();
        expect(wrapper.get('span').classes()).not.toContain('lg:sr-only');
    });
});
