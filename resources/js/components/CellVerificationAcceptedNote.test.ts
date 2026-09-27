import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import TableLink from '@/components/TableLink.vue';
import { formatDateTime } from '@/lib/date';
import { t } from '@/lib/i18n';
import CellVerificationAcceptedNote from './CellVerificationAcceptedNote.vue';

vi.mock('@inertiajs/vue3', async () => {
    const { createLinkStub } = await import('@/testing/inertiaStubs');

    return { Link: createLinkStub() };
});

describe('CellVerificationAcceptedNote', () => {
    it('shows when the report was accepted and links the admin who accepted it', () => {
        const wrapper = mount(CellVerificationAcceptedNote, {
            props: {
                acceptedAt: '2026-09-20T09:15:00Z',
                acceptedBy: { id: 4, name: 'Zoe Admin' },
            },
        });

        expect(wrapper.text()).toContain(
            t('cellVerificationReport.acceptedOn', {
                date: formatDateTime('2026-09-20T09:15:00Z'),
            }),
        );
        expect(wrapper.text()).toContain(
            t('cellVerificationReport.acceptedBy'),
        );

        const link = wrapper.getComponent(TableLink);
        expect(link.props('href')).toEqual({
            url: '/admin/users/4',
            method: 'get',
        });
        expect(link.text()).toBe('Zoe Admin');
    });

    it('still shows the accepted date when the accepting admin no longer exists', () => {
        const wrapper = mount(CellVerificationAcceptedNote, {
            props: { acceptedAt: '2026-09-20T09:15:00Z', acceptedBy: null },
        });

        expect(wrapper.text()).toContain(
            t('cellVerificationReport.acceptedOn', {
                date: formatDateTime('2026-09-20T09:15:00Z'),
            }),
        );
        expect(wrapper.text()).not.toContain(
            t('cellVerificationReport.acceptedBy'),
        );
        expect(wrapper.findComponent(TableLink).exists()).toBe(false);
    });
});
