import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import type { PaginationLink } from '@/types/admin';
import Pagination from './Pagination.vue';

function link(overrides: Partial<PaginationLink> = {}): PaginationLink {
    return {
        url: '/admin/rows?page=2',
        label: '2',
        active: false,
        ...overrides,
    };
}

describe('Pagination', () => {
    it('renders nothing when there are 3 or fewer links', () => {
        const wrapper = mount(Pagination, {
            props: { links: [link(), link(), link()] },
        });

        expect(wrapper.find('nav').exists()).toBe(false);
    });

    it('renders the nav once there are more than 3 links', () => {
        const wrapper = mount(Pagination, {
            props: { links: [link(), link(), link(), link()] },
        });

        expect(wrapper.find('nav').exists()).toBe(true);
    });

    it('renders a disabled span (not a link) for a null url', () => {
        const wrapper = mount(Pagination, {
            props: {
                links: [
                    link({ url: null, label: '&laquo; Previous' }),
                    link(),
                    link(),
                    link(),
                ],
            },
        });

        const spans = wrapper.findAll('span');
        expect(spans[0].text()).toBe('« Previous');
        expect(wrapper.findAll('a')).toHaveLength(3);
    });

    it('styles the active link differently from inactive links', () => {
        const wrapper = mount(Pagination, {
            props: {
                links: [
                    link({ label: '1', active: false }),
                    link({ label: '2', active: true }),
                    link({ label: '3', active: false }),
                    link({ label: '4', active: false }),
                ],
            },
        });

        const activeLink = wrapper.findAll('a').find((a) => a.text() === '2');
        const inactiveLink = wrapper.findAll('a').find((a) => a.text() === '1');

        expect(activeLink?.classes()).toContain('bg-gray-900');
        expect(inactiveLink?.classes()).not.toContain('bg-gray-900');
    });

    it('links to the url given for each page link', () => {
        const wrapper = mount(Pagination, {
            props: {
                links: [
                    link({ url: '/admin/rows?page=1', label: '1' }),
                    link({ url: '/admin/rows?page=2', label: '2' }),
                    link({ url: '/admin/rows?page=3', label: '3' }),
                    link({ url: '/admin/rows?page=4', label: '4' }),
                ],
            },
        });

        const firstLink = wrapper.findAll('a').find((a) => a.text() === '1');
        expect(firstLink?.attributes('href')).toBe('/admin/rows?page=1');
    });
});
