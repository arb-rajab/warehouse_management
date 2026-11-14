import { FileText, ImageOff } from '@lucide/vue';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { t } from '@/lib/i18n';
import type { ReceiptFile } from '@/types/admin';
import ReceiptFileTile from './ReceiptFileTile.vue';

function file(overrides: Partial<ReceiptFile> = {}): ReceiptFile {
    return {
        id: 4,
        original_name: 'bill-front.jpg',
        mime_type: 'image/jpeg',
        size: 204800,
        url: '/admin/receipts/9/files/4',
        created_at: '2026-08-01T10:00:00Z',
        ...overrides,
    };
}

function mountTile(overrides: Partial<ReceiptFile> = {}) {
    return mount(ReceiptFileTile, { props: { file: file(overrides) } });
}

describe('ReceiptFileTile', () => {
    it('renders a photo as a lazy thumbnail of the authenticated url', () => {
        const wrapper = mountTile();
        const img = wrapper.get('img');

        expect(img.attributes('src')).toBe('/admin/receipts/9/files/4');
        expect(img.attributes('alt')).toBe('bill-front.jpg');
        expect(img.attributes('loading')).toBe('lazy');
    });

    it('opens the photo in a new tab without an opener', () => {
        const wrapper = mountTile();
        const links = wrapper.findAll('a');

        expect(links).toHaveLength(2);

        for (const link of links) {
            expect(link.attributes('href')).toBe('/admin/receipts/9/files/4');
            expect(link.attributes('target')).toBe('_blank');
            expect(link.attributes('rel')).toBe('noopener');
            expect(link.attributes('aria-label')).toBe(
                t('receipts.show.openFile', { name: 'bill-front.jpg' }),
            );
        }
    });

    it('renders a PDF as an icon tile with an Open link and no image', () => {
        const wrapper = mountTile({
            original_name: 'bill.pdf',
            mime_type: 'application/pdf',
            url: '/admin/receipts/9/files/5',
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.findComponent(FileText).exists()).toBe(true);
        expect(wrapper.findComponent(ImageOff).exists()).toBe(false);
        expect(wrapper.text()).not.toContain(t('receipts.show.imageFailed'));

        const links = wrapper.findAll('a');
        expect(links).toHaveLength(1);
        expect(links[0].text()).toBe(t('receipts.show.open'));
        expect(links[0].attributes('href')).toBe('/admin/receipts/9/files/5');
        expect(links[0].attributes('target')).toBe('_blank');
        expect(links[0].attributes('rel')).toBe('noopener');
    });

    it('shows the file name and a size in kilobytes', () => {
        const wrapper = mountTile();

        expect(wrapper.get('p').text()).toBe('bill-front.jpg');
        expect(wrapper.text()).toContain(
            t('receipts.show.sizeKb', { size: '200' }),
        );
    });

    it('shows the size in megabytes from one megabyte up', () => {
        const wrapper = mountTile({ size: 2.5 * 1024 * 1024 });

        expect(wrapper.text()).toContain(
            t('receipts.show.sizeMb', { size: '2.5' }),
        );
        expect(wrapper.text()).not.toContain('KB');
    });

    it('turns a photo that fails to load into a message that keeps the open link', async () => {
        const wrapper = mountTile();

        await wrapper.get('img').trigger('error');

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.findComponent(ImageOff).exists()).toBe(true);
        expect(wrapper.text()).toContain(t('receipts.show.imageFailed'));

        const links = wrapper.findAll('a');
        expect(links).toHaveLength(1);
        expect(links[0].text()).toBe(t('receipts.show.open'));
        expect(links[0].attributes('href')).toBe('/admin/receipts/9/files/4');
        expect(wrapper.text()).toContain('bill-front.jpg');
    });

    it('never renders a storage path', () => {
        const wrapper = mountTile();

        expect(wrapper.html()).not.toMatch(
            /storage|receipts\/\d+\/[^/"]+\.jpg/,
        );
        expect(wrapper.html()).not.toContain('path');
    });
});
