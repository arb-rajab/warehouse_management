import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { t } from '@/lib/i18n';
import { defaultAuthProps, resetMocks } from '@/testing/inertiaPageMocks';
import Rows from './Rows.vue';

const { usePageMock } = vi.hoisted(() => ({
    usePageMock: vi.fn(),
}));

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub, headStub } = await import('@/testing/inertiaStubs');

    return {
        Head: headStub,
        Link: createLinkStub(),
        usePage: usePageMock,
    };
});

function mountPage() {
    usePageMock.mockReturnValue({
        url: '/admin/help/rows',
        props: defaultAuthProps(),
    });

    return mount(Rows);
}

describe('Help Rows', () => {
    beforeEach(() => {
        resetMocks({ usePageMock });
    });

    it('explains what a row is and how cells/levels multiply into pallet slots', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.rows.grid.body'));
        expect(wrapper.text()).toContain(t('help.rows.dimensions.body'));
    });

    it('explains creating, editing, and deleting a row', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('help.rows.creating.body'));
        expect(wrapper.text()).toContain(t('help.rows.editing.body'));
        expect(wrapper.text()).toContain(t('help.rows.deleting.body'));
    });

    it('renders a preview of the add/edit/delete row controls', () => {
        const wrapper = mountPage();

        expect(wrapper.text()).toContain(t('rows.index.addRow'));
        expect(wrapper.text()).toContain(t('rows.show.editRow'));
        expect(wrapper.text()).toContain(t('rows.index.delete'));
    });

    it('links to the rows page', () => {
        const wrapper = mountPage();

        const rowsLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('rows.index.title'));
        expect(rowsLink?.attributes('href')).toBe('/admin/rows');
    });

    it('links back to the help landing page', () => {
        const wrapper = mountPage();

        const backLink = wrapper
            .findAll('a')
            .find((a) => a.text() === t('help.backToHelp'));
        expect(backLink?.attributes('href')).toBe('/admin/help');
    });
});
