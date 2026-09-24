import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import FilterDialog from './FilterDialog.vue';

function mountDialog(open = true) {
    return mount(FilterDialog, {
        props: {
            title: 'Filters',
            closeLabel: 'Close',
            open,
            'onUpdate:open': (value: boolean) => {
                open = value;
            },
        },
        slots: {
            default: '<p>Filter fields</p>',
        },
    });
}

describe('FilterDialog', () => {
    it('renders nothing when closed', () => {
        const wrapper = mountDialog(false);

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('renders the title and slot content when open', () => {
        const wrapper = mountDialog(true);

        expect(wrapper.get('[role="dialog"]').text()).toContain('Filters');
        expect(wrapper.text()).toContain('Filter fields');
    });

    it('emits update:open false when the close button is clicked', async () => {
        const wrapper = mountDialog(true);

        await wrapper.get('[aria-label="Close"]').trigger('click');

        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });

    it('emits update:open false when the backdrop is clicked', async () => {
        const wrapper = mountDialog(true);

        await wrapper.get('.fixed.inset-0.bg-black\\/50').trigger('click');

        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });

    it('emits update:open false when Escape is pressed', async () => {
        const wrapper = mountDialog(true);

        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();

        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });

    it('moves focus into the first focusable slot field when it opens', async () => {
        const outsideButton = document.createElement('button');
        document.body.appendChild(outsideButton);
        outsideButton.focus();

        const wrapper = mount(FilterDialog, {
            attachTo: document.body,
            props: {
                title: 'Filters',
                closeLabel: 'Close',
                open: false,
                'onUpdate:open': () => {},
            },
            slots: { default: '<input id="first-field" />' },
        });

        await wrapper.setProps({ open: true });
        await nextTick();

        expect(document.activeElement?.id).toBe('first-field');

        wrapper.unmount();
        outsideButton.remove();
    });

    it('focuses the close button when the slot has no focusable field', async () => {
        const wrapper = mount(FilterDialog, {
            attachTo: document.body,
            props: {
                title: 'Filters',
                closeLabel: 'Close',
                open: false,
                'onUpdate:open': () => {},
            },
            slots: { default: '<p>Filter fields</p>' },
        });

        await wrapper.setProps({ open: true });
        await nextTick();

        expect(document.activeElement?.getAttribute('aria-label')).toBe(
            'Close',
        );

        wrapper.unmount();
    });

    it('restores focus to the previously focused element when it closes', async () => {
        const outsideButton = document.createElement('button');
        document.body.appendChild(outsideButton);
        outsideButton.focus();

        const wrapper = mount(FilterDialog, {
            attachTo: document.body,
            props: {
                title: 'Filters',
                closeLabel: 'Close',
                open: false,
                'onUpdate:open': () => {},
            },
            slots: { default: '<input id="first-field" />' },
        });

        await wrapper.setProps({ open: true });
        await nextTick();
        await wrapper.setProps({ open: false });

        expect(document.activeElement).toBe(outsideButton);

        wrapper.unmount();
        outsideButton.remove();
    });

    it('restores focus without scrolling the page, so a scroll position set while the dialog was open survives closing it', async () => {
        const outsideButton = document.createElement('button');
        document.body.appendChild(outsideButton);
        outsideButton.focus();
        const focusSpy = vi.spyOn(outsideButton, 'focus');

        const wrapper = mount(FilterDialog, {
            attachTo: document.body,
            props: {
                title: 'Filters',
                closeLabel: 'Close',
                open: false,
                'onUpdate:open': () => {},
            },
            slots: { default: '<input id="first-field" />' },
        });

        await wrapper.setProps({ open: true });
        await nextTick();
        await wrapper.setProps({ open: false });

        expect(focusSpy).toHaveBeenCalledWith({ preventScroll: true });

        wrapper.unmount();
        outsideButton.remove();
    });

    it('wraps Tab from the last focusable element back to the first', async () => {
        const wrapper = mount(FilterDialog, {
            attachTo: document.body,
            props: {
                title: 'Filters',
                closeLabel: 'Close',
                open: true,
                'onUpdate:open': () => {},
            },
            slots: { default: '<input id="only-field" />' },
        });
        await nextTick();

        const closeButton = wrapper.get('[aria-label="Close"]')
            .element as HTMLElement;
        const field = document.getElementById('only-field') as HTMLElement;

        field.focus();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab' }));

        expect(document.activeElement).toBe(closeButton);

        wrapper.unmount();
    });

    it('wraps Shift+Tab from the first focusable element back to the last', async () => {
        const wrapper = mount(FilterDialog, {
            attachTo: document.body,
            props: {
                title: 'Filters',
                closeLabel: 'Close',
                open: true,
                'onUpdate:open': () => {},
            },
            slots: { default: '<input id="only-field" />' },
        });
        await nextTick();

        const closeButton = wrapper.get('[aria-label="Close"]')
            .element as HTMLElement;
        const field = document.getElementById('only-field') as HTMLElement;

        closeButton.focus();
        document.dispatchEvent(
            new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true }),
        );

        expect(document.activeElement).toBe(field);

        wrapper.unmount();
    });

    it('renders the footer slot outside the scrollable content area', () => {
        const wrapper = mount(FilterDialog, {
            props: {
                title: 'Filters',
                closeLabel: 'Close',
                open: true,
                'onUpdate:open': () => {},
            },
            slots: {
                default: '<p>Filter fields</p>',
                footer: '<button type="button">Apply</button>',
            },
        });

        const content = wrapper
            .get('p')
            .element.closest('.overflow-y-auto') as HTMLElement;
        const footerButton = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Apply')?.element;

        expect(content).not.toBeNull();
        expect(footerButton).toBeDefined();
        expect(content.contains(footerButton as HTMLElement)).toBe(false);
    });

    it('does not render a footer section when no footer slot is provided', () => {
        const wrapper = mountDialog(true);

        expect(wrapper.get('[role="dialog"]').element.children).toHaveLength(2);
    });

    it('includes the footer slot in the Tab focus trap', async () => {
        const wrapper = mount(FilterDialog, {
            attachTo: document.body,
            props: {
                title: 'Filters',
                closeLabel: 'Close',
                open: true,
                'onUpdate:open': () => {},
            },
            slots: {
                default: '<input id="only-field" />',
                footer: '<button id="footer-apply" type="button">Apply</button>',
            },
        });
        await nextTick();

        const footerButton = document.getElementById(
            'footer-apply',
        ) as HTMLElement;

        footerButton.focus();
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab' }));

        const closeButton = wrapper.get('[aria-label="Close"]')
            .element as HTMLElement;

        expect(document.activeElement).toBe(closeButton);

        wrapper.unmount();
    });
});
